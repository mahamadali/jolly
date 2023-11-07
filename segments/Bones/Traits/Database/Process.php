<?php

namespace Bones\Traits\Database;

use Bones\Database;
use Bones\DatabaseException;
use Bones\Skeletons\Database\Config;

trait Process
{
    private $param_index = 0;
    protected $TABLE;

    protected function methodInMaker(array $list, $callback)
    {
        foreach ($list as $item) {
            $param_name = $this->addToParamAutoName($item);
            $callback($param_name);
        }
    }

    protected function addToParam($name, $value)
    {

        if ($value === false) {
            $value = 0;
        } elseif ($value === true) {
            $value = 1;
        }

        $this->PARAMS[":$name"] = $value;
        return ":$name";
    }

    protected function addToParamAutoName($value)
    {
        $name = $this->getNewParamName();
        return $this->addToParam($name, $value);
    }

    protected function getNewParamName()
    {
        $this->param_index++;
        return 'p' . $this->param_index;
    }

    protected function fixColumnName($name)
    {
        $array = explode('.', $name);
        $count = count($array);

        $table = '';
        $column = '';
        $type = '';

        if ($count == 1) {
            $table = $this->TABLE;
            $column = $array[0];
            $type = 'column';
        } else if ($count == 2) {
            $table = $array[0];
            $column = $array[1];
            $type = 'table_and_column';
        }

        if ($column != '*') {
            $column = "`$column`";
        }

        $table = "`$table`";

        return ['name' => "$table.$column", 'table' => $table, 'column' => $column, 'type' => $type];
    }

    protected function fixOperatorAndValue(&$operator, &$value)
    {
        if (($value == false || $value == null) && $value !== 0) {
            $value = $operator;
            $operator = '=';
        }
    }

    protected function rawMaker($query, $values)
    {
        $index = 0;

        do {

            $find = strpos($query, '?');

            if ($find === false) {
                break;
            }

            $param_name = $this->addToParamAutoName($values[$index]);
            $query = substr_replace($query, $param_name, $find, 1);
            $index++;
        } while ($find !== false);

        return $query;
    }

    public function getValue($param, $name)
    {
        if (empty($param) || !$param) {
            return false;
        }

        if (
            $this
            ->CONFIG
            ->getFetch() == Config::FETCH_CLASS
        ) {
            return $param->{$name};
        } else {
            return $param[$name];
        }
    }

    public function getParams()
    {
        return $this->PARAMS;
    }

    public function getSourceValue()
    {
        return $this->SOURCE_VALUE;
    }

    protected function sqlQueryStructure($key = null)
    {
        $arr = ['SELECT' => 1, 'FIELDS' => 2, 'ALL' => 3, 'DISTINCT' => 4, 'DISTINCTROW' => 5, 'HIGH_PRIORITY' => 6, 'STRAIGHT_JOIN' => 7, 'FROM' => 8, 'JOIN' => 9, 'WHERE' => 10, 'GROUP_BY' => 12, 'HAVING' => 13, 'ORDER_BY' => 14, 'LIMIT' => 15, 'OFFSET' => 16, 'UNION' => 17];
        if ($key == null) {
            return $arr;
        } else {
            return $arr[$key];
        }
    }

    public function makeQueryForCount($query, $alias = 'total_records')
    {
        $query = preg_replace("/LIMIT (\d+) OFFSET (\d+)/", '', $query);
        $query = preg_replace("/SELECT (.*) FROM/", 'SELECT count(*) as '.$alias.' FROM', $query);

        return $query;
    }

    public function executeForCount($query, $alias = 'total_records')
    {
        return Database::rawQuery($query)[0]->{$alias};
    }

}