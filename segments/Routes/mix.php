<?php

use Bones\Router;
use Controllers\WelcomeController;

Router::get(['/home', '/'], [ WelcomeController::class, 'index' ])->name('landing');