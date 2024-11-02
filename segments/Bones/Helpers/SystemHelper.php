<?php

use Bones\Cache;
use Bones\Language;
use Bones\Str;
use Bones\Router;
use Bones\Request;
use Bones\Session;
use Jolly\Engine;
use Bones\Redirect;
use Bones\Response;
use Bones\RouteException;
use Bones\CompileException;
use Bones\VariableFileNotFound;
use Bones\VariableFileKeyNotFound;

if (!function_exists('findFileVariableByKey')) {
    function findFileVariableByKey($file, $param, $default = '')
    {
        $cache_key = $file . '.' . $param;

        $cached = Cache::isEnabled();

        if ($cached) {
            $value = Cache::get($cache_key);
            if ($value !== null) {
                return $value;  // Return the cached value
            }
        }

        $paramChain = explode('.', $param);
        if (!empty($paramChain[0]) && !empty($paramChain[1])) {
            $variableFile = $file . '/' . $paramChain[0] . '.php';
            if (file_exists($variableFile)) {
                $variables = require($variableFile);
                if (!empty($variables)) {
                    $variables = Str::array_change_key_case_recursive($variables, CASE_LOWER);
                    $cursorAt = [];
                    foreach ($paramChain as $cKey => $key) {
                        $key = strtolower(strtoupper($key));
                        if ($cKey == 0)
                            continue;
                        if ($cKey == (count($paramChain) - 1)) {
                            if (!empty($cursorAt) && !empty($cursorAt[$key])) {
                                ($cached) ? Cache::set($cache_key, $cursorAt[$key]) : null;
                                return $cursorAt[$key];
                            } else if (!empty($variables[$key])) {
                                ($cached) ? Cache::set($cache_key, $variables[$key]) : null;
                                return $variables[$key];
                            } else {
                                ($cached) ? Cache::set($cache_key, $default) : null;
                                return $default;
                            }
                        } else {
                            if (empty($cursorAt)) {
                                if (empty($variables[$key]))
                                    if (!empty($default)) {
                                        ($cached) ? Cache::set($cache_key, $default) : null;
                                        return $default;
                                    } else {
                                        throw new VariableFileKeyNotFound('`' . $key . '` key could not find in '. $file . '/' . $paramChain[0] . '.php');
                                    }
                                $cursorAt = $variables[$key];
                            } else {
                                $cursorAt = $cursorAt[$key];
                            }
                        }
                    }
                } else {
                    ($cached) ? Cache::set($cache_key, $default) : null;
                    return $default;
                }
            } else {
                if (!empty($default)) {
                    ($cached) ? Cache::set($cache_key, $default) : null;
                    return $default;
                }
                throw new VariableFileNotFound($file . '/' . $paramChain[0] . '.php file not found');
            }
        } else {
            if (!empty($paramChain[0])) {
                $variableFile = $file . '/' . $paramChain[0] . '.php';
                if (file_exists($variableFile)) {
                    $variables = require($variableFile);
                    if (!empty($variables)) {
                        ($cached) ? Cache::set($cache_key, $variables) : null;
                        return $variables;
                    } else {
                        ($cached) ? Cache::set($cache_key, $default) : null;
                        return $default;
                    }
                } else {
                    if (!empty($default)) {
                        ($cached) ? Cache::set($cache_key, $default) : null;
                        return $default;
                    }
                    throw new VariableFileNotFound($file . '/' . $paramChain[0] . '.php file not found');
                }
            } else {
                ($cached) ? Cache::set($cache_key, $default) : null;
                return $default;
            }
        }
    }
}

if (!function_exists('setting')) {
    function setting($param, $default = '')
    {
        return findFileVariableByKey('settings', $param, $default);
    }
}

if (!function_exists('cacheSetting')) {
    function cacheSetting($variable, $default = '')
    {   
        $cache_file = 'settings/cache.php';

        if (!file_exists($cache_file))
            return $default;

        $variables = require($cache_file);

        if (!is_array($variables) || empty($variables))
            return $default;

        return (isset($variables[$variable])) ? $variables[$variable] : $default;
    }
}

if (!function_exists('trans')) {
    function trans($word = '', $data = [])
    {
        if (Str::empty($word)) {
            return $word;
        }

        return Language::trans($word, $data);
    }
}

if (!function_exists('render')) {
    function render($jlySource = '', array $with = [], $stopExecution = false)
    {
        if (empty(trim($jlySource))) throw new CompileException('Empty source can not be generated');
        return Engine::render($jlySource, $with, false, $stopExecution);
    }
}

if (!function_exists('content')) {
    function content($jlySource = '', array $with = [], $stopExecution = false)
    {
        if (empty(trim($jlySource))) throw new CompileException('Empty source can not be generated');
        return Engine::render($jlySource, $with, true, $stopExecution);
    }
}

if (!function_exists('formData')) {
    function formData($param)
    {
        extract($_REQUEST);
        if (!empty($_REQUEST) && !empty($param) && !empty($_REQUEST[$param])) {
            return $_REQUEST[$param];
        }
        return '';
    }
}

if (!function_exists('prevent_csrf_token')) {
    function prevent_csrf_token()
    {
        $prevent_csrf_token = md5(uniqid(mt_rand(), true));
        session()->appendSet('prevent_csrf_token', $prevent_csrf_token, true);
        return $prevent_csrf_token;
    }
}

if (!function_exists('prevent_csrf_field')) {
    function prevent_csrf_field()
    {
        echo '<input type="hidden" name="prevent_csrf_token" value="'.prevent_csrf_token().'" />' . PHP_EOL;
    }
}

if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null)
    {
        $array = array();
        foreach ($input as $value) {
            if (!array_key_exists($columnKey, $value)) {
                trigger_error("Key \"$columnKey\" does not exist in array");
                return false;
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            } else {
                if (!array_key_exists($indexKey, $value)) {
                    trigger_error("Key \"$indexKey\" does not exist in array");
                    return false;
                }
                if (!is_scalar($value[$indexKey])) {
                    trigger_error("Key \"$indexKey\" does not contain scalar value");
                    return false;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}

if (!function_exists('redirectPageTo')) {
    function redirectPageTo($url)
    {
        header('location: ' . $url);
        exit;
    }
}

if (!function_exists('op')) {
    function op(...$args) {
        echo '<style>
            .op-container { background-color: #f9f9f9; padding: 15px; font-size: 14px; font-family: Arial, sans-serif; color: #333; }
            .op-container pre { background-color: #f3f3f3; padding: 10px; border: 1px solid #ddd; overflow-x: auto; white-space: pre; word-wrap: break-word; }
            .op-key { color: #007acc; font-weight: bold; }
            .op-value-string { color: #d14; }
            .op-value-number { color: #098658; }
            .op-value-null { color: #999; font-style: italic; }
            .op-arrow { color: #666; margin: 0 5px; }
        </style>';

        echo '<div class="op-container">';
        foreach ($args as $arg) {
            echo '<pre>';
            ob_start();
            print_r($arg);
            $output = ob_get_clean();

            // Add styling to output
            $output = preg_replace('/\[([^\]]+)\] => /', '<span class="op-key">$1</span><span class="op-arrow"> => </span>', $output);
            $output = preg_replace('/=>\s*([0-9]+)\b/', '=> <span class="op-value-number">$1</span>', $output);
            $output = preg_replace('/=>\s*NULL\b/', '=> <span class="op-value-null">NULL</span>', $output);
            $output = preg_replace('/=>\s*\'([^\']*)\'/', '=> <span class="op-value-string">\'$1\'</span>', $output);

            echo $output;
            echo '</pre>';
        }
        echo '</div>';
    }
}

if (!function_exists('opd')) {
    function opd(...$args) {
        op(...$args);
        die();
    }
}

if (!function_exists('url')) {
    function url($url)
    {
        if (empty($url) || gettype($url) != 'string') 
            return $url;
        
        return Router::url(trim($url, '/'));
    }
}

if (!function_exists('error')) {
    function error($error_code, $data = '')
    {
        if (!empty($data) && gettype($data) == 'string') {
            $message = $data;
            $data = [];
            $data['message'] = $message;
        } else if (empty($data)) {
            $data = [];
        }

        if (empty($data['error'])) {
            $data['error'] = (!empty($data['message']) && !empty(trim($data['message']))) ? $data['message'] : trans('errors.page.' . $error_code);
        }
        
        render(setting('templates.'.$error_code, 'defaults/error'), $data);
        exit;
    }
}

if (!function_exists('template')) {
    function template($template, $data = [])
    {
        render(setting('templates.'.$template, 'defaults/' . $template), $data, true);
        exit;
    }
}

if (!function_exists('toStdClass')) {
    function toStdClass($array)
    {
        if (gettype($array) != 'array') return $array;
        return json_decode(json_encode($array));
    }
}

if (!function_exists('resolveAsArray'))
{
    function resolveAsArray(...$attrs)
    {
        $attrSet = [];
        
        $arguments = func_get_args();

        if (!empty($arguments)) {
            if (!empty($arguments[0]) && !empty($arguments[0][0])) {
                if (is_array($arguments[0][0])) {
                    return $arguments[0][0];
                } else {
                    if (is_array($arguments[0])) {
                        foreach ($arguments[0] as $arg) {
                            $attrSet[] = (is_string($arg)) ? trim($arg) : $arg;
                        }
                    } else {
                        return [$arguments[0]];
                    }
                }
            }
        }

        return $attrSet;
    }
}

if (!function_exists('objectToArray')) 
{
    function objectToArray($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = [];
            foreach ($data as $key => $value)
            {
                $result[$key] = (is_array($value) || is_object($value)) ? objectToArray($value) : $value;
            }
            return $result;
        }
        return $data;
    }
}

if (!function_exists('route')) {
    function route(string $route, array $params = [])
    {
        if (empty($route))
            throw new RouteException('Route with empty name could not be found');
        return Router::prepare($route, $params);
    }
}

if (!function_exists('request')) {
    function request()
    {
        return new Request($_REQUEST, $_FILES);
    }
}

if (!function_exists('response')) {
    function response()
    {
        return new Response();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to = '', int $status = 302, array $headers = [])
    {
        return new Redirect($to, $status, $headers);
    }
}

if (!function_exists('session')) {
    function session()
    {
        return new Session();
    }
}

if (!function_exists('asset')) {
    function asset(string $asset_path = '')
    {
        return url($asset_path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $public_path = '')
    {
        $public_drive = str_replace('/', DIRECTORY_SEPARATOR, rtrim(setting('filesystem.drives.public', ''), '/'));
        return pathWith(ltrim($public_drive, DIRECTORY_SEPARATOR), ltrim($public_path, '/'));
    }
}

if (!function_exists('path')) {
    function path()
    {
        return dirname(__FILE__, 4);
    }
}

if (!function_exists('pathWith')) {
    function pathWith(string $base = '', string $path = '')
    {
        if (!empty($base)) 
            $base .= DIRECTORY_SEPARATOR;

        return path() . DIRECTORY_SEPARATOR . $base . str_replace('/', '\\', $path);
    }
}

if (!function_exists('asset_path')) {
    function asset_path(string $path = '')
    {
        return pathWith('asset', $path);
    }
}

if (!function_exists('locker_path')) {
    function locker_path(string $path = '')
    {
        return pathWith('locker', $path);
    }
}