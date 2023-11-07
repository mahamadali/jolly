<?php

namespace Bones;

use DateTime;
use Bones\DateTimer;
use InvalidArgumentException;

class URL
{
    public static function adjustRoute(string $url)
    {
        $parsedURL = parse_url($url);
        $route = str_replace(setting('app.sub_dir', ''), '', (!empty($parsedURL['path']) ? $parsedURL['path'] : ''));
        if (!empty($parsedURL['query']))
            $route .= '?' . $parsedURL['query'];

        return $route;
    }

    public static function matchesTo($url = '/', $pattern = 'no-pattern')
    {
        if (!Str::startsWith($pattern, '/')) {
            $url = ltrim($url, '/');
        }

        $current_page_parts = explode('/', $url);
        $pattern_parts = explode('/', $pattern);

        foreach ($pattern_parts as $index => $pattern_part) {
            if ($pattern_part != '*' && (!isset($current_page_parts[$index]) || $pattern_part != $current_page_parts[$index]))
                return false;
        }

        return true;
    }

    public static function removeQuery($url)
    {
        if (strtok($url, '?'))
            return strtok($url, '?');

        return $url;
    }

    public function addQueryParam($url, $key, $value = '')
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $queryParams);
            $queryParams[$key] = $value;
            $url = str_replace("?$query", '?' . http_build_query($queryParams), $url);
        } else {
            $url .= '?' . urlencode($key) . '=' . urlencode($value);
        }

        return (string) url($url);
    }

    public static function addQueryParams($url, $params = [])
    {
        if (!empty($params) && (count($params) == count($params, COUNT_RECURSIVE))) {
            $query_string = http_build_query($params);
            return $url . '?' . $query_string;
        }

        return $url;
    }

    public static function addQueryParamToCurrentPage($key, $value = '')
    {
        return (new static)->addQueryParam(request()->currentPage(), $key, $value);
    }

    public static function getQueryParams($url)
    {
        $parts = parse_url($url);
        return $parts['query'];
    }

    public static function hasQueryParam($url, $param)
    {
        $parts = parse_url($url);

        if (empty($parts['query']))
            return false;

        parse_str($parts['query'], $query);

        return !empty($parts['query']) && isset($query[$param]);
    }

    public static function getQueryParam($url, $param)
    {
        if (!self::hasQueryParam($url, $param))
            return '';

        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        return (!empty($parts['query']) && isset($query[$param])) ? $query[$param] : '';
    }

    public static function createSignature($url)
    {
        return md5($url);
    }

    public static function withSignature($url, $expires_at = null)
    {
        if (self::hasQueryParam($url, 'signature'))
            throw new InvalidArgumentException('"signature" is a required<->reserved parameter for signing the url. Please update your route syntax.');

        if (self::hasQueryParam($url, 'expires_at'))
            throw new InvalidArgumentException('"expires_at" is a required<->reserved parameter for signing the url. Please update your route syntax.');

        $signature = $url . '::';
        $queryParams = [];

        if (!empty($expires_at)) {

            if ($expires_at instanceof DateTimer || $expires_at instanceof DateTime) {
                $queryParams['expires_at'] = $expires_at->format('U');
            } else if (gettype($expires_at == 'string') && Str::isTimestamp($expires_at)) {
                $queryParams['expires_at'] = $expires_at;
            } else {
                if (!$expires_at instanceof DateTimer && !$expires_at instanceof DateTime) {
                    throw new InvalidArgumentException('"expires_at" parameter must be type of ' . DateTimer::class . ' or ' . DateTime::class . ' object');
                }
            }

            $signature .= $expires_at;
        }

        $queryParams['signature'] = self::createSignature($signature);

        return (string) $url . '?' . http_build_query($queryParams);
    }

    public static function routeWithSignature($route, $segmentValues = [], $expires_at = null)
    {
        if (array_key_exists('signature', $segmentValues))
            throw new InvalidArgumentException('"signature" is a required<->reserved parameter for signing the url. Please update your route syntax.');

        if (array_key_exists('expires_at', $segmentValues))
            throw new InvalidArgumentException('"expires_at" is a required<->reserved parameter for signing the url. Please update your route syntax.');

        return self::withSignature(route($route, $segmentValues), $expires_at);
    }

    public static function verifySignature($url)
    {
        if (!self::hasQueryParam($url, 'signature'))

            return false;

        $url_for_signature = self::removeQuery($url) . '::';

        if (self::hasQueryParam($url, 'expires_at') && !empty($expires_at = self::getQueryParam($url, 'expires_at'))) {
            if ($expires_at < DateTimer::currentTimestamp())
                return false;

            $url_for_signature .= $expires_at;
        }

        if (self::createSignature($url_for_signature) != self::getQueryParam($url, 'signature'))
            return false;

        return true;
    }
}