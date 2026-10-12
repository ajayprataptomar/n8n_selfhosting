<?php

use App\Enums\StatusEnum;

return [

    'app_name'    => "XSender",
    'software_id' => "BX32DOTW4Q797ZF3",
    'version' => '5.0.0',

    'cacheFile'   => 'X2ZpbGVjYWNoZWluZw==',

    'core' => [
        'appVersion' => '5.0.0',
        'minPhpVersion' => '8.2'
    ],

    'requirements' => [

        'php' => [
            'Core',
            'bcmath',
            'openssl',
            'pdo_mysql',
            'mbstring',
            'tokenizer',
            'json',
            'curl',
            'gd',
            'zip',
            'mbstring',


        ],
        'apache' => [
            'mod_rewrite',
        ],

    ],
    'permissions' => [
        '.env'              => '666',
        'storage'           => '775',
        'bootstrap/cache/'  => '775',
    ],

    'demo_config' => [
        'admin' => [
            'username' => 'admin',
            'password' => 'admin',
        ],
        'user' => [
            'email' => 'xsender@demo.test',
            'password' => '12345678',
        ]
    ]

];
