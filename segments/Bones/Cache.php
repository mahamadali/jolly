<?php

namespace Bones;

class Cache
{
    protected static $cache_dir = null;
    protected static $memory_cache = [];

    // Get the cache directory, defaulting to 'cache/' if not configured
    protected static function getCacheDirectory()
    {
        if (self::$cache_dir === null) {
            // Fetch cache directory from settings or use default
            self::$cache_dir = locker_path('cache');
            self::$cache_dir = rtrim(self::$cache_dir, '/') . '/';
        }

        return self::$cache_dir;
    }

    // Get from cache (memory or file)
    public static function get($key, $default = null)
    {
        // Check memory cache first
        if (isset(self::$memory_cache[$key])) {
            return self::$memory_cache[$key];
        }

        self::ensureCacheDirectoryExists();

        // Check file cache
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            $data = file_get_contents($file);
            return unserialize($data); // Unserialize the stored data
        }

        return $default;
    }

    // Set data into cache (memory and file)
    public static function set($key, $value, $ttl = null)
    {
        $ttl = (!is_null($ttl)) ? $ttl : cacheSetting('ttl', 3600);

        // Store in memory cache
        self::$memory_cache[$key] = $value;

        self::ensureCacheDirectoryExists();

        // Write to file cache
        $file = self::getFilePath($key);
        file_put_contents($file, serialize($value)); // Serialize to store in file
        touch($file, time() + $ttl); // Set expiration time (ttl)
    }

    // Delete a cache key
    public static function delete($key)
    {
        unset(self::$memory_cache[$key]); // Remove from memory cache

        self::ensureCacheDirectoryExists();

        $file = self::getFilePath($key);
        if (file_exists($file)) {
            unlink($file); // Delete the cache file
        }
    }

    // Clear all cache (memory and file-based)
    public static function clear()
    {
        self::$memory_cache = []; // Clear memory cache

        self::ensureCacheDirectoryExists();

        // Remove all cache files
        array_map('unlink', glob(self::getCacheDirectory() . '*.cache'));
    }

    // Helper to generate file path for caching
    protected static function getFilePath($key)
    {
        self::ensureCacheDirectoryExists();

        return self::getCacheDirectory() . md5($key) . '.cache'; // Hash the key for unique file names
    }

    // Ensure the cache directory exists
    protected static function ensureCacheDirectoryExists()
    {
        $cacheDir = self::getCacheDirectory();

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    // Ensure the cache directory exists
    public static function isEnabled()
    {
        return cacheSetting('enabled', false);
    }
}