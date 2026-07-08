import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';

import CrudSearchPanel from '../crud-search-panel.vue';

const ButtonStub = defineComponent({
  name: 'Button',
  props: {
    disabled: Boolean,
    loading: Boolean,
  },
  emits: ['click'],
  setup(props, { attrs, emit, slots }) {
    return () => h('button', {
      ...attrs,
      'data-loading': props.loading ? 'true' : 'false',
      disabled: props.disabled,
      onClick: (event: MouseEvent) => emit('click', event),
    }, slots.default?.());
  },
});

const PassthroughStub = (name: string, tag = 'div') => defineComponent({
  name,
  setup(_, { attrs, slots }) {
    return () => h(tag, attrs, slots.default?.());
  },
});

const CrudFilterSummaryStub = defineComponent({
  name: 'CrudFilterSummary',
  props: {
    emptyText: String,
    items: {
      default: () => [],
      type: Array,
    },
  },
  setup(props) {
    return () => h('div', { class: 'summary-stub' }, (props.items as Array<{ label: string; value: string }>).length > 0
      ? (props.items as Array<{ label: string; value: string }>).map((item) => `${item.label}:${item.value}`).join('|')
      : props.emptyText);
  },
});

function mountPanel(options: Record<string, unknown> = {}) {
  return mount(CrudSearchPanel, {
    ...options,
    global: {
      stubs: {
        AButton: ButtonStub,
        ACard: PassthroughStub('Card'),
        ACol: PassthroughStub('Col'),
        ARow: PassthroughStub('Row'),
        ASpace: PassthroughStub('Space'),
        Button: ButtonStub,
        Card: PassthroughStub('Card'),
        Col: PassthroughStub('Col'),
        CrudFilterSummary: CrudFilterSummaryStub,
        Row: PassthroughStub('Row'),
        Space: PassthroughStub('Space'),
      },
    },
  });
}

describe('CrudSearchPanel', () => {
  it('renders default actions and emits search/reset events', async () => {
    const wrapper = mountPanel({
      props: {
        emptyText: '全部记录',
        filterItems: [{ label: '状态', value: '启用' }],
        itemWidth: 320,
      },
      slots: {
        default: '<div data-test="field">字段</div>',
      },
    });

    expect(wrapper.find('[data-test="field"]').exists()).toBe(true);
    expect(wrapper.find('.summary-stub').text()).toBe('状态:启用');
    expect(wrapper.find('.crud-search-grid').attributes('style')).toContain('--crud-search-item-width: 320px');

    const buttons = wrapper.findAll('button');
    expect(buttons.map((button) => button.text())).toEqual(['搜索', '重置']);

    await buttons[0]!.trigger('click');
    await buttons[1]!.trigger('click');

    expect(wrapper.emitted('search')).toHaveLength(1);
    expect(wrapper.emitted('reset')).toHaveLength(1);
  });

  it('supports custom actions and summary slots', () => {
    const wrapper = mountPanel({
      slots: {
        actions: '<button class="custom-action">筛选</button>',
        default: '<div>字段</div>',
        summary: '<div class="custom-summary">自定义摘要</div>',
      },
    });

    expect(wrapper.find('.custom-action').exists()).toBe(true);
    expect(wrapper.find('.custom-summary').text()).toBe('自定义摘要');
    expect(wrapper.find('.summary-stub').exists()).toBe(false);
    expect(wrapper.findAll('button')).toHaveLength(1);
  });

  it('can hide the default filter summary', () => {
    const wrapper = mountPanel({
      props: {
        showSummary: false,
      },
    });

    expect(wrapper.find('.summary-stub').exists()).toBe(false);
  });
});
