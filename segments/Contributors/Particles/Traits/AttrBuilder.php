<?php

namespace Contributors\Particles\Traits;

trait AttrBuilder
{
    public function buildAttrs($attrs = [], $additional = [])
    {
        if (!empty($additional['name']))
            $additional['name'] = $additional['name'];

        foreach ($additional as $attrName => $attrValue) {
            $attrs[$attrName] = $attrValue;
        }

        if (!empty($attrs['name']) && !array_key_exists('id', $attrs))
            $attrs['id'] = $attrs['name'];

        return $this->sanitizeAttrs($attrs);
    }

    public function gluedAttrs($attrs, $excludes = [])
    {
        $attrPairs = [];

        array_walk($attrs, function ($value, $key) use (&$attrPairs, $excludes) {
            if (!in_array($key, $excludes))
                $attrPairs[] = $key . "='" . $value . "'";
        });

        return (!empty($attrPairs)) ? ' ' . implode(' ', $attrPairs) : '';
    }

    public function sanitizeAttrs($attrs)
    {
        array_walk($attrs, function ($value, $key) use (&$attrs) {
            if ($value == null)
                unset($attrs[$key]);
            if ($key == 'type' && $value == 'hidden' && array_key_exists('id', $attrs))
                unset($attrs['id']);
        });

        return $attrs;
    }

    public function _argv($arguments, $index, $default = null)
    {
        return isset($arguments[$index]) ? $arguments[$index] : $default;
    }

}