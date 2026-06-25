<?php

use App\Http\Controllers\CheckOnboardingUsernameController;
use App\Http\Controllers\InviteOnboardingController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OnboardingRegistrationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudioChartSearchController;
use App\Http\Controllers\StudioChartsController;
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
    Route::get('/studio/charts/search', StudioChartSearchController::class)->name('studio.charts.search');
    Route::get('/studio/charts', [StudioChartsController::class, 'index'])->name('studio.charts.index');
    Route::get('/studio/charts/{song}', [StudioChartsController::class, 'show'])->name('studio.charts.show');
    Route::get('/studio/charts/files/{chart}', [StudioChartsController::class, 'chartFile'])->name('studio.charts.file');
    Route::get('/studio/profile/edit', [ProfileController::class, 'edit'])->name('studio.profile.edit');
    Route::get('/studio/profile/photo', [ProfileController::class, 'photo'])->name('studio.profile.photo');
    Route::put('/studio/profile', [ProfileController::class, 'update'])->name('studio.profile.update');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::post('/invite/check-username', CheckOnboardingUsernameController::class)
    ->middleware('guest')
    ->name('invite.check-username');

Route::get('/invite/{token}', [InviteOnboardingController::class, 'show'])
    ->middleware('guest')
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('invite.show');

Route::post('/invite/{token}/complete', [OnboardingRegistrationController::class, 'store'])
    ->middleware('guest')
    ->where('token', '[A-Za-z0-9\-_]+')
    ->name('invite.complete');
