import { createApp, h } from 'vue';

import CrudExportDialog from '#/components/crud-export-dialog.vue';
import CrudImportDialog from '#/components/crud-import-dialog.vue';
import xlsxScriptUrl from 'xlsx-js-style/dist/xlsx.min.js?url';

export type CellValue = boolean | number | string | null | undefined;

export interface CrudExcelColumn<T = Record<string, any>> {
  key: string;
  title: string;
  width?: number;
  exportable?: boolean;
  importable?: boolean;
  required?: boolean;
  example?: CellValue;
  rule?: string;
  formatter?: (record: T) => CellValue;
  parser?: (value: CellValue, row: Record<string, CellValue>) => any;
}

export interface CrudPageResult<T> {
  items?: T[];
  pageInfo?: {
    currentPage?: number;
    total?: number;
    totalPage?: number;
  };
  total?: number;
}

export interface CrudExcelSheet<T = Record<string, any>> {
  columns: CrudExcelColumn<T>[];
  rows: T[];
  sheetName: string;
}

interface ExportCrudXlsxOptions<T> {
  columnCount?: number;
  columns?: CrudExcelColumn<T>[];
  fetchPage?: (page: number, pageSize: number) => Promise<CrudPageResult<T>>;
  filename: string;
  pageSize?: number;
  rules?: string[];
  sheetName?: string;
  sheetCount?: number;
  sheets?: Array<CrudExcelSheet<any>> | ((onProgress: ExportProgressHandler) => Array<CrudExcelSheet<any>> | Promise<Array<CrudExcelSheet<any>>>);
  transformItems?: (items: T[]) => T[];
}

interface ImportCrudRowsOptions<TPayload> {
  columns: CrudExcelColumn[];
  moduleName: string;
  submit?: (payload: TPayload, row: Record<string, CellValue>, index: number) => Promise<unknown>;
  submitRows?: (payloads: TPayload[], rows: Array<Record<string, CellValue>>) => Promise<unknown>;
  afterDone?: () => Promise<void> | void;
  buildPayload?: (row: Record<string, CellValue>, index: number) => TPayload;
  rules?: string[];
  sampleRow?: Record<string, CellValue>;
  templateFilename?: string;
}

interface ImportResultRow {
  index: number;
  message: string;
  status: 'failed' | 'success';
}

interface ImportProgress {
  current: number;
  failed: number;
  success: number;
  total: number;
}

type ExportProgressHandler = (progress: { loaded: number; total: number }) => void;

let xlsxLoader: null | Promise<any> = null;

// 树形列表导出时保持当前层级顺序，但写入 xlsx 前转成平铺行，避免 Excel 单元格里塞 children 对象。
export function flattenTreeRows<T extends Record<string, any>>(items: T[], childrenKey = 'children'): T[] {
  const result: T[] = [];
  const visit = (rows: T[]) => {
    rows.forEach((item) => {
      result.push(item);
      const children = item[childrenKey];
      if (Array.isArray(children) && children.length > 0) {
        visit(children as T[]);
      }
    });
  };
  visit(items);
  return result;
}

// 标准列表导出只复用现有列表分页接口：后台负责权限、数据范围和租户隔离，前端负责确认、进度、生成 xlsx 与下载。
// 不新增或调用后端 */export 文件接口，避免导出口径绕过列表筛选、权限和租户边界。
export async function exportCrudXlsx<T extends Record<string, any>>(options: ExportCrudXlsxOptions<T>) {
  const pageSize = options.pageSize ?? 100;
  const columns = (options.columns || []).filter((item) => item.exportable !== false);
  const moduleName = options.sheetName || '数据';
  const filename = ensureXlsxFilename(options.filename);
  const staticSheets = Array.isArray(options.sheets) ? options.sheets : undefined;
  const sheetCount = options.sheetCount ?? staticSheets?.length ?? 1;
  const columnCount = options.columnCount ?? (staticSheets
    ? staticSheets.reduce((count, sheet) => count + sheet.columns.filter((item) => item.exportable !== false).length, 0)
    : columns.length);

  await mountDialog(CrudExportDialog, {
    columnCount,
    filename,
    moduleName,
    pageSize,
    rules: options.rules || [],
    sheetCount,
    runExport: async (onProgress: (progress: { loaded: number; total: number }) => void) => {
      if (options.sheets) {
        const rawSheets = typeof options.sheets === 'function' ? await options.sheets(onProgress) : options.sheets;
        const sheets = rawSheets.map((sheet) => ({
          ...sheet,
          columns: sheet.columns.filter((item) => item.exportable !== false),
        }));
        const totalRows = sheets.reduce((count, sheet) => count + sheet.rows.length, 0);
        onProgress({ loaded: 0, total: totalRows });
        await writeWorkbook({
          filename,
          sheets,
        });
        onProgress({ loaded: totalRows, total: totalRows });
        return { total: totalRows };
      }

      if (!options.fetchPage || columns.length === 0) {
        throw new Error('导出参数不完整');
      }

      const rows: T[] = [];
      let page = 1;
      let total = 0;
      let totalPage = 1;

      do {
        const result = await options.fetchPage(page, pageSize);
        const rawItems = result.items || [];
        const items = options.transformItems ? options.transformItems(rawItems) : rawItems;
        rows.push(...items);
        total = Number(result.pageInfo?.total ?? result.total ?? rows.length);
        totalPage = Number(result.pageInfo?.totalPage ?? Math.max(1, Math.ceil(total / pageSize)));
        onProgress({ loaded: rows.length, total: total || rows.length });

        if (rawItems.length === 0) {
          // 接口未返回 totalPage 且当前页为空时立即停止，避免不断请求空页。
          break;
        }
        page += 1;
      } while (page <= totalPage);

      await writeWorkbook({
        filename,
        sheets: [{ columns, rows, sheetName: moduleName }],
      });

      return { total: rows.length };
    },
  });
}

// 标准导入统一走流程：上传层负责模板和文件选择，预览层承接确认、进度和结果明细。
export async function openCrudImport<TPayload extends Record<string, any>>(options: ImportCrudRowsOptions<TPayload>) {
  const columns = options.columns.filter((column) => column.importable !== false);
  if (!options.submit && !options.submitRows) {
    throw new Error('导入提交参数不完整');
  }

  await mountDialog(CrudImportDialog, {
    columns,
    downloadTemplate: () => downloadImportTemplate(options),
    moduleName: options.moduleName,
    readRows: readWorkbookRows,
    rules: options.rules || [],
    runImport: async (
      rows: Array<Record<string, CellValue>>,
      onProgress: (progress: ImportProgress) => void,
    ) => {
      if (options.submitRows) {
        // 部分业务（如测试用例导入）必须一次提交全部行，避免逐条提交时中途触发状态闭环导致后续行被拒绝。
        return await runBatchImport(options, columns, rows, onProgress);
      }

      const results: ImportResultRow[] = [];
      let success = 0;

      // 导入必须串行执行，避免批量并发绕过后端 Service/Mapper 中的唯一性、权限和日志约束。
      for (const [index, row] of rows.entries()) {
        try {
          validateRequiredColumns(columns, row);
          const payload = options.buildPayload
            ? options.buildPayload(row, index)
            : buildPayloadFromColumns<TPayload>(columns, row);
          await options.submit!(payload, row, index);
          results.push({ index: index + 1, message: '处理成功', status: 'success' });
          success += 1;
        } catch (error) {
          results.push({ index: index + 1, message: resolveErrorMessage(error), status: 'failed' });
        }

        onProgress({
          current: index + 1,
          failed: results.length - success,
          success,
          total: rows.length,
        });
      }

      await options.afterDone?.();
      return { results, success, total: rows.length };
    },
  });
}

async function runBatchImport<TPayload extends Record<string, any>>(
  options: ImportCrudRowsOptions<TPayload>,
  columns: CrudExcelColumn[],
  rows: Array<Record<string, CellValue>>,
  onProgress: (progress: ImportProgress) => void,
) {
  const results: ImportResultRow[] = [];
  const payloads: TPayload[] = [];
  const validRows: Array<Record<string, CellValue>> = [];
  const validIndexes: number[] = [];

  for (const [index, row] of rows.entries()) {
    try {
      validateRequiredColumns(columns, row);
      payloads.push(options.buildPayload ? options.buildPayload(row, index) : buildPayloadFromColumns<TPayload>(columns, row));
      validRows.push(row);
      validIndexes.push(index + 1);
    } catch (error) {
      results.push({ index: index + 1, message: resolveErrorMessage(error), status: 'failed' });
    }
    onProgress({
      current: index + 1,
      failed: results.length,
      success: 0,
      total: rows.length,
    });
  }

  let success = 0;
  if (payloads.length > 0) {
    try {
      await options.submitRows!(payloads, validRows);
      validIndexes.forEach((index) => results.push({ index, message: '处理成功', status: 'success' }));
      success = payloads.length;
    } catch (error) {
      const message = resolveErrorMessage(error);
      validIndexes.forEach((index) => results.push({ index, message, status: 'failed' }));
    }
  }

  results.sort((left, right) => left.index - right.index);
  onProgress({
    current: rows.length,
    failed: rows.length - success,
    success,
    total: rows.length,
  });
  await options.afterDone?.();

  return { results, success, total: rows.length };
}

// 状态枚举允许中文和常见布尔文本，导入模板不要求用户记住后端数字值。
export function statusText(status?: number | string) {
  return Number(status) === 1 ? '启用' : '禁用';
}

export function parseStatus(value: CellValue, defaultValue = 1): number {
  const text = String(value ?? '').trim();
  if (['1', '启用', '正常', 'true', 'TRUE'].includes(text)) {
    return 1;
  }
  if (['0', '禁用', '停用', 'false', 'FALSE'].includes(text)) {
    return 0;
  }
  return defaultValue;
}

export function parseNumber(value: CellValue, defaultValue = 0): number {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : defaultValue;
}

export function parseOptionalNumber(value: CellValue): number | undefined {
  const text = String(value ?? '').trim();
  if (text === '') {
    return undefined;
  }
  const numeric = Number(text);
  return Number.isFinite(numeric) ? numeric : undefined;
}

export function parseStringList(value: CellValue): string[] {
  return String(value ?? '')
    .split(/[，,|/]/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function mountDialog(component: any, props: Record<string, any>) {
  return new Promise<void>((resolve) => {
    const host = document.createElement('div');
    document.body.append(host);
    let app: ReturnType<typeof createApp> | null = null;

    const cleanup = () => {
      window.setTimeout(() => {
        app?.unmount();
        host.remove();
        resolve();
      }, 160);
    };

    app = createApp({
      render: () => h(component, { ...props, onClose: cleanup }),
    });
    app.mount(host);
  });
}

function buildPayloadFromColumns<TPayload>(columns: CrudExcelColumn[], row: Record<string, CellValue>): TPayload {
  const payload: Record<string, any> = {};
  columns.forEach((column) => {
    const value = resolveImportCellValue(column, row);
    payload[column.key] = column.parser ? column.parser(value, row) : value;
  });

  return payload as TPayload;
}

function resolveImportCellValue(column: CrudExcelColumn, row: Record<string, CellValue>) {
  return row[column.title] ?? row[column.key] ?? '';
}

function validateRequiredColumns(columns: CrudExcelColumn[], row: Record<string, CellValue>) {
  const missing = columns
    .filter((column) => column.required)
    .filter((column) => String(resolveImportCellValue(column, row) ?? '').trim() === '')
    .map((column) => column.title);

  if (missing.length > 0) {
    throw new Error(`必填字段不能为空：${missing.join('、')}`);
  }
}

function resolveExportValue<T>(column: CrudExcelColumn<T>, record: T): CellValue {
  if (column.formatter) {
    return column.formatter(record);
  }

  return (record as any)[column.key];
}

function normalizeCellValue(value: CellValue): CellValue {
  if (value === null || value === undefined) {
    return '';
  }
  if (Array.isArray(value)) {
    return value.join(' / ');
  }
  return value;
}

function ensureXlsxFilename(filename: string): string {
  return filename.endsWith('.xlsx') ? filename : `${filename}.xlsx`;
}

async function readWorkbookRows(file: File): Promise<Array<Record<string, CellValue>>> {
  const XLSX = await loadXlsx();
  const workbook = file.name.toLowerCase().endsWith('.csv')
    ? XLSX.read(await file.text(), { type: 'string' })
    : XLSX.read(await file.arrayBuffer(), { type: 'array' });
  const sheetName = workbook.SheetNames[0];
  if (!sheetName) {
    return [];
  }

  return XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], {
    defval: '',
    raw: false,
  }) as Array<Record<string, CellValue>>;
}

async function downloadImportTemplate<TPayload extends Record<string, any>>(options: ImportCrudRowsOptions<TPayload>) {
  const columns = options.columns.filter((column) => column.importable !== false);
  const header = columns.map((column) => column.title);
  const sample = columns.map((column) => {
    if (options.sampleRow && column.key in options.sampleRow) {
      return normalizeCellValue(options.sampleRow[column.key]);
    }
    if (options.sampleRow && column.title in options.sampleRow) {
      return normalizeCellValue(options.sampleRow[column.title]);
    }
    return normalizeCellValue(column.example);
  });

  const XLSX = await loadXlsx();
  const workbook = XLSX.utils.book_new();
  const dataSheet = XLSX.utils.aoa_to_sheet([header, sample]);
  dataSheet['!cols'] = columns.map((column) => ({ wch: Math.max(12, Math.ceil((column.width || 120) / 7)) }));
  styleHeaderRow(XLSX, dataSheet);
  XLSX.utils.book_append_sheet(workbook, dataSheet, `${options.moduleName}导入`);

  const rules = buildTemplateRules(options);
  const ruleSheet = XLSX.utils.aoa_to_sheet(rules);
  ruleSheet['!cols'] = [{ wch: 18 }, { wch: 16 }, { wch: 24 }, { wch: 64 }];
  styleHeaderRow(XLSX, ruleSheet);
  XLSX.utils.book_append_sheet(workbook, ruleSheet, '导入规则');

  XLSX.writeFile(
    workbook,
    ensureXlsxFilename(options.templateFilename || `${options.moduleName}_导入模板.xlsx`),
    { compression: true },
  );
}

function buildTemplateRules<TPayload extends Record<string, any>>(options: ImportCrudRowsOptions<TPayload>) {
  const columns = options.columns.filter((column) => column.importable !== false);
  const rows = [
    ['字段', '是否必填', '示例', '规则说明'],
    ...columns.map((column) => [
      column.title,
      column.required ? '必填' : '选填',
      normalizeCellValue(column.example) || '',
      column.rule || '',
    ]),
  ];

  if (options.rules && options.rules.length > 0) {
    rows.push([], ['通用规则', '', '', '']);
    options.rules.forEach((rule) => rows.push([rule, '', '', '']));
  }

  return rows;
}

async function writeWorkbook(options: {
  filename: string;
  sheets: Array<CrudExcelSheet<any>>;
}) {
  const XLSX = await loadXlsx();
  const workbook = XLSX.utils.book_new();
  const usedSheetNames = new Set<string>();

  options.sheets.forEach((sheet, index) => {
    // 多 Sheet 导出时每个 Sheet 使用自己的列定义，避免把不同报表区块合并成一张大宽表后出现大量空列。
    const worksheet = makeExportWorksheet(XLSX, sheet);
    const sheetName = normalizeSheetName(sheet.sheetName || `Sheet${index + 1}`, usedSheetNames);
    XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
  });

  XLSX.writeFile(workbook, options.filename, { compression: true });
}

function makeExportWorksheet(XLSX: any, sheet: CrudExcelSheet<any>) {
  const body = sheet.rows.map((record) =>
    sheet.columns.map((column) => normalizeCellValue(resolveExportValue(column, record))),
  );
  const worksheet = XLSX.utils.aoa_to_sheet([sheet.columns.map((column) => column.title), ...body]);

  worksheet['!cols'] = sheet.columns.map((column) => ({ wch: Math.max(10, Math.ceil((column.width || 120) / 7)) }));
  worksheet['!freeze'] = { xSplit: 0, ySplit: 1 };
  if (sheet.columns.length > 0) {
    worksheet['!autofilter'] = {
      ref: XLSX.utils.encode_range({ s: { c: 0, r: 0 }, e: { c: sheet.columns.length - 1, r: 0 } }),
    };
  }
  styleWorksheet(XLSX, worksheet);

  return worksheet;
}

function normalizeSheetName(name: string, usedSheetNames: Set<string>): string {
  const base = String(name || '数据')
    .replace(/[:\\/?*\[\]]/g, '_')
    .slice(0, 31) || '数据';
  let sheetName = base;
  let index = 2;
  while (usedSheetNames.has(sheetName)) {
    const suffix = `_${index}`;
    sheetName = `${base.slice(0, 31 - suffix.length)}${suffix}`;
    index += 1;
  }
  usedSheetNames.add(sheetName);
  return sheetName;
}

function styleWorksheet(XLSX: any, worksheet: any) {
  styleHeaderRow(XLSX, worksheet);
  styleBodyRows(XLSX, worksheet);
}

function styleHeaderRow(XLSX: any, worksheet: any) {
  if (!worksheet['!ref']) {
    return;
  }
  const range = XLSX.utils.decode_range(worksheet['!ref']);
  for (let columnIndex = range.s.c; columnIndex <= range.e.c; columnIndex += 1) {
    const cellRef = XLSX.utils.encode_cell({ c: columnIndex, r: 0 });
    if (worksheet[cellRef]) {
      worksheet[cellRef].s = {
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: excelCellBorder(),
        fill: { fgColor: { rgb: 'E8F1FF' }, patternType: 'solid' },
        font: { bold: true, color: { rgb: '1D4ED8' } },
      };
    }
  }
}

function styleBodyRows(XLSX: any, worksheet: any) {
  if (!worksheet['!ref']) {
    return;
  }

  const range = XLSX.utils.decode_range(worksheet['!ref']);
  for (let rowIndex = 1; rowIndex <= range.e.r; rowIndex += 1) {
    for (let columnIndex = range.s.c; columnIndex <= range.e.c; columnIndex += 1) {
      const cellRef = XLSX.utils.encode_cell({ c: columnIndex, r: rowIndex });
      if (!worksheet[cellRef]) {
        continue;
      }
      worksheet[cellRef].s = {
        alignment: { vertical: 'top', wrapText: true },
        border: excelCellBorder(),
        ...(rowIndex % 2 === 0 ? { fill: { fgColor: { rgb: 'FAFCFF' }, patternType: 'solid' } } : {}),
      };
    }
  }
}

function excelCellBorder() {
  return {
    bottom: { color: { rgb: 'D8DEE9' }, style: 'thin' },
    left: { color: { rgb: 'D8DEE9' }, style: 'thin' },
    right: { color: { rgb: 'D8DEE9' }, style: 'thin' },
    top: { color: { rgb: 'D8DEE9' }, style: 'thin' },
  };
}

async function loadXlsx() {
  if ((window as any).XLSX) {
    return (window as any).XLSX;
  }

  xlsxLoader ||= new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.async = true;
    script.src = xlsxScriptUrl;
    script.onload = () => {
      const XLSX = (window as any).XLSX;
      if (!XLSX) {
        reject(new Error('Excel 组件加载失败'));
        return;
      }
      resolve(XLSX);
    };
    script.onerror = () => reject(new Error('Excel 组件加载失败'));
    document.head.append(script);
  });

  // 使用 UMD 浏览器构建，避免 Vite 运行时加载 Node fs/stream 兼容分支并污染控制台。
  return xlsxLoader;
}

function resolveErrorMessage(error: unknown): string {
  if (!error) {
    return '未知错误';
  }
  if (error instanceof Error) {
    return error.message;
  }
  const data = (error as any)?.response?.data;
  return data?.info || data?.message || (error as any)?.message || String(error);
}
