<?php

declare(strict_types=1);

namespace PHPSTORM_META;

    use winwin\dingtalk\platform\infrastructure\dingtalk\ClientFactory;

    override(\Psr\Container\ContainerInterface::get(0), map([
        '' => '@',
    ]));
    override(ClientFactory::create(0), map([
        '' => '@',
    ]));
