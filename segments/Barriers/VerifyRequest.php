<?php

namespace Barriers;

use Bones\Request;

class VerifyRequest 
{
    public function check(Request $request)
    {
        return true;
    }
}