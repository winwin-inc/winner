<?php

declare(strict_types=1);

namespace winwin\winner;

class TypeUtils
{
    private const CLASS_NAME_REGEX = '/^(\\\\?(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\\\\)*[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(<.*?>)?$/';
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
     */
    private static function isBuiltinType($value): bool
    {
        return in_array($value, self::$BUILTIN_TYPES, true)
            || isset(self::$BUILTIN_TYPES[$value]);
    }

    public static function parse($type): array
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
        if (in_array($type, ['$this', 'self', 'static'], true)) {
            return ['isa' => 'class', 'class' => $type, 'self' => true];
        }
        if (self::isBuiltinType($type)) {
            return ['isa' => 'primitive', 'type' => $type];
        }
        if (preg_match('/^array<(.*)>$/', $type, $arrayTypes)) {
            $valueType = trim(trim($arrayTypes[1]), '()');
            if (preg_match("/^\S+\s*,\s*/", $valueType)) {
                $valueType = explode(',', $valueType, 2)[1];
            }

            return ['isa' => 'array', 'valueType' => self::parse($valueType)];
        } elseif (preg_match('/^(.*)\[\]$/', $type, $arrayTypes)) {
            return ['isa' => 'array', 'valueType' => self::parse(trim($arrayTypes[1], '()'))];
        }
        if (preg_match(self::CLASS_NAME_REGEX, $type, $matches)) {
            return ['isa' => 'class', 'class' => $matches[1], 'template' => $matches[2] ?? null];
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

    public static function isClass($type): bool
    {
        return isset($type['isa']) && 'class' === $type['isa'];
    }

    public static function isArray($type): bool
    {
        return isset($type['isa']) && 'array' === $type['isa'];
    }

    public static function isComposite($type): bool
    {
        return isset($type['isa']) && 'composite' === $type['isa'];
    }

    public static function isSelf($type): bool
    {
        return self::isClass($type) && !empty($type['self']);
    }
}
