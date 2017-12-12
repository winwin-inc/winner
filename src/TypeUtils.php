<?php

namespace winwin\winner;

class TypeUtils
{
    const CLASS_NAME_REGEX = '/^\\\\?([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\\)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';
    private static $BUILTIN_TYPES = [
        'bool',
        'int',
        'double',
        'string',
        'mixed',
        'null',
        'object',
        'callable',
        'resource',
        'void' => 'null',
        'integer' => 'int',
        'object' => 'mixed',
        'float' => 'double',
        'boolean' => 'bool',
        'false' => 'bool',
        'true' => 'bool',
    ];

    /**
     * checks whether the given type is a builtin type.
     *
     * @param string $value
     *
     * @return bool
     */
    private static function isBuiltinType($value)
    {
        return in_array($value, self::$BUILTIN_TYPES)
            || isset(self::$BUILTIN_TYPES[$value]);
    }

    public static function parse($type)
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('type cannot be empty');
        }
        if (!is_string($type)) {
            throw new \InvalidArgumentException('type should be string, got '.gettype($type));
        }
        if (strpos($type, '|') !== false) {
            $types = [];
            foreach (explode('|', $type) as $oneType) {
                $types[] = self::parse($oneType);
            }

            return ['isa' => 'composite', 'types' => $types];
        } elseif ($type === 'array') {
            return ['isa' => 'array', 'valueType' => 'mixed'];
        } elseif (preg_match('/array<(.*)>/', $type, $arrayTypes)
                  || preg_match('/(.*)\[\]$/', $type, $arrayTypes)) {
            return ['isa' => 'array', 'valueType' => self::parse($arrayTypes[1])];
        } else {
            if (!preg_match(self::CLASS_NAME_REGEX, $type)) {
                throw new \InvalidArgumentException("Invalid type declaration '{$type}'");
            }
            if (self::isBuiltinType($type)) {
                return ['isa' => 'primitive', 'type' => $type];
            } else {
                return ['isa' => 'class', 'class' => $type];
            }
        }
    }

    public static function isClass($type)
    {
        return isset($type['isa']) && $type['isa'] == 'class';
    }

    public static function isArray($type)
    {
        return isset($type['isa']) && $type['isa'] == 'array';
    }

    public static function isComposite($type)
    {
        return isset($type['isa']) && $type['isa'] == 'composite';
    }
}
