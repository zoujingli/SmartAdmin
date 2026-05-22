import { h } from 'vue';

import CrudEmptyState from '../crud-empty-state/crud-empty-state.vue';

export function buildCrudTableLocale(
  description: string,
  size: 'lg' | 'md' | 'sm' = 'sm',
) {
  return {
    emptyText: h(CrudEmptyState, {
      description,
      size,
    }),
  };
}
