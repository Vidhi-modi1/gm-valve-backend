<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;

Route::get('/hash-password/{password}', function ($password) {
  	//echo phpinfo();
    $password =  Hash::make('MD@6070#');
  	echo $password;
	exit;
});

Route::get('/check-pass', function () {
    $hash = '$2y$12$4NZgBQJmIoaC8ytdB4tJcu6fua1QyXT0oDkRFhHFJkHz2SvBttmJ6';

   dd(Hash::check('MD@6070#', $hash));
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('route:clear');
    Artisan::call('view:clear');

    return "Cache and config cleared!";
});



