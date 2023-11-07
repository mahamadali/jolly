<?php

namespace Bones;

use Bones\Skeletons\DBFiller\Refill;
use Bones\Skeletons\DBGram\Adjustor;
use Bones\Skeletons\Supporters\BackgroundAction;
use Bones\Skeletons\Supporters\BaseCodeTemplate;

class Commander
{
    protected $commands = [];
    protected $attribute;
    protected $extraAttrs;
    protected $baseCodeTemplate;
    public $appStopperFile = 'locker/system/stop';
    public $settingDir = 'settings';
    protected $agent = '[COMMANDER VIA JOLLY]: ';

    public function __construct($commands = [])
    {
        unset($commands[0]);
        $this->commands = array_values($commands);
        $this->baseCodeTemplate = new BaseCodeTemplate(array_values($this->commands));
        session()->set('from_cli', true, true);
    }

    public static function run(string $args)
    {
        $args = 'commander ' . $args;
        $args = explode(' ', $args);
        return (new self($args))->proceed();
    }

    public function proceed()
    {
        $action = $this->commands[0];
        $this->attribute = (!empty($this->commands[1])) ? $this->commands[1] : '';
        unset($this->commands[0]);
        if (count($this->commands) > 1) {
            unset($this->commands[1]);
        }
        $this->extraAttrs = array_values($this->commands);
        $this->authenticate($action);
        $this->baseCodeTemplate = new BaseCodeTemplate($this->extraAttrs);
    }

    public function authenticate(string $action)
    {
        $action = explode(':', $action);
        $commandFor = '';
        if (count($action) > 1) {
            $command = strtolower($action[0]);
            $commandFor = strtolower($action[1]);
        } else {
            $command = strtolower($action[0]);
        }
        switch ($command) {
            case 'create':
                $this->create($commandFor);
                break;
            case 'remove':
                $this->remove($commandFor);
                break;
            case 'start':
                $this->startApp($commandFor);
                break;
            case 'stop':
                $this->stopApp($commandFor);
                break;
            case 'clear':
                $this->clear($commandFor);
                break;
            case 'list':
                $this->list($commandFor);
                break;
            case 'run':
                $this->execute($commandFor);
                break;
            case 'rollback':
                $this->rollback($commandFor);
                break;
            case 'set':
                $this->set($commandFor);
                break;
            case 'listen':
                $this->listen($commandFor);
                break;
            case 'serve':
                $this->serve($commandFor);
                break;
            case 'self-update':
                $this->selfUpdate();
                break;
            default:
                return $this->throwError('%s is not a valid command', [$command]);
                break;
        }
    }

    public function create(string $commandFor)
    {
        switch ($commandFor) {
            case 'model':
                $this->createModelFile();
                break;
            case 'controller':
                $this->createControllerFile();
                break;
            case 'view':
                $this->createViewFile();
                break;
            case 'barrier':
                $this->createBarrierFile();
                break;
            case 'dbgram':
                $this->createDBGramFile();
                break;
            case 'dbfiller':
                $this->createDBFillerFile();
                break;
            case 'mailer':
                $this->createMailerFile();
                break;
            case 'texter':
                $this->createTexterFile();
                break;
            case 'bg-action':
                $this->createBGAction();
                break;
            default:
                return $this->throwError('%s is not a valid segment to create', [$commandFor]);
                break;
        }
    }

    public function remove(string $commandFor)
    {
        switch ($commandFor) {
            case 'model':
                $this->removeModelFile();
                break;
            case 'controller':
                $this->removeControllerFile();
                break;
            case 'view':
                $this->removeViewFile();
                break;
            case 'barrier':
                $this->removeBarrierFile();
                break;
            case 'dbgram':
                $this->removeDBGramFile();
                break;
            case 'dbfiller':
                $this->removeDBFillerFile();
                break;
            case 'mailer':
                $this->removeMailerFile();
                break;
            case 'texter':
                $this->removeTexterFile();
                break;
            default:
                return $this->throwError('%s is not a valid segment to remove', [$commandFor]);
                break;
        }
    }

    public function clear(string $commandFor)
    {
        switch ($commandFor) {
            case 'builds':
                $this->clearDir('compiler/builds/');
                break;
            case 'db-backups':
                $this->clearDir('locker/system/db/backups/');
                break;
            case 'bg-actions':
                (new BackgroundAction())->clearAll();
                return $this->showMsg('All background processes are cleared', [], 'success');
                break;
            default:
                return $this->throwError('%s is not a valid segment to clear', [$commandFor]);
                break;
        }
    }

    public function list(string $commandFor)
    {
        switch ($commandFor) {
            case 'routes':
                $this->listRoutes();
                break;
            case 'dbgrams':
                $this->listDBGrams();
                break;
            default:
                return $this->throwError('%s is not a valid segment to list out', [$commandFor]);
                break;
        }
    }

    public function execute(string $commandFor)
    {
        switch ($commandFor) {
            case 'dbgram':
                $this->execDBGrams();
                break;
            case 'dbfiller':
                $this->execDBFillers();
                break;
            case 'bg-actions':
                $this->execBGActions();
                break;
            default:
                return $this->throwError('%s is not a valid segment to run (NOT EXECUTABLE)', [$commandFor]);
                break;
        }
    }

    public function rollback(string $commandFor)
    {
        switch ($commandFor) {
            case 'dbgram':
                return (new Adjustor())->rollback($this->attribute, $this->extraAttrs);
                break;
            default:
                return $this->throwError('%s is not a valid segment to run (NOT EXECUTABLE)', [$commandFor]);
                break;
        }
    }

    public function set(string $commandFor)
    {
        switch ($commandFor) {
            case 'config':
                return $this->setConfigSettings();
                break;
            default:
                return $this->throwError('%s is not a valid segment to set (NOT CONFIGURABLE)', [$commandFor]);
                break;
        }
    }

    public function listen(string $commandFor)
    {
        switch ($commandFor) {
            case 'bg-actions':
                $this->listenBGActions();
                break;
            default:
                return $this->throwError('%s is not a valid segment to listen (NOT LISTENABLE)', [$commandFor]);
                break;
        }
    }

    public function serve()
    {
        $port = rand(8000, 8080);
        $target_directory = '';
        $configuration_file = '';

        $serve_as = 'php -S 127.0.0.1:';

        $serve_attributes = array_merge([$this->attribute], $this->extraAttrs);

        foreach ($serve_attributes as $extra_attr) {
            if (Str::startsWith($extra_attr, '--p=')) {
                $port_attr = explode('=', $extra_attr);

                if (!empty($port_attr) && isset($port_attr[1]) && !empty($port_attr[1])) $port = $port_attr[1];
            } else if (Str::startsWith($extra_attr, '--d=')) {
                $target_directory_attr = explode('=', $extra_attr);

                if (!empty($target_directory_attr) && isset($target_directory_attr[1]) && !empty($target_directory_attr[1])) $target_directory = $target_directory_attr[1];
            } else if (Str::startsWith($extra_attr, '--c=')) {
                $configuration_file_attr = explode('=', $extra_attr);

                if (!empty($configuration_file_attr) && isset($configuration_file_attr[1]) && !empty($configuration_file_attr[1])) $configuration_file = $configuration_file_attr[1];
            }
        }
        
        $serve_as .= $port;

        if (!empty($target_directory)) $serve_as .= ' -t=' . $target_directory;
        if (!empty($configuration_file)) $serve_as .= ' -c=' . $configuration_file;

        passthru($serve_as);
    }

    public function selfUpdate()
    {
        return (new JollyManager($this))->update();
    }

    public function createModelFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Model] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $modelFilePath = 'segments/Models/' . $this->getDecamelizedPath() . '.php';
        if (file_exists($modelFilePath)) {
            return $this->throwError('[Model] FILE ALREADY EXISTS at %s' . PHP_EOL, [$modelFilePath]);
        }
        $modelFileDoors = explode('/', $modelFilePath);
        $modelFileNameParts = explode('.php', basename($modelFileDoors[count($modelFileDoors) - 1]));
        $nameSpace = 'Models';
        unset($modelFileDoors[count($modelFileDoors) - 1]);
        foreach ($modelFileDoors as $door) {
            if (!in_array($door, ['segments', 'Models'])) {
                $nameSpace .= '\\' . $door;
            }
        }
        if (!file_exists(implode('/', $modelFileDoors))) {
            mkdir(implode('/', $modelFileDoors), 0644, true);
        }
        $f = fopen($modelFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create model file at ', [$modelFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->model($modelFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Model saved at ' . $modelFilePath . '!', [], 'success');
    }

    public function createControllerFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Controller] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $controllerFilePath = 'segments/Controllers/' . $this->getDecamelizedPath() . 'Controller.php';
        if (file_exists($controllerFilePath)) {
            return $this->throwError('[Controller] FILE ALREADY EXISTS at %s' . PHP_EOL, [$controllerFilePath]);
        }
        $controllerFileDoors = explode('/', $controllerFilePath);
        $controllerFileNameParts = explode('.php', basename($controllerFileDoors[count($controllerFileDoors) - 1]));
        $nameSpace = 'Controllers';
        unset($controllerFileDoors[count($controllerFileDoors) - 1]);
        foreach ($controllerFileDoors as $doorName => $door) {
            $controllerFileDoors[$doorName] = Str::decamelize($door);
            if (!in_array($door, ['segments', 'Controllers'])) {
                $nameSpace .= '\\' . $door;
            }
        }
        if (!file_exists(implode('/', $controllerFileDoors))) {
            mkdir(implode('/', $controllerFileDoors), 0644, true);
        }
        $f = fopen($controllerFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create controller file at ', [$controllerFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->controller($controllerFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Controller saved at ' . $controllerFilePath . '!', [], 'success');
    }

    public function createViewFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [View] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $viewFilePath = 'segments/views/' . $this->attribute . '.jly.php';
        if (file_exists($viewFilePath)) {
            return $this->throwError('[View] FILE ALREADY EXISTS at %s' . PHP_EOL, [$viewFilePath]);
        }
        $viewFileDoors = explode('/', $viewFilePath);
        unset($viewFileDoors[count($viewFileDoors) - 1]);
        if (!file_exists(implode('/', $viewFileDoors))) {
            mkdir(implode('/', $viewFileDoors), 0644, true);
        }
        $f = fopen($viewFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create view file at ', [$viewFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->view());
        fclose($f);
        return $this->showMsg('View saved at ' . $viewFilePath . '!', [], 'success');
    }

    public function createBarrierFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Barrier] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $barrierFilePath = 'segments/Barriers/' . $this->getDecamelizedPath() . '.php';
        if (file_exists($barrierFilePath)) {
            return $this->throwError('[Barrier] FILE ALREADY EXISTS at %s' . PHP_EOL, [$barrierFilePath]);
        }
        $barrierFileDoors = explode('/', $barrierFilePath);
        $barrierFileNameParts = explode('.php', basename($barrierFileDoors[count($barrierFileDoors) - 1]));
        $nameSpace = 'Barriers';
        unset($barrierFileDoors[count($barrierFileDoors) - 1]);
        foreach ($barrierFileDoors as $door) {
            if (!in_array($door, ['segments', 'Barriers'])) {
                $nameSpace .= '\\' . $door;
            }
        }
        if (!file_exists(implode('/', $barrierFileDoors))) {
            mkdir(implode('/', $barrierFileDoors), 0644, true);
        }
        $f = fopen($barrierFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create barrier file at ', [$barrierFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->barrier($barrierFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Barrier saved at ' . $barrierFilePath . '!', [], 'success');
    }

    public function createDBGramFile()
    {
        return (new Adjustor())->create($this->attribute, $this->extraAttrs);
    }

    public function createDBFillerFile()
    {
        return (new Refill())->create($this->attribute, $this->extraAttrs);
    }

    public function createMailerFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Mailer] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $mailerFilePath = 'segments/Mail/' . $this->getDecamelizedPath() . '.php';
        if (file_exists($mailerFilePath)) {
            return $this->throwError('[Mailer] FILE ALREADY EXISTS at %s' . PHP_EOL, [$mailerFilePath]);
        }
        $mailerFileDoors = explode('/', $mailerFilePath);
        $mailerFileNameParts = explode('.php', basename($mailerFileDoors[count($mailerFileDoors) - 1]));
        $nameSpace = 'Mail';
        unset($mailerFileDoors[count($mailerFileDoors) - 1]);
        foreach ($mailerFileDoors as $doorName => $door) {
            $mailerFileDoors[$doorName] = Str::decamelize($door);
            if (!in_array($door, ['segments', 'Mail'])) {
                $nameSpace .= '\\' . $door;
            }
        }
        if (!file_exists(implode('/', $mailerFileDoors))) {
            mkdir(implode('/', $mailerFileDoors), 0644, true);
        }
        $f = fopen($mailerFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create mailer file at ', [$mailerFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->mailer($mailerFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Mailer saved at ' . $mailerFilePath . '!', [], 'success');
    }

    public function createTexterFile()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Texter] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $texterFilePath = 'segments/SMS/' . $this->getDecamelizedPath() . '.php';
        if (file_exists($texterFilePath)) {
            return $this->throwError('[Texter] FILE ALREADY EXISTS at %s' . PHP_EOL, [$texterFilePath]);
        }
        $texterFileDoors = explode('/', $texterFilePath);
        $texterFileNameParts = explode('.php', basename($texterFileDoors[count($texterFileDoors) - 1]));
        $nameSpace = 'SMS';
        unset($texterFileDoors[count($texterFileDoors) - 1]);
        foreach ($texterFileDoors as $doorName => $door) {
            $texterFileDoors[$doorName] = Str::decamelize($door);
            if (!in_array($door, ['segments', 'SMS'])) {
                $nameSpace .= '\\' . $door;
            }
        }
        if (!file_exists(implode('/', $texterFileDoors))) {
            mkdir(implode('/', $texterFileDoors), 0644, true);
        }
        $f = fopen($texterFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create texter file at ', [$texterFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->texter($texterFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Texter saved at ' . $texterFilePath . '!', [], 'success');
    }

    public function createBGAction()
    {
        if (empty($this->attribute)) {
            return $this->throwError('EMPTY [Background Action] FILE CAN NOT BE CREATED' . PHP_EOL);
        }

        $bgActionFilePath = 'segments/BackgroundActions/' . $this->getDecamelizedPath() . '.php';
        if (file_exists($bgActionFilePath)) {
            return $this->throwError('[Background Action] FILE ALREADY EXISTS at %s' . PHP_EOL, [$bgActionFilePath]);
        }
        $bgActionFileDoors = explode('/', $bgActionFilePath);
        $bgActionFileNameParts = explode('.php', basename($bgActionFileDoors[count($bgActionFileDoors) - 1]));
        $nameSpace = '';
        unset($bgActionFileDoors[count($bgActionFileDoors) - 1]);
        foreach ($bgActionFileDoors as $doorName => $door) {
            $bgActionFileDoors[$doorName] = Str::decamelize($door);
            if (!in_array($door, ['segments'])) {
                if (!empty($nameSpace))
                    $nameSpace .= '\\';
                $nameSpace .= $door;
            }
        }
        if (!file_exists(implode('/', $bgActionFileDoors))) {
            mkdir(implode('/', $bgActionFileDoors), 0644, true);
        }
        $f = fopen($bgActionFilePath, 'wb');
        if (!$f) {
            return $this->throwError('%s can not create texter file at ', [$bgActionFilePath]);
        }
        fwrite($f, $this->baseCodeTemplate->backgroundAction($bgActionFileNameParts[0], $nameSpace));
        fclose($f);
        return $this->showMsg('Background Action file saved at ' . $bgActionFilePath . '!', [], 'success');
    }

    public function setConfigSettings()
    {
        if (file_exists($this->settingDir)) {
            $this->showMsgAndContinue($this->settingDir . ' directory already exists. Do you want to remove it and set a fresh config files?' . PHP_EOL);

            if ($this->confirm('Enter Y for [Yes] or N for [No]: ')) {
                $this->showMsgAndContinue('Setting up setting files in %s' . PHP_EOL, [$this->settingDir], 'info');
            } else {
                return $this->showMsg('config setup process stopped' . PHP_EOL, [], 'error');
            }
        }

        if (!file_exists($this->settingDir)) {
            $this->showMsgAndContinue('Creating %s [SETTING DIRECTORY]' . PHP_EOL, [$this->settingDir], 'warning');
            mkdir($this->settingDir, 655, true);
            $this->showMsgAndContinue('%s [SETTING DIRECTORY] created!' . PHP_EOL, [$this->settingDir], 'success');
        }

        return $this->createFreshSettingFiles();
    }

    public function createFreshSettingFiles()
    {
        $this->createSettingAppFile();
        $this->createSettingAliasFile();
        $this->createSettingDatabaseFile();
        $this->createSettingSessionFile();
        $this->createSettingAlertFile();
        $this->createSettingTemplateFile();

        return true;
    }

    public function createSettingAppFile()
    {
        $settingAppFile = $this->settingDir . '/app.php';

        $settingContent = $this->baseCodeTemplate->setting('app');

        $f = fopen($settingAppFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingAppFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingAppFile], 'success');
    }

    public function createSettingAliasFile()
    {
        $settingAliasFile = $this->settingDir . '/aliases.php';

        $settingContent = $this->baseCodeTemplate->setting('alias');

        $f = fopen($settingAliasFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingAliasFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingAliasFile], 'success');
    }

    public function createSettingDatabaseFile()
    {
        $settingDatabaseFile = $this->settingDir . '/database.php';

        $settingContent = $this->baseCodeTemplate->setting('database');

        $f = fopen($settingDatabaseFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingDatabaseFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingDatabaseFile], 'success');
    }

    public function createSettingSessionFile()
    {
        $settingSessionFile = $this->settingDir . '/session.php';

        $settingContent = $this->baseCodeTemplate->setting('session');

        $f = fopen($settingSessionFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingSessionFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingSessionFile], 'success');
    }

    public function createSettingAlertFile()
    {
        $settingAliasFile = $this->settingDir . '/alert.php';

        $settingContent = $this->baseCodeTemplate->setting('alert');

        $f = fopen($settingAliasFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingAliasFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingAliasFile], 'success');
    }

    public function createSettingTemplateFile()
    {
        $settingAliasFile = $this->settingDir . '/templates.php';

        $settingContent = $this->baseCodeTemplate->setting('template');

        $f = fopen($settingAliasFile, 'wb');
        if (!$f) {
            return $this->throwError('Setting file can not be created at %s' . PHP_EOL, [$settingAliasFile]);
        }

        fwrite($f, $settingContent);
        fclose($f);

        $this->showMsgAndContinue('%s [SETTING FILE] created!' . PHP_EOL, [$settingAliasFile], 'success');
    }

    public function removeModelFile()
    {
        $modelFilePath = 'segments/Models/' . $this->attribute . '.php';
        return $this->removeAsset($modelFilePath, 'Model');
    }

    public function removeControllerFile()
    {
        $controllerFilePath = 'segments/Controllers/' . $this->attribute . 'Controller.php';
        return $this->removeAsset($controllerFilePath, 'Controller');
    }

    public function removeViewFile()
    {
        $viewFilePath = 'segments/Views/' . $this->attribute . '.jly.php';
        return $this->removeAsset($viewFilePath, 'View');
    }

    public function removeBarrierFile()
    {
        $barrierFilePath = 'segments/Barriers/' . $this->attribute . '.php';
        return $this->removeAsset($barrierFilePath, 'Barrier');
    }

    public function removeDBGramFile()
    {
        if (empty(trim($this->attribute)) && $this->attribute != '--all') {
            return $this->throwError('dbgram file path must be given or apply remove:dbgram --all to remove all dbgram files');
        }
        return (new Adjustor())->removeAllFiles($this->attribute);
    }

    public function removeDBFillerFile()
    {
        if (empty(trim($this->attribute)) && $this->attribute != '--all') {
            return $this->throwError('dbfiller file path must be given or apply remove:dbfiller --all to remove all dbfiller files');
        }
        return (new Refill())->removeAllFiles($this->attribute);
    }

    public function removeMailerFile()
    {
        $mailerFilePath = 'segments/Mail/' . $this->attribute . '.php';
        return $this->removeAsset($mailerFilePath, 'Mailer');
    }

    public function removeTexterFile()
    {
        $texterFilePath = 'segments/SMS/' . $this->attribute . '.php';
        return $this->removeAsset($texterFilePath, 'Texter');
    }

    public function removeAsset($assetPath, $assetType = '')
    {
        if (!file_exists($assetPath))
            return $this->throwError('[' . $assetType . ' File] %s does not exists', [$assetPath]);
        if (!unlink($assetPath))
            return $this->throwError('Error while removing [' . $assetType . ' File] %s', [$assetPath]);

        return $this->showMsg('[' . $assetType . ' File] %s removed successfully!', [$assetPath], 'warning');
    }

    public function startApp()
    {
        if (!file_exists('locker/system')) {
            mkdir('locker/system/', 655);
        }

        if (file_exists($this->appStopperFile)) {
            if (!unlink($this->appStopperFile)) {
                return $this->throwError('Processing Issue: App can not be started. Kindly remove %s file to start the app manually.', [$this->appStopperFile]);
            } else {
                $this->writeMessageToAppStopper();
                return $this->showMsg('App successfully started!', [], 'success');
            }
        } else {
            $this->writeMessageToAppStopper();
            return $this->showMsg('App is already in running mode!', [], 'info');
        }
    }

    public function stopApp()
    {
        if (!file_exists('locker/system')) {
            mkdir('locker/system/', 655);
        }

        $this->writeMessageToAppStopper();
        if (!file_exists($this->appStopperFile)) {
            if (!touch($this->appStopperFile)) {
                return $this->throwError('Processing Issue: App can not be stopped. Kindly add %s file to stop the app manually.', [$this->appStopperFile]);
            } else {
                $this->setAppStopperMsg();
                return $this->showMsg('App successfully stopped!', [], 'warning');
            }
        } else {
            $this->setAppStopperMsg();
            return $this->showMsg('App is already in stop mode!', [], 'warning');
        }
    }

    public function setAppStopperMsg()
    {
        if (!empty($this->attribute) && gettype($this->attribute) == 'string' && Str::startsWith($this->attribute, '--message')) {
            $messageParts = explode('=', $this->attribute);
            if (count($messageParts) > 1 && $messageParts[0] == '--message' && !empty($messageParts[1])) {
                $this->writeMessageToAppStopper($messageParts[1]);
            }
        }
    }

    public function writeMessageToAppStopper(string $msg = '')
    {
        if (!file_exists($this->appStopperFile))
            return false;

        $f = fopen($this->appStopperFile, 'wb');
        if (!$f) {
            return $this->throwError('{%s} can not be set as message as app stopper ', [$msg]);
        }
        fwrite($f, $msg);
        fclose($f);
    }

    public function clearDir(string $dir)
    {
        if (file_exists($dir) && is_dir($dir)) {
            foreach (glob($dir . '*') as $file) {
                unlink($file);
            }
            return $this->showMsg('{%s} [DIR] is clear now' . PHP_EOL, [$dir], 'success');
        }
        return $this->throwError('{%s} [DIR] does not exists ' . PHP_EOL, [$dir]);
    }

    public function listRoutes()
    {
        $routes = Router::list();
        if (count($routes) === 0) {
            return $this->throwError('No routes defined' . PHP_EOL);
        }

        echo '----------------------------------------------------------------------------------------------------' . PHP_EOL;
        echo count($routes) . ' routes registered' . PHP_EOL;
        echo '----------------------------------------------------------------------------------------------------' . PHP_EOL;
        $mask = "%6s | %-30.30s | %-10s | %s\n";

        foreach ($routes as $routeMethod => $routeInfo) {
            foreach ($routeInfo as $route) {
                if (empty($route['caption'])) $route['caption'] = '/';
                $namedAs = (!empty($route['namedAs']) && !$route['nameFromParent']) ? $route['namedAs'] : 'N/A';
                $barriersCount = (!empty($route['barriers'])) ? count($route['barriers']) . ' barrier(s)' : 'No barrier';
                printf($mask, strtoupper($routeMethod), $namedAs, $barriersCount, $route['caption']);
            }
        }

        return true;
    }

    public function listDBGrams()
    {
        $dbGrams = (new Adjustor())->getDBGrams($this->attribute, $this->extraAttrs);

        if (count($dbGrams) === 0) {
            return $this->throwError('No dbgram(s) found' . PHP_EOL);
        }

        echo '====================================================================================================' . PHP_EOL;
        echo count($dbGrams) . ' dbgram(s) found (Columns order is Is_Adjusted, Stack (If Adjusted), Name)' . PHP_EOL;
        echo '----------------------------------------------------------------------------------------------------' . PHP_EOL;

        foreach ($dbGrams as $dbGram) {
            $routeLine = ($dbGram['is_adjusted']) ? 'Adjusted' : 'Not Adjusted';
            $routeLine .= "\t";
            $routeLine .= (!empty($dbGram['stack'])) ? 'Stack #' . $dbGram['stack'] : 'N/A';
            $routeLine .= "\t";
            $routeLine .= $dbGram['name'];
            $routeLine .= PHP_EOL;
            echo $routeLine;
        }

        return true;
    }

    public function execDBGrams()
    {
        return (new Adjustor())->proceedDBGramAdjustment($this->attribute, $this->extraAttrs);
    }

    public function execDBFillers()
    {
        return (new Refill())->proceedDBFillers($this->attribute);
    }

    public function execBGActions()
    {
        return (new BackgroundAction())->proceedBGActions($this->attribute);
    }

    public function listenBGActions()
    {
        return (new BackgroundAction())->listenBGActions($this->attribute);
    }

    public function getDecamelizedPath()
    {
        $relativePathParts = explode('/', $this->attribute);
        foreach ($relativePathParts as &$part) {
            $part = Str::decamelize($part);
        }

        return implode('/', $relativePathParts);
    }

    public function confirm($message)
    {
        $confirm = readline($message);

        return (strtoupper($confirm) == 'Y' || ucfirst(strtolower($confirm)) == 'Yes');
    }

    public function throwError(string $error_message, array $args = [])
    {
        $error_message = $this->agent() . $this->printedMsg($error_message, 'error');
        $error_parts = array_merge([$error_message], $args);
        echo call_user_func_array('sprintf', $error_parts);
        exit;
    }

    public function showMsg(string $message, array $args = [], $type = '')
    {
        $message = $this->agent(). $this->printedMsg($message, $type);
        $error_parts = array_merge([$message], $args);
        echo call_user_func_array('sprintf', $error_parts);
        return true;
    }

    public function showMsgAndExit(string $message, array $args = [], $type = '')
    {
        $this->showMsgAndContinue($message, $args, $type);
        exit;
    }

    public function showMsgAndContinue(string $message, array $args = [], $type = '')
    {
        $message = $this->agent() . $this->printedMsg($message, $type);
        $error_parts = array_merge([$message], $args);
        echo call_user_func_array('sprintf', $error_parts);
    }

    public function agent()
    {
        return $this->printedMsg($this->agent, 'important');
    }

    public function printedMsg($str, $type = '')
    {
        switch ($type) {
            case 'warning' :
                $color_code = 93;
                break;
            case 'info' :
                $color_code = 95;
                break;
            case 'error' :
                $color_code = 91;
                break;
            case 'success' :
                $color_code = 92;
                break;
            case 'important' :
                $color_code = 96;
                break;
            default:
                $color_code = 0;
                break;
        }

        return "\033[" . $color_code . "m" . $str . "\033[0m";
    }
}