<?php

declare(strict_types=1);

namespace winwin\winner\linter;

use InvalidArgumentException;
use PhpParser\Comment\Doc;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Use_ as UseStmt;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use winwin\winner\linter\error\AnnotationError;
use winwin\winner\linter\error\ClassNotFound;
use winwin\winner\linter\error\FunctionNotFound;
use winwin\winner\linter\error\SyntaxError;
use winwin\winner\linter\error\UseConflict;
use winwin\winner\linter\reporter\ReporterInterface;
use winwin\winner\NodeVisitor;
use winwin\winner\TypeUtils;

/**
 * php lint.
 */
class Linter extends NodeVisitor
{
    private static $IGNORED_NAMES = [
        // Annotation tags
        'Annotation' => true, 'Target' => true,
        // Widely used tags (but not existent in phpdoc)
        'fix' => true, 'fixme' => true,
        'mixin' => true,
        'override' => true,
        // PHPDocumentor 1 tags
        'abstract' => true, 'access' => true,
        'code' => true, 'date' => true, 'update' => true,
        'deprec' => true,
        'endcode' => true, 'exception' => true,
        'final' => true,
        'ingroup' => true, 'inheritdoc' => true, 'inheritDoc' => true,
        'magic' => true,
        'name' => true,
        'toc' => true, 'tutorial' => true,
        'private' => true,
        'static' => true, 'staticvar' => true, 'staticVar' => true,
        'throw' => true,
        // PHPDocumentor 2 tags.
        'api' => true, 'author' => true,
        'category' => true, 'copyright' => true,
        'deprecated' => true,
        'example' => true,
        'filesource' => true,
        'global' => true,
        'ignore' => true, /* Can we enable this? 'index' => true, */
        'internal' => true,
        'license' => true, 'link' => true,
        'method' => true,
        'package' => true, 'param' => true, 'property' => true, 'property-read' => true, 'property-write' => true,
        'return' => true,
        'see' => true, 'since' => true, 'source' => true, 'subpackage' => true,
        'throws' => true, 'todo' => true, 'TODO' => true,
        'usedby' => true, 'uses' => true,
        'var' => true, 'version' => true,
        // PHPUnit tags
        'codeCoverageIgnore' => true, 'codeCoverageIgnoreStart' => true, 'codeCoverageIgnoreEnd' => true,
        // PHPCheckStyle
        'SuppressWarnings' => true,
        // PHPStorm
        'noinspection' => true,
        // PEAR
        'package_version' => true,
        // PlantUML
        'startuml' => true, 'enduml' => true,
    ];

    /**
     * @var string
     */
    private $file;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var ReporterInterface
     */
    private $reporter;

    /**
     * @var array
     */
    private $context;

    /**
     * @var array
     */
    private $uses;

    /**
     * @var array
     */
    private $ignoredClasses = [];

    /**
     * Constructor.
     *
     * @param string|resource $source file name or code
     */
    public function __construct($source, ReporterInterface $reporter)
    {
        parent::__construct();
        if (is_resource($source)) {
            $this->stream = $source;
        } else {
            $this->file = $source;
        }
        $this->setReporter($reporter);
    }

    public function lint()
    {
        $code = $this->stream ? stream_get_contents($this->stream)
            : file_get_contents($this->file);
        $this->resetContext();
        $parser = (new ParserFactory())->create(ParserFactory::ONLY_PHP7);
        $traverser = new NodeTraverser();
        try {
            $statements = $parser->parse($code);
            $traverser->addVisitor($this);
            $traverser->traverse($statements);
        } catch (Error $e) {
            $this->syntaxError($e);
        }

        return $this;
    }

    protected function resetContext($namespace = ''): void
    {
        $this->context = [
            'namespace' => $namespace,
            'use' => [],
            'class' => [],
        ];
    }

    protected function syntaxError(Error $e): void
    {
        $error = new SyntaxError($e);
        $error->setFile($this->file)
            ->setLine($e->getStartLine());
        $this->reporter->add($error);
    }

    protected function conflictUseStatementError(Node\Stmt\UseUse $node): void
    {
        $error = new UseConflict($node);
        $error->setFile($this->file)
            ->setLine($node->getLine());
        $this->reporter->add($error);
    }

    protected function classNotFoundError(Node\Name $node): void
    {
        $error = new ClassNotFound($node);
        $error->setFile($this->file)
            ->setLine($node->getLine());
        $this->reporter->add($error);
    }

    protected function functionNotFoundError(Node\Name $node)
    {
        $error = new FunctionNotFound($node);
        $error->setFile($this->file)
            ->setLine($node->getLine());
        $this->reporter->add($error);
    }

    protected function annotationError($message, $line)
    {
        $error = new AnnotationError($message);
        $error->setFile($this->file)
            ->setLine($line);
        $this->reporter->add($error);
    }

    protected function checkClassExists(Node $name)
    {
        if (!$name instanceof Node\Name) {
            return;
        }
        if ($name->isFullyQualified()) {
            $class = (string) $name;
        } else {
            $class = (string) $name;
            if (in_array($class, ['self', 'static', 'parent', 'string', 'bool', 'int', '$this'], true)) {
                return;
            }
            $alias = (string) $name->getFirst();
            if (isset($this->context['use'][UseStmt::TYPE_NORMAL][$alias])) {
                $class = (string) $this->context['use'][UseStmt::TYPE_NORMAL][$alias]->name;
                if (count($name->parts) > 1) {
                    $class .= '\\'.$name->slice(1);
                }
            } else {
                $class = $this->context['namespace'].'\\'.$name;
            }
        }
        if (!class_exists($class) && !interface_exists($class)
            && !trait_exists($class) && !$this->isIgnoredClass($class)) {
            $this->classNotFoundError($name);
        }
    }

    protected function checkFunctionExists(Node $name)
    {
        if (!$name instanceof Node\Name) {
            return;
        }
        $fullname = (string) $name;
        if ($name->isFullyQualified()) {
            $func = $fullname;
        } else {
            $alias = $name->getFirst();
            if (isset($this->context['use'][UseStmt::TYPE_NORMAL][$alias])) {
                $parts = $name->parts;
                array_shift($parts);
                $namespace = $this->context['use'][UseStmt::TYPE_NORMAL][$alias]->name;
                $func = $namespace.($parts ? '\\'.implode('\\', $parts) : '');
            } elseif (isset($this->context['use'][UseStmt::TYPE_FUNCTION][$fullname])) {
                $func = (string) $this->context['use'][UseStmt::TYPE_FUNCTION][$fullname]->name;
            } else {
                $func = $this->context['namespace'].'\\'.$fullname;
            }
        }
        if (!function_exists($fullname) && !function_exists($func)) {
            $this->functionNotFoundError($name);
        }
    }

    protected function checkAnnotations(Doc $doc = null)
    {
        if (null === $doc || !empty($this->context['class']['is_annotation'])) {
            return;
        }
        $linenum = $doc->getLine();
        foreach (explode("\n", $doc->getText()) as $line) {
            if (preg_match('#\s*\*\s*\@([^ \(]+)#', $line, $matches)) {
                $name = $matches[1];
                if ('Annotation' === $name) {
                    $this->context['class']['is_annotation'] = true;
                    continue;
                }
                $attributes = [
                    'startLine' => $linenum,
                    'annotation' => $line,
                ];
                if (preg_match('#\s*\*\s*\@(var|param|return|throws)\s*(\S+)#', $line, $matches)) {
                    try {
                        $this->checkTypeClassNameExists(TypeUtils::parse($matches[2]), $attributes);
                    } catch (InvalidArgumentException $e) {
                        $this->annotationError($e->getMessage(), $linenum);
                    }
                } elseif (isset(self::$IGNORED_NAMES[$name])) {
                    continue;
                } else {
                    $this->checkClassNameExists($name, $attributes);
                }
            }
            ++$linenum;
        }
    }

    protected function checkTypeClassNameExists($type, $attributes)
    {
        if (TypeUtils::isClass($type) && !TypeUtils::isSelf($type)) {
            $this->checkClassNameExists($type['class'], $attributes);
        } elseif (TypeUtils::isArray($type)) {
            $this->checkTypeClassNameExists($type['valueType'], $attributes);
        } elseif (TypeUtils::isComposite($type)) {
            foreach ($type['types'] as $subtype) {
                $this->checkTypeClassNameExists($subtype, $attributes);
            }
        }
    }

    protected function checkClassNameExists($name, $attributes)
    {
        if ('\\' === $name[0]) {
            $node = new Node\Name\FullyQualified(explode('\\', $name), $attributes);
        } else {
            $node = new Node\Name(explode('\\', $name), $attributes);
        }
        $this->checkClassExists($node);
    }

    public function enterNamespace(Node\Stmt\Namespace_ $node)
    {
        $this->resetContext((string) $node->name);
    }

    public function leaveNamespace()
    {
        $this->resetContext();
    }

    public function enterUse(Node\Stmt\Use_ $node)
    {
        $type = $node->type;
        foreach ($node->uses as $use) {
            $alias = $use->alias ? (string) $use->alias : (string) $use->name->getLast();
            if (isset($this->context['use'][$type][$alias])) {
                $this->conflictUseStatementError($use);
            } else {
                $this->context['use'][$type][$alias] = $use;
            }
        }
    }

    public function enterClass(Node\Stmt\Class_ $node)
    {
        if ($node->extends) {
            $this->checkClassExists($node->extends);
        }
        if ($node->implements) {
            foreach ($node->implements as $name) {
                $this->checkClassExists($name);
            }
        }
        $this->checkAnnotations($node->getDocComment());
    }

    public function leaveClass()
    {
        $this->context['class'] = [];
    }

    public function enterMethod(Node\Stmt\ClassMethod $node)
    {
        $this->checkAnnotations($node->getDocComment());
    }

    public function enterMethodParam(Node\Param $node)
    {
        if ($node->type && $node->type instanceof Node\Name) {
            $this->checkClassExists($node->type);
        }
    }

    public function enterProperty(Node\Stmt\Property $node)
    {
        $this->checkAnnotations($node->getDocComment());
    }

    public function enterNewClass(Node\Expr\New_ $node)
    {
        $this->checkClassExists($node->class);
    }

    public function enterStaticClass(Node\Expr\StaticCall $node)
    {
        $this->checkClassExists($node->class);
    }

    public function enterInstanceof(Node\Expr\Instanceof_ $node)
    {
        $this->checkClassExists($node->class);
    }

    public function enterTryCatch(Node\Stmt\Catch_ $node)
    {
        if (isset($node->type)) {
            $this->checkClassExists($node->type);
        } elseif (isset($node->types) && is_array($node->types)) {
            foreach ($node->types as $type) {
                $this->checkClassExists($type);
            }
        }
    }

    public function enterFuncall(Node\Expr\FuncCall $node)
    {
        $name = $node->name;
        // not Node\Expr\Variable and not global function
        if ($name instanceof Node\Name) {
            $this->checkFunctionExists($name);
        }
    }

    public function enterClassConstFetch(Node\Expr\ClassConstFetch $node)
    {
        // maybe PhpParser\Node\Expr\Variable
        if ($node->class instanceof Node\Name) {
            $this->checkClassExists($node->class);
        }
    }

    /**
     * @return ReporterInterface
     */
    public function getReporter()
    {
        return $this->reporter;
    }

    public function setReporter($reporter)
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getIgnoredClasses()
    {
        return $this->ignoredClasses;
    }

    public function setIgnoredClasses(array $ignoredClasses)
    {
        $this->ignoredClasses = $ignoredClasses;

        return $this;
    }

    public function addIgnoredClasses(array $ignoredClasses)
    {
        $this->ignoredClasses = array_unique(array_merge($this->ignoredClasses, $ignoredClasses));

        return $this;
    }

    public function isIgnoredClass($className)
    {
        return in_array($className, $this->ignoredClasses, true);
    }
}
