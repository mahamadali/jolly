<?php

namespace Bones\Skeletons\DataWing;

use Bones\Str;
use Bones\BadMethodException;
use Bones\DataWingException;

class ColumnStructure
{
    public $column = null;
    
    public function __construct($column)
    {
        $this->column = $column;
    }

    public function primaryKey()
    {
        $this->column['primaryKey'] = true;
        return $this;
    }

    public function unsigned(bool $unsigned = true)
    {
        $this->column['unsigned'] = $unsigned;
        return $this;
    }

    public function nullable(bool $nullable = true)
    {
        $this->column['nullable'] = $nullable;
        return $this;
    }

    public function unique(bool $drop = false)
    {
        if (!$drop) {
            $this->column['unique'] = true;
        } else {
            $this->column['unique'] = false;
        }
        
        return $this;
    }

    public function comment($comment)
    {
        if (!empty($comment)) {
            $this->column['comment'] = $comment;
        }
        
        return $this;
    }

    public function modify()
    {
        $this->column['modify'] = true;
        return $this;
    }

    public function after($column)
    {
        $this->column['after'] = $column;
        return $this;
    }

    public function virtual()
    {
        if (isset($this->column['auto_calculate']) && $this->column['auto_calculate']) {
            $this->column['storeMode'] = 'VIRTUAL';
            return $this;
        }

        throw new DataWingException(__FUNCTION__.' is only applicable on auto calculated type of column');
    }

    public function stored()
    {
        if (isset($this->column['auto_calculate']) && $this->column['auto_calculate']) {
            $this->column['storeMode'] = 'STORED';
            return $this;
        }
        
        throw new DataWingException(__FUNCTION__.'() is only applicable on auto calculated type of column');
    }

    public function default($default = NULL)
    {
        $this->column['default'] = $default;
        return $this;
    }

    public function __call($name, $arguments)
    {
        $method = Str::multiReplace($name, ['drop'], ['']);
        if (method_exists($this, $method)) {
            return $this->$method(true);
        }

        throw new BadMethodException($name.' method not found');
    }

}