<?php

return [
    'default'   => 'mysql',
    'debugging' => true,

    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => '0.0.0.0',
            'port'     => '3306',
            'database' => 'perpustakaan',
            'username' => 'root',
            'password' => 'root',
            'charset'  => 'utf8mb4',
        ],
    ],
];