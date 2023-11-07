<?php

namespace Bones\Traits\DataWing;

use Bones\Database;

trait Commands
{
    protected $statement;

    public function generateCommand()
    {
        $this->statement = '';

        if (trim(empty($this->action)))
            return '';

        switch ($this->action) {
            case 'create':
                return $this->prepareCreate();
                break;
            case 'modify':
                return $this->prepareAlters();
                break;
            default:
                break;
        }

        return '';
    }

    public function executeBaseStatement($baseStatement)
    {
        if (gettype($baseStatement) == 'string') {
            Database::rawQuery($baseStatement);
        } else if (gettype($baseStatement) == 'array') {
            foreach ($baseStatement as $statement) {
                Database::rawQuery($statement);
            }
        }
    }

    public function executeStatements()
    {
        foreach ($this->statements as $statement) {
            if (!empty($statement['index'])) {
                $index_type = 'INDEX';
                if (strtoupper($statement['name']) != 'INDEX')
                    $index_type = $statement['name'] . ' INDEX';

                $sql = 'CREATE ' . strtoupper($index_type) . ' ' . $statement['index'] . ' ON `' . $this->prefix . $this->table . '` (`' . implode('`, `', $statement['columns']) . '`)';
                Database::rawQuery($sql);
            } else if ($statement['name'] == 'foreign') {
                $sql = "ALTER TABLE `" . $this->prefix . $this->table . "`
                ADD CONSTRAINT " . $statement['alias'] . "
                FOREIGN KEY (`" . implode('`, `', $statement['columns']) . "`) REFERENCES `" . $statement['referenceTable'] . "`(`" . implode('`, `', $statement['referenceToColumns']) . "`) ON DELETE " . (!empty($statement['on_delete']) ? $statement['on_delete'] : 'CASCADE') . " ON UPDATE " . (!empty($statement['on_update']) ? $statement['on_update'] : 'CASCADE') . ";";
                Database::rawQuery($sql);
            }
        }
    }

    public function prepareCreate()
    {
        $this->statement = 'CREATE TABLE `' . $this->prefix . $this->table . '` (';
        $columnSets = [];
        foreach ($this->columns as $column) {
            $columnAttr = $column->column;
            $columnSets[] = $this->getExecutableColumnDefination($columnAttr);
        }
        $this->statement .= implode(', ', $columnSets);
        $this->statement .= ') ENGINE=' . $this->engine . ' DEFAULT CHARSET=' . $this->charSet . ' COLLATE=' . $this->collation . ';';
        return $this->statement;
    }

    public function prepareAlters()
    {
        $this->statement = [];

        if (!empty($this->columns)) {
            foreach ($this->columns as $column) {
                $operation = 'ADD';
                $afterColumn = '';
                if (isset($column->column['modify']) && $column->column['modify']) {
                    $operation = 'MODIFY';
                }
                if (!empty($column->column['after'])) {
                    $afterColumn = ' AFTER ' . $column->column['after'];
                }
                $columnAttr = $column->column;
                $columnToAdd = $this->getExecutableColumnDefination($columnAttr);
                $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` ' . $operation . ' COLUMN ' . $columnToAdd . $afterColumn;
            }
        }

        if (!empty($this->dropColumns)) {
            foreach ($this->dropColumns as $columnToRemove) {
                if (Database::table($this->prefix . $this->table)->hasColumn($columnToRemove))
                    $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` DROP COLUMN `' . $columnToRemove . '`';
            }
        }

        if (!empty($this->dropIndexes)) {
            foreach ($this->dropIndexes as $indexToRemove) {
                $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` DROP FOREIGN KEY IF EXISTS ' . $indexToRemove;
                $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` DROP INDEX IF EXISTS ' . $indexToRemove;
            }
        }

        if (!empty($this->dropForeignKeys)) {
            foreach ($this->dropForeignKeys as $foreignToRemove) {
                $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` DROP FOREIGN KEY IF EXISTS ' . $foreignToRemove;
                $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` DROP INDEX ' . $foreignToRemove;
            }
        }

        if (!empty($this->renameAs)) {
            $this->statement[] = 'ALTER TABLE `' . $this->prefix . $this->table . '` RENAME TO `' . $this->renameAs . '`;';
        }

        return $this->statement;
    }

    public function getExecutableColumnDefination($column)
    {
        return '`' . $column['name'] . '` ' . $this->getExecutableColumnBehaviour($column);
    }

    public function getExecutableColumnBehaviour($column)
    {
        $behaviour = strtoupper($column['type']);
        switch ($behaviour) {
            case 'TINYINTEGER':
                $behaviour = 'TINYINT';
                break;
            case 'SMALLINTEGER':
                $behaviour = 'SMALLINT';
                break;
            case 'MEDIUMINTEGER':
                $behaviour = 'MEDIUMINT';
                break;
            case 'BIGINTEGER':
                $behaviour = 'BIGINT';
                break;
            case 'FLOATAUTO':
                $behaviour = 'FLOAT';
                break;
            case 'STRING':
                $behaviour = 'VARCHAR';
                break;
            default:
                break;
        }

        return $behaviour . ' ' . $this->getConstraints($column);
    }

    public function getConstraints($column)
    {
        $constraints = [];

        if ($this->isNumberColumn($column)) {
            $columnTypeArgs = '';
            if (isset($column['total']) || isset($column['places'])) {
                $columnTypeArgs .= '(';
            }
            if (isset($column['total'])) {
                $columnTypeArgs .= $column['total'] . ',';
            }
            if (isset($column['places'])) {
                $columnTypeArgs .= $column['places'];
            }
            if (isset($column['total']) || isset($column['places'])) {
                $columnTypeArgs .= ')';
            }
            $constraints[] = $columnTypeArgs;
        }

        if ($this->isSpatial($column)) {
            if ($column['type'] == 'point' && isset($column['srid'])) {
                $constraints[] = "SRID '" . $column['srid'] . "'";
            }
        }

        if (isset($column['auto_calculate']) && $column['auto_calculate'] && isset($column['as'])) {
            $storeMode = (!empty($column['storeMode'])) ? $column['storeMode'] : '';
            $constraints[] = "GENERATED ALWAYS AS ('" . $column['as'] . "') " . $storeMode;
        }

        if ($this->isDateTimeColumn($column) && isset($column['precision'])) {
            $constraints[] = '(' . $column['precision'] . ')';
        }

        if ($column['type'] == 'floatAuto' && isset($column['size'])) {
            $constraints[] = '(' . $column['size'] . ')';
        }

        if ($column['type'] == 'varchar' && isset($column['length'])) {
            $constraints[] = '(' . $column['length'] . ')';
        }

        if ($column['type'] == 'enum' && !empty($column['allowed'])) {
            $constraints[] = "('" . implode("', '", $column['allowed']) . "')";
        }

        if ($column['type'] == 'set' && !empty($column['allowed'])) {
            $constraints[] = "('" . implode("', '", $column['allowed']) . "')";
        }

        if (isset($column['unsigned']) && $column['unsigned']) {
            $constraints[] = 'UNSIGNED';
        }

        if (isset($column['autoIncrement']) && $column['autoIncrement']) {
            $constraints[] = 'AUTO_INCREMENT';
        }

        if (isset($column['primaryKey']) && $column['primaryKey']) {
            $constraints[] = 'PRIMARY KEY';
        }

        if (isset($column['nullable']) && !$column['nullable']) {
            $constraints[] = 'NOT NULL';
        }

        if (isset($column['nullable']) && $column['nullable']) {
            $constraints[] = 'NULL';
        }

        if (isset($column['unique']) && $column['unique']) {
            $constraints[] = 'UNIQUE';
        }

        if (!empty($column['comment'])) {
            $constraints[] = "COMMENT '" . addslashes($column['comment']) . "'";
        }

        if (!$this->isDateTimeColumn($column) && isset($column['default'])) {
            if (gettype($column['default']) == 'string')
                $column['default'] = '"' . $column['default'] . '"';
            if (gettype($column['default']) == 'boolean')
                $column['default'] = $column['default'] ? '1' : '0';
            $constraints[] = 'DEFAULT ' . $column['default'];
        }

        if ($this->isDateTimeColumn($column) && !empty($column['default'])) {
            if ($column['default'] == 'current') {
                $constraints[] = 'DEFAULT CURRENT_TIMESTAMP';
            } else if ($column['default'] == 'on_update_current') {
                $constraints[] = 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
            } else {
                $constraints[] = 'DEFAULT ' . $column['default'];
            }
        }

        return implode(' ', $constraints);
    }

    public function isNumberColumn($column)
    {
        if (in_array($column['type'], ['double', 'decimal', 'float', 'real', 'serial'])) {
            return true;
        }

        return false;
    }

    public function isDateTimeColumn($column)
    {
        if (in_array($column['type'], ['date', 'dateTime', 'timestamp', 'dateTimeTz', 'time', 'timeTz', 'year'])) {
            return true;
        }

        return false;
    }

    public function isSpatial($column)
    {
        if (in_array($column['type'], ['geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection'])) {
            return true;
        }

        return false;
    }

}