<?php

use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('shows.index')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified', 'director'])->group(function () {
    Route::get('/shows', [ShowController::class, 'index'])->name('shows.index');
    Route::post('/shows/{show}/activate', [ShowController::class, 'activate'])->name('shows.activate');
    Route::get('/shows/{show}/playlist', [PlaylistController::class, 'show'])->name('playlist.show');
});

Route::get('/dashboard', function () {
    return redirect()->route('shows.index');
})->middleware(['auth', 'verified', 'director'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
