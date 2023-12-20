<?php

namespace Bones;

use Bones\RequestException;
use Bones\RequestManipulation;
use Bones\RequestParamNotFound;
use Bones\URL;

class Request extends Validation
{

    public $request;
    public $files;

    /**
     * Constructor
     */
    public function __construct($request, $files = [], $route = [])
    {
        $this->purifyAttributes($request);

        $request['__route'] = $route;
        $this->request = toStdClass($request);
        $this->files = [];
        foreach ($files as $fileName => $file) {
            if (is_array($file['name']) && count($file['name']) > 0) {
                foreach ($file['name'] as $fileIndex => $requestFileName) {
                    if (empty($requestFileName)) continue;
                    $fileObj = [
                        'tmp_name' => $file['tmp_name'][$fileIndex],
                        'name' => $file['name'][$fileIndex],
                        'error' => $file['error'][$fileIndex],
                        'type' => $file['type'][$fileIndex],
                        'size' => $file['size'][$fileIndex]
                    ];
                    $this->files[$fileName][$fileIndex] = new File($fileObj);
                }
            } else {
                $this->files[$fileName] = new File($file);
            }
        }
    }

    /**
     * Purify request attributes
     * 
     * @param mixed $request
     * 
     */
    private function purifyAttributes(&$request)
    {
        foreach ($request as &$value) {
            if (is_array($value)) {
                $this->purifyAttributes($value);
            } else {
                $value = Str::isBase64Encoded($value) ? $value : urldecode($value);
            }
        }
    }

    public function __get($param)
    {
        if (!isset($this->request->$param)) throw new RequestParamNotFound($param);
        return $this->request->$param;
    }

    public function __set($param, $value)
    {
        if (!empty($this->request->$param)) {
            throw new RequestManipulation($param);
        }
        $this->request->$param = $value;
    }

    public function __isset($param)
    {
        return isset($this->request->$param);
    }

    /**
     * Get all file or specific file object
     * @param optional string $attr
     * 
     * @return File object(s)
     */
    public function files(string $fileName = '')
    {
        if (!empty($fileName) && $this->hasFile($fileName)) {
            return $this->files[$fileName];
        }
        return $this->files;
    }

    /**
     * Check whether request has this attribute
     * @param string $attr
     * 
     * @return bool true if attribute exists and false if attribute does not exist in request
     */
    public function has($attr)
    {
        return (!empty($this->request)) ? array_key_exists($attr, (array) $this->request) : false;
    }

    /**
     * Check whether request has this file
     * @param string $fileName
     * 
     * @return bool true if file exists and false if file does not exist in request
     */
    public function hasFile($fileName)
    {
        if (!empty($this->files[$fileName]) && (is_array($this->files[$fileName])) && !empty($this->files[$fileName][0])) {
            return (!empty($this->files)) ? (array_key_exists($fileName, $this->files) && !empty($this->files[$fileName][0]->file['tmp_name'])) : false;
        } else {
            return (!empty($this->files)) ? (array_key_exists($fileName, $this->files) && !empty($this->files[$fileName]->file['tmp_name'])) : false;
        }
    }

    /**
     * Get current request method
     * 
     * @return string type of request method
     */
    public function type()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get all request attributes
     * 
     * @return array collection of request attributes
     */
    public function all()
    {
        return (array) $this->except(['__route']);
    }

    /**
     * Get request attributes after excluding specific attribute(s)
     * @param string|array $attr Attrbiute(s) to exclude
     * 
     * @return string|array collection of request attributes
     */
    public function except($attrs)
    {
        if (empty($this->request)) return $this->request;
        if (!empty($attrs)) {
            unset($this->request->__route);
            $requestExcept = $this->request;
            if (gettype($attrs) == 'string') {
                unset($requestExcept->$attrs);
                return (array) $requestExcept;
            } else if (is_array($attrs)) {
                foreach ($attrs as $attr) {
                    unset($requestExcept->$attr);
                }
                return (array) $requestExcept;
            }
        }
        throw new RequestException('except() method on Request object has 1 required parameter with type either string or array');
    }

    /**
     * Get specific request attribute(s)
     * @param string|array $attr Attrbiute(s) to get
     * 
     * @return string|array collection of request attributes
     */
    public function getOnly($attrs)
    {
        if (empty($this->request)) return $this->request;
        if (!empty($attrs)) {
            if (gettype($attrs) == 'string') {
                return $this->get($attrs);
            } else if (is_array($attrs)) {
                foreach ($attrs as $attr) {
                    if (empty($requestWith)) $requestWith = [];
                    if ($this->has($attr))
                        $requestWith[$attr] = $this->get($attr);
                }
                return $requestWith;
            }
        }
        throw new RequestException('getOnly() method on Request object has 1 required parameter with type either string or array');
    }

    /**
     * Get specific request attribute
     * @param string $attr Attrbiute to get
     * 
     * @return string|array value of request attribute
     */
    public function get($attr)
    {
        if (empty($attr)) return '';
        return (!empty($this->request->$attr)) ? $this->request->$attr : '';
    }

    /**
     * Add validation rules to request
     * 
     * @param array $rules
     * @param array $errMsgs
     * 
     * return Validation
     */
    public function validate(array $rules = [], array $errMsgs = [])
    {
        return (new Validation($rules, $errMsgs))->check($this->all(), $this->files);
    }

    /**
     * Get current request page
     * 
     */
    public static function currentPage()
    {
        $appSubDir = rtrim(setting('app.sub_dir', ''), '/');

        return (Str::startsWith(trim($_SERVER['REQUEST_URI'], '/'), $appSubDir)) ? str_replace($appSubDir, '', trim($_SERVER['REQUEST_URI'], '/')) : $_SERVER['REQUEST_URI'];
    }

    /**
     * Get current request uri
     * 
     */
    public static function currentUri()
    {
        $appSubDir = rtrim(setting('app.sub_dir', ''), '/');

        return str_replace($appSubDir, '', trim($_SERVER['REQUEST_URI'], '/'));
    }

    /**
     * Get current request uri without query
     * 
     */
    public static function currentUriWithoutQuery()
    {
        return URL::removeQuery(self::currentUri());
    }

    /**
     * Check current page matches to pattern
     * 
     * @param string $pattern
     * 
     * @return bool
     */
    public static function matchesTo($pattern)
    {
        $currentPage = URL::removeQuery(self::currentPage());

        if ((empty($currentPage) || $currentPage == '/') && $pattern == '/') return true;

        return URL::matchesTo($currentPage, $pattern);
    }

    /**
     * Check request has valid signature
     * 
     * @return bool
     */
    public static function hasValidSignature()
    {
        return URL::verifySignature(url(self::currentPage()));
    }

    /**
     * Clean request collection by removing default class attributes
     * 
     * @return object collection of request attributes after removing default class attributes
     */
    public function __getAfterCleanifyDefaultAttrs()
    {
        return $this->all();
    }
}