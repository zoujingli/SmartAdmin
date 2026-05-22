# 微信公众号平台插件

> Apache-2.0 开源插件。面向 SmartAdmin 租户侧微信接入，覆盖公众号/小程序接口账号、粉丝同步、自定义菜单、自动回复、素材图文和微信支付 APIv3。

## 开源协议

本插件随 SmartAdmin 以 Apache License 2.0 发布，允许在遵守协议要求的前提下使用、复制、修改、再分发和商业使用源码。

| 项目 | 说明 |
|---|---|
| 协议 | Apache License 2.0 |
| 代码包 | `zoujingli/smart-plugin-wechat-client` |
| 运行环境 | SmartAdmin、PHP >= 8.4、Hyperf 3.2、Swoole 6 |
| 微信 SDK | `zoujingli/wechat-developer` 2.x |
| 问题反馈 | 通过 SmartAdmin Issue 或项目维护渠道提交可复现信息 |

> 注意：本插件开源不代表自动获得微信官方能力。使用公众号、小程序和微信支付前，请自行完成微信公众平台、微信开放平台、微信支付商户号、数据安全和资金结算等官方资质与协议要求。

## 功能地图

| 功能 | 后台入口 | 核心能力 | 关键边界 |
|---|---|---|---|
| 接口账号 | 微信公众号平台 / 基础管理 / 接口账号 | 直连公众号、小程序与开放平台网关配置 | `appsecret`、`token`、`encodingaeskey` 加密保存，列表只展示脱敏状态 |
| 粉丝管理 | 微信公众号平台 / 基础管理 / 粉丝管理 | 拉取公众号粉丝与基础资料 | 依赖公众号接口权限；开放平台模式通过内部网关代调用 |
| 菜单发布 | 微信公众号平台 / 内容互动 / 菜单发布 | 模拟微信自定义菜单编辑、校验并发布 | 一级菜单最多 3 个，二级菜单最多 5 个，名称长度按微信官方规则校验 |
| 自动回复 | 微信公众号平台 / 内容互动 / 自动回复 | 订阅、默认、关键词、菜单点击回复 | 延时回复为内存协程调度，服务重启会丢失未执行任务 |
| 素材管理 | 微信公众号平台 / 内容互动 / 素材管理 | 永久素材同步、本地文件上传到微信 | 网关模式不传输本地文件流；远程文件地址会做 SSRF 防护 |
| 文章管理 | 微信公众号平台 / 内容互动 / 文章管理 | 本地图文、草稿上传、发布与状态查询 | 发布前必须先上传微信草稿并取得 `media_id` |
| 支付商户 | 微信公众号平台 / 支付能力 / 支付商户 | 微信支付 APIv3 商户配置 | APIv3 Key、商户私钥、平台公钥等敏感字段加密保存 |
| 支付订单 | 微信公众号平台 / 支付能力 / 支付订单 | 下单、通知同步、主动查单、退款入口 | 主动查单仅作为通知丢失补偿；终态订单优先返回本地状态 |
| 退款记录 | 微信公众号平台 / 支付能力 / 退款记录 | 退款申请、通知同步、主动查退 | 退款金额按分处理，累计退款不能超过订单金额 |

## 快速接入

1. 确认根项目已启用 path repository，并安装本插件依赖。
2. 执行数据库初始化或菜单同步，将插件菜单和权限写入系统菜单表。
3. 在后台维护接口账号：直连模式填写 AppID、AppSecret、Token、EncodingAESKey；开放平台模式填写 JSON-RPC 网关地址、Key 和 Secret。
4. 在微信公众平台配置服务器地址：`/wechat-client/api/push/{appid}`。
5. 如需微信支付，维护支付商户并配置通知地址：`/wechat-client/api/payment/notify/order/{merchantId}` 和 `/wechat-client/api/payment/notify/refund/{merchantId}`。
6. 使用 [微信客户端接口文档](../../docs/接口参考/微信客户端接口.md) 进行接口联调。

## 目录规范

- `src/`：插件运行期 PHP 代码，包含 `Controller`、`Service`、`Mapper`、`Model`、`Support` 等目录。
- `stc/view/`：插件前端页面与组件资源，由 `plugin.view_root` 显式启用。
- `stc/languages/`：插件语言包，由 `plugin.language_root` 显式启用。
- `stc/migrations/`：插件开发期数据库迁移文件，由 `plugin.migration_root` 显式启用。
- `composer.json`：插件包元数据、autoload 与 Hyperf Provider 配置。
- `plugin.json`：应用、菜单、view、按钮权限和模块元数据清单。

## 数据库命名规范

WechatClient 模块数据库表统一使用 `wechat_client_` 前缀，Model 必须显式声明 `$table`，避免类名调整后依赖自动推导产生偏差。

| 业务实体 | 表名 | 代码命名 |
|---|---|---|
| 接口账号 | `wechat_client_account` | `WechatClientAccount*` |
| 本地用户/公众号粉丝 | `wechat_client_user` | `WechatClientUser*` |
| 用户标签 | `wechat_client_user_tag` | `WechatClientUserTag` |
| 自动回复规则 | `wechat_client_reply_rule` | `WechatClientReplyRule*` |
| 自定义菜单 | `wechat_client_menu` | `WechatClientMenu*` |
| 素材 | `wechat_client_media` | `WechatClientMedia*` |
| 图文文章 | `wechat_client_article` | `WechatClientArticle*` |
| 支付商户 | `wechat_client_payment_merchant` | `WechatClientPaymentMerchant*` |
| 支付订单 | `wechat_client_payment_order` | `WechatClientPaymentOrder*` |
| 支付退款 | `wechat_client_payment_refund` | `WechatClientPaymentRefund*` |

命名约束：

- 后台菜单和业务文案可以继续使用“粉丝管理”，但数据库与 PHP 实体统一使用 `user`，表示公众号用户在本系统内的本地档案。
- 微信支付相关表名、类名和服务名统一保留完整 `payment`，不使用 `pay` 缩写。
- 新增迁移文件、索引名和表注释必须与表名语义保持一致，并继续使用模块前缀隔离不同插件的数据表。

## 约束

运行期类命名空间保持不变，仅通过 composer autoload 将命名空间根目录指向 `src/`。
页面、语言包和迁移目录均由 `plugin.json` 的资源根字段声明；未配置的资源不会被运行时或构建流程自动扫描。

## 微信内容与回复管理

- `素材管理`：维护本地素材台账，可同步微信永久素材，也可将本地文件地址上传为微信永久素材，生成 `media_id` 后供自动回复和菜单发布使用。
- `文章管理`：维护本地图文文章，支持上传微信草稿、提交发布和查询发布状态；菜单关联文章时要求已上传草稿并具备微信官方 `media_id`，普通外链请使用“网页链接”菜单类型。
- `自动回复`：支持订阅回复、默认回复、关键词回复和菜单点击回复，回复类型包含文本、图片、语音、视频和图文。
- `订阅延时回复`：关注事件会按本地启用的订阅回复规则全部发送，顺序为 `sort asc, id asc`，每条规则可单独配置延迟秒数。
- 延时回复采用 Swoole 内存协程实现，不新增持久化队列；服务重启会丢失尚未执行的延时任务，如需强可靠发送建议后续切换为数据库任务表或 Redis 队列。
- `菜单发布` 已改为可视化设计器，支持网页链接、本地素材、本地文章、菜单点击回复和高级 JSON 模式；发布前后端会校验菜单层级、名称和关联资源可用性。

## 微信支付服务

`WechatClient` 内置一个面向业务模块的微信支付服务：

```php
Plugin\WechatClient\Service\WechatClientPaymentService
```

其他模块只需要创建业务订单，然后调用这个服务创建支付或退款。支付状态、退款状态优先由微信通知推进；查询接口只作为兜底补偿，在本地未完成时才访问微信线上接口并同步本地。

### 接入前提

使用支付服务前，需要先在后台 `微信公众号平台 -> 支付商户` 维护可用商户：

- `appid`：支付 AppID，通常是公众号或小程序 AppID。
- `mch_id`：微信支付商户号。
- `api_v3_key`：APIv3 Key。
- `merchant_serial`：商户证书序列号。
- `merchant_private_key`：商户私钥 PEM。
- `platform_public_key` 与 `platform_serial`：微信支付平台公钥及序列号，用于通知验签。
- `status` 必须为启用。

如果一个租户只有一个启用商户，业务调用可不传 `merchant_id`，服务会取当前租户下第一条启用商户。多商户场景建议显式传 `merchant_id`。

在 HTTP 请求上下文中，服务会根据当前请求自动生成微信通知地址。CLI、队列、异步任务中没有请求域名，必须在 `options` 中传完整 `notify_url`，并且必须是 `http://` 或 `https://` 开头的绝对 URL。

### 后台支付操作说明

- `支付订单` 页面提供订单流水查看、主动查单和退款入口。查单仅用于通知丢失或人工排查：本地订单已进入终态时直接返回本地状态，未终态才访问微信线上接口并同步本地记录。
- `支付订单 -> 退款` 会真实调用微信退款接口，前端会二次确认，后端仍会校验订单必须为 `SUCCESS`、退款金额必须大于 0 且累计 `PROCESSING + SUCCESS` 退款金额不能超过原订单金额。
- `退款记录` 页面提供退款流水查看和主动查退。查退与查单一致，只用于后台补偿；本地已终态的退款记录不会重复访问微信接口。
- 后台金额统一按“分”提交，页面会辅助展示人民币格式；业务模块之间调用仍推荐直接使用 `WechatClientPaymentService`，不要绕过服务层直接写订单或退款表。

### 编号规则

- 业务订单号：业务模块自己的订单号，字段名为 `order_no`。
- 支付号：传给微信的 `out_trade_no`，返回字段为 `payment_no`，规则为 `业务订单号 + 三位支付发起序号`。
- 退款号：传给微信的 `out_refund_no`，返回字段为 `refund_no` 或退款模型的 `out_refund_no`，规则为 `业务订单号 + 三位退款发起序号`。

示例：

| 类型 | 业务订单号 | 第一次 | 第二次 |
| --- | --- | --- | --- |
| 支付号 | `ORDER202605030001` | `ORDER202605030001001` | `ORDER202605030001002` |
| 退款号 | `ORDER202605030001` | `ORDER202605030001001` | `ORDER202605030001002` |

支付号和退款号分别属于微信支付与微信退款的独立命名空间，字符串可以相同，不会互相冲突。

### 推荐服务方法

业务模块优先使用这四个语义化方法：

```php
public function createOrderPayment(
    string $orderNo,
    int $amountTotal,
    string $description,
    array $options = []
): array;

public function createOrderRefund(
    string $orderNo,
    int $amountRefund,
    string $reason = '',
    array $options = []
): WechatClientPaymentRefund;

public function queryOrderPayment(string $orderNo, array $options = []): array;

public function queryOrderRefund(string $orderNo, array $options = []): array;
```

底层数组入口仍保留，适合网关或通用封装调用：

```php
payment(array $data): array
refund(array $data): WechatClientPaymentRefund
queryPayment(array $data): array
queryRefund(array $data): array
syncPayment(string $paymentNo, bool $force = false): array
syncRefund(string $refundNo, bool $force = false): array
```

`syncPayment($paymentNo, true)` 与 `syncRefund($refundNo, true)` 会强制查微信线上状态；不传 `force` 时，本地已终态会直接返回。它们建议只在补偿任务或人工排查中使用。普通业务查询应调用 `queryOrderPayment`、`queryOrderRefund`，由服务判断是否需要访问微信。

### 创建订单支付

推荐通过依赖注入调用：

```php
<?php

declare(strict_types=1);

namespace Plugin\Order\Service;

use Plugin\WechatClient\Service\WechatClientPaymentService;

final class OrderPaymentService
{
    public function __construct(
        private readonly WechatClientPaymentService $wechatPayment,
    ) {}

    public function payment(string $orderNo, int $amountFen, string $openid): array
    {
        return $this->wechatPayment->createOrderPayment(
            orderNo: $orderNo,
            amountTotal: $amountFen,
            description: '订单支付',
            options: [
                'trade_type' => 'JSAPI',
                'openid' => $openid,
            ],
        );
    }
}
```

`createOrderPayment` 参数说明：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `orderNo` | `string` | 是 | 业务订单号，最长 97 位，服务会追加三位序号生成支付号。 |
| `amountTotal` | `int` | 是 | 支付金额，单位分，必须大于 0。 |
| `description` | `string` | 是 | 微信支付描述，会写入本地支付订单。 |
| `options.merchant_id` | `int` | 否 | 指定支付商户，不传则取当前租户默认启用商户。 |
| `options.trade_type` | `string` | 否 | `JSAPI` 或 `NATIVE`，默认 `JSAPI`。 |
| `options.openid` / `options.payer_openid` | `string` | JSAPI 必填 | 付款人 OpenID。 |
| `options.notify_url` | `string` | CLI/异步必填 | 完整支付通知地址。 |
| `options.attach` | `string` | 否 | 微信支付附加数据。 |
| `options.time_expire` | `string` | 否 | 微信支付过期时间，格式按微信 APIv3 要求传入。 |

返回结构核心字段：

| 字段 | 说明 |
| --- | --- |
| `order` | 本地 `WechatClientPaymentOrder` 模型。 |
| `order_no` | 业务订单号。 |
| `payment_no` / `out_trade_no` | 商户支付号，即传给微信的 `out_trade_no`。 |
| `trade_type` | 支付类型，`JSAPI` 或 `NATIVE`。 |
| `prepayment_id` | JSAPI 预支付 ID。 |
| `code_url` | NATIVE 二维码链接。 |
| `payment_params` | JSAPI 前端调起参数，包含 `appId`、`timeStamp`、`nonceStr`、`package`、`signType`、`paySign`。 |
| `raw` | 微信下单原始返回。 |

JSAPI 前端可直接使用 `payment_params` 调起微信支付。NATIVE 场景使用 `code_url` 生成二维码。

### 创建订单退款

以下示例假设当前类已通过构造函数注入 `WechatClientPaymentService $wechatPayment`。退款前，本地支付单必须已是 `SUCCESS`。服务会按业务订单号找到最近一笔已支付支付单；如果一个业务订单存在多次支付尝试，建议显式传 `payment_no` 或 `order_id` 指定退款来源。

```php
$refund = $this->wechatPayment->createOrderRefund(
    orderNo: 'ORDER202605030001',
    amountRefund: 100,
    reason: '订单退款',
    options: [
        'payment_no' => $payment['payment_no'],
    ],
);
```

`createOrderRefund` 参数说明：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `orderNo` | `string` | 是 | 业务订单号。 |
| `amountRefund` | `int` | 是 | 退款金额，单位分，必须大于 0 且不能超过订单金额。 |
| `reason` | `string` | 否 | 退款原因。 |
| `options.payment_no` / `options.out_trade_no` | `string` | 否 | 指定商户支付号。 |
| `options.order_id` | `int` | 否 | 指定本地支付订单 ID。 |
| `options.notify_url` | `string` | CLI/异步必填 | 完整退款通知地址。 |

返回值为 `WechatClientPaymentRefund` 模型，常用字段：

| 字段 | 说明 |
| --- | --- |
| `order_no` | 业务订单号。 |
| `out_trade_no` | 商户支付号。 |
| `out_refund_no` | 商户退款号。 |
| `refund_id` | 微信退款单号，微信返回后回填。 |
| `amount_total` | 原支付金额，单位分。 |
| `amount_refund` | 本次退款金额，单位分。 |
| `refund_status` | 退款状态。 |

### 查询支付与退款状态

以下示例假设当前类已通过构造函数注入 `WechatClientPaymentService $wechatPayment`。查询方法会先看本地状态；本地未完成时才查微信线上接口，并把线上结果同步回本地后返回。

```php
$refund = $this->wechatPayment->createOrderRefund(
    orderNo: 'ORDER202605030001',
    amountRefund: 100,
    reason: '订单退款',
    options: ['payment_no' => $payment['payment_no']]
);

$paymentStatus = $this->wechatPayment->queryOrderPayment(
    orderNo: 'ORDER202605030001',
    options: ['payment_no' => $payment['payment_no']]
);

$refundStatus = $this->wechatPayment->queryOrderRefund(
    orderNo: 'ORDER202605030001',
    options: ['refund_no' => (string)$refund->out_refund_no]
);
```

支付查询返回结构：

| 字段 | 说明 |
| --- | --- |
| `order` | 最新本地支付订单模型。 |
| `order_no` | 业务订单号。 |
| `payment_no` | 商户支付号。 |
| `trade_state` | 最新支付状态。 |
| `local_finished` | 本地是否已终态。 |
| `online_queried` | 本次是否查询了微信线上接口。 |
| `online` | 微信线上查询原始返回；未查询时为空数组。 |

退款查询返回结构：

| 字段 | 说明 |
| --- | --- |
| `refund` | 最新本地退款模型。 |
| `order_no` | 业务订单号。 |
| `payment_no` | 商户支付号。 |
| `refund_no` | 商户退款号。 |
| `refund_status` | 最新退款状态。 |
| `local_finished` | 本地是否已终态。 |
| `online_queried` | 本次是否查询了微信线上接口。 |
| `online` | 微信线上查询原始返回；未查询时为空数组。 |

查询时可在 `options` 中指定更精确的记录：

```php
// 按支付号查支付
$this->wechatPayment->queryOrderPayment($orderNo, ['payment_no' => $paymentNo]);

// 按本地支付订单 ID 查支付
$this->wechatPayment->queryPayment(['order_id' => $paymentOrderId]);

// 按退款号查退款
$this->wechatPayment->queryOrderRefund($orderNo, ['refund_no' => $refundNo]);

// 按本地退款记录 ID 查退款
$this->wechatPayment->queryRefund(['refund_record_id' => $refundRecordId]);
```

### 状态说明

支付状态 `trade_state` 常见值：

| 状态 | 说明 | 是否终态 |
| --- | --- | --- |
| `CREATED` | 本地已创建，尚未完成微信下单。 | 否 |
| `NOTPAY` | 微信下单成功，用户未支付。 | 否 |
| `USERPAYING` | 用户支付中。 | 否 |
| `ACCEPT` | 微信已接收，等待扣款。 | 否 |
| `SUCCESS` | 支付成功。 | 是 |
| `REFUND` | 微信侧存在退款记录。 | 否 |
| `CLOSED` | 订单关闭。 | 是 |
| `REVOKED` | 订单撤销。 | 是 |
| `PAYERROR` | 支付失败。 | 是 |

退款状态 `refund_status` 常见值：

| 状态 | 说明 | 是否终态 |
| --- | --- | --- |
| `PROCESSING` | 退款处理中。 | 否 |
| `SUCCESS` | 退款成功。 | 是 |
| `CLOSED` | 退款关闭。 | 是 |
| `ABNORMAL` | 退款异常。 | 是 |
| `FAIL` | 退款失败。 | 是 |

业务模块不要只依赖创建支付或创建退款接口的即时返回来推进业务终态，应以通知事件为准；查询接口用于用户主动刷新、后台补偿或人工排查。

### 微信通知地址

微信通知由本插件公开控制器接收：

- 支付通知地址：`/wechat-client/api/payment/notify/order/{merchantId}`。
- 退款通知地址：`/wechat-client/api/payment/notify/refund/{merchantId}`。

服务创建支付和退款时会自动把对应通知地址传给微信。通知处理流程：

1. 根据 `{merchantId}` 找到支付商户并恢复租户上下文。
2. 使用商户 APIv3 Key 和平台公钥验签、解密微信通知。
3. 按支付号或退款号幂等更新本地记录。
4. 状态进入成功时派发内部事件。

### 支付与退款事件

业务模块应通过监听事件完成订单状态推进：

| 事件 | 触发时机 |
| --- | --- |
| `Plugin\WechatClient\Event\WechatClientPaymentOrderPaid` | 支付状态首次进入 `SUCCESS`。 |
| `Plugin\WechatClient\Event\WechatClientPaymentRefundSucceeded` | 退款状态首次进入 `SUCCESS`。 |
| `Plugin\WechatClient\Event\WechatClientPaymentOrderStateChanged` | 支付状态发生变化。 |
| `Plugin\WechatClient\Event\WechatClientPaymentRefundStateChanged` | 退款状态发生变化。 |

支付成功监听示例：

```php
<?php

declare(strict_types=1);

namespace Plugin\Order\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Plugin\WechatClient\Event\WechatClientPaymentOrderPaid;

#[Listener]
final class MarkOrderPaidListener implements ListenerInterface
{
    public function listen(): array
    {
        return [WechatClientPaymentOrderPaid::class];
    }

    public function process(object $event): void
    {
        if (!$event instanceof WechatClientPaymentOrderPaid) {
            return;
        }

        $orderNo = (string)$event->order->order_no;
        $paymentNo = (string)$event->order->out_trade_no;
        $transactionId = (string)$event->order->transaction_id;
        $amountTotal = (int)$event->order->amount_total;

        // 在业务模块内按 order_no 幂等更新订单为已支付。
    }
}
```

退款成功监听示例：

```php
<?php

declare(strict_types=1);

namespace Plugin\Order\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Plugin\WechatClient\Event\WechatClientPaymentRefundSucceeded;

#[Listener]
final class MarkOrderRefundedListener implements ListenerInterface
{
    public function listen(): array
    {
        return [WechatClientPaymentRefundSucceeded::class];
    }

    public function process(object $event): void
    {
        if (!$event instanceof WechatClientPaymentRefundSucceeded) {
            return;
        }

        $orderNo = (string)$event->refund->order_no;
        $refundNo = (string)$event->refund->out_refund_no;
        $amountRefund = (int)$event->refund->amount_refund;

        // 在业务模块内按 order_no + refund_no 幂等更新退款状态。
    }
}
```

事件中的 `payload` 为微信通知或线上查询的标准化数据，调试时可以读取；业务落库建议优先使用事件携带的本地模型字段。

### 后台 HTTP 查询接口

后台或调试工具可以调用：

| 方法 | 路径 | 参数 | 说明 |
| --- | --- | --- | --- |
| `GET` | `/wechat-client/payment/order/query` | `order_no` / `payment_no` / `order_id` | 查询支付状态，本地未完成时查微信并同步。 |
| `GET` | `/wechat-client/payment/refund/query` | `order_no` / `refund_no` / `refund_record_id` / `refund_id` | 查询退款状态，本地未完成时查微信并同步。 |

这两个接口需要登录态；查单使用 `wechat.client.payment.order.query` 权限，查退使用 `wechat.client.payment.refund.query` 权限，业务模块之间调用仍推荐直接用服务层。

### 幂等与注意事项

- 支付成功和退款成功事件只在本地状态从非成功变为成功时派发，重复微信通知只会回填数据，不会重复触发成功事件。
- 金额以“分”为单位，业务模块不要传元。
- `order_no` 最长 97 位，因为服务会追加三位序号组成微信支付号或退款号。
- 创建退款会校验累计 `PROCESSING + SUCCESS` 退款金额，不能超过原订单金额。
- 支付通知金额与本地订单金额不一致时，未支付订单会拒绝同步，避免错单。
- 多次发起支付后，退款建议传 `payment_no` 精确指定需要退款的支付单。
- CLI、队列、定时任务中创建支付或退款时必须传完整 `notify_url`。

## 公众号推送事件

微信官方推送到 `/wechat-client/api/push/{appid}` 的关注事件会先同步本地粉丝状态，再派发内部事件：

- `Plugin\WechatClient\Event\WechatClientUserSubscribed`：用户关注公众号，事件包含 `account`、`user`、原始 `payload`、`eventKey` 和 `ticket`。
- `Plugin\WechatClient\Event\WechatClientUserUnsubscribed`：用户取消关注公众号，事件包含 `account`、`user` 和原始 `payload`。

其他模块可注册 Hyperf Listener 监听上述事件，避免直接解析微信 XML。
