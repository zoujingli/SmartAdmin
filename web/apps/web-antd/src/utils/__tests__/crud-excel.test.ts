import { describe, expect, it, vi } from 'vitest';

import { runCrudBatchImport } from '../crud-excel';

const columns = [
  { key: 'title', required: true, title: '标题' },
];

describe('crud excel atomic imports', () => {
  it('does not submit any valid rows when one row fails atomic validation', async () => {
    const submitRows = vi.fn();
    const result = await runCrudBatchImport({
      atomicBatch: true,
      columns,
      moduleName: '测试',
      submitRows,
    }, columns, [
      { 标题: '合法行' },
      { 标题: '' },
    ], vi.fn());

    expect(submitRows).not.toHaveBeenCalled();
    expect(result).toMatchObject({ committed: false, success: 0, total: 2 });
    expect(result.results).toEqual([
      { index: 1, message: '本批未提交：请先修正其他错误行', status: 'skipped' },
      { index: 2, message: '必填字段不能为空：标题', status: 'failed' },
    ]);
  });

  it('keeps a committed batch done and returns a warning when refresh fails', async () => {
    const afterDone = vi.fn(async () => {
      throw new Error('列表接口暂不可用');
    });
    const result = await runCrudBatchImport({
      atomicBatch: true,
      afterDone,
      columns,
      moduleName: '测试',
      submitRows: vi.fn(async () => ({ case_count: 1 })),
    }, columns, [{ 标题: '已写入' }], vi.fn());

    expect(result).toMatchObject({ committed: true, success: 1, total: 1 });
    expect(result.warning).toContain('数据已写入成功，但页面刷新失败');
    expect(result.warning).toContain('列表接口暂不可用');
    expect(afterDone).toHaveBeenCalledWith({ case_count: 1 });
  });

  it('rejects a batch request failure so the preview can retry the same session', async () => {
    const afterDone = vi.fn();
    await expect(runCrudBatchImport({
      atomicBatch: true,
      afterDone,
      columns,
      moduleName: '测试',
      submitRows: vi.fn(async () => {
        throw new Error('网络结果不确定');
      }),
    }, columns, [{ 标题: '待重试' }], vi.fn())).rejects.toThrow('网络结果不确定');

    expect(afterDone).not.toHaveBeenCalled();
  });
});
