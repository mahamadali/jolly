<?php

namespace Bones;

use Bones\Skeletons\DataWing\Skeleton;
use Bones\BadMethodException;

class DataWing
{
    protected $skeleton;
    protected $baseTable;

    public function __construct($table, $action, $callable)
    {
        if (!empty($callable))
            $this->skeleton = call_user_func_array($callable, [new Skeleton($table, $action)]);
        
        $this->baseTable = $table;
    }

    public function __create()
    {
        return $this->skeleton->run('create');
    }

    public function __modify()
    {
        return $this->skeleton->run('modify');
    }

    public function __drop()
    {
        return Database::rawQuery('DROP TABLE IF EXISTS `' . $this->baseTable . '`');
    }

    public function __truncate()
    {
        return Database::rawQuery('TRUNCATE TABLE `' . $this->baseTable . '`');
    }

    public static function __callStatic($name, $arguments)
    {
        $table = $arguments[0];

        if (method_exists(self::class, '__' . $name)) {
            if (!empty($arguments[1])) {
                return call_user_func_array([(new self($table, $name, $arguments[1])), '__'.$name], $arguments);
            } else {
                return call_user_func_array([(new self($table, $name, null)), '__'.$name], $arguments);
            }
        }

        if (method_exists(Skeleton::class, $name))
            return Skeleton::$name(...$arguments);

        throw new BadMethodException($name.'() method not found');
    }

}