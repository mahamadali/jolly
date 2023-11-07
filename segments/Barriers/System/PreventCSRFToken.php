<?php

namespace Barriers\System;

use Bones\Request;

class PreventCSRFToken
{
    public $excludeRoutes = [
        // define routes to exclude from csrf-token check
    ];

    public function check(Request $request)
    {
        if (!$request->has('prevent_csrf_token') || !session()->has('prevent_csrf_token', true)) return false;
        
        $request_token = $request->get('prevent_csrf_token');
        $session_tokens = session()->get('prevent_csrf_token', true);

        session()->removeFromSet('prevent_csrf_token', $request_token, true);

        return (!empty($request_token) && session()->has('prevent_csrf_token', true) && in_array($request_token, (array) $session_tokens));
    }
}