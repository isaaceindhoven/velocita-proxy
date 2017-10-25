<?php

Route::get('/dist/{site}/{path}', 'DistController@download')
    ->where(['path' => '.*']);
Route::get('/repo/{repo}/packages.json', 'RepositoryController@getPackages');
Route::get('/repo/{repo}/packages-velocita.json', 'RepositoryController@getPackagesForVelocita');
Route::get('/repo/{repo}/{namespace}/{package}.json', 'RepositoryController@downloadPackage');
Route::get('/endpoints', 'MetadataController@getEndpoints');
