<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support;

use Library\Constants\Status;

final class SystemNodeRegistry
{
    public const SOURCE_ANNOTATION = 'annotation';

    public const SOURCE_MENU = 'menu';

    public const SOURCE_SYSTEM = 'system';

    public const SYSTEM_ROLE_NODE_REF = 'system:super_admin';

    public const SYNC_FIELDS = ['name', 'type', 'source', 'ref', 'status', 'meta'];

    /**
     * @param array<string, bool|int|string> $meta
     * @return array{name:string,type:string,source:string,ref:string,status:int,meta:string}
     */
    public static function payload(
        string $name,
        string $type,
        string $source,
        string $ref,
        array $meta = [],
        int $status = Status::ENABLED
    ): array {
        return [
            'name' => $name,
            'type' => $type,
            'source' => $source,
            'ref' => $ref,
            'status' => $status,
            'meta' => self::encodeMeta($meta),
        ];
    }

    /**
     * @param array<string, bool|int|string> $meta
     * @return array{
     *   node:string,
     *   name:string,
     *   type:string,
     *   source:string,
     *   ref:string,
     *   status:int,
     *   meta:string,
     *   created_by:int,
     *   updated_by:int,
     *   created_at:string,
     *   updated_at:string
     * }
     */
    public static function record(
        string $node,
        string $name,
        string $type,
        string $source,
        string $ref,
        array $meta = [],
        int $status = Status::ENABLED,
        int $actorId = 0,
        ?string $now = null
    ): array {
        $now ??= self::currentTimestamp();

        return [
            'node' => $node,
            ...self::payload($name, $type, $source, $ref, $meta, $status),
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array{menu_id:int,menu_status:int,menu_type:string}
     */
    public static function menuMeta(
        int $menuId,
        string $menuType,
        int $menuStatus = Status::ENABLED
    ): array {
        return [
            'menu_id' => $menuId,
            'menu_status' => $menuStatus,
            'menu_type' => $menuType,
        ];
    }

    /**
     * @return array{
     *   node:string,
     *   name:string,
     *   type:string,
     *   source:string,
     *   ref:string,
     *   status:int,
     *   meta:string,
     *   created_by:int,
     *   updated_by:int,
     *   created_at:string,
     *   updated_at:string
     * }
     */
    public static function systemRecord(string $node, ?string $now = null): array
    {
        return self::record(
            $node,
            $node === '*' ? 'All permissions' : $node,
            '',
            self::SOURCE_SYSTEM,
            self::SYSTEM_ROLE_NODE_REF,
            ['system' => true],
            Status::ENABLED,
            0,
            $now
        );
    }

    /**
     * @param array<string, bool|int|string> $meta
     */
    public static function mergeMeta(string $currentMeta, array $meta): string
    {
        return self::encodeMeta(array_merge(self::decodeMeta($currentMeta), $meta));
    }

    /**
     * @param array<string, bool|int|string> $meta
     */
    private static function encodeMeta(array $meta): string
    {
        ksort($meta);

        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * @return array<string, bool|int|string>
     */
    private static function decodeMeta(string $meta): array
    {
        $decoded = json_decode($meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function currentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}
