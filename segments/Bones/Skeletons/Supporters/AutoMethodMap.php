<?php

namespace Bones\Skeletons\Supporters;

use Bones\BadMethodException;

class AutoMethodMap
{
    public static function __callStatic($method, $parameters)
    {
        if (method_exists((new static), '__' . $method)) {
            return (new static)->{'__' . $method}(...$parameters);
        }

        throw new BadMethodException('Method {' . $method . '} not found in '.get_class(new static));
    }

    public function __call(string $method, $parameters)
    {
        if (method_exists($this, '__'.$method)) {
            return $this->{'__' . $method}(...$parameters);
        }

        throw new BadMethodException('Method {' . $method . '} not found in '.get_class($this));
    }
}