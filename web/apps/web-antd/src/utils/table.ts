interface TableColumnLike {
  children?: TableColumnLike[];
  minWidth?: number | string;
  width?: number | string;
}

interface BuildTableScrollXOptions {
  defaultColumnWidth?: number;
  extraWidth?: number;
  minWidth?: number;
  selectionWidth?: number;
}

type TableActionText = false | null | string | undefined;


interface TableActionWidthLike {
  label?: TableActionText;
  visible?: boolean;
}

interface EstimateVisibleActionColumnWidthOptions extends EstimateActionColumnWidthOptions {
  inlineBeforeMore?: number;
  maxInline?: number;
  moreLabel?: string;
}

interface EstimateActionColumnWidthOptions {
  charWidth?: number;
  gapWidth?: number;
  horizontalPadding?: number;
  maxWidth?: number;
  minWidth?: number;
  safetyWidth?: number;
}

const DEFAULT_COLUMN_WIDTH = 160;
const DEFAULT_EXTRA_WIDTH = 48;
const DEFAULT_MIN_WIDTH = 960;
const DEFAULT_ACTION_CHAR_WIDTH = 14;
const DEFAULT_ACTION_GAP_WIDTH = 8;
const DEFAULT_ACTION_HORIZONTAL_PADDING = 12;
const DEFAULT_ACTION_MAX_WIDTH = 360;
const DEFAULT_ACTION_MIN_WIDTH = 96;
const DEFAULT_ACTION_SAFETY_WIDTH = 32;

function parseWidth(value?: number | string) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value !== 'string') {
    return undefined;
  }

  const normalized = value.trim().toLowerCase();
  if (
    normalized === '' ||
    normalized.includes('%') ||
    normalized.includes('calc(') ||
    normalized.includes('max-content')
  ) {
    return undefined;
  }

  const width = Number.parseFloat(normalized);
  return Number.isFinite(width) ? width : undefined;
}

function resolveColumnWidth(column: TableColumnLike, fallbackWidth: number): number {
  const explicitWidth = parseWidth(column.width) ?? parseWidth(column.minWidth);
  if (explicitWidth) {
    return explicitWidth;
  }

  if (Array.isArray(column.children) && column.children.length > 0) {
    return column.children.reduce(
      (total, child) => total + resolveColumnWidth(child, fallbackWidth),
      0,
    );
  }

  return fallbackWidth;
}

export function buildTableScrollX(
  columns: TableColumnLike[],
  options: BuildTableScrollXOptions = {},
) {
  const {
    defaultColumnWidth = DEFAULT_COLUMN_WIDTH,
    extraWidth = DEFAULT_EXTRA_WIDTH,
    minWidth = DEFAULT_MIN_WIDTH,
    selectionWidth = 0,
  } = options;

  const contentWidth = columns.reduce(
    (total, column) => total + resolveColumnWidth(column, defaultColumnWidth),
    selectionWidth + extraWidth,
  );

  return {
    x: Math.max(minWidth, Math.ceil(contentWidth)),
  };
}

function normalizeActionRows(
  actionRows: TableActionText[] | TableActionText[][],
): TableActionText[][] {
  if (!Array.isArray(actionRows) || actionRows.length === 0) {
    return [];
  }

  return Array.isArray(actionRows[0])
    ? actionRows as TableActionText[][]
    : [actionRows as TableActionText[]];
}

function estimateActionTextWidth(text: string, charWidth: number) {
  return Array.from(text).reduce((width, char) => {
    // 中文按钮按完整字符宽估算，英文/数字按窄字符估算，避免固定操作列过宽。
    return width + (/[\u3400-\u9FFF\uF900-\uFAFF]/.test(char) ? charWidth : charWidth * 0.62);
  }, 0);
}

export function estimateActionColumnWidth(
  actionRows: TableActionText[] | TableActionText[][],
  options: EstimateActionColumnWidthOptions = {},
) {
  const {
    charWidth = DEFAULT_ACTION_CHAR_WIDTH,
    gapWidth = DEFAULT_ACTION_GAP_WIDTH,
    horizontalPadding = DEFAULT_ACTION_HORIZONTAL_PADDING,
    maxWidth = DEFAULT_ACTION_MAX_WIDTH,
    minWidth = DEFAULT_ACTION_MIN_WIDTH,
    safetyWidth = DEFAULT_ACTION_SAFETY_WIDTH,
  } = options;

  const rows = normalizeActionRows(actionRows);
  const maxRowWidth = rows.reduce((maxWidthValue, row) => {
    const labels = row.filter((label): label is string => Boolean(label));
    if (labels.length === 0) {
      return maxWidthValue;
    }

    const buttonWidth = labels.reduce(
      (total, label) => total + estimateActionTextWidth(label, charWidth) + horizontalPadding,
      0,
    );
    const gapTotal = Math.max(0, labels.length - 1) * gapWidth;
    return Math.max(maxWidthValue, buttonWidth + gapTotal + safetyWidth);
  }, 0);

  return Math.min(maxWidth, Math.max(minWidth, Math.ceil(maxRowWidth)));
}

function normalizeVisibleActionRows(
  actionRows: (TableActionText | TableActionWidthLike)[] | (TableActionText | TableActionWidthLike)[][],
): string[][] {
  if (!Array.isArray(actionRows) || actionRows.length === 0) {
    return [];
  }

  const rows = Array.isArray(actionRows[0])
    ? actionRows as (TableActionText | TableActionWidthLike)[][]
    : [actionRows as (TableActionText | TableActionWidthLike)[]];

  return rows.map((row) => row
    .filter((action) => {
      if (typeof action === 'object' && action !== null && 'visible' in action) {
        return action.visible !== false;
      }
      return Boolean(action);
    })
    .map((action) => (typeof action === 'object' && action !== null ? action.label : action))
    .filter((label): label is string => Boolean(label)));
}

function collapseActionLabels(labels: string[], maxInline: number, inlineBeforeMore: number, moreLabel: string) {
  if (labels.length <= maxInline) {
    return labels;
  }

  return [...labels.slice(0, inlineBeforeMore), moreLabel];
}

export function estimateVisibleActionColumnWidth(
  actionRows: (TableActionText | TableActionWidthLike)[] | (TableActionText | TableActionWidthLike)[][],
  options: EstimateVisibleActionColumnWidthOptions = {},
) {
  const {
    inlineBeforeMore = 2,
    maxInline = 3,
    maxWidth = 220,
    minWidth = DEFAULT_ACTION_MIN_WIDTH,
    moreLabel = '更多',
    ...rest
  } = options;

  const rows = normalizeVisibleActionRows(actionRows)
    .map((labels) => collapseActionLabels(labels, maxInline, inlineBeforeMore, moreLabel));

  return estimateActionColumnWidth(rows, { ...rest, maxWidth, minWidth });
}
