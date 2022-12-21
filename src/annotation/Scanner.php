<?php

namespace Wind\Annotation;

use Attribute;

/**
 * Attributes Scanner
 */
class Scanner
{

    const VALID_PSR_NAME = '/^[a-z_][a-z0-9_]*(\.php)?$/i';

    private $map = [];

    public function addMap($namespace, $path)
    {
        $namespace = rtrim($namespace, '\\');
        $path = rtrim($path, '/\\');
        $this->map[] = [$namespace, $path];
    }

    public function scan()
    {
        foreach ($this->map as $ns) {
            yield from $this->scanRecursive($ns[0], $ns[1]);
        }
    }

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
                $ref = new \ReflectionClass($class);

                foreach ($ref->getAttributes() as $attr) {
                    yield [
                        'reference' => $ref,
                        'attribute' => $attr
                    ];
                }

                foreach ($ref->getMethods() as $method) {
                    foreach ($method->getAttributes() as $attr) {
                        yield [
                            'reference' => $method,
                            'attribute' => $attr
                        ];
                    }
                }
            }
        }
    }

}
