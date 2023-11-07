<?php

namespace Bones;

use Bones\Str;
use Exception;
use Bones\BadMethodException;

class Session
{
    protected static $prefix;
    protected static $reservedPrefix;
    protected static $languageKey = 'platform_language';

    /**
     * Constructor
     * 
     * @param string $cacheExpire
     * @param string $cacheLimiter
     */
    public function __construct(string $cacheExpire = null, string $cacheLimiter = null)
    {
        if (session_status() === PHP_SESSION_NONE) {

            ini_set('session.cookie_lifetime', setting('session.age', 14400));
            ini_set('session.gc_maxlifetime', setting('session.age', 14400));
            
            if ($cacheLimiter !== null) {
                session_cache_limiter($cacheLimiter);
            }

            if ($cacheExpire !== null) {
                session_cache_expire($cacheExpire);
            }

            self::$prefix = '___jly_session_attr_';
            self::$reservedPrefix = '___jly_rsrvd_kwrds_';

            session_start();
        }
    }

    /**
     * @param string $key
     * 
     * @return session attribute if exists else null
     */
    public static function get(string $key, bool $reserved = false)
    {
        if (self::has($key, $reserved)) {
            $value = ($reserved) ? $_SESSION[self::$reservedPrefix . $key] : $_SESSION[self::$prefix . $key];
            return $value;
        }

        return null;
    }

    /**
     * @param string $key
     * @param $value
     * @param bool optional $reserved 
     * @param bool optional $isPersistent - True if attribute need to set as one time usage like flash else false 
     * 
     * @return Session
     */
    public static function set(string $key, $value, bool $reserved = false)
    {
        if ($reserved) {
            $_SESSION[self::$reservedPrefix . $key] = $value;
        }
        else {
            $_SESSION[self::$prefix . $key] = $value;
        }
        return $_SESSION;
    }

    /**
     * Remove Session variable
     */
    public static function remove(string $key, bool $reserved = false): void
    {
        if (self::has($key, $reserved)) {
            if ($reserved) {
                unset($_SESSION[self::$reservedPrefix.$key]);
            } else {
                unset($_SESSION[self::$prefix.$key]);
            }
        }
    }

    /**
     * @param string $key
     * @param $value
     * @param bool optional $reserved 
     * 
     * @return Session
     */
    public static function appendSet(string $key, $value, bool $reserved = false)
    {
        $existingSet = (self::has($key, $reserved)) ? self::get($key, $reserved) : [];

        if (!is_array($existingSet)) {
            throw new Exception('Session: appendSet() can only be applied on array');
        }

        $existingSet[] = $value;
        return self::set($key, $existingSet, $reserved);
    }

    /**
     * Remove specific element from session array
     * 
     * @param string $key to remove element from
     * @param $element to remove
     * @param bool optional $reserved 
     * 
     * @return Session
     */
    public static function removeFromSet(string $key, $element, bool $reserved = false)
    {
        $existingSet = (self::has($key, $reserved)) ? self::get($key, $reserved) : [];
        
        if (!is_array($existingSet)) {
            throw new Exception('Session: removeSet() can only be applied on array');
        }

        $alteredSet = [];
        if (count($existingSet) == count($existingSet, COUNT_RECURSIVE)) {
            array_walk($existingSet, function($attribute) use ($element, &$alteredSet) {
                if ($attribute != $element) {
                    $alteredSet[] = $attribute;
                }
            });
        } else {
            foreach ($existingSet as $attribute => $set) {
                if ($attribute != $element) {
                    $alteredSet[$attribute] = $set;
                }
            }
        }
        
        return self::set($key, $alteredSet, $reserved);
    }

    /**
     * Set flash text for one time usage
     */
    public static function setFlash($type = 'general', string $text = '')
    {
        return self::set('flash_'.$type, $text);
    }

    /**
     * Get flash text for one time usage
     */
    public static function flash($type = 'general')
    {
        $flashKey = 'flash_'.Str::camelize($type);
        $flash = (self::has($flashKey)) ? self::get($flashKey) : null;
        self::remove('flash_'.Str::camelize($type));
        return $flash;
    }

    /**
     * Clear Session
     */
    public static function clear(): void
    {
        session_unset();
    }

    /**
     * Check Session has key
     * 
     * @param string $key
     * @param bool optional $reserved 
     * 
     * @return bool
     */
    public static function has(string $key, bool $reserved = false, bool $isFlash = false): bool
    {
        return array_key_exists((($reserved) ? self::$reservedPrefix . $key : self::$prefix . $key), $_SESSION);
    }

    public static function hasFlash(string $key): bool
    {
        return array_key_exists(self::$prefix . 'flash_' . $key, $_SESSION);
    }

    /**
     * Check session has platform laguage
     * 
     */
    public static function hasLanguage()
    {
        return self::has(self::$languageKey, true);
    }

    /**
     * Set platform laguage in session 
     * 
     * @param string $lang
     */
    public static function setLanguage($lang = 'en')
    {
        return self::set(self::$languageKey, $lang, true);
    }

    /**
     * Get platform laguage from session 
     * 
     * @param string $lang
     */
    public static function getLanguage()
    {
        return self::get(self::$languageKey, true);
    }

    /**
     * Start Session
     * 
     * @return Session
     */
    public static function start()
    {
        return new self;
    }

    /**
     * Dynamic method bind for reserved attributes
     * 
     */
    public static function __callStatic($method, $arguments)
    {
        return self::__mapDynamicMethods($method, $arguments);
    }

    public function __call($method, $arguments) {
       return self::__mapDynamicMethods($method, $arguments);
    }

    public static function __mapDynamicMethods($method, $arguments)
    {
        if (Str::contains($method, 'Reserved')) {
            $method = Str::remove($method, 'Reserved');
            if (method_exists(self::class, $method)) {
                array_push($arguments, (bool) true);
                return call_user_func_array([Session::class, $method], $arguments);
            }
        }
        if (Str::startsWith($method, 'flash')) {
            $methodParticles = preg_split('/flash/i', $method);
            $flashType = Str::camelize($methodParticles[1]);
            if (gettype($arguments) == 'string') {
                return self::setFlash($flashType, $arguments);
            } else {
                throw new BadMethodException($method . ': flash can only be set with text but '.ucfirst(gettype($arguments)) . ' found at ' . self::class);
            }
        }
        if (!empty($attribute = self::get($method))) {
            return $attribute;
        }
        throw new BadMethodException($method . ' method not found in ' . self::class);
    }

}