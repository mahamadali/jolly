<?php

namespace Bones\Skeletons\DataWing;

use Bones\Database;
use Bones\Skeletons\DataWing\ColumnStructure;
use Bones\Traits\DataWing\Commands;
use Bones\DataWingException;

class Skeleton extends ColumnStructure
{
    use Commands;

    public $prefix = '';
    public $table;
    protected $engine = 'InnoDB';
    protected $collation = 'utf8_general_ci';
    protected $charSet = 'utf8';
    protected $columns;
    protected $statements;
    protected $dropColumns = [];
    protected $dropIndexes = [];
    protected $dropForeignKeys = [];
    protected $renameAs = '';
    protected $action = null;

    public function __construct($table, $action)
    {
        $this->table = $table;
        $this->action = $action;
    }

    public function engine($engine)
    {
        $this->engine = $engine;
    }

    public function prefix($prefix)
    {
        $this->prefix = $prefix;
    }

    public function collation($collation)
    {
        $this->collation = $collation;
    }

    public function charSet($charSet)
    {
        $this->charSet = $charSet;
    }

    public function addColumn($type, $name, array $parameters = [])
    {
        return $this->addColumnDefinition(new ColumnStructure(
            array_merge(compact('type', 'name'), $parameters)
        ));
    }

    protected function addCommand($name, array $parameters = [])
    {
        $this->statements[] = $command = $this->createCommand($name, $parameters);
        return $command;
    }

    protected function createCommand($name, array $parameters = [])
    {
        return array_merge(compact('name'), $parameters);
    }

    public function addColumnDefinition($definition)
    {
        $this->columns[] = $definition;
        return $definition;
    }

    public function autoIncrement($column, $unsigned = true)
    {
        return $this->integer($column, true, $unsigned);
    }

    public function autoIncrementBig($column, $unsigned = true)
    {
        return $this->bigInteger($column, true, $unsigned);
    }

    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function smallInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    public function unsignedTinyInteger($column, $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    public function unsignedSmallInteger($column, $autoIncrement = false)
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    public function unsignedMediumInteger($column, $autoIncrement = false)
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    public function float($column, $total = 8, $places = 2, $unsigned = false)
    {
        return $this->addColumn('float', $column, compact('total', 'places', 'unsigned'));
    }

    public function floatAuto($column, $size = 8, $unsigned = false)
    {
        return $this->addColumn('floatAuto', $column, compact('size', 'unsigned'));
    }

    public function real($column, $total = 8, $places = 2, $unsigned = false)
    {
        return $this->addColumn('real', $column, compact('total', 'places', 'unsigned'));
    }

    public function serial($column)
    {
        return $this->addColumn('serial', $column);
    }

    public function bit($column)
    {
        return $this->addColumn('bit', $column);
    }

    public function double($column, $total = null, $places = null, $unsigned = false)
    {
        return $this->addColumn('double', $column, compact('total', 'places', 'unsigned'));
    }

    public function decimal($column, $total = 8, $places = 2, $unsigned = false)
    {
        return $this->addColumn('decimal', $column, compact('total', 'places', 'unsigned'));
    }

    public function unsignedFloat($column, $total = 8, $places = 2)
    {
        return $this->float($column, $total, $places, true);
    }

    public function unsignedFloatAuto($column, $total = 8)
    {
        return $this->floatAuto($column, $total, true);
    }

    public function unsignedDouble($column, $total = null, $places = null)
    {
        return $this->double($column, $total, $places, true);
    }

    public function unsignedDecimal($column, $total = 8, $places = 2)
    {
        return $this->decimal($column, $total, $places, true);
    }

    public function char($column)
    {
        return $this->addColumn('char', $column);
    }

    public function varchar($column, $length = 255)
    {
        return $this->addColumn('varchar', $column, compact('length'));
    }

    public function string($column, $length = 255)
    {
        return $this->varchar($column, $length);
    }

    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    public function set($column, array $allowed)
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    public function jsonb($column)
    {
        return $this->addColumn('jsonb', $column);
    }

    public function date($column)
    {
        return $this->addColumn('date', $column);
    }
    
    public function dateTime($column, $precision = 0)
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    public function dateTimeTz($column, $precision = 0)
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    public function time($column, $precision = 0)
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    public function timeTz($column, $precision = 0)
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    public function timestamp($column, $precision = 0)
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    public function timestampTz($column, $precision = 0)
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    public function year($column, $precision = 4)
    {
        return $this->addColumn('year', $column, compact('precision'));
    }

    public function geometry($column)
    {
        return $this->addColumn('geometry', $column);
    }

    public function point($column, $srid = null)
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    public function linestring($column)
    {
        return $this->addColumn('linestring', $column);
    }

    public function polygon($column)
    {
        return $this->addColumn('polygon', $column);
    }

    public function geometryCollection($column)
    {
        return $this->addColumn('geometrycollection', $column);
    }

    public function multiPoint($column)
    {
        return $this->addColumn('multipoint', $column);
    }

    public function multiLineString($column)
    {
        return $this->addColumn('multilinestring', $column);
    }

    public function multiPolygon($column)
    {
        return $this->addColumn('multipolygon', $column);
    }

    public function multiPolygonZ($column)
    {
        return $this->addColumn('multipolygonz', $column);
    }

    public function autoCalculateAs($type, $column, $as, $auto_calculate = true)
    {
        return $this->addColumn($type, $column, compact('auto_calculate', 'as'));
    }

    public function autoCalculateAsInt($column, $as)
    {
        return $this->autoCalculateAs('integer', $column, $as);
    }

    public function autoCalculateAsDouble($column, $as)
    {
        return $this->autoCalculateAs('double', $column, $as);
    }

    public function autoCalculateAsFloat($column, $as)
    {
        return $this->autoCalculateAs('float', $column, $as);
    }

    public function id($column = 'id')
    {
        return $this->autoIncrementBig($column);
    }

    public function rememberToken()
    {
        return $this->string('remember_token', 120)->nullable();
    }

    public function setIndex($columns, $name = null, $algorithm = null)
    {
        return $this->addIndexStatement('index', $columns, $name, $algorithm);
    }

    public function setUnique($columns, $name = null, $algorithm = null)
    {
        return $this->addIndexStatement('unique', $columns, $name, $algorithm);
    }

    public function setFullText($columns, $name = null, $algorithm = null)
    {
        return $this->addIndexStatement('fulltext', $columns, $name, $algorithm);
    }

    public function setSpatialIndex($columns, $name = null)
    {
        return $this->addIndexStatement('spatial', $columns, $name);
    }

    protected function addIndexStatement($type, $columns, $index, $algorithm = null)
    {
        $columns = (array) $columns;

        $index = (!empty($index)) ? $index : $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type, compact('index', 'columns', 'algorithm')
        );
    }

    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->prefix.$this->table.'_'.implode('_', $columns).'_'.$type);
        return str_replace(['-', '.'], '_', $index);
    }

    public function dropIndex($indexes)
    {
        if (gettype($indexes) == 'string') {
            $this->dropIndexes[] = $indexes;
        } else if (gettype($indexes) == 'array') {
            foreach ($indexes as $index) {
                $this->dropIndexes[] = $index;
            }
        }
        
        return $this;
    }

    public function setForeign(array $columns, $referenceTable, array $referenceToColumns, $name = '')
    {
        $foreignKeyName = (!empty($name)) ? $name : 'FK_'.$this->table.'_'.implode('_', $columns).
        '_'.$referenceTable.'_'.implode('_', $referenceToColumns);
        
        return $this->createForeignStatement($columns, $referenceTable, $referenceToColumns, $foreignKeyName);
    }

    protected function createForeignStatement($columns, $referenceTable, $referenceToColumns, $alias)
    {
        $type = 'foreign';
        
        $this->addCommand(
            $type , compact('columns', 'referenceTable', 'referenceToColumns', 'alias')
        );

        return $this;
    }

    public function dropForeign($foreignKeyConstraints)
    {
        if (gettype($foreignKeyConstraints) == 'string') {
            $this->dropForeignKeys[] = $foreignKeyConstraints;
        } else if (gettype($foreignKeyConstraints) == 'array') {
            foreach ($foreignKeyConstraints as $foreignKeyConstraint) {
                $this->dropForeignKeys[] = $foreignKeyConstraint;
            }
        }
        
        return $this;
    }

    public function onUpdate(string $action = 'RESTRICT')
    {
        array_walk($this->statements, function (&$statement) use ($action) {
            if ($this->isForeignStatment($statement)) {
                $statement['on_update'] = strtoupper($action);
            }
        });

        return $this;
    }

    public function onDelete(string $action = 'RESTRICT')
    {
        array_walk($this->statements, function (&$statement) use ($action) {
            if ($this->isForeignStatment($statement)) {
                $statement['on_delete'] = strtoupper($action);
            }
        });

        return $this;
    }

    public function isForeignStatment($statement)
    {
        return (!empty($statement) && !empty($statement['name']) && $statement['name'] == 'foreign');
    }

    public function renameTable(string $tableName)
    {   
        if (empty($tableName)) {
            throw new DataWingException('Empty name can not be set to the table');
        }

        return $this->renameAs = $tableName;
    }

    public function timestamps($precision = 0)
    {
        $this->addColumn('timestamp', 'created_at', compact('precision'))->default('current')->nullable();
        $this->addColumn('timestamp', 'updated_at', compact('precision'))->default('on_update_current')->nullable();
    }

    public function trashMask($column = 'deleted_at', $precision = 0)
    {
        $this->addColumn('timestamp', $column, compact('precision'))->nullable();
    }
    
    public function dropColumn($name)
    {
        if ($this->action == 'modify') {
            return $this->addDropColumn($name);
        }

        if ($this->columns == null) return $this;

        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c->column['name'] != $name;
        }));

        return $this;
    }

    protected function addDropColumn($name)
    {
        $this->dropColumns[] = $name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public static function hasColumn($table, $column)
    {
        return Database::table($table)->hasColumn($column);
    }

    public function prepareAndExecuteStatement()
    {
        $baseStatement = $this->generateCommand();
        (!empty($baseStatement)) ? $this->executeBaseStatement($baseStatement) : null;
        (!empty($this->statements)) ? $this->executeStatements() : null;
        return true;
    }

    public function run()
    {
        return $this->prepareAndExecuteStatement();
    }

}