<?php

namespace winwin\winner\fixtures;

use kuiper\helper\Enum;

/**
 * @method MALE() : static
 * @method FEMALE() : static
 * @method UNKNOWN() : static
 *
 * @property string $description
 * @property string $intval
 */
class Gender extends Enum
{
    const MALE = 'male';
    const FEMALE = 'female';
    const UNKNOWN = 'unknown';

    protected static $PROPERTIES = [
        'description' => [
            self::MALE => '',
            self::FEMALE => '',
        ],
        'intval' => [
            self::MALE => 1,
        ],
    ];
}
