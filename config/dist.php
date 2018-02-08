<?php

return [
    'github' => [
        'type' => 'github',
        'options' => [
            'baseURL'  => env('GITHUB_API_URL', 'https://api.github.com'),
            'username' => env('GITHUB_API_USERNAME'),
            'token'    => env('GITHUB_API_TOKEN'),
        ],
    ],
];
