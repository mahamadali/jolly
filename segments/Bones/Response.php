<?php

namespace Bones;

class Response
{
    protected static $response = [];
    protected $status;

    public static function json($stack = null)
    {
        self::format('application/json');

        if (gettype($stack) == 'object' || gettype($stack) == 'array') {
            foreach ($stack as $key => $element) {
                self::$response[$key] = $element;
            }
        }

        echo json_encode(self::$response);

        // Stop the execution after giving response
        exit;
    }
    
    public static function format($contentType = 'text/html')
    {
        header('Content-type: ' . $contentType);
    }

    public static function redirect(string $url = '')
    {
        if (empty($url)) $url = url('/');
        header('Location: ' . $url);
    }

}