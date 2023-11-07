<?php

use Bones\Router;
use Bones\URL;
use Jolly\Engine;

class AppStarter
{

    protected static $segmentDirPath = 'segments/';

    public static function boost()
    {
        $start_time = microtime(true);

        // Load defaults on app start
        self::loadDefaults($start_time);

        // Fetch current uri and adjust route
        $route = URL::adjustRoute($_SERVER['REQUEST_URI']);

        // Dispatch route to proceed
        Router::dispatch($route);
    }

    public static function loadDefaults($start_time)
    {
        // Register base classes for autoload
        self::autoloader();

        // Include needed components throughout an app
        self::bagNeededComponents();

        // Start an app session
        \Bones\Session::start();

        // Save execution start time
        session()->set('execution_start_time', $start_time);

        // Set default platform language to english if not set
        if (!session()->hasLanguage() && !empty($defaultLang = setting('app.default_lang'))) {
            session()->setLanguage($defaultLang);
        }
    }

    public static function loadBones(array $bones = [])
    {
        if (!empty($bones)) {
            foreach ($bones as $bone) {
                $boneFile = __DIR__ . '/../Bones/' . $bone . '.php';
                if (file_exists($boneFile)) {
                    require_once($boneFile);
                } else {
                    Engine::error([
                        'error' => 'File Bones/' . $bone . ' could not be found'
                    ]);
                }
            }
        } else {
            foreach (glob('Bones/*') as $bone) {
                require_once($bone);
            }
        }
    }

    public static function bagNeededComponents()
    {
        // Include compiler engine
        require_once('compiler/Engine.php');

        // Include Helper file(s)
        foreach (glob('segments/Bones/Helpers/*.php') as $helper) {
            include_once($helper);
        }

        // Register aliases
        $aliases = setting('aliases');
        foreach ($aliases as $alias => $for) {
            if (!in_array($alias, ['Barriers']))
                class_alias($for, $alias);
        }

        // Include route file(s)
        foreach (glob('segments/Routes/*.php') as $route) {
            require_once($route);
        }

        // Include error handling helpers
        require_once('segments/Bones/Exception.php');
        require_once('compiler/ErrorHandler.php');
    }

    public static function autoloader()
    {
        spl_autoload_register(function ($class) {

            $parts = explode('\\', $class);
            $class = implode('/', $parts);
            $isClassFound = FALSE;

            self::$segmentDirPath = 'segments/';

            $dirsToAutoLoad = [
                'Routes',
                'Bones',
                'Models'
            ];

            // Auto find technique based on psr-4 naming conventions on directory
            if (!empty($source = self::__autoLoadDynamicDirs($class))) {
                require_once $source;
                $isClassFound = true;
            }

            // Autoload default directories with recursive pattern to load a class file
            if (!$isClassFound) {
                foreach ($dirsToAutoLoad as $dir) {
                    $iterator = new RecursiveDirectoryIterator(self::$segmentDirPath . $dir . '/');
                    foreach (new RecursiveIteratorIterator($iterator) as $source) {
                        if (is_file($source)) {
                            if ($class == str_replace('.php', '', basename($source))) {
                                require_once $source;
                            }
                        }
                    }
                }
            }
            
        });
    }

    public static function __autoLoadDynamicDirs(string $class)
    {
        if (file_exists($class = self::$segmentDirPath . $class . '.php')) {
            return $class;
        }

        return null;
    }
}