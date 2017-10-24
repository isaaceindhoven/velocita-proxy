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

Route::get('/dist/{site}/{path}', 'DistController@download')
    ->where(['path' => '.*']);
Route::get('/repo/{repo}/packages.json', 'RepositoryController@rootPackages');
Route::get('/repo/{repo}/{namespace}/{package}.json', 'RepositoryController@downloadPackage');
