<?php

declare(strict_types=1);

use Doctrine\Common\Annotations\AnnotationReader;
use function kuiper\helper\env;
use winwin\corp\facade\CorpServiceInterface;
use winwin\corp\facade\MessageServiceInterface;
use winwin\dsp\facade\WechatOpenPlatformServiceInterface;
use winwin\userCenter\facade\UserServiceInterface;
use winwin\zhidou\constant\OptionNamespace;
use winwin\zhidou\dc\facade\BranchServiceInterface;
use winwin\zhidou\dc\facade\MigrationServiceInterface;

AnnotationReader::addGlobalIgnoredNamespace('winwin\\mapper');

return [
    'application' => [
        'web' => [
            'view' => [
                'path' => '{application.base-path}/resources/views',
                'cache' => 'true' === env('APP_DEBUG_MODE') ? false : '{application.logging.path}/view-cache',
            ],
            'session' => [
                'cookie_name' => 'PHPSESSID',
                'lifetime' => env('APP_SESSION_LIFETIME', 3600 * 24),
            ],
            'context-url' => '',
            'error' => [
                'include-stacktrace' => 'true' === env('APP_DEBUG_MODE')
                    ? IncludeStacktrace::ALWAYS
                    : IncludeStacktrace::ON_TRACE_PARAM,
                'display-error' => 'true' === env('APP_DEBUG_MODE'),
            ],
            'middleware' => [
                HealthyStatus::class,
                SessionMiddleware::class,
                ErrorMiddleware::class,
                HttpCodeOkForError::class,
                BodyParsingMiddleware::class,
            ],
        ],
        'database' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'name' => env('DB_NAME'),
            'user' => env('DB_USER'),
            'password' => env('DB_PASS'),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'table-prefix' => env('DB_TABLE_PREFIX', 'zhidou_'),
            'logging' => 'true' === env('DB_LOGGING'),
        ],
        'hologres' => [
            'driver' => 'pgsql',
            'host' => env('HOLO_HOST', 'localhost'),
            'port' => env('HOLO_PORT', 80),
            'name' => env('HOLO_NAME'),
            'user' => env('HOLO_USER'),
            'password' => env('HOLO_PASS'),
            'logging' => 'true' === env('DB_LOGGING'),
        ],
        'cache' => [
            'namespace' => env('APP_CACHE_PREFIX'),
            'lifetime' => (int) env('APP_CACHE_LIFETIME', 300),
        ],
        'redis' => [
            'host' => env('REDIS_HOST'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD'),
        ],
        'jsonrpc' => [
            'http-options' => [
                'base_uri' => env('APP_RPC_GATEWAY'),
                'headers' => [
                    'apikey' => env('APP_RPC_APIKEY'),
                ],
                'logging' => true,
                'log-format' => env('APP_RPC_LOG_FORMAT'),
            ],
            'clients' => [
                MigrationServiceInterface::class => '/zhidou-data-center',
                BranchServiceInterface::class => '/zhidou-data-center',
                WechatOpenPlatformServiceInterface::class => '/dsp',
                UserServiceInterface::class => '/qrpay-rpc',
                MessageServiceInterface::class => '/utilities',
                CorpServiceInterface::class => '/utilities',
            ],
        ],
        'sync' => [
            'namespace' => OptionNamespace::BOSS,
        ],
        'wechat' => [
            'boss_auth' => [
                'app_id' => env('WECHAT_BOSS_AUTH_APP_ID', ''),
            ],
            'boss_wx_mini' => [
                'app_id' => env('WECHAT_BOSS_WX_MINI_APP_ID', ''),
            ],
        ],
        'beanstalk' => [
            'host' => env('BEANSTALK_HOST', 'localhost'),
            'tube' => env('BEANSTALK_TUBE', 'zhidou-boss'),
        ],
        'http-client' => [
            'logging' => 'true' === env('HTTP_LOGGING'),
            'log-format' => env('HTTP_LOG_FORMAT', 'clf'),
        ],
        'job-processor' => [
            'enabled' => 'true' === env('JOB_QUEUE_ENABLED'),
            'workers' => env('JOB_QUEUE_WORKERS'),
            'max-job-request' => (int) env('JOB_QUEUE_MAX_JOB_REQUEST', 100),
        ],
        'tracing' => [
            'enabled' => 'true' === env('TRACING_ENABLED'),
            'reporting_url' => env('TRACING_REPORTING_URL'),
            'logging' => 'true' === env('TRACING_LOGGING'),
            'sampler' => [
                'type' => env('TRACING_SAMPLER_TYPE', 'const'),
                'param' => json_decode(env('TRACING_SAMPLER_PARAM', 'true'), true),
            ],
        ],
        'server' => [
            'enable-hook' => 'true' === env('APP_SERVER_ENABLE_HOOK'),
        ],
    ],
];

// Local Variables:
// mode: php
// End:
