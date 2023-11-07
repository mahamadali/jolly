<?php

namespace Models\Base\Supporters;

use Bones\Str;

class Transform
{
    protected $setType;
    protected $getType;
    protected $attribute;
    protected $attributeBehaviour;
    protected $operation;
    protected $transformDefaultTypes = ['int', 'float', 'double', 'string', 'boolean'];
    protected $transformDefaultFunctions = ['serialize', 'unserialize', 'json_encode', 'json_decode'];
    protected $transformCollections = ['array', 'object'];
    protected $transformSpecialTypeCallbacks = [];

    public function __construct($type, $attribute, $operation = 'set')
    {
        $type = explode('<->', $type);
        $this->setType = $type[0];
        $this->getType = (!empty($type[1])) ? $type[1] : null;
        $this->attribute = $attribute;
        $this->operation = $operation;
    }

    public function mutate()
    {
        if ($this->attribute == null) return $this->attribute;

        $mutateType = ($this->operation == 'set') ? $this->setType : $this->getType;

        if (empty($mutateType)) return $this->attribute;
        
        $mutateType = $this->setAttributeBehaviour($mutateType);

        if (in_array($mutateType, $this->transformDefaultTypes)) {
            return $this->as($mutateType);
        }

        if (in_array($mutateType, $this->transformDefaultFunctions)) {
            return call_user_func($mutateType, $this->attribute);
        }

        $mutated = $this->performCustomTransformation($mutateType);

        return (!empty($mutated)) ? $mutated : $this->attribute;
    }

    public function performCustomTransformation($type)
    {
        $transformSpecialTypeCallbacks = $this->special();

        if (in_array($type, $this->transformCollections)) {
            if (Str::isJson($this->attribute)) {
                $this->attribute = json_decode($this->attribute);
            }
            return $this->as($type);
        }

        $callback = (!empty($transformSpecialTypeCallbacks) && !empty($transformSpecialTypeCallbacks[$type])) ? $transformSpecialTypeCallbacks[$type] : null;

        if (!empty($callback)) {
            return call_user_func_array([$callback[0], $callback[1]], (!empty($callback[2]) && is_array($callback[2])) ? array_merge($callback[2], $this->attribute) : [$this->attribute]);
        }

        $transformed = $this->specialWithBehaviour($type);

        return ($transformed != null) ? $transformed : $this->attribute;
    }

    public function setAttributeBehaviour($mutateType)
    {
        $mutateTypeParts = explode(':', $mutateType, 2);
        $this->attributeBehaviour = (count($mutateTypeParts) > 1) ? $mutateTypeParts[1] : null;

        return $mutateTypeParts[0];
    }

    public function special()
    {
        return [
            'slug' => [Str::class, 'toSlug'],
            'unslug' => [Str::class, 'toReadable'],
            'json' => [Str::class, 'toJson'],
            'timestamp' => [Str::class, 'toTimestamp'],
        ];
    }

    public function specialWithBehaviour($type)
    {
        if ($type == 'decimal') {
            $preceision = (is_numeric($this->attributeBehaviour)) ? $this->attributeBehaviour : 2;
            return sprintf('%0.'.$preceision.'f', $this->attribute);
        }

        if ($type == 'date') {
            $format = (!empty($this->attributeBehaviour)) ? $this->attributeBehaviour : 'Y-m-d';
            if (Str::isTimestamp($this->attribute))
                return date($format, $this->attribute);
            else
                return date($format, strtotime($this->attribute));
        }

        if ($type == 'datetime') {
            $format = (!empty($this->attributeBehaviour)) ? $this->attributeBehaviour : 'Y-m-d H:i:s';
            if (Str::isTimestamp($this->attribute))
                return date($format, $this->attribute);
            else
                return date($format, strtotime($this->attribute));
        }

        if (function_exists($type))
            return call_user_func_array($type, [$this->attribute]);

        return null;
    }

    public function as($type)
    {
        settype($this->attribute, $type);
        return $this->attribute;
    }

    public function typeSupported($type)
    {
        return in_array($type, array_merge($this->transformDefaultTypes, $this->transformDefaultFunctions, $this->transformCollections));
    }
}