<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/invite/{token}', function (string $token) {
    return view('onboarding.invite', [
        'token' => $token,
    ]);
})->where('token', '[A-Za-z0-9\-_]+');

Route::view('/studio', 'studio.index');
