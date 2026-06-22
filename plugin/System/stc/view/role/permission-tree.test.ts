import { describe, expect, it } from 'vitest';

import {
  isPermissionNodeChecked,
  isPermissionNodeIndeterminate,
  mapPermissionMenuIdsToCodes,
  normalizePermissionMenuIds,
  togglePermissionNodeIds,
  type PermissionTreeNode,
} from './permission-tree';

const permissionTree: PermissionTreeNode[] = [
  {
    id: 1,
    name: '系统管理',
    code: 'system',
    children: [
      {
        id: 2,
        name: '用户管理',
        code: 'system.user',
        children: [
          { id: 3, name: '用户列表', code: 'system.user.index' },
          { id: 4, name: '新增用户', code: 'system.user.create' },
        ],
      },
    ],
  },
];

describe('permission-tree', () => {
  it('selects ancestors when a third-level child is checked', () => {
    const userNode = permissionTree[0]!.children![0]!;
    const indexNode = userNode.children![0]!;

    const selected = togglePermissionNodeIds([], indexNode, true, permissionTree);

    expect(selected).toEqual([1, 2, 3]);
    expect(mapPermissionMenuIdsToCodes(selected, permissionTree)).toEqual([
      'system',
      'system.user',
      'system.user.index',
    ]);
    expect(isPermissionNodeChecked(selected, permissionTree[0]!)).toBe(true);
    expect(isPermissionNodeChecked(selected, userNode)).toBe(true);
    expect(isPermissionNodeIndeterminate(selected, permissionTree[0]!)).toBe(true);
    expect(isPermissionNodeIndeterminate(selected, userNode)).toBe(true);
  });

  it('keeps a parent permission when one child is unchecked', () => {
    const userNode = permissionTree[0]!.children![0]!;
    const createNode = userNode.children![1]!;

    const selectedAll = togglePermissionNodeIds([], userNode, true, permissionTree);
    const selectedPartial = togglePermissionNodeIds(selectedAll, createNode, false, permissionTree);

    expect(selectedPartial).toEqual([1, 2, 3]);
    expect(mapPermissionMenuIdsToCodes(selectedPartial, permissionTree)).toEqual([
      'system',
      'system.user',
      'system.user.index',
    ]);
    expect(isPermissionNodeChecked(selectedPartial, userNode)).toBe(true);
    expect(isPermissionNodeIndeterminate(selectedPartial, userNode)).toBe(true);
  });

  it('saves a single parent node without children', () => {
    const tree = [{ id: 10, name: '报表中心', code: 'system.report' }];

    const selected = togglePermissionNodeIds([], tree[0]!, true, tree);

    expect(selected).toEqual([10]);
    expect(mapPermissionMenuIdsToCodes(selected, tree)).toEqual(['system.report']);
  });

  it('supports top-level group permissions as independent nodes', () => {
    const selected = normalizePermissionMenuIds([1], permissionTree);

    expect(isPermissionNodeChecked(selected, permissionTree[0]!)).toBe(true);
    expect(mapPermissionMenuIdsToCodes(selected, permissionTree)).toEqual(['system']);
  });

  it('keeps structure ancestors but skips empty ancestor codes when saving', () => {
    const tree = [
      {
        id: 20,
        name: '结构目录',
        code: '',
        children: [
          { id: 21, name: '业务页面', code: 'system.business.index' },
        ],
      },
    ];

    const selected = togglePermissionNodeIds([], tree[0]!.children![0]!, true, tree);

    expect(selected).toEqual([20, 21]);
    expect(mapPermissionMenuIdsToCodes(selected, tree)).toEqual(['system.business.index']);
  });

  it('removes a parent and all descendants when the parent is unchecked', () => {
    const selected = togglePermissionNodeIds([1, 2, 3, 4], permissionTree[0]!, false, permissionTree);

    expect(selected).toEqual([]);
    expect(mapPermissionMenuIdsToCodes(selected, permissionTree)).toEqual([]);
  });

  it('ignores pseudo root ids and invalid node ids', () => {
    const tree = [
      {
        id: 0,
        name: '根目录',
        code: 'root',
        children: [
          { id: 11, name: '真实菜单', code: 'system.real' },
          { id: 'bad', name: '无效菜单', code: 'system.bad' },
        ],
      },
    ];

    expect(normalizePermissionMenuIds([0, 11, 'bad'], tree)).toEqual([11]);
    expect(mapPermissionMenuIdsToCodes([0, 11, 'bad'], tree)).toEqual(['system.real']);
  });
});
