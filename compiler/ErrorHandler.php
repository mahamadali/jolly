<?php

use Bones\Commander;
use Jolly\Engine;

register_shutdown_function("jollyCustomFatalErrorHandler");
set_error_handler("jollyCustomWarningHandler", E_WARNING | E_NOTICE);

function jollyCustomWarningHandler($type, $message, $file, $line)
{
    if (!empty($type) && !empty($message) && !session()->has('from_cli', true)) {
        jollyHandleAppError(compact('type', 'message', 'file', 'line'));
    } else {
        $error_message = $message . ' in ' . $file . ' on line ' . $line;
        $console = (new Commander());
        $console->throwError($error_message . PHP_EOL);
    }
}

function jollyCustomFatalErrorHandler() 
{
    $error = error_get_last();
    if (!empty($error)) {
        jollyHandleAppError($error);
    }
}

function jollyHandleAppError($error)
{
    if (!empty($error)) {
        if (ob_get_length() > 0) {
            //ob_clean();
        }

        if ($error instanceof Error) {
            $errorMessage = $error->getMessage();
            $errorMessage .= '<br>File: ' . $error->getFile();
            $errorMessage .= '<br>Line: ' . $error->getLine();
            $errorType = $error->getCode();
        } elseif ($error instanceof Exception) {
            $errorMessage = $error->getMessage();
            $errorMessage .= '<br>File: ' . $error->getFile();
            $errorMessage .= '<br>Line: ' . $error->getLine();
            $errorType = $error->getCode();
        } elseif (is_array($error)) {
            $errorMessage = $error['message'] ?? '';
            $errorMessage .= '<br>File: ' . ($error['file'] ?? '');
            $errorMessage .= '<br>Line: ' . ($error['line'] ?? '');
            $errorType = $error['type'] ?? 0;
        } else {
            $errorMessage = 'Unknown error';
            $errorType = 0;
        }

        if (session()->has('from_cli', true)) {
            session()->remove('from_cli', true);
            throw new Exception($errorMessage, $errorType);
        } else {
            Engine::setErrorBackTrace();
        }
    }
}

set_exception_handler(function ($exception) {
    if (session()->has('from_cli', true)) {
        $console = (new Commander());
        $console->throwError($exception->getMessage() . PHP_EOL);
    } else {
        jollyHandleAppError($exception);
    }
});