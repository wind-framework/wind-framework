<?php

namespace Wind\Annotation;

/**
 * Annoation can be collection
 */
interface Collectable
{

    /**
     * Collect attribute
     *
     * @param \ReflectionClass|\ReflectionMethod $reference
     */
    public function collect($reference);

}
