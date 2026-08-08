import { flushPromises, mount } from '@vue/test-utils';
import { Modal } from 'ant-design-vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

import type { CrudTableAction } from '../crud-table-actions.vue';
import CrudTableActions from '../crud-table-actions.vue';

interface CrudTableActionsProps {
  actions: CrudTableAction[];
  explicitInline?: boolean;
  inlineBeforeMore?: number;
  loadingText?: string;
  maxInline?: number;
  moreText?: string;
}

const ButtonStub = defineComponent({
  name: 'Button',
  props: {
    danger: Boolean,
    disabled: Boolean,
    loading: Boolean,
  },
  emits: ['click'],
  setup(props, { attrs, emit, slots }) {
    return () => h('button', {
      ...attrs,
      'data-danger': props.danger ? 'true' : 'false',
      'data-loading': props.loading ? 'true' : 'false',
      disabled: props.disabled,
      onClick: (event: MouseEvent) => emit('click', event),
    }, slots.default?.());
  },
});

const DropdownStub = defineComponent({
  name: 'Dropdown',
  setup(_, { slots }) {
    return () => h('div', { class: 'dropdown-stub' }, [
      slots.default?.(),
      slots.overlay?.(),
    ]);
  },
});

const MenuStub = defineComponent({
  name: 'Menu',
  setup(_, { slots }) {
    return () => h('ul', slots.default?.());
  },
});

const MenuItemStub = defineComponent({
  name: 'MenuItem',
  props: {
    danger: Boolean,
    disabled: Boolean,
  },
  emits: ['click'],
  setup(props, { attrs, emit, slots }) {
    return () => h('li', {
      ...attrs,
      'data-danger': props.danger ? 'true' : 'false',
      'data-disabled': props.disabled ? 'true' : 'false',
      onClick: (event: MouseEvent) => {
        if (!props.disabled) emit('click', event);
      },
    }, slots.default?.());
  },
});

const SpaceStub = defineComponent({
  name: 'Space',
  setup(_, { attrs, slots }) {
    return () => h('div', attrs, slots.default?.());
  },
});

const TooltipStub = defineComponent({
  name: 'Tooltip',
  setup(_, { slots }) {
    return () => h('span', { class: 'tooltip-stub' }, slots.default?.());
  },
});

function mountActions(props: CrudTableActionsProps) {
  return mount(CrudTableActions, {
    props,
    global: {
      stubs: {
        AButton: ButtonStub,
        ADropdown: DropdownStub,
        AMenu: MenuStub,
        AMenuItem: MenuItemStub,
        ASpace: SpaceStub,
        ATooltip: TooltipStub,
        Button: ButtonStub,
        Dropdown: DropdownStub,
        Menu: MenuStub,
        MenuItem: MenuItemStub,
        Space: SpaceStub,
        Tooltip: TooltipStub,
      },
    },
  });
}

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

describe('CrudTableActions', () => {
  it('keeps only explicitly inline actions outside the more menu', () => {
    const wrapper = mountActions({
      actions: [
        { inline: true, label: '查看' },
        { inline: true, label: '编辑' },
        { label: '验收标准' },
        { label: '取消', visible: false },
      ],
      explicitInline: true,
    });

    expect(wrapper.findAll('button').map((button) => button.text())).toEqual(['查看', '编辑', '更多']);
    expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['验收标准']);
  });

  it('does not render more when every visible action is inline', () => {
    const wrapper = mountActions({
      actions: [
        { inline: true, label: '查看' },
        { label: '验收标准', visible: false },
      ],
      explicitInline: true,
    });

    expect(wrapper.findAll('button').map((button) => button.text())).toEqual(['查看']);
    expect(wrapper.findAll('li')).toHaveLength(0);
  });

  it('renders only more when no visible action is explicitly inline', () => {
    const wrapper = mountActions({
      actions: [
        { inline: true, label: '查看', visible: false },
        { label: '验收标准' },
        { label: '指派', visible: false },
      ],
      explicitInline: true,
    });

    expect(wrapper.findAll('button').map((button) => button.text())).toEqual(['更多']);
    expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['验收标准']);
  });

  it('renders no action container when every action is hidden', () => {
    const wrapper = mountActions({
      actions: [
        { inline: true, label: '查看', visible: false },
        { label: '验收标准', visible: false },
      ],
      explicitInline: true,
    });

    expect(wrapper.find('.crud-table-actions').exists()).toBe(false);
    expect(wrapper.findAll('button')).toHaveLength(0);
    expect(wrapper.findAll('li')).toHaveLength(0);
  });

  it('collapses actions after maxInline and keeps danger actions in the dropdown', () => {
    const wrapper = mountActions({
      actions: [
        { label: '查看' },
        { label: '编辑', icon: 'i-lucide-pencil', tooltip: '编辑记录' },
        { label: '同步' },
        { danger: true, label: '删除' },
      ],
    });

    const buttons = wrapper.findAll('button').map((button) => button.text());
    expect(buttons).toEqual(['查看', '编辑', '更多']);
    expect(wrapper.findAll('li').map((item) => item.text())).toEqual(['同步', '删除']);
    expect(wrapper.findAll('li')[1]?.attributes('data-danger')).toBe('true');
  });

  it('locks the row while one action promise is still running', async () => {
    vi.useFakeTimers();

    let resolveAction!: () => void;
    const firstAction = vi.fn(() => new Promise<void>((resolve) => {
      resolveAction = resolve;
    }));
    const secondAction = vi.fn();
    const wrapper = mountActions({
      actions: [
        { key: 'first', label: '执行', onClick: firstAction },
        { key: 'second', label: '编辑', onClick: secondAction },
      ],
    });

    await wrapper.findAll('button')[0]!.trigger('click');
    await nextTick();
    await wrapper.findAll('button')[1]!.trigger('click');

    expect(firstAction).toHaveBeenCalledTimes(1);
    expect(secondAction).not.toHaveBeenCalled();
    expect(wrapper.findAll('button')[1]!.attributes('disabled')).toBeDefined();

    resolveAction();
    await flushPromises();
    await vi.runAllTimersAsync();
  });

  it('uses confirm options only when confirmTitle is provided', async () => {
    const confirmSpy = vi.spyOn(Modal, 'confirm').mockReturnValue({ destroy: vi.fn(), update: vi.fn() } as any);
    const onClick = vi.fn();
    const wrapper = mountActions({
      actions: [
        {
          confirmCancelText: '不用了',
          confirmContent: '删除后不可恢复',
          confirmOkText: '删除',
          confirmTitle: '确认删除？',
          danger: true,
          label: '删除',
          onClick,
        },
      ],
    });

    await wrapper.find('button').trigger('click');

    expect(confirmSpy).toHaveBeenCalledTimes(1);
    const options = confirmSpy.mock.calls[0]![0] as any;
    expect(options).toMatchObject({
      cancelText: '不用了',
      content: '删除后不可恢复',
      okText: '删除',
      okType: 'danger',
      title: '确认删除？',
    });

    await options.onOk();
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('does not auto-confirm danger actions without confirmTitle', async () => {
    const confirmSpy = vi.spyOn(Modal, 'confirm').mockReturnValue({ destroy: vi.fn(), update: vi.fn() } as any);
    const onClick = vi.fn();
    const wrapper = mountActions({
      actions: [{ danger: true, label: '删除', onClick }],
    });

    await wrapper.find('button').trigger('click');

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
