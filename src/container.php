<?php

declare(strict_types=1);
/**
 * This file is automatic generator by kuiper/component-installer, don't edit it manually.
 */

return [
    'component_scan' => [
        'winwin\\winner\\integration',
    ],
    'configuration' => [
        'kuiper\\annotations\\AnnotationConfiguration',
        'kuiper\\cache\\CacheConfiguration',
        'kuiper\\event\\EventConfiguration',
        'kuiper\\logger\\LoggerConfiguration',
        'kuiper\\reflection\\ReflectionConfiguration',
        'kuiper\\resilience\\ResilienceConfiguration',
        'kuiper\\serializer\\SerializerConfiguration',
        'kuiper\\swoole\\config\\FoundationConfiguration',
        'kuiper\\swoole\\config\\GuzzleHttpMessageFactoryConfiguration',
        'kuiper\\swoole\\config\\DiactorosHttpMessageFactoryConfiguration',
        'kuiper\\tars\\config\\TarsClientConfiguration',
    ],
];
