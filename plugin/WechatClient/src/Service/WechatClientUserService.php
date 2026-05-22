<?php

declare(strict_types=1);

namespace Plugin\WechatClient\Service;

use Library\CoreService;
use Plugin\WechatClient\Event\WechatClientUserSubscribed;
use Plugin\WechatClient\Event\WechatClientUserUnsubscribed;
use Plugin\WechatClient\Mapper\WechatClientUserMapper;
use Plugin\WechatClient\Model\WechatClientAccount;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * 公众号本地用户服务。
 *
 * 负责粉丝资料同步为本地用户档案，以及把微信官方关注/取消关注推送转换为系统内部事件。
 */
final class WechatClientUserService extends CoreService
{
    public function __construct(
        protected WechatClientUserMapper $mapper,
        private readonly WechatClientAccountService $accounts,
        private readonly EventDispatcherInterface $events,
    ) {}

    /**
     * 处理公众号官方推送事件；关注/取消关注会同步本地粉丝状态并派发内部事件。
     *
     * @param array<string,mixed> $payload
     */
    public function handleOfficialPush(WechatClientAccount $account, array $payload): void
    {
        $msgType = strtolower(trim((string)($payload['MsgType'] ?? '')));
        $event = strtolower(trim((string)($payload['Event'] ?? '')));
        if ($msgType !== 'event' || !in_array($event, ['subscribe', 'unsubscribe'], true)) {
            return;
        }

        $openid = trim((string)($payload['FromUserName'] ?? ''));
        if ($openid === '') {
            return;
        }

        // 先同步本地粉丝状态，再派发内部事件，确保监听器能直接读取最新模型数据。
        $subscribeTime = $this->officialEventTime($payload);
        $user = $this->mapper->upsertByOpenid((string)$account->appid, $openid, [
            'tenant_id' => (int)$account->tenant_id,
            'account_id' => (int)$account->id,
            'appid' => (string)$account->appid,
            'openid' => $openid,
            'subscribe' => $event === 'subscribe' ? 1 : 0,
            'subscribe_time' => $event === 'subscribe' ? $subscribeTime : null,
            'raw_payload' => $payload,
        ]);

        if ($event === 'subscribe') {
            $this->events->dispatch(new WechatClientUserSubscribed(
                $account,
                $user,
                $payload,
                (string)($payload['EventKey'] ?? ''),
                (string)($payload['Ticket'] ?? ''),
            ));
            return;
        }

        $this->events->dispatch(new WechatClientUserUnsubscribed($account, $user, $payload));
    }

    /**
     * 从微信同步粉丝基础资料；按官方批量接口每 100 个 openid 拉取，避免单个请求过大。
     */
    public function sync(int $accountId, int $maxPages = 20): array
    {
        $account = $this->accounts->requireAccount($accountId);
        $next = '';
        $total = 0;

        for ($page = 0; $page < max(1, $maxPages); ++$page) {
            $list = $this->accounts->officialRequest($account, 'cgi-bin/user/get', [
                'next_openid' => $next,
            ], 'GET');
            $openids = (array)($list['data']['openid'] ?? []);
            foreach (array_chunk(array_map('strval', $openids), 100) as $chunk) {
                $batch = $this->accounts->officialRequest($account, 'cgi-bin/user/info/batchget', [
                    'user_list' => array_map(static fn (string $openid): array => ['openid' => $openid, 'lang' => 'zh_CN'], $chunk),
                ]);
                foreach ((array)($batch['user_info_list'] ?? []) as $item) {
                    if (!is_array($item) || empty($item['openid'])) {
                        continue;
                    }
                    $this->mapper->upsertByOpenid((string)$account->appid, (string)$item['openid'], [
                        'tenant_id' => (int)$account->tenant_id,
                        'account_id' => (int)$account->id,
                        'appid' => (string)$account->appid,
                        'openid' => (string)$item['openid'],
                        'unionid' => (string)($item['unionid'] ?? ''),
                        'nickname' => (string)($item['nickname'] ?? ''),
                        'avatar' => (string)($item['headimgurl'] ?? ''),
                        'subscribe' => (int)($item['subscribe'] ?? 0),
                        'subscribe_time' => empty($item['subscribe_time']) ? null : date('Y-m-d H:i:s', (int)$item['subscribe_time']),
                        'remark' => (string)($item['remark'] ?? ''),
                        'tagids' => $item['tagid_list'] ?? [],
                        'raw_payload' => $item,
                    ]);
                    ++$total;
                }
            }
            $next = (string)($list['next_openid'] ?? '');
            if ($next === '' || $openids === []) {
                break;
            }
        }

        return ['synced' => $total, 'next_openid' => $next];
    }

    /**
     * 将微信推送时间转换为系统时间字符串。
     *
     * @param array<string,mixed> $payload
     */
    private function officialEventTime(array $payload): string
    {
        $time = (int)($payload['CreateTime'] ?? 0);

        return date('Y-m-d H:i:s', $time > 0 ? $time : time());
    }
}
