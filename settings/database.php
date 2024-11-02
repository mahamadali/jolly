<?php

return [
    
    'enable' => true,

    // Database details [ Key of this set is database name ]
    'jolly_master' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'port' => 3306,
        'prefix' => 'tl_',
        'charset' => 'utf8',
        'socket' => null,
        'is_primary' => true
    ],

    'jolly_child' => [
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'port' => 3306,
        'prefix' => 'tl_',
        'charset' => 'utf8',
        'socket' => null,
        'is_primary' => false
    ]
    
];