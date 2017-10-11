<?php

return [
	'mirrors' => [
		'packagist' => [
			'url' => env('PACKAGIST_URL', 'https://packagist.org'),
		],
	],

	'github' => [
		'api' => [
			'username' => env('GITHUB_API_USERNAME'),
			'token'    => env('GITHUB_API_TOKEN'),
		],
	],
];
