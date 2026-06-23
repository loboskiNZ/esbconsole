<?php

use App\Http\Controllers\InviteOnboardingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/invite/{token}', [InviteOnboardingController::class, 'show'])
    ->where('token', '[A-Za-z0-9\-_]+');

Route::view('/studio', 'studio.index');
