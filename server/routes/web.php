<?php

use App\Http\Controllers\InviteOnboardingController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OnboardingRegistrationController;
use App\Http\Controllers\StudioController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home', [
        'onboardingComplete' => request()->query('onboarding') === 'complete',
    ]);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::post('/login', [LoginController::class, 'store'])->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/studio', [StudioController::class, 'index'])->name('studio');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::get('/invite/{token}', [InviteOnboardingController::class, 'show'])
    ->middleware('guest')
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('invite.show');

Route::post('/invite/{token}/complete', [OnboardingRegistrationController::class, 'store'])
    ->middleware('guest')
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('invite.complete');
