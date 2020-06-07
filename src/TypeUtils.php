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
        if (0 === strpos($type, '?')) {
            $type = substr($type, 1);
        }
        if ('array' === $type) {
            return ['isa' => 'array', 'valueType' => 'mixed'];
        }
        if (in_array($type, ['$this', 'self', 'static'])) {
            return ['isa' => 'class', 'class' => $type, 'self' => true];
        }
        if (self::isBuiltinType($type)) {
            return ['isa' => 'primitive', 'type' => $type];
        }
        if (preg_match('/^array<(.*)>$/', $type, $arrayTypes)
            || preg_match('/^(.*)\[\]$/', $type, $arrayTypes)) {
            return ['isa' => 'array', 'valueType' => self::parse(trim($arrayTypes[1], '()'))];
        }
        if (preg_match(self::CLASS_NAME_REGEX, $type)) {
            return ['isa' => 'class', 'class' => $type];
        }
        if (false !== strpos($type, '|')) {
            $types = [];
            foreach (explode('|', $type) as $oneType) {
                $types[] = self::parse($oneType);
            }

            return ['isa' => 'composite', 'types' => $types];
        }
        throw new \InvalidArgumentException("Invalid type declaration '{$type}'");
    }

    public static function isClass($type)
    {
        return isset($type['isa']) && 'class' == $type['isa'];
    }

    public static function isArray($type)
    {
        return isset($type['isa']) && 'array' == $type['isa'];
    }

    public static function isComposite($type)
    {
        return isset($type['isa']) && 'composite' == $type['isa'];
    }

    public static function isSelf($type)
    {
        return self::isClass($type) && !empty($type['self']);
    }
}
