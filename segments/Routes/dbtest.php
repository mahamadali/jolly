<?php

use Bones\Router;
use Controllers\DBTestController;

Router::get(['/db-test', '/db-operations'], [ DBTestController::class, 'index' ])->name('landing');