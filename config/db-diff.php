<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | mysql1 is the SOURCE (the schema you treat as the source of truth).
    | mysql2 is the TARGET (the schema being compared against the source).
    |
    | The command generates SQL files describing what mysql2 is missing
    | or has different compared to mysql1.
    |
    */

    'connections' => [

        'mysql1' => [
            'driver'    => 'mysql',
            'host'      => env('DB_DIFF_MYSQL1_HOST'),
            'database'  => env('DB_DIFF_MYSQL1_DATABASE'),
            'username'  => env('DB_DIFF_MYSQL1_USERNAME'),
            'password'  => env('DB_DIFF_MYSQL1_PASSWORD'),
            'charset'   => env('DB_DIFF_MYSQL1_CHARSET', 'utf8mb4'),
            'collation' => env('DB_DIFF_MYSQL1_COLLATION', 'utf8mb4_unicode_ci'),
        ],

        'mysql2' => [
            'driver'    => 'mysql',
            'host'      => env('DB_DIFF_MYSQL2_HOST'),
            'database'  => env('DB_DIFF_MYSQL2_DATABASE'),
            'username'  => env('DB_DIFF_MYSQL2_USERNAME'),
            'password'  => env('DB_DIFF_MYSQL2_PASSWORD'),
            'charset'   => env('DB_DIFF_MYSQL2_CHARSET', 'utf8mb4'),
            'collation' => env('DB_DIFF_MYSQL2_COLLATION', 'utf8mb4_unicode_ci'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Output disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk where the generated SQL files are written.
    | Defaults to 'local' which maps to storage/app/ in a Laravel app.
    |
    */

    'output_disk' => env('DB_DIFF_OUTPUT_DISK', 'local'),

];
