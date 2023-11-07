<?php

namespace Bones\Skeletons\DBGram;

use Bones\Commander;
use Bones\Database;
use Bones\File;
use Bones\Str;
use Bones\DBGramException;
use Bones\Traits\Commander\AttrPairGenerator;

class Adjustor
{
    use AttrPairGenerator;
    
    protected $table = 'dbgrams';
    protected $console;
    protected $filesBasePath = 'locker/dbgrams/';
    protected $modifyPropertyPrefixes = ['add_column', 'modify_column', 'drop_column', 'add_index', 'add_spatial_index', 'add_fulltext_index', 'add_unique', 'drop_index', 'add_foregin', 'drop_foregin', 'remove_column', 'remove_foreign', 'remove_index', 'add_foreign_key', 'rename', 'rename_'];

    public function __construct()
    {
        $this->console = (new Commander());
        if (!file_exists($this->filesBasePath))
            mkdir($this->filesBasePath, 0644, true);
    }

    public function create($commandAttr, $commandExtraAttrs)
    {
        if (empty($commandAttr)) {
            return $this->console->throwError('EMPTY [Database Diagram] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $dbGramFiles = explode(',', $commandAttr);

        foreach ($dbGramFiles as $dbGramFile) {
            $this->createFile($dbGramFile, $commandExtraAttrs);
        }

        return $this->console->showMsg(count($dbGramFiles).' [Database Diagram] file(s) created!' . PHP_EOL);
    }

    public function createFile($dbGramFile, $commandExtraAttrs)
    {
        $DBGramFilePath = $this->filesBasePath . Str::camelize($dbGramFile) . '_' . date("Y_m_d_His") . '.php';
        if (file_exists($DBGramFilePath)) {
            return $this->console->throwError('[Database Diagram] FILE ALREADY EXISTS at %s' . PHP_EOL, [$DBGramFilePath]);
        }
        $DBGramFileDoors = explode('/', $DBGramFilePath);
        unset($DBGramFileDoors[count($DBGramFileDoors) - 1]);
        if (!file_exists(implode('/', $DBGramFileDoors))) {
            mkdir(implode('/', $DBGramFileDoors), 0644, true);
        }
        $f = fopen($DBGramFilePath, 'wb');
        if (!$f) {
            return $this->console->throwError('%s can not create dbgram file at ' . PHP_EOL, [$DBGramFilePath]);
        }
        fwrite($f, $this->getBaseCode($dbGramFile, $commandExtraAttrs));
        fclose($f);
        return $this->console->showMsg('dbgram [Database Diagram] file saved at ' . $DBGramFilePath . '!' . PHP_EOL);
    }

    public function proceedDBGramAdjustment($commandAttrs, $extraAttrs)
    {
        $additionalAttrs = $this->generateExtraAttrs($commandAttrs, $extraAttrs);

        if (in_array('--export', array_keys($additionalAttrs))) {
            return $this->exportDB();
        }

        $this->setupGround();

        $dbGramFiles = [];
        $forceFresh = false;

        $existingDBGramFiles = File::dirFiles($this->filesBasePath, true);

        if ($commandAttrs == '--force') {

            $forceFresh = true;

            foreach ($existingDBGramFiles as $dbGramFile) {
                $dbGramFiles[] = $dbGramFile;
            }
        } else if ($commandAttrs == '--fresh') {

            $this->console->showMsgAndContinue('dbgram(s) fresh in progress...' . PHP_EOL);
            $statementPairs = Database::rawQuery("SELECT concat('DROP TABLE IF EXISTS `', table_name, '`;') as statement, table_name
            FROM information_schema.tables
            WHERE table_schema = '" . Database::primaryDB()['db'] . "';");

            Database::rawQuery('SET FOREIGN_KEY_CHECKS=0;');

            foreach ($statementPairs as $statementPair) {
                $this->console->showMsgAndContinue('Dropping table [' . $statementPair->table_name . ']...' . PHP_EOL);
                Database::rawQuery($statementPair->statement);
                $this->console->showMsgAndContinue('Table [' . $statementPair->table_name . '] dropped!' . PHP_EOL);
            }

            Database::rawQuery('SET FOREIGN_KEY_CHECKS=1;');

            $this->setupGround();
            $this->console->showMsgAndContinue('[' . $this->table . '] table created!' . PHP_EOL);

            $this->console->run('run:dbgram');

            $this->console->showMsgAndContinue('dbgram(s) fresh process is completed!' . PHP_EOL);

            if (in_array('--fill', array_keys($additionalAttrs))) {
                $this->console->run('run:dbfiller');
            }
        } else if ($commandAttrs == '--setup') {

            $this->console->showMsgAndContinue('Setting up dbgram platform...' . PHP_EOL);
            $this->setupGround();
            $this->console->showMsgAndContinue('[' . Str::decamelize($this->table) . '] table created!' . PHP_EOL);
        } else if ($commandAttrs == '--reset') {

            $this->console->showMsgAndContinue('Resetting dbgram(s)' . PHP_EOL);
            $this->removeAllFiles('--all');
            Database::rawQuery('TRUNCATE TABLE `' . $this->table . '`');
            return $this->console->showMsgAndContinue('dbgram(s) has been reset successfully!' . PHP_EOL);
        } else if ($commandAttrs == '--refresh') {

            $this->console->run('rollback:dbgram --all');
            $this->console->run('run:dbgram --force');
            if (in_array('--fill', array_keys($additionalAttrs))) {
                $this->console->run('run:dbfiller');
            }
        } else if (!empty($commandAttrs)) {
            $dbGramFiles = explode(',', $commandAttrs);

            foreach ($dbGramFiles as &$dbGramFile) {
                $dbGramFile = str_replace($this->filesBasePath . '', '', $dbGramFile);
                $dbGramFile = str_replace('.php', '', $dbGramFile);
                $dbGramFile = $this->filesBasePath . '' . $dbGramFile . '.php';
            }
        } else {
            $adjustedDBGrams = $this->adjusted();

            foreach ($existingDBGramFiles as $dbGramFile) {
                if (!in_array(trim($dbGramFile), $adjustedDBGrams)) {
                    $dbGramFiles[] = $dbGramFile;
                }
            }
        }

        return $this->adjust($dbGramFiles, $forceFresh);
    }

    public function adjusted()
    {
        $adjusted = Database::table($this->table)->get();
        $adjustedDBGrams = [];
        foreach ($adjusted as $dbGram) {
            $adjustedDBGrams[] = $this->filesBasePath . '' . $dbGram->dbgram . '.php';
        }
        return $adjustedDBGrams;
    }

    public function adjust($dbGramFiles, $forceFresh = false)
    {
        if (empty($dbGramFiles)) {
            $this->console->showMsg('No dbgram(s) to adjust' . PHP_EOL);
        }

        if ($forceFresh) {
            Database::table($this->table)->truncate();
        }

        $nextStackNo = $this->getNextStackNo();

        foreach ($dbGramFiles as $dbGramFile) {
            if (file_exists($dbGramFile)) {
                $dbGramToAdjust = require($dbGramFile);
                $dbGramObj = new $dbGramToAdjust();
                if ($forceFresh) {
                    if (method_exists($dbGramToAdjust, 'fall')) {
                        $dbGramObj->fall();
                    }
                }
                if (method_exists($dbGramToAdjust, 'arise')) {
                    $this->console->showMsgAndContinue('Adjusting ' . $dbGramFile . ' [dbgram]' . PHP_EOL);
                    $dbGramObj->arise();
                    $this->addToAdjusted($dbGramFile, $nextStackNo);
                    $this->console->showMsgAndContinue($dbGramFile . ' adjusted!' . PHP_EOL);
                } else {
                    throw new DBGramException('Not valid structure of dbgram file ' . $dbGramFile);
                }
            }
        }
    }

    public function addToAdjusted($dbGramFile, $nextStackNo)
    {
        $dbGramFile = str_replace($this->filesBasePath . '', '', $dbGramFile);
        $dbGramFile = str_replace('.php', '', $dbGramFile);

        return Database::table($this->table)->insert([
            'dbgram' => $dbGramFile,
            'stack' => $nextStackNo
        ]);
    }

    public function getDBGrams($commandAttr, $extraAttrs)
    {
        $dbGrams = [];

        $dbGramFiles = glob($this->filesBasePath . '*.php');
        $adjustedDBGrams = $this->adjustedWithDepth();

        array_multisort(
            array_map('filemtime', $dbGramFiles),
            SORT_NUMERIC,
            SORT_ASC,
            $dbGramFiles
        );

        foreach ($dbGramFiles as $dbGramFile) {
            $dbGramFileBaseName = Str::removeWords(basename($dbGramFile), ['.php']);
            $adjustedDBGram = array_search($dbGramFileBaseName, array_column($adjustedDBGrams, 'name'));
            $dbGrams[] = [
                'name' => $dbGramFileBaseName,
                'stack' => $adjustedDBGrams[$adjustedDBGram]['stack'],
                'is_adjusted' => $adjustedDBGram >= 0,
            ];
        }

        return $dbGrams;
    }

    public function adjustedWithDepth()
    {
        $adjusted = Database::table($this->table)->select('dbgram, stack')->get();
        $adjustedDBGrams = [];
        foreach ($adjusted as $dbGram) {
            $adjustedDBGrams[] = [
                'name' => $dbGram->dbgram,
                'stack' => $dbGram->stack,
            ];
        }
        return $adjustedDBGrams;
    }

    public function getLatestStackNo()
    {
        $dbGramsRes = Database::table($this->table)->orderBy('stack', 'desc')->select('stack')->first();
        if (empty($dbGramsRes) && empty($dbGramsRes->stack)) return 0;

        return $dbGramsRes->stack;
    }

    public function getNextStackNo()
    {
        return $this->getLatestStackNo() + 1;
    }

    public function getBaseCode($commandAttr, $commandExtraAttrs)
    {
        $properties = $this->autoDetectProperties($commandAttr, $commandExtraAttrs);

        $baseAdjustmentCode = '<?php' . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= 'use Bones\DataWing;' . PHP_EOL;
        $baseAdjustmentCode .= 'use Bones\Skeletons\DataWing\Skeleton;' . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= 'return new class ' . PHP_EOL;
        $baseAdjustmentCode .= '{' . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= "\tprotected \$table = '" . $properties['table'] . "';" . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= "\tpublic function arise()" . PHP_EOL;
        $baseAdjustmentCode .= "\t{" . PHP_EOL;

        if (in_array($properties['action'], ['create', 'modify'])) {
            $baseAdjustmentCode .= "\t\tDataWing::" . $properties['action'] . "(\$this->table, function (Skeleton \$table)" . PHP_EOL;
            $baseAdjustmentCode .= "\t\t{" . PHP_EOL;
        } else if (in_array($properties['action'], ['drop', 'truncate'])) {
            $baseAdjustmentCode .= "\t\tDataWing::" . $properties['action'] . "(\$this->table);" . PHP_EOL;
        }

        if ($properties['action'] == 'create') {
            $baseAdjustmentCode .= "\t\t\t\$table->id()->primaryKey();" . PHP_EOL;
        }

        if (in_array($properties['action'], ['create', 'modify'])) {
            $baseAdjustmentCode .= "\t\t\treturn \$table;" . PHP_EOL;
            $baseAdjustmentCode .= "\t\t});" . PHP_EOL;
        }

        $baseAdjustmentCode .= "\t}" . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= "\tpublic function fall()" . PHP_EOL;
        $baseAdjustmentCode .= "\t{" . PHP_EOL;

        if ($properties['action'] == 'create') {
            $baseAdjustmentCode .= "\t\tDataWing::drop(\$this->table);" . PHP_EOL;
        } else if ($properties['action'] == 'modify') {
            $baseAdjustmentCode .= "\t\tDataWing::" . $properties['action'] . "(\$this->table, function (Skeleton \$table)" . PHP_EOL;
            $baseAdjustmentCode .= "\t\t{" . PHP_EOL;
            $baseAdjustmentCode .= "\t\t\treturn \$table;" . PHP_EOL;
            $baseAdjustmentCode .= "\t\t});" . PHP_EOL;
        }

        $baseAdjustmentCode .= "\t}" . PHP_EOL . PHP_EOL;
        $baseAdjustmentCode .= '};' . PHP_EOL;

        return $baseAdjustmentCode;
    }

    public function autoDetectProperties($commandAttr, $commandExtraAttrs)
    {
        $table = '';
        $action = '';

        if (!empty($commandExtraAttrs)) {
            foreach ($commandExtraAttrs as $extraAttr) {
                $attribute = explode('=', $extraAttr);
                $attrName = $attribute[0];
                if (Str::startsWith($attrName, '--') && count($attribute) > 0) {
                    $attrVal = $attribute[1];
                    $attrName = str_replace('--', '', $attrName);
                    if ($attrName == 'table') {
                        $table = Str::removeQuotes($attrVal);
                    }
                    if ($attrName == 'action') {
                        $action = Str::removeQuotes($attrVal);
                    }
                }
            }
        }

        if (!empty($action) && !empty($table)) {
            return [
                'action' => $action,
                'table' => $table
            ];
        }

        if (empty($action)) {
            $action = $this->commandRelateTo($commandAttr);
        }

        if (empty($table)) {
            if ($this->commandRelateTo($commandAttr) == 'create') {
                $table = Str::removeWords($commandAttr, ['create_', '_table']);
            } else if ($this->commandRelateTo($commandAttr) == 'modify') {
                $table = $this->autoDetectTableProperty($commandAttr);
            } else if ($this->commandRelateTo($commandAttr) == 'drop') {
                $table = Str::removeWords($commandAttr, ['drop_', 'remove_', '_table']);
            } else if ($this->commandRelateTo($commandAttr) == 'truncate') {
                $table = Str::removeWords($commandAttr, ['truncate_', '_table']);
            }
        }

        return [
            'action' => $action,
            'table' => $table
        ];
    }

    public function commandRelateTo($commandAttr)
    {
        $relateTo = '';

        if (Str::containsWord($commandAttr, $this->modifyPropertyPrefixes))
            $relateTo = 'modify';

        if ($relateTo != 'modify' && Str::containsWord($commandAttr, ['drop_', 'remove_']))
            $relateTo = 'drop';

        if (Str::containsWord($commandAttr, ['truncate_']))
            $relateTo = 'truncate';

        if (empty($relateTo))
            if (Str::startsWith($commandAttr, 'modify_') || Str::startsWith($commandAttr, 'change_'))
                $relateTo = 'modify';

        return (!empty($relateTo)) ? $relateTo : 'create';
    }

    public function autoDetectTableProperty($commandAttr)
    {
        $table = Str::removeWords($commandAttr, $this->modifyPropertyPrefixes);
        if (preg_match('/from_(.*?)_table/', $table, $matches) == 1) {
            $table = $matches[1];
        }
        if (preg_match('/to_(.*?)_table/', $table, $matches) == 1) {
            $table = $matches[1];
        }
        if (preg_match('/into_(.*?)_table/', $table, $matches) == 1) {
            $table = $matches[1];
        }
        if (preg_match('/in_(.*?)_table/', $table, $matches) == 1) {
            $table = $matches[1];
        }

        return $table;
    }

    public function removeAllFiles($commandAttr)
    {
        if ($commandAttr == '--all') {
            return $this->console->clearDir($this->filesBasePath);
        }

        $commandAttr = $this->cleanBaseFilePath($commandAttr);
        $dbGramFilePath = $this->filesBasePath . $commandAttr . '.php';
        return $this->console->removeAsset($dbGramFilePath, 'dbgram');
    }

    public function rollback($commandAttr, $commandExtraAttrs)
    {
        $rollBackForStacks = [];
        $extraAttrs = $this->generateExtraAttrs($commandAttr, $commandExtraAttrs);

        if (in_array('--all', array_keys($extraAttrs))) {

            $stacksToRollback = Database::rawQuery('SELECT `stack` FROM `' . $this->table . '` GROUP by `stack` ORDER BY `stack` DESC');

            if (!empty($stacksToRollback)) {
                foreach ($stacksToRollback as $dbGram) {
                    $rollBackForStacks[] = $dbGram->stack;
                }
            }
        } else if (in_array('--files', array_keys($extraAttrs))) {
            if (!empty($extraAttrs['--files'])) {
                $rollbackFiles = explode(',', $extraAttrs['--files']);

                $this->console->showMsgAndContinue('Rolling back dbgram(s)' . PHP_EOL);

                if (empty($rollbackFiles)) {
                    return $this->console->showMsg('No dbgram(s) to rollback' . PHP_EOL);
                }

                foreach ($rollbackFiles as $rollbackFilePath) {
                    $rollbackFile = path() . $rollbackFilePath;
                    if (file_exists($rollbackFile)) {
                        $dbGramToRollback = require($rollbackFile);
                        $dbGramObj = new $dbGramToRollback();
                        if (method_exists($dbGramObj, 'fall')) {
                            $dbGramObj->fall();
                        }

                        $rollbackFilePath = str_replace($this->filesBasePath . '', '', $rollbackFilePath);
                        $rollbackFilePath = str_replace('.php', '', $rollbackFilePath);
                        $rollbackFilePath = trim(ltrim(rtrim($rollbackFilePath, '/'), '/'));

                        $this->removeDBGramByName($rollbackFilePath);
                        $this->console->showMsgAndContinue('Rollback done for dbgram ' . $rollbackFile . PHP_EOL);
                    } else {
                        return $this->console->showMsg('%s (dbgram) file could not be found' . PHP_EOL, [$rollbackFile]);
                    }
                }

                return $this->console->showMsg('Rollback process done for %d (dbgram) files' . PHP_EOL, [count($rollbackFiles)]);
            }
        } else if (in_array('--limit', array_keys($extraAttrs))) {
            $rollBackUptoStack = (!empty($extraAttrs) && !empty($extraAttrs['--limit'])) ? $extraAttrs['--limit'] : 1;
            $stacksToRollback = Database::rawQuery('SELECT `stack` FROM `' . $this->table . '` GROUP by `stack` ORDER BY `stack` DESC limit ' . $rollBackUptoStack);
            if (!empty($stacksToRollback)) {
                foreach ($stacksToRollback as $dbGram) {
                    $rollBackForStacks[] = $dbGram->stack;
                }
            }
        } else if (in_array('--stack', array_keys($extraAttrs))) {
            $rollBackForStacks[] = (!empty($extraAttrs) && !empty($extraAttrs['--stack'])) ? $extraAttrs['--stack'] : $this->getLatestStackNo();
        } else {
            $rollBackForStacks[] = $this->getLatestStackNo();
        }

        return $this->startRollBack($rollBackForStacks);
    }

    public function startRollBack($rollBackForStacks)
    {

        if (empty($rollBackForStacks)) {
            return $this->console->showMsg('Rollback process done!' . PHP_EOL);
        }

        $rollbackFiles = $this->getStackFiles($rollBackForStacks);

        $this->console->showMsgAndContinue('Rolling back dbgram(s)' . PHP_EOL);

        foreach ($rollbackFiles as $rollbackFileObj) {
            $rollbackFile = $this->filesBasePath . $rollbackFileObj->dbgram . '.php';
            if (file_exists($rollbackFile)) {
                $dbGramToRollback = require($rollbackFile);
                $dbGramObj = new $dbGramToRollback();
                if (method_exists($dbGramObj, 'fall')) {
                    $dbGramObj->fall();
                }
                $this->removeDBGramById($rollbackFileObj->id);
                $this->console->showMsgAndContinue('Rollback done for dbgram ' . $rollbackFile . PHP_EOL);
            }
        }

        $this->console->showMsgAndContinue('Rollback process done!' . PHP_EOL);
    }

    public function removeDBGramByName($name)
    {
        return Database::table($this->table)->where('dbgram', $name)->delete();
    }

    public function removeDBGramById($id)
    {
        return Database::table($this->table)->where('id', $id)->delete();
    }

    public function getStackFiles($rollBackForStacks)
    {
        return Database::table($this->table)->select('id, dbgram')->whereIn('stack', $rollBackForStacks)->orderBy('id', 'desc')->get();
    }

    public function exportDB()
    {
        $database = Database::primaryDB()['db'];
        $tables = [];

        if (empty($tables)) {
            $res = Database::rawQuery("SELECT TABLE_NAME AS _table FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '" . $database . "'");
            array_walk($res, function ($table) use (&$tables) {
                $tables[] = $table->_table;
            });
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        $this->console->showMsgAndContinue('Export database process started for ' . count($tables) . ' table(s)' . PHP_EOL, [], 'warning');

        $return = '';

        foreach ($tables as $table) {

            $this->console->showMsgAndContinue('Dumping table ' . $table . PHP_EOL, [], 'warning');

            $table_info = Database::rawQuery("SELECT count(*) as field_count
            FROM information_schema.columns
            WHERE table_schema = '".$database."'  
            AND table_name = '".$table."'");
            $num_of_fileds = $table_info[0]->field_count;

            $result = Database::rawQuery('SELECT * FROM ' . $table);

            $return .= 'DROP TABLE IF EXISTS ' . $table . ';' . PHP_EOL;
            $return .= PHP_EOL . PHP_EOL . array_values((array) Database::rawQuery('SHOW CREATE TABLE ' . $table))[0]->{'Create Table'} . PHP_EOL . PHP_EOL;

            for ($fieldCount = 0; $fieldCount < $num_of_fileds; $fieldCount++) {
                foreach ($result as $row) {
                    $row = array_values((array) $row);
                    $return .= 'INSERT INTO ' . $table . ' VALUES(';
                    for ($columnCount = 0; $columnCount < $num_of_fileds; $columnCount++) {
                        if (!empty($row[$columnCount])) {
                            $row[$columnCount] = addslashes($row[$columnCount]);
                            $row[$columnCount] = preg_replace("/\\\\n/", "/\\\\\n/", $row[$columnCount]);
                        }
                        if (isset($row[$columnCount])) {
                            $return .= '"' . $row[$columnCount] . '"';
                        } else {
                            $return .= '""';
                        }
                        if ($columnCount < ($num_of_fileds - 1)) {
                            $return .= ',';
                        }
                    }
                    $return .= ");" . PHP_EOL;
                }
            }

            $return .= PHP_EOL . PHP_EOL . PHP_EOL;

            $this->console->showMsgAndContinue('Table ' . $table . ' dumped!' . PHP_EOL, [], 'info');
        }

        $db_backup_dir = 'locker/system/db/backups/';

        if (!file_exists($db_backup_dir)) {
            mkdir($db_backup_dir, 0644, true);
        }

        $backUpFileAs = $db_backup_dir . time() . '-' . (md5(implode(',', $tables))) . '.sql';
        $handle = fopen($backUpFileAs, 'w+');
        fwrite($handle, $return);
        fclose($handle);

        return $this->console->showMsg('Export done for database ' . $database . '. File saved at ' . $backUpFileAs . PHP_EOL, [], 'success');
    }

    public function cleanBaseFilePath($path)
    {
        $path = str_replace($this->filesBasePath . '', '', $path);
        return str_replace('.php', '', $path);
    }

    public function setupGround()
    {
        Database::rawQuery('CREATE TABLE IF NOT EXISTS `' . $this->table . '` (`id` INTEGER UNSIGNED AUTO_INCREMENT PRIMARY KEY, `dbgram` VARCHAR (191) NOT NULL, `stack` INTEGER NOT NULL COLLATE utf8mb4_unicode_ci)');
    }

}