<?php

use App\Http\Controllers\ChartController;
use App\Http\Controllers\CueController;
use App\Http\Controllers\InstrumentPartController;
use App\Http\Controllers\MusicianController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SongInstrumentPartController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('shows.index')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified', 'director'])->group(function () {
    Route::get('/shows', [ShowController::class, 'index'])->name('shows.index');
    Route::get('/shows/create', [ShowController::class, 'create'])->name('shows.create');
    Route::post('/shows', [ShowController::class, 'store'])->name('shows.store');
    Route::get('/shows/{show}', [ShowController::class, 'show'])->name('shows.show');
    Route::get('/shows/{show}/edit', [ShowController::class, 'edit'])->name('shows.edit');
    Route::put('/shows/{show}', [ShowController::class, 'update'])->name('shows.update');
    Route::post('/shows/{show}/activate', [ShowController::class, 'activate'])->name('shows.activate');

    Route::get('/shows/{show}/playlist', [PlaylistController::class, 'show'])->name('playlist.show');
    Route::post('/shows/{show}/playlist', [PlaylistController::class, 'store'])->name('playlist.store');
    Route::post('/shows/{show}/playlist/reorder', [PlaylistController::class, 'reorder'])->name('playlist.reorder');
    Route::delete('/shows/{show}/playlist/{playlistItem}', [PlaylistController::class, 'destroy'])->name('playlist.destroy');

    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::get('/songs/create', [SongController::class, 'create'])->name('songs.create');
    Route::post('/songs', [SongController::class, 'store'])->name('songs.store');
    Route::get('/songs/{song}', [SongController::class, 'show'])->name('songs.show');
    Route::get('/songs/{song}/edit', [SongController::class, 'edit'])->name('songs.edit');
    Route::put('/songs/{song}', [SongController::class, 'update'])->name('songs.update');

    Route::post('/songs/{song}/cues', [CueController::class, 'store'])->name('songs.cues.store');
    Route::delete('/songs/{song}/cues/{cue}', [CueController::class, 'destroy'])->name('songs.cues.destroy');

    Route::post('/songs/{song}/instrument-parts', [SongInstrumentPartController::class, 'store'])->name('songs.instrument-parts.store');
    Route::delete('/songs/{song}/instrument-parts/{songInstrumentPart}', [SongInstrumentPartController::class, 'destroy'])->name('songs.instrument-parts.destroy');

    Route::post('/songs/{song}/charts', [ChartController::class, 'store'])->name('songs.charts.store');
    Route::post('/charts/{chart}/assign', [ChartController::class, 'assign'])->name('charts.assign');

    Route::get('/musicians', [MusicianController::class, 'index'])->name('musicians.index');
    Route::post('/musicians', [MusicianController::class, 'store'])->name('musicians.store');
    Route::get('/musicians/{musician}/edit', [MusicianController::class, 'edit'])->name('musicians.edit');
    Route::put('/musicians/{musician}', [MusicianController::class, 'update'])->name('musicians.update');

    Route::get('/instrument-parts', [InstrumentPartController::class, 'index'])->name('instrument-parts.index');
    Route::post('/instrument-parts', [InstrumentPartController::class, 'store'])->name('instrument-parts.store');
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
