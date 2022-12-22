<?php

namespace Wind\Annotation;

/**
 * Annoation can be collection
 */
interface Collectable
{

    /**
     * Collect attribute
     */
    public function collectClass(\ReflectionClass $reference);

    /**
     * Collect attribute
     */
    public function collectMethod(\ReflectionMethod $reference);

}
