export interface PermissionTreeNode {
  id?: number | string
  code?: number | string
  children?: PermissionTreeNode[]
  [key: string]: unknown
}

export function getPermissionNodeId(node: PermissionTreeNode | undefined): null | number {
  const id = Number(node?.id);

  return Number.isInteger(id) && id > 0 ? id : null;
}

export function getPermissionNodeChildren(node: PermissionTreeNode | undefined): PermissionTreeNode[] {
  return Array.isArray(node?.children) ? node.children : [];
}

export function collectPermissionNodeIds(nodes: PermissionTreeNode[]): number[] {
  const ids: number[] = [];

  const visit = (items: PermissionTreeNode[]) => {
    for (const node of items ?? []) {
      const id = getPermissionNodeId(node);
      if (id !== null) {
        ids.push(id);
      }

      const children = getPermissionNodeChildren(node);
      if (children.length > 0) {
        visit(children);
      }
    }
  };

  visit(nodes);

  return Array.from(new Set(ids));
}

export function normalizePermissionMenuIds(rawIds: Iterable<unknown>, tree: PermissionTreeNode[]): number[] {
  const source = new Set(
    Array.from(rawIds)
      .map((id) => Number(id))
      .filter((id) => Number.isInteger(id) && id > 0),
  );
  const selectedWithAncestors = new Set<number>();

  // 授权保存依赖父级菜单作为可见路径；选中任意下级时必须补齐有效祖先节点。
  const visit = (items: PermissionTreeNode[], ancestorIds: number[] = []) => {
    for (const node of items ?? []) {
      const id = getPermissionNodeId(node);
      const nextAncestorIds = id === null ? ancestorIds : [...ancestorIds, id];
      if (id !== null && source.has(id)) {
        for (const ancestorId of nextAncestorIds) {
          selectedWithAncestors.add(ancestorId);
        }
      }

      const children = getPermissionNodeChildren(node);
      if (children.length > 0) {
        visit(children, nextAncestorIds);
      }
    }
  };

  visit(tree);

  return collectPermissionNodeIds(tree).filter((id) => selectedWithAncestors.has(id));
}

export function togglePermissionNodeIds(
  currentIds: Iterable<unknown>,
  node: PermissionTreeNode,
  checked: boolean,
  tree: PermissionTreeNode[],
): number[] {
  // 父级菜单自身也是可授权节点，点击父级只负责批量联动后代；
  // 后续取消某个子级时不能反向删除父级自身授权，否则半选父级会丢失数据库授权。
  const next = new Set(normalizePermissionMenuIds(currentIds, tree));
  const nodeIds = collectPermissionNodeIds([node]);

  for (const id of nodeIds) {
    if (checked) {
      next.add(id);
    } else {
      next.delete(id);
    }
  }

  return normalizePermissionMenuIds(next, tree);
}

export function isPermissionNodeChecked(selectedIds: Iterable<unknown>, node: PermissionTreeNode): boolean {
  const id = getPermissionNodeId(node);
  if (id === null) {
    return false;
  }

  return normalizePermissionMenuIds(selectedIds, [node]).includes(id);
}

export function isPermissionNodeIndeterminate(selectedIds: Iterable<unknown>, node: PermissionTreeNode): boolean {
  const selected = new Set(normalizePermissionMenuIds(selectedIds, [node]));
  const descendantIds = collectPermissionNodeIds(getPermissionNodeChildren(node));
  if (descendantIds.length === 0) {
    return false;
  }

  const selectedDescendantCount = descendantIds.filter((id) => selected.has(id)).length;
  if (selectedDescendantCount === 0) {
    return false;
  }

  const selfId = getPermissionNodeId(node);
  const selfChecked = selfId !== null && selected.has(selfId);

  return !selfChecked || selectedDescendantCount < descendantIds.length;
}

export function mapPermissionMenuIdsToCodes(menuIds: Iterable<unknown>, tree: PermissionTreeNode[]): string[] {
  const selected = new Set(normalizePermissionMenuIds(menuIds, tree));
  const codes: string[] = [];

  const visit = (items: PermissionTreeNode[]) => {
    for (const node of items ?? []) {
      const id = getPermissionNodeId(node);
      const code = String(node?.code ?? '').trim();
      if (id !== null && selected.has(id) && code !== '') {
        codes.push(code);
      }

      const children = getPermissionNodeChildren(node);
      if (children.length > 0) {
        visit(children);
      }
    }
  };

  visit(tree);

  return Array.from(new Set(codes));
}
