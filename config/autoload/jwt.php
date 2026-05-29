<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */
use System\Model\SystemUser;

/*
 * JWT 配置文件
 * 支持多场景配置，每个场景可以有不同的密钥、算法、过期时间等.
 */

return [
    // ==================== 默认配置 ====================
    'alg' => 'HS256',                    // JWT 签名算法，支持 HS256/HS384/HS512/RS256/RS384/RS512/ES256/ES384/ES512
    'secret' => 'zoKNKURDQKDrYraFYS2xwS1QqRf933ppRow3VVdHUoEk5kWxmobQRDuDd4aBzYQbiyBGCz0uiF08s3AAN5hIrA==', // JWT 签名密钥（64位Base64编码）
    'ttl' => 86400,                      // Token 过期时间（秒），24小时
    'type' => 'mpop',                    // 登录类型：mpop=多点登录，sso=单点登录
    'blacklist_prefix' => 'jwt',         // 黑名单缓存前缀
    'blacklist_enabled' => true,         // 是否启用黑名单功能，用于强制登出

    // ==================== 算法配置 ====================
    'supported_algs' => [
        // 对称加密算法（HMAC）- 使用相同密钥进行签名和验证
        'HS256' => 'Lcobucci\JWT\Signer\Hmac\Sha256',  // HMAC SHA256，推荐用于大多数场景
        'HS384' => 'Lcobucci\JWT\Signer\Hmac\Sha384',  // HMAC SHA384，更高的安全性
        'HS512' => 'Lcobucci\JWT\Signer\Hmac\Sha512',  // HMAC SHA512，最高安全性

        // 非对称加密算法（ECDSA）- 使用私钥签名，公钥验证
        'ES256' => 'Lcobucci\JWT\Signer\Ecdsa\Sha256', // ECDSA P-256 + SHA256
        'ES384' => 'Lcobucci\JWT\Signer\Ecdsa\Sha384', // ECDSA P-384 + SHA384
        'ES512' => 'Lcobucci\JWT\Signer\Ecdsa\Sha512', // ECDSA P-521 + SHA512

        // 非对称加密算法（RSA）- 使用私钥签名，公钥验证
        'RS256' => 'Lcobucci\JWT\Signer\Rsa\Sha256',   // RSA + SHA256
        'RS384' => 'Lcobucci\JWT\Signer\Rsa\Sha384',   // RSA + SHA384
        'RS512' => 'Lcobucci\JWT\Signer\Rsa\Sha512',   // RSA + SHA512
    ],

    // 算法分类（用于内部逻辑判断）
    'symmetry_algs' => ['HS256', 'HS384', 'HS512'],                    // 对称算法列表
    'asymmetric_algs' => ['RS256', 'RS384', 'RS512', 'ES256', 'ES384', 'ES512'], // 非对称算法列表

    // ==================== 场景配置 ====================
    // 根配置只声明系统用户场景；插件登录模型和开放接口场景由插件 Provider 自注册。
    'scene' => [
        // 系统用户模型 - 后台管理系统用户
        SystemUser::class => [
            'type' => 'mpop',                   // 多点登录，允许同时多个设备登录
            // 其他配置继承全局配置：secret、ttl、alg、blacklist_enabled等
        ],
    ],
];
