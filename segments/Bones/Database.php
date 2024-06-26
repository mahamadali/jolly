<?php

namespace Bones;

use Bones\Skeletons\Database\Builder;
use Bones\Skeletons\Database\Config;
use Bones\Skeletons\Database\Raw;

class Database extends Builder
{
    protected static $CONFIG_LIST;
    protected static $USE_DATABASE = null;
    protected static $CHANGE_ONCE = false;
    protected static $KEEP_LOG = false;

    public function __construct()
    {
        $this->prepareConnections();
    }

    public static function addConnection($config_name, array $config_params)
    {
        self::$CONFIG_LIST[$config_name] = new Config($config_params);
    }

    public static function getCurrentDatabase()
    {
        return self::$CONFIG_LIST[self::$USE_DATABASE];
    }

    private static function getCurrentConfig()
    {
        if (empty(self::$USE_DATABASE))
            self::$USE_DATABASE = self::primaryDB();

        if (!isset(self::$CONFIG_LIST) || !isset(self::$CONFIG_LIST[self::$USE_DATABASE]))
            throw new DatabaseException('Connection "' . self::$USE_DATABASE . "' not found");

        return self::$CONFIG_LIST[self::$USE_DATABASE];
    }

    public static function table($name)
    {
        $builder = (new static)->builder();
        $builder->setTable($name);
        return $builder;
    }

    public static function use(string $config_name)
    {
        self::$USE_DATABASE = $config_name;

        return new static;
    }

    public static function useOnce(string $config_name)
    {
        self::$USE_DATABASE = $config_name;
        self::$CHANGE_ONCE = true;
        return new static;
    }

    public static function beginTransaction()
    {
        Database::getCurrentConfig()->connect();
        Database::getCurrentConfig()->pdo()->beginTransaction();
    }

    public static function rollBack()
    {
        Database::getCurrentConfig()->pdo()->rollBack();
    }

    public static function commit()
    {
        Database::getCurrentConfig()->pdo()->commit();
    }

    public static function setTimestamp()
    {
        $now = date('Y-m-d H:i:s');

        return [
            'created_at' => $now,
            'updated_at' => $now
        ];
    }

    protected function builder()
    {
        $config = self::getCurrentConfig();
        $builder = new Builder($config);
        return $builder;
    }

    public static function raw($query, array $values = [])
    {
        $raw = new Raw;
        $raw->setRawData($query, $values);
        return $raw;
    }

    public static function rawQuery($query)
    {
        $builder = (new static)->builder();
        return $builder->execute($query, [], true);
    }

    public static function primaryDB()
    {
        $settings = setting('database');
        $primaryDB = [];
        $primaryDBFound = false;
        foreach ($settings as $database => $setting) {
            if (is_array($setting)) {
                if (key_exists('host', $setting) && key_exists('username', $setting) && key_exists('password', $setting) && key_exists('is_primary', $setting)) {
                    if ($setting['is_primary'] == true) {
                        if ($primaryDBFound) {
                            throw new MultiplePrimaryDBFound('Mutiple primary DB found in settings/database.php');
                        }
                        $primaryDB = $database;
                        $primaryDBFound = true;
                    } else if (!$primaryDBFound) {
                        $primaryDB = $database;
                    }
                }
            }
        }

        return $primaryDB;
    }

    public function prepareConnections()
    {
        $database_connections = setting('database');

        foreach ($database_connections as $database_name => $database_connection) {
            if (is_array($database_connection)) {
                $this->addConnection($database_name, [
                    'host' => $database_connection['host'],
                    'port' => $database_connection['port'],
                    'database' => $database_name,
                    'username' => $database_connection['username'],
                    'password' => $database_connection['password'],
                    'charset' => (!empty($database_connection['charset'])) ? $database_connection['charset'] : Config::UTF8,
                    'collation' => (!empty($database_connection['collation'])) ? $database_connection['collation'] : Config::UTF8_GENERAL_CI,
                    'fetch' => Config::FETCH_CLASS
                ]);
            }
        }
    }

    public static function totalConnections()
    {
        $database_connections = setting('database');

        $total_connections = 1;

        foreach ($database_connections as $database_connection) {
            if (is_array($database_connection))
                $total_connections += 1;
        }

        return $total_connections;
    }

    public static function hasMultipleConnections()
    {
        return self::totalConnections() > 1;
    }

    public static function setLastExecutedQuery($query)
    {
        Session::set('last_executed_query', $query, true);
    }

    public static function getLastExecutedQuery()
    {
        return Session::get('last_executed_query', true);
    }

    public static function keepQueryLog($keep_query_log = true)
    {
        Session::remove('db_query_log', true);
        Session::set('keep_db_query_log', $keep_query_log, true);
    }

    public static function setQueryLog($query)
    {
        if (Session::has('keep_db_query_log', true) && Session::get('keep_db_query_log', true)) {
            Session::appendSet('db_query_log', $query, true);
        }
    }

    public static function getQueryLog()
    {
        $query_log = Session::get('db_query_log', true);
        Session::remove('db_query_log', true);
        Session::remove('keep_db_query_log', true);

        return $query_log;
    }

}