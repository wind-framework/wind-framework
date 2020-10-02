<?php

namespace App;

class Context
{

    private $content = [];

    /**
     * @param string $id
     * @return mixed|null
     */
    public function get($id) {
        return $this->content[$id] ?? null;
    }

    /**
     * @param string $id
     * @param mixed $value
     */
    public function set($id, $value) {
        $this->content[$id] = $value;
    }

}