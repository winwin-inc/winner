<?php

namespace winwin\winner;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    const ENTER = 'enter';

    const LEAVE = 'leave';

    /**
     * @var array key is NodeVisitor class name, value is [nodeClass => methods ]
     */
    protected static $enterMethods = [];

    /**
     * @var array key is NodeVisitor class name, value is [nodeClass => methods ]
     */
    protected static $leaveMethods = [];

    /**
     * @var array key is Node class name, value is methods
     */
    private $cachedMethods;

    public function __construct()
    {
        self::register(get_class($this));
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        foreach ($this->getRules($node, self::ENTER) as $method) {
            if (is_string($method)) {
                $this->$method($node);
            } else {
                call_user_func($method, $node, $this);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveNode(Node $node)
    {
        foreach ($this->getRules($node, self::LEAVE) as $method) {
            if (is_string($method)) {
                $this->$method($node);
            } else {
                call_user_func($method, $this, $node);
            }
        }
    }

    /**
     * collect all rule method according to node type of parameter.
     */
    protected static function register($className)
    {
        if (isset(self::$enterMethods[$className])) {
            return;
        }
        $class = new \ReflectionClass($className);
        $enterMethods = [];
        $leaveMethods = [];

        foreach ($class->getMethods() as $method) {
            $name = $method->getName();
            if (preg_match('/^(enter|leave)(.+)/', $name, $matches)) {
                if ($matches[2] === 'Node') {
                    continue;
                }
                $params = $method->getParameters();
                if (isset($params[0]) && $params[0]->getClass()) {
                    $nodeType = $params[0]->getClass()->getName();
                    if ($matches[1] === 'enter') {
                        $enterMethods[$nodeType][] = $name;
                    } else {
                        $leaveMethods[$nodeType][] = $name;
                    }
                }
            }
        }
        self::$enterMethods[$className] = $enterMethods;
        self::$leaveMethods[$className] = $leaveMethods;
    }

    /**
     * @param Node   $node
     * @param string $type
     *
     * @return array return all possible callbacks
     */
    protected function getRules($node, $type)
    {
        if (!in_array($type, [self::ENTER, self::LEAVE])) {
            throw new \InvalidArgumentException('type is invalid');
        }
        $class = is_object($node) ? get_class($node) : $node;
        if (!isset($this->cachedMethods[$type][$class])) {
            $rules = $type == self::ENTER ? self::$enterMethods : self::$leaveMethods;
            $matches = [];
            foreach ($rules[get_class($this)] as $nodeType => $methods) {
                if (is_a($node, $nodeType)) {
                    $matches = array_merge($matches, $methods);
                }
            }
            $this->cachedMethods[$type][$class] = $matches;
        }

        return $this->cachedMethods[$type][$class];
    }

    protected function addRule($nodeClass, $callback, $type)
    {
        if (!isset($this->cachedMethods[$type][$nodeClass])) {
            $this->cachedMethods[$type][$nodeClass] = [];
        }
        array_push($this->cachedMethods[$type][$nodeClass], $callback);

        return $this;
    }

    public function whenEnter($nodeClass, $callback)
    {
        return $this->addRule($nodeClass, $callback, self::ENTER);
    }

    public function whenLeave($nodeClass, $callback)
    {
        return $this->addRule($nodeClass, $callback, self::LEAVE);
    }
}
