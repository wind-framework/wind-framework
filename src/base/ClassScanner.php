<?php

namespace Wind\Base;

/**
 * Class Scanner
 */
class ClassScanner
{

    public const TYPE_ALL = 0;
    public const TYPE_CLASS = 1;
    public const TYPE_ABSTRACT = 2;
    public const TYPE_INTERFACE = 4;
    public const TYPE_FINAL = 8;
    public const TYPE_TRAIT = 16;
    public const TYPE_ENUM = 32;

    private const VALID_PSR_NAME = '/^[a-z_][a-z0-9_]*(\.php)?$/i';

    private $map = [];

    /**
     * @param int $types Scan class types
     */
    public function __construct(private int $types=self::TYPE_ALL)
    {
    }

    /**
     * Add NS map to scan
     *
     * @param array $map
     * @return static
     */
    public function addMap($map)
    {
        foreach ($map as $ns => $path) {
            $this->addNamespace($ns, $path);
        }
        return $this;
    }

    public function addNamespace($namespace, $path)
    {
        $namespace = rtrim($namespace, '\\');
        $path = rtrim($path, '/\\');
        $this->map[] = [$namespace, $path];
        return $this;
    }

    /**
     * Scan and get class reflection iterator
     * @return \Generator<mixed, \ReflectionClass>
     */
    public function scan()
    {
        foreach ($this->map as $ns) {
            yield from $this->scanRecursive($ns[0], $ns[1]);
        }
    }

    /**
     * Scan and get class reflection array
     * @return \ReflectionClass[]
     */
    public function scanArray()
    {
        $result = [];

        foreach ($this->scan() as $row) {
            $result[] = $row;
        }

        return $result;
    }

    private function scanRecursive($namespace, $path)
    {
        foreach (glob($path.DIRECTORY_SEPARATOR.'*') as $path) {
            $filename = basename($path);

            if (!preg_match(self::VALID_PSR_NAME, $filename)) {
                continue;
            }

            $class = $namespace.'\\'.pathinfo($filename, PATHINFO_FILENAME);

            if (is_dir($path)) {
                yield from $this->scanRecursive($class, $path);
            } else {
                if (!class_exists($class)) {
                    continue;
                }

                /** @psalm-suppress ArgumentTypeCoercion */
                $ref = new \ReflectionClass($class);

                if ($this->types !== self::TYPE_ALL) {
                    if (($this->types & self::TYPE_CLASS) !== 0 && $ref->isInstantiable()) {
                        yield $ref;
                    } elseif (($this->types & self::TYPE_ABSTRACT) !== 0 && $ref->isAbstract()) {
                        yield $ref;
                    } elseif (($this->types & self::TYPE_INTERFACE) !== 0 && $ref->isInterface()) {
                        yield $ref;
                    } elseif (($this->types & self::TYPE_FINAL) !== 0 && $ref->isFinal()) {
                        yield $ref;
                    } elseif (($this->types & self::TYPE_TRAIT) !== 0 && $ref->isTrait()) {
                        yield $ref;
                    } elseif (($this->types & self::TYPE_ENUM) !== 0 && $ref->isEnum()) {
                        yield $ref;
                    }
                }
            }
        }
    }

}
