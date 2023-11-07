<?php

use Jolly\Engine;

register_shutdown_function("jollyCustomFatalErrorHandler");
set_error_handler("jollyCustomWarningHandler", E_WARNING | E_NOTICE);

function jollyCustomWarningHandler($type, $message, $file, $line)
{
    if (!empty($type) && !empty($message))
        jollyHandleAppError(compact('type', 'message', 'file', 'line'));
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
        if (ob_get_length() > 0)
            ob_clean();
        $errorMessage = $error['message'];
        $errorMessage.= '<br>File: '.$error["file"];
        $errorMessage.= '<br>Line: '.$error["line"];
        if (session()->has('from_cli', true)) {
            session()->remove('from_cli', true);
            throw new Exception($errorMessage, $error["type"]);
        } else {
            Engine::setErrorBackTrace();
        }
    }
}