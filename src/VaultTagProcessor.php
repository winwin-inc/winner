<?php

namespace winwin\winner;

use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class VaultTagProcessor
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * VaultTagProcessor constructor.
     *
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public function process($yaml)
    {
        $result = Yaml::parse($yaml, Yaml::PARSE_CUSTOM_TAGS);

        return $this->traverse($result);
    }

    private function traverse($array)
    {
        if (!is_array($array)) {
            return $array;
        }
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->traverse($value);
            } elseif ($value instanceof TaggedValue) {
                $result[$key] = call_user_func($this->handler, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
