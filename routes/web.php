<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

$repoPath = config('repositories.public_path');

Route::get('/repo/{repo}/packages.json', 'RepositoryController@rootPackages');
Route::get('/repo/{repo}/pack/{namespace}/{package}.json', 'RepositoryController@downloadPackage');
Route::get('/repo/{repo}/dist/{namespace}/{package}/{version}-{reference}.{type}', 'RepositoryController@downloadDist');
