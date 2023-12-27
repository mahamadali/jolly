<?php

namespace Bones\Skeletons\DBFiller;

use Bones\Commander;
use Bones\Str;
use Bones\Traits\Commander\AttrPairGenerator;

class Refill
{
    use AttrPairGenerator;
    
    protected $console;
    protected $filesBasePath = 'locker/dbfillers/';

    public function __construct()
    {
        $this->console = (new Commander());
        if (!file_exists($this->filesBasePath))
            mkdir($this->filesBasePath, 0644, true);
    }

    public function proceedDBFillers($commandAttr)
    {
        $dbFillerFiles = [];

        if (!empty($commandAttr)) {

            $dbFillerFiles = explode(',', $commandAttr);

            foreach ($dbFillerFiles as &$dbFillerFile) {
                $dbFillerFile = $this->cleanBaseFilePath($dbFillerFile) . '.php';
            }
        } else {
            $dbFillerFiles = [];

            if (file_exists($dbFillerConfig = $this->filesBasePath . '/config.php')) {
                $dbFillerFiles = require_once($dbFillerConfig);
                if (!empty($dbFillerFiles)) {
                    $dbFillerFiles = array_map(function($dbFillerFile) {
                        return $dbFillerFile . '.php';
                    }, $dbFillerFiles);
                }
            }
            
            $dbFillerFiles = (!empty($dbFillerFiles)) ? $dbFillerFiles : $this->getAllDBFillerFiles($this->filesBasePath);
        }

        $this->runFiller($dbFillerFiles);
    }

    public function runFiller($dbFillerFiles)
    {
        if (empty($dbFillerFiles)) {
            $this->console->showMsgAndContinue('No [Database Filler] file(s) found' . PHP_EOL);
        }

        $this->console->showMsgAndContinue('Database filler in progress...' . PHP_EOL);
        $processedFilesCount = 0;

        foreach ($dbFillerFiles as $dbFillerFile) {
            if ($dbFillerFile == 'config.php') {
                continue;
            }
            
            $DBFillerFilePath = path() . DIRECTORY_SEPARATOR . $this->filesBasePath . $dbFillerFile;
            
            if (!file_exists($DBFillerFilePath)) {
                $this->console->showMsgAndContinue('Trying to find [Database Filler] %s with auto search technique' . PHP_EOL, [basename($DBFillerFilePath)]);
                $DBFillerFilePath = path() . DIRECTORY_SEPARATOR . $this->filesBasePath . Str::decamelize($dbFillerFile);
            }

            if (file_exists($DBFillerFilePath)) {
                $dbFiller = require($DBFillerFilePath);
                $dbFiller = new $dbFiller();
                if (method_exists($dbFiller, 'fill')) {
                    $this->console->showMsgAndContinue('[Database Filler] started for %s' . PHP_EOL, [$DBFillerFilePath]);
                    $dbFiller->fill();
                    $this->console->showMsgAndContinue('[Database Filler] completed for %s' . PHP_EOL, [$DBFillerFilePath]);
                    $processedFilesCount++;
                }
            } else {
                $this->console->showMsgAndContinue('Skipping [Database Filler] %s does not exists' . PHP_EOL, [$DBFillerFilePath]);
            }
        }

        if ($processedFilesCount == 0) {
            $this->console->showMsgAndContinue('[Database Filler] FILE NOT FOUND' . PHP_EOL);
        } else {
            $this->console->showMsgAndContinue('Database Filler process done for %d files' . PHP_EOL, [$processedFilesCount]);
        }
    }

    public function create($commandAttr, $commandExtraAttrs)
    {
        if (empty($commandAttr)) {
            return $this->console->throwError('EMPTY [Database Filler] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $dbFillerFiles = explode(',', $commandAttr);

        foreach ($dbFillerFiles as $dbFillerFile) {
            $this->createFile($dbFillerFile, $commandExtraAttrs);
        }

        return $this->console->showMsg(count($dbFillerFiles).' [Database Filler] file(s) created!' . PHP_EOL);
    }

    public function createFile($dbFillerFile, $commandExtraAttrs)
    {
        $dbFillerFile = $this->cleanBaseFilePath($dbFillerFile);
        if (!Str::endsWith($dbFillerFile, 'filler')) {
            $dbFillerFile = $dbFillerFile . '_filler';
        }
        $DBFillerFilePath = $this->filesBasePath . Str::decamelize($dbFillerFile) . '.php';
        if (file_exists($DBFillerFilePath)) {
            return $this->console->throwError('[Database Filler] FILE ALREADY EXISTS at %s' . PHP_EOL, [$DBFillerFilePath]);
        }
        $DBFillerFileDoors = explode('/', $DBFillerFilePath);
        $dbFillerFileNameParts = explode('.php', basename($DBFillerFileDoors[count($DBFillerFileDoors) - 1]));
        unset($DBFillerFileDoors[count($DBFillerFileDoors) - 1]);
        if (!file_exists(implode('/', $DBFillerFileDoors))) {
            mkdir(implode('/', $DBFillerFileDoors), 0644, true);
        }
        $f = fopen($DBFillerFilePath, 'wb');
        if (!$f) {
            return $this->console->throwError('%s can not create dbfiller file at ' . PHP_EOL, [$DBFillerFilePath]);
        }
        fwrite($f, $this->getBaseCode($dbFillerFileNameParts[0], $this->generateExtraAttrs($dbFillerFile, $commandExtraAttrs)));
        fclose($f);
        return $this->console->showMsg('dbfiller [Database Filler] file saved at ' .  $DBFillerFilePath . '!' . PHP_EOL);
    }

    public function getBaseCode(string $name, array $extraAttrs = [])
    {
        $baseFillerCode = '<?php' . PHP_EOL . PHP_EOL;
        $baseFillerCode .= 'namespace Bones\Skeletons\DBFiller;' . PHP_EOL . PHP_EOL;
        $baseFillerCode .= 'use Bones\Database;' . PHP_EOL . PHP_EOL;
        $baseFillerCode .= 'return new class' . PHP_EOL;
        $baseFillerCode .= '{' . PHP_EOL;
        $table = '';
        if (!empty($extraAttrs['--table'])) {
            $table = $extraAttrs['--table'];
        } else {
            $table = Str::pluralize(Str::removeWords(strtolower($name), ['filler']));
        }
        $baseFillerCode .= "\tprotected \$table = '" . $table . "';" . PHP_EOL . PHP_EOL;
        $baseFillerCode .= "\tprotected \$fake_filler;" . PHP_EOL . PHP_EOL;
        $baseFillerCode .= "\tpublic function __construct()" . PHP_EOL;
        $baseFillerCode .= "\t{" . PHP_EOL;
        $baseFillerCode .= "\t\t\$this->fake_filler = new FakeFiller();" . PHP_EOL;
        $baseFillerCode .= "\t}" . PHP_EOL . PHP_EOL;
        $baseFillerCode .= "\tpublic function fill()" . PHP_EOL;
        $baseFillerCode .= "\t{" . PHP_EOL;
        $baseFillerCode .= "\t\tDatabase::table(\$this->table)->insertMulti([" . PHP_EOL;
        $baseFillerCode .= "\t\t\t[" . PHP_EOL;
        $baseFillerCode .= "\t\t\t\t'name' => \$this->fake_filler->name()," . PHP_EOL;
        $baseFillerCode .= "\t\t\t\t'address' => \$this->fake_filler->address()," . PHP_EOL;
        $baseFillerCode .= "\t\t\t\t'column_3' => \$this->fake_filler->randomString()," . PHP_EOL;
        $baseFillerCode .= "\t\t\t]," . PHP_EOL;
        $baseFillerCode .= "\t\t]);" . PHP_EOL;
        $baseFillerCode .= "\t}" . PHP_EOL . PHP_EOL;
        $baseFillerCode .= "};";
        return $baseFillerCode;
    }

    public function getAllDBFillerFiles($dir, &$dbFillerFiles = array())
    {
        $dirFiles = scandir($dir, 1);

        $dirFilesTmp = [];
        foreach ($dirFiles as $dirFile) {
            if ($dirFile != "." && $dirFile != "..") {
                $dirFilesTmp[$dirFile] = filemtime($dir . DIRECTORY_SEPARATOR . $dirFile);
            } else {
                $dirFilesTmp[$dirFile] = $dirFile;
            }
        }

        // Sort files by modification date
        asort($dirFilesTmp);

        // Save sorted temp file arrays
        $dirFiles = array_keys($dirFilesTmp);

        // Destroying temp variable
        unset($dirFilesTmp);

        foreach ($dirFiles as  $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path) && Str::endsWith($path, '.php')) {
                $path = Str::removeWords($path, [path()]);
                $path = Str::removeCharAt(Str::multiReplace($path, [DIRECTORY_SEPARATOR], ['/']), 0);
                $dbFillerFiles[] = $this->cleanBaseFilePath($path) . '.php';
            } else if ($value != "." && $value != "..") {
                $this->getAllDBFillerFiles($path, $dbFillerFiles);
            }
        }

        return $dbFillerFiles;
    }

    public function removeAllFiles($commandAttr)
    {
        if ($commandAttr == '--all') {
            return $this->console->clearDir($this->filesBasePath);
        }

        $commandAttr = $this->cleanBaseFilePath($commandAttr);
        $dbFillerFilePath = $this->filesBasePath . $commandAttr . '.php';
        return $this->console->removeAsset($dbFillerFilePath, 'dbfiller');
    }

    public function cleanBaseFilePath($path)
    {
        $path = str_replace($this->filesBasePath . '', '', $path);
        return str_replace('.php', '', $path);
    }
}