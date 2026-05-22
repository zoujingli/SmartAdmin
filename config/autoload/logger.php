<?php

declare(strict_types=1);

/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://github.com/zoujingli/SmartAdmin/blob/master/readme.md
 */
use Library\Logger\Handler\StdoutLoggerHandler;
use Library\Logger\Processor\RequestIdProcessor;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

use function Hyperf\Support\env;

/**
 * 将 .env 中的字符串配置解析为布尔值。
 * 兼容 true/false、on/off、yes/no、1/0 等常见写法，无法识别时回退默认值。
 */
$envBool = static function (string $key, bool $default): bool {
    $value = env($key, $default);
    if (is_bool($value)) {
        return $value;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    return $parsed ?? $default;
};

// 操作日志输出配置
$logs = ['logWrite'];
if ($envBool('APP_DEBUG_XLOG', (bool)env('APP_ENV') === 'dev')) {
    $logs[] = 'stdout';
}

// 数据日志输出配置
$sqls = ['sqlWrite'];
if ($envBool('APP_DEBUG_XSQL', (bool)env('APP_ENV') === 'dev')) {
    $sqls[] = 'stdout';
}

return [
    'default' => 'log',
    'channels' => [
        'log' => [
            'handlers' => $logs,
            'processor' => [
                'class' => RequestIdProcessor::class,
            ],
        ],
        'sql' => [
            'handlers' => $sqls,
            'processor' => [
                'class' => RequestIdProcessor::class,
            ],
        ],
        // 屏幕输出日志 - 使用 StdoutLogger 支持颜色
        'stdout' => [
            'handler' => [
                'class' => StdoutLoggerHandler::class,
                'constructor' => [
                    'level' => Level::Info,
                ],
            ],
        ],
        // 操作日志记录配置
        'logWrite' => [
            'handler' => [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'level' => Level::Info,
                    'filename' => runpath('runtime/logger/debug/console.log'),
                    'maxFiles' => 7,
                ],
            ],
            'formatter' => [
                'class' => LineFormatter::class,
                'constructor' => [
                    'format' => null,
                    'dateFormat' => 'Y-m-d H:i:s',
                    'allowInlineLineBreaks' => true,
                ],
            ],
        ],
        // sql 日志记录配置
        'sqlWrite' => [
            'handler' => [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'level' => Level::Info,
                    'filename' => runpath('runtime/logger/sql/sql.log'),
                    'maxFiles' => 7,
                ],
            ],
            'formatter' => [
                'class' => LineFormatter::class,
                'constructor' => [
                    'format' => null,
                    'dateFormat' => 'Y-m-d H:i:s',
                    'allowInlineLineBreaks' => true,
                ],
            ],
        ],
    ],
];
