<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
return [
    // RSA 私钥由系统自动维护在运行目录；公钥由私钥实时导出，不需要部署人员配置证书或公钥文件。
    'key_path' => runpath('runtime/keys/password_crypto.pem'),
    'key_bits' => 3072,
];
