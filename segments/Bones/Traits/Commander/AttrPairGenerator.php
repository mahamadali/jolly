<?php

namespace Bones\Traits\Commander;

use Bones\Str;

trait AttrPairGenerator
{
    public function generateExtraAttrs($commandAttr, $commandExtraAttrs)
    {
        $commandExtraAttrPairs = [];

        if (!empty($commandExtraAttrs)) {
            if (gettype($commandAttr) == 'string') $commandAttr = [$commandAttr];
            foreach ($commandAttr as $extraAttr) {
                if (Str::startsWith($extraAttr, '--')) {
                    $attribute = explode('=', $extraAttr);
                    $attrName = $attribute[0];
                    $commandExtraAttrPairs[$attrName] = (!empty($attribute[1])) ? $attribute[1] : '';
                }
            }
        }

        if (!empty($commandExtraAttrs)) {
            foreach ($commandExtraAttrs as $extraAttr) {
                if (Str::startsWith($extraAttr, '--')) {
                    $attribute = explode('=', $extraAttr);
                    $attrName = $attribute[0];
                    $commandExtraAttrPairs[$attrName] = (!empty($attribute[1])) ? $attribute[1] : '';
                }
            }
        }

        return $commandExtraAttrPairs;
    }
}