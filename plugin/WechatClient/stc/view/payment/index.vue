<template>
  <Page :title="pageTitle">
    <template #extra>
      <Space wrap class="justify-end">
        <Button :loading="loading" @click="reloadCurrentTab">
          <span class="i-lucide-refresh-cw" />刷新
        </Button>
        <Button v-if="tab === 'merchant' && canSavePayment" type="primary" @click="openCreate">
          <span class="i-lucide-plus" />新增商户
        </Button>
      </Space>
    </template>

    <Card class="crud-page-shell">
      <Tabs v-model:activeKey="tab" class="crud-page-tabs payment-single-tabs">
        <TabPane v-if="tab === 'merchant'" key="merchant" tab="商户配置">
          <CrudStatCards class="mb-5" :items="merchantSummaryCards" />
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="merchantKeyword" allow-clear placeholder="商户名称 / AppID / 商户号" /></SearchField>
              </Col>
              <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
                <Space wrap>
                  <Button type="primary" :loading="loading" @click="searchMerchants"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loading" @click="resetMerchants"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary :items="merchantFilterItems" empty-text="当前显示全部支付商户，可按商户名称、AppID 或商户号筛选。" />
          </Card>
          <Card>
            <CrudTableHeader title="支付商户" description="维护微信支付 APIv3 商户配置和平台证书参数。" :count-text="`${merchantPage.total} 条记录`" />
            <Table :columns="merchantColumns" :data-source="merchants" :loading="loading" :locale="buildCrudTableLocale('暂无支付商户')" :pagination="merchantPage" :scroll="merchantTableScroll" row-key="id" @change="onMerchantChange">
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'name'">
                  <Tooltip :title="record.name" placement="topLeft"><div class="truncate">{{ record.name || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'appid'">
                  <Tooltip :title="record.appid" placement="topLeft"><div class="truncate">{{ record.appid || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'status'">
                  <Tag :color="Number(record.status) === 1 ? 'success' : 'default'">{{ Number(record.status) === 1 ? '启用' : '禁用' }}</Tag>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="merchantActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="tab === 'order'" key="order" tab="支付订单">
          <CrudStatCards class="mb-5" :items="orderSummaryCards" />
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="orderKeyword" allow-clear placeholder="业务订单号 / 支付号 / 微信订单号 / 描述" /></SearchField>
              </Col>
              <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
                <Space wrap>
                  <Button type="primary" :loading="loading" @click="searchOrders"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loading" @click="resetOrders"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary :items="orderFilterItems" empty-text="当前显示全部支付订单，可按业务订单号、支付号、微信订单号或商品描述筛选。" />
          </Card>
          <Card>
            <CrudTableHeader title="支付订单" description="查看微信支付订单流水、支付状态和退款入口。" :count-text="`${orderPage.total} 条记录`" />
            <Table :columns="orderColumns" :data-source="orders" :loading="loading" :locale="buildCrudTableLocale('暂无支付订单')" :pagination="orderPage" :scroll="orderTableScroll" row-key="id" @change="onOrderChange">
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'order_no'">
                  <Tooltip :title="record.order_no" placement="topLeft"><div class="truncate">{{ record.order_no || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'out_trade_no'">
                  <Tooltip :title="record.out_trade_no" placement="topLeft"><div class="truncate">{{ record.out_trade_no || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'description'">
                  <Tooltip :title="record.description" placement="topLeft"><div class="truncate">{{ record.description || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'amount_total'">{{ formatCent(record.amount_total) }}</template>
                <template v-else-if="column.key === 'trade_state'"><Tag :color="tradeStateColor(record.trade_state)">{{ tradeStateText(record.trade_state) }}</Tag></template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="orderActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>

        <TabPane v-if="tab === 'refund'" key="refund" tab="退款记录">
          <CrudStatCards class="mb-5" :items="refundSummaryCards" />
          <Card class="mb-5" :body-style="{ padding: '20px 24px' }">
            <Row :gutter="[16, 16]" class="mb-4 crud-search-grid">
              <Col :xs="24" :sm="12" :xl="6">
                <SearchField label="搜索内容"><Input v-model:value="refundKeyword" allow-clear placeholder="业务订单号 / 支付号 / 退款号 / 微信退款号" /></SearchField>
              </Col>
              <Col class="crud-search-grid__actions" :xs="24" :sm="12" :xl="6">
                <Space wrap>
                  <Button type="primary" :loading="loading" @click="searchRefunds"><span class="i-lucide-search" />搜索</Button>
                  <Button :disabled="loading" @click="resetRefunds"><span class="i-lucide-refresh-cw" />重置</Button>
                </Space>
              </Col>
            </Row>
            <CrudFilterSummary :items="refundFilterItems" empty-text="当前显示全部退款记录，可按业务订单号、支付号、退款号或微信退款号筛选。" />
          </Card>
          <Card>
            <CrudTableHeader title="退款记录" description="查看微信支付退款流水和退款状态。" :count-text="`${refundPage.total} 条记录`" />
            <Table :columns="refundColumns" :data-source="refunds" :loading="loading" :locale="buildCrudTableLocale('暂无退款记录')" :pagination="refundPage" :scroll="refundTableScroll" row-key="id" @change="onRefundChange">
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'order_no'">
                  <Tooltip :title="record.order_no" placement="topLeft"><div class="truncate">{{ record.order_no || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'out_refund_no'">
                  <Tooltip :title="record.out_refund_no" placement="topLeft"><div class="truncate">{{ record.out_refund_no || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'out_trade_no'">
                  <Tooltip :title="record.out_trade_no" placement="topLeft"><div class="truncate">{{ record.out_trade_no || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'amount_refund'">{{ formatCent(record.amount_refund) }}</template>
                <template v-else-if="column.key === 'refund_status'"><Tag :color="refundStateColor(record.refund_status)">{{ refundStateText(record.refund_status) }}</Tag></template>
                <template v-else-if="column.key === 'reason'">
                  <Tooltip :title="record.reason" placement="topLeft"><div class="truncate">{{ record.reason || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'fail_reason'">
                  <Tooltip :title="record.fail_reason" placement="topLeft"><div class="truncate">{{ record.fail_reason || '-' }}</div></Tooltip>
                </template>
                <template v-else-if="column.key === 'action'">
                  <CrudTableActions :actions="refundActions(record)" />
                </template>
              </template>
            </Table>
          </Card>
        </TabPane>
      </Tabs>
    </Card>

    <Drawer
      :open="open"
      title="支付商户"
      :body-style="{ padding: '20px 24px 8px' }"
      width="min(800px, calc(100vw - 32px))"
      placement="right"
      @close="open = false"
    >
      <Form :model="form" layout="vertical">
        <Row :gutter="[16, 0]">
          <Col :md="12" :span="24"><FormItem label="商户名称"><Input v-model:value="form.name" :maxlength="120" allow-clear placeholder="请输入商户名称" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="接口账号 ID"><InputNumber v-model:value="form.account_id" :min="0" class="w-full" placeholder="可选，关联接口账号" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="AppID"><Input v-model:value="form.appid" :maxlength="64" allow-clear placeholder="请输入支付 AppID" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="商户号"><Input v-model:value="form.mch_id" :maxlength="64" allow-clear placeholder="请输入商户号" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="APIv3 Key"><InputPassword v-model:value="form.api_v3_key" placeholder="请输入 APIv3 Key" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="状态"><Switch v-model:checked="merchantEnabled" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="商户证书序列号"><InputPassword v-model:value="form.merchant_serial" placeholder="请输入商户证书序列号" /></FormItem></Col>
          <Col :md="12" :span="24"><FormItem label="平台证书序列号"><InputPassword v-model:value="form.platform_serial" placeholder="请输入平台证书序列号" /></FormItem></Col>
          <Col :span="24"><FormItem label="商户私钥"><Textarea v-model:value="form.merchant_private_key" :rows="5" placeholder="请输入商户私钥 PEM" /></FormItem></Col>
          <Col :span="24"><FormItem label="平台公钥"><Textarea v-model:value="form.platform_public_key" :rows="5" placeholder="请输入微信支付平台公钥" /></FormItem></Col>
        </Row>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="open = false">取消</Button>
          <Button type="primary" :loading="saving" @click="saveMerchant">确定</Button>
        </div>
      </template>
    </Drawer>

    <Drawer
      :open="refundOpen"
      title="发起退款"
      :body-style="{ padding: '20px 24px 8px' }"
      width="min(560px, calc(100vw - 32px))"
      placement="right"
      @close="refundOpen = false"
    >
      <Form :model="refundForm" layout="vertical">
        <FormItem label="业务订单号"><Input v-model:value="refundForm.order_no" disabled /></FormItem>
        <FormItem label="支付号"><Input v-model:value="refundForm.out_trade_no" disabled /></FormItem>
        <FormItem label="退款金额(分)">
          <InputNumber v-model:value="refundForm.amount_refund" :min="1" :max="refundForm.amount_total || undefined" :precision="0" class="w-full" />
          <div class="mt-1 text-xs text-gray-500">单次不超过订单金额 {{ formatCent(refundForm.amount_total) }}；剩余额度以后端累计校验为准；提交后将调用微信退款接口。</div>
        </FormItem>
        <FormItem label="退款原因"><Textarea v-model:value="refundForm.reason" :rows="3" allow-clear /></FormItem>
      </Form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <Button @click="refundOpen = false">取消</Button>
          <Button type="primary" danger :disabled="!canSubmitRefund" :loading="refundSubmitting" @click="submitRefund">确认退款</Button>
        </div>
      </template>
    </Drawer>
  </Page>
</template>

<script setup lang="ts">
import type { CrudFilterSummaryItem } from '@vben/common-ui';

import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAccess } from '@vben/access';
import { buildCrudTableLocale, CrudFilterSummary, CrudStatCards, CrudTableHeader, Page } from '@vben/common-ui';
import { Button, Card, Col, Drawer, Form, FormItem, Input, InputNumber, InputPassword, message, Modal, Row, Space, Switch, Table, TabPane, Tabs, Tag, Textarea, Tooltip } from 'ant-design-vue';

import SearchField from '#/components/crud-search-field.vue';
import CrudTableActions from '#/components/crud-table-actions.vue';
import { requestClient } from '#/api/request';
import { buildTableScrollX, estimateVisibleActionColumnWidth } from '#/utils/table';

type PaymentTabKey = 'merchant' | 'order' | 'refund';

const props = defineProps<{
  /** 当前独立菜单页面的业务模式：商户、订单、退款三个路由分别只加载自己的数据源。 */
  mode: PaymentTabKey;
}>();

const { hasAccessByCodes } = useAccess();
const router = useRouter();
const canSavePayment = computed(() => hasAccessByCodes(['wechat.client.payment.merchant.save']));
const canQueryOrder = computed(() => hasAccessByCodes(['wechat.client.payment.order.query']));
const canQueryRefund = computed(() => hasAccessByCodes(['wechat.client.payment.refund.query']));
const canRefundPayment = computed(() => hasAccessByCodes(['wechat.client.payment.refund.create']));
const paymentTabRoutes: Record<PaymentTabKey, string> = {
  merchant: '/wechat/client/payment/merchant',
  order: '/wechat/client/payment/order',
  refund: '/wechat/client/payment/refund',
};
const paymentTabTitles: Record<PaymentTabKey, string> = {
  merchant: '支付商户',
  order: '支付订单',
  refund: '退款记录',
};
const tab = ref<PaymentTabKey>(props.mode);
const pageTitle = computed(() => paymentTabTitles[tab.value] || '微信支付');
const loading = ref(false);
const saving = ref(false);
const refundSubmitting = ref(false);
const open = ref(false);
const refundOpen = ref(false);
const editingId = ref<number | null>(null);
const merchantKeyword = ref('');
const orderKeyword = ref('');
const refundKeyword = ref('');
const merchants = ref<any[]>([]);
const orders = ref<any[]>([]);
const refunds = ref<any[]>([]);
const merchantPage = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const orderPage = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const refundPage = reactive({ current: 1, pageSize: 10, total: 0, showSizeChanger: true });
const form = reactive<any>({ name: '', account_id: 0, appid: '', mch_id: '', api_v3_key: '', merchant_serial: '', merchant_private_key: '', platform_public_key: '', platform_serial: '', status: 1 });
const refundForm = reactive<any>({ order_id: undefined, order_no: '', out_trade_no: '', amount_total: 0, amount_refund: 0, reason: '' });
const merchantEnabled = computed({ get: () => Number(form.status) === 1, set: (value: boolean) => { form.status = value ? 1 : 0; } });
const canSubmitRefund = computed(() => {
  const amount = Number(refundForm.amount_refund || 0);
  const total = Number(refundForm.amount_total || 0);
  return Number.isInteger(amount) && amount > 0 && total > 0 && amount <= total;
});

const merchantActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([merchantActions({})], { maxWidth: 150 }));
const orderActionColumnWidth = computed(() => estimateVisibleActionColumnWidth(orders.value.length > 0 ? orders.value.map(orderActions) : [orderActions({})], { maxWidth: 180 }));
const refundActionColumnWidth = computed(() => estimateVisibleActionColumnWidth([refundActions({})], { maxWidth: 150 }));
const merchantColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '名称', key: 'name', dataIndex: 'name', width: 180 },
  { title: 'AppID', key: 'appid', dataIndex: 'appid', width: 190 },
  { title: '商户号', dataIndex: 'mch_id', width: 160 },
  { title: '状态', key: 'status', dataIndex: 'status', width: 100 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: merchantActionColumnWidth.value, fixed: 'right' as const },
]);
const orderColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '业务订单号', key: 'order_no', dataIndex: 'order_no', width: 180 },
  { title: '支付号', key: 'out_trade_no', dataIndex: 'out_trade_no', width: 220 },
  { title: '微信订单号', dataIndex: 'transaction_id', width: 220 },
  { title: '商品描述', key: 'description', dataIndex: 'description', width: 220 },
  { title: '金额', key: 'amount_total', dataIndex: 'amount_total', width: 120 },
  { title: '状态', key: 'trade_state', dataIndex: 'trade_state', width: 130 },
  { title: '支付时间', dataIndex: 'paid_at', width: 180 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: orderActionColumnWidth.value, fixed: 'right' as const },
]);
const refundColumns = computed(() => [
  { title: 'ID', dataIndex: 'id', width: 80 },
  { title: '业务订单号', key: 'order_no', dataIndex: 'order_no', width: 180 },
  { title: '支付号', key: 'out_trade_no', dataIndex: 'out_trade_no', width: 220 },
  { title: '退款号', key: 'out_refund_no', dataIndex: 'out_refund_no', width: 220 },
  { title: '微信退款号', dataIndex: 'refund_id', width: 220 },
  { title: '退款金额', key: 'amount_refund', dataIndex: 'amount_refund', width: 120 },
  { title: '状态', key: 'refund_status', dataIndex: 'refund_status', width: 130 },
  { title: '退款原因', key: 'reason', dataIndex: 'reason', width: 180 },
  { title: '失败原因', key: 'fail_reason', dataIndex: 'fail_reason', width: 220 },
  { title: '退款时间', dataIndex: 'refunded_at', width: 180 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', key: 'action', width: refundActionColumnWidth.value, fixed: 'right' as const },
]);
const merchantTableScroll = computed(() => buildTableScrollX(merchantColumns.value, { minWidth: 1040 }));
const orderTableScroll = computed(() => buildTableScrollX(orderColumns.value, { minWidth: 1680 }));
const refundTableScroll = computed(() => buildTableScrollX(refundColumns.value, { minWidth: 1860 }));

function merchantActions(record: any) {
  return [
    { label: '编辑', visible: canSavePayment.value, onClick: () => openEdit(record) },
  ];
}

function orderActions(record: any) {
  return [
    { label: '查单', visible: canQueryOrder.value, confirmTitle: '确认主动查询微信支付状态？', onClick: () => queryOrder(record) },
    { label: '退款', visible: canRefundPayment.value && record?.trade_state === 'SUCCESS', onClick: () => openRefund(record) },
  ];
}

function refundActions(record: any) {
  return [
    { label: '查退', visible: canQueryRefund.value, confirmTitle: '确认主动查询微信退款状态？', onClick: () => queryRefund(record) },
  ];
}

const merchantFilterItems = computed<CrudFilterSummaryItem[]>(() => merchantKeyword.value.trim() ? [{ label: '关键字', value: merchantKeyword.value.trim() }] : []);
const orderFilterItems = computed<CrudFilterSummaryItem[]>(() => orderKeyword.value.trim() ? [{ label: '关键字', value: orderKeyword.value.trim() }] : []);
const refundFilterItems = computed<CrudFilterSummaryItem[]>(() => refundKeyword.value.trim() ? [{ label: '关键字', value: refundKeyword.value.trim() }] : []);

const merchantSummaryCards = computed(() => {
  const enabled = merchants.value.filter((item) => Number(item.status) === 1).length;
  return [
    { label: '商户总数', value: String(merchantPage.total), desc: '当前筛选条件下的支付商户数量', icon: 'i-lucide-landmark', tone: 'primary' as const },
    { label: '本页启用', value: String(enabled), desc: '当前页启用的支付商户', icon: 'i-lucide-circle-check', tone: 'success' as const },
    { label: '本页禁用', value: String(merchants.value.length - enabled), desc: '当前页禁用的支付商户', icon: 'i-lucide-circle-off', tone: 'warning' as const },
    { label: 'APIv3', value: '安全密文', desc: '密钥字段保存为后端密文', icon: 'i-lucide-shield-check', tone: 'info' as const },
  ];
});
const orderSummaryCards = computed(() => [
  { label: '订单总数', value: String(orderPage.total), desc: '当前筛选条件下的支付订单数量', icon: 'i-lucide-receipt-text', tone: 'primary' as const },
  { label: '本页成功', value: String(orders.value.filter((item) => item.trade_state === 'SUCCESS').length), desc: '当前页支付成功订单', icon: 'i-lucide-circle-check', tone: 'success' as const },
  { label: '本页金额', value: formatCent(orders.value.reduce((sum, item) => sum + Number(item.amount_total || 0), 0)), desc: '当前页订单金额合计', icon: 'i-lucide-coins', tone: 'warning' as const },
  { label: '查单补偿', value: canQueryOrder.value ? '已授权' : '未授权', desc: '本地未终态时可主动查询微信状态', icon: 'i-lucide-refresh-cw', tone: 'info' as const },
]);
const refundSummaryCards = computed(() => [
  { label: '退款总数', value: String(refundPage.total), desc: '当前筛选条件下的退款记录数量', icon: 'i-lucide-rotate-ccw', tone: 'primary' as const },
  { label: '本页成功', value: String(refunds.value.filter((item) => item.refund_status === 'SUCCESS').length), desc: '当前页退款成功记录', icon: 'i-lucide-circle-check', tone: 'success' as const },
  { label: '本页金额', value: formatCent(refunds.value.reduce((sum, item) => sum + Number(item.amount_refund || 0), 0)), desc: '当前页退款金额合计', icon: 'i-lucide-coins', tone: 'warning' as const },
  { label: '处理中', value: String(refunds.value.filter((item) => item.refund_status === 'PROCESSING').length), desc: '当前页处理中的退款记录', icon: 'i-lucide-loader-circle', tone: 'info' as const },
]);

function formatCent(value: unknown) {
  return `¥${(Number(value || 0) / 100).toFixed(2)}`;
}
function tradeStateColor(value: string) {
  if (value === 'SUCCESS') return 'success';
  if (value === 'PAYERROR') return 'error';
  if (value === 'CREATED' || value === 'NOTPAY') return 'warning';
  if (value === 'CLOSED' || value === 'REVOKED') return 'default';
  return 'processing';
}
function tradeStateText(value: string) {
  return ({ CREATED: '已创建', NOTPAY: '待支付', USERPAYING: '支付中', SUCCESS: '支付成功', CLOSED: '已关闭', REVOKED: '已撤销', PAYERROR: '支付异常' } as Record<string, string>)[value] || value || '-';
}
function refundStateColor(value: string) {
  if (value === 'SUCCESS') return 'success';
  if (value === 'ABNORMAL' || value === 'FAIL') return 'error';
  if (value === 'CLOSED') return 'default';
  return 'processing';
}
function refundStateText(value: string) {
  return ({ PROCESSING: '处理中', SUCCESS: '退款成功', CLOSED: '已关闭', ABNORMAL: '退款异常', FAIL: '退款失败' } as Record<string, string>)[value] || value || '-';
}

async function loadMerchant() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/payment/merchant', { params: { page: merchantPage.current, pageSize: merchantPage.pageSize, keyword: merchantKeyword.value } });
    merchants.value = data?.items || [];
    merchantPage.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
async function loadOrders() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/payment/order', { params: { page: orderPage.current, pageSize: orderPage.pageSize, keyword: orderKeyword.value } });
    orders.value = data?.items || [];
    orderPage.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
async function loadRefunds() {
  if (loading.value) return;
  loading.value = true;
  try {
    const data = await requestClient.get<any>('wechat-client/payment/refund', { params: { page: refundPage.current, pageSize: refundPage.pageSize, keyword: refundKeyword.value } });
    refunds.value = data?.items || [];
    refundPage.total = data?.pageInfo?.total || 0;
  } finally { loading.value = false; }
}
function ensureTabLoaded(value: PaymentTabKey) {
  // 支付管理已拆成商户、订单、退款三个菜单；同一页面按路由激活对应数据源，避免进入订单/退款页时误加载商户列表。
  if (value === 'merchant' && merchants.value.length === 0) return loadMerchant();
  if (value === 'order' && orders.value.length === 0) return loadOrders();
  if (value === 'refund' && refunds.value.length === 0) return loadRefunds();
}
function reloadCurrentTab() { if (tab.value === 'merchant') loadMerchant(); else if (tab.value === 'order') loadOrders(); else loadRefunds(); }
function searchMerchants() { merchantPage.current = 1; loadMerchant(); }
function resetMerchants() { merchantKeyword.value = ''; searchMerchants(); }
function searchOrders() { orderPage.current = 1; loadOrders(); }
function resetOrders() { orderKeyword.value = ''; searchOrders(); }
function searchRefunds() { refundPage.current = 1; loadRefunds(); }
function resetRefunds() { refundKeyword.value = ''; searchRefunds(); }

function resetMerchantForm() { Object.assign(form, { name: '', account_id: 0, appid: '', mch_id: '', api_v3_key: '', merchant_serial: '', merchant_private_key: '', platform_public_key: '', platform_serial: '', status: 1 }); }
function openCreate() { editingId.value = null; resetMerchantForm(); open.value = true; }
function openEdit(record: any) { editingId.value = record.id; Object.assign(form, record, { api_v3_key: '******', merchant_serial: '******', merchant_private_key: '******', platform_public_key: '******', platform_serial: '******' }); open.value = true; }
async function saveMerchant() {
  saving.value = true;
  try {
    if (editingId.value) await requestClient.put(`wechat-client/payment/merchant/update/${editingId.value}`, form);
    else await requestClient.post('wechat-client/payment/merchant/create', form);
    message.success('保存成功');
    open.value = false;
    await loadMerchant();
  } finally { saving.value = false; }
}
function openRefund(record: any) {
  Object.assign(refundForm, { order_id: record.id, order_no: record.order_no, out_trade_no: record.out_trade_no, amount_total: Number(record.amount_total || 0), amount_refund: Number(record.amount_total || 0), reason: '' });
  refundOpen.value = true;
}
function submitRefund() {
  if (!canSubmitRefund.value) {
    message.warning('请输入有效退款金额，且不能超过订单金额');
    return;
  }

  // 退款会真实调用微信退款接口，必须在弹窗确认后再提交，避免后台误点造成资金变更。
  Modal.confirm({
    title: '确认发起微信退款？',
    content: `支付号：${refundForm.out_trade_no || '-'}，退款金额：${formatCent(refundForm.amount_refund)}。`,
    okText: '确认退款',
    okType: 'danger',
    cancelText: '再检查一下',
    async onOk() {
      refundSubmitting.value = true;
      try {
        await requestClient.post('wechat-client/payment/refund/create', { order_id: refundForm.order_id, amount_refund: refundForm.amount_refund, reason: refundForm.reason });
        message.success('退款已提交');
        refundOpen.value = false;
        await router.push(paymentTabRoutes.refund);
      } finally { refundSubmitting.value = false; }
    },
  });
}
async function queryOrder(record: any) {
  loading.value = true;
  try {
    // 查单只用于后台人工补偿：后端会在本地未终态时访问微信并同步结果，终态订单直接返回本地状态。
    const data = await requestClient.get<any>('wechat-client/payment/order/query', { params: { id: record.id } });
    message.success(data?.online_queried ? `已同步微信状态：${tradeStateText(data?.trade_state)}` : `本地已是终态：${tradeStateText(data?.trade_state)}`);
    await loadOrders();
  } finally { loading.value = false; }
}
async function queryRefund(record: any) {
  loading.value = true;
  try {
    // 查退只用于后台人工补偿：后端会在本地未终态时访问微信并同步结果，终态退款直接返回本地状态。
    const data = await requestClient.get<any>('wechat-client/payment/refund/query', { params: { id: record.id } });
    message.success(data?.online_queried ? `已同步微信状态：${refundStateText(data?.refund_status)}` : `本地已是终态：${refundStateText(data?.refund_status)}`);
    await loadRefunds();
  } finally { loading.value = false; }
}
function onMerchantChange(pag: any) { merchantPage.current = pag.current; merchantPage.pageSize = pag.pageSize; loadMerchant(); }
function onOrderChange(pag: any) { orderPage.current = pag.current; orderPage.pageSize = pag.pageSize; loadOrders(); }
function onRefundChange(pag: any) { refundPage.current = pag.current; refundPage.pageSize = pag.pageSize; loadRefunds(); }

onMounted(() => {
  void ensureTabLoaded(tab.value);
});
</script>

<style scoped>
.payment-single-tabs :deep(.ant-tabs-nav) {
  display: none;
}
</style>
