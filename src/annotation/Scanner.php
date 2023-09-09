<?php

namespace Wind\Annotation;

use Wind\Base\ClassScanner;

/**
 * Attributes Scanner
 */
class Scanner
{

    const VALID_PSR_NAME = '/^[a-z_][a-z0-9_]*(\.php)?$/i';

    private $scanner;

    public function __construct()
    {
        $this->scanner = new ClassScanner();
    }

    public function addMap($map)
    {
        $this->scanner->addMap($map);
        return $this;
    }

    public function addNamespace($namespace, $path)
    {
        $this->scanner->addNamespace($namespace, $path);
        return $this;
    }

    /**
     * Scan and get attributes with class reflection
     * @return \Generator<mixed, array{ref:\ReflectionClass, attribute:\ReflectionAttribute}>
     */
    public function scan()
    {
        foreach ($this->scanner->scan() as $ref) {
            foreach ($ref->getAttributes() as $attr) {
                yield [
                    'ref' => $ref,
                    'attribute' => $attr
                ];
            }

            foreach ($ref->getMethods() as $method) {
                foreach ($method->getAttributes() as $attr) {
                    yield [
                        'ref' => $method,
                        'attribute' => $attr
                    ];
                }
            }
        }
    }

    /**
     * @return list<array{ref:\ReflectionClass, attribute:\ReflectionAttribute}>
     */
    public function scanArray()
    {
        $result = [];

        foreach ($this->scan() as $row) {
            $result[] = $row;
        }

        return $result;
    }

}
