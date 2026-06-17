<?php

use App\Http\Controllers\BandPersonController;
use App\Http\Controllers\BulkFestivalController;
use App\Http\Controllers\BulkSongController;
use App\Http\Controllers\BulkVenueController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\FestivalController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\CueController;
use App\Http\Controllers\InstrumentPartController;
use App\Http\Controllers\MusicianController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SongInstrumentPartController;
use App\Http\Controllers\VenueController;
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

    Route::get('/shows/{show}/console', [ConsoleController::class, 'showForShow'])->name('shows.console');
    Route::get('/shows/{show}/console/routing', [ConsoleController::class, 'routingForShow'])->name('shows.console.routing');
    Route::get('/shows/{show}/console/bus/{bus}/layout', [ConsoleController::class, 'busLayoutForShow'])
        ->whereNumber('bus')
        ->name('shows.console.bus.layout');
    Route::post('/shows/{show}/console/bus/{bus}/sends', [ConsoleController::class, 'updateMonitorSend'])
        ->whereNumber('bus')
        ->name('shows.console.bus.sends.update');
    Route::post('/shows/{show}/console/bus/{bus}/eq', [ConsoleController::class, 'updateMonitorEq'])
        ->whereNumber('bus')
        ->name('shows.console.bus.eq.update');
    Route::post('/shows/{show}/console/bus/{bus}/master', [ConsoleController::class, 'updateMonitorBusMaster'])
        ->whereNumber('bus')
        ->name('shows.console.bus.master.update');
    Route::get('/shows/{show}/monitors/{busNumber}', [ConsoleController::class, 'redirectLegacyMonitorRoute'])
        ->whereNumber('busNumber')
        ->name('shows.monitors');
    Route::get('/shows/{show}/console/configuration', [ConsoleController::class, 'configurationForShow'])->name('shows.console.configuration');
    Route::post('/shows/{show}/console/save', [ConsoleController::class, 'saveForShow'])->name('shows.console.save');
    Route::post('/shows/{show}/console/parameters', [ConsoleController::class, 'updateParameter'])->name('shows.console.parameters.update');
    Route::post('/shows/{show}/console/controls', [ConsoleController::class, 'updateControl'])->name('shows.console.controls.update');
    Route::get('/shows/{show}/console/learn', [ConsoleController::class, 'learnForShow'])->name('shows.console.learn');
    Route::post('/shows/{show}/console/learn', [ConsoleController::class, 'storeForShow'])->name('shows.console.learn.store');

    Route::get('/shows/{show}/playlist', [PlaylistController::class, 'show'])->name('playlist.show');
    Route::post('/shows/{show}/playlist', [PlaylistController::class, 'store'])->name('playlist.store');
    Route::post('/shows/{show}/playlist/reorder', [PlaylistController::class, 'reorder'])->name('playlist.reorder');
    Route::delete('/shows/{show}/playlist/items', [PlaylistController::class, 'bulkDestroy'])->name('playlist.bulk-destroy');
    Route::delete('/shows/{show}/playlist/{playlistItem}', [PlaylistController::class, 'destroy'])->name('playlist.destroy');

    Route::get('/songs', [SongController::class, 'index'])->name('songs.index');
    Route::get('/songs/bulk-create', [BulkSongController::class, 'create'])->name('songs.bulk-create');
    Route::post('/songs/bulk-create', [BulkSongController::class, 'store'])->name('songs.bulk-store');
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

    Route::get('/people', [BandPersonController::class, 'index'])->name('people.index');
    Route::post('/people', [BandPersonController::class, 'store'])->name('people.store');
    Route::get('/people/{musician}/edit', [BandPersonController::class, 'edit'])->name('people.edit');
    Route::put('/people/{musician}', [BandPersonController::class, 'update'])->name('people.update');
    Route::post('/people/{musician}/archive', [BandPersonController::class, 'archive'])->name('people.archive');
    Route::post('/people/{musician}/restore', [BandPersonController::class, 'restore'])->name('people.restore');

    Route::get('/musicians', [MusicianController::class, 'index'])->name('musicians.index');
    Route::post('/musicians', [MusicianController::class, 'store'])->name('musicians.store');
    Route::get('/musicians/{musician}/edit', [MusicianController::class, 'edit'])->name('musicians.edit');
    Route::put('/musicians/{musician}', [MusicianController::class, 'update'])->name('musicians.update');
    Route::post('/musicians/{musician}/archive', [MusicianController::class, 'archive'])->name('musicians.archive');
    Route::post('/musicians/{musician}/restore', [MusicianController::class, 'restore'])->name('musicians.restore');

    Route::get('/instrument-parts', [InstrumentPartController::class, 'index'])->name('instrument-parts.index');
    Route::post('/instrument-parts', [InstrumentPartController::class, 'store'])->name('instrument-parts.store');

    Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
    Route::get('/venues/create', [VenueController::class, 'create'])->name('venues.create');
    Route::post('/venues', [VenueController::class, 'store'])->name('venues.store');
    Route::get('/venues/bulk-create', [BulkVenueController::class, 'create'])->name('venues.bulk-create');
    Route::post('/venues/bulk-create', [BulkVenueController::class, 'store'])->name('venues.bulk-store');
    Route::get('/venues/{venue}/edit', [VenueController::class, 'edit'])->name('venues.edit');
    Route::put('/venues/{venue}', [VenueController::class, 'update'])->name('venues.update');
    Route::post('/venues/{venue}/archive', [VenueController::class, 'archive'])->name('venues.archive');
    Route::post('/venues/{venue}/restore', [VenueController::class, 'restore'])->name('venues.restore');

    Route::get('/festivals', [FestivalController::class, 'index'])->name('festivals.index');
    Route::get('/festivals/create', [FestivalController::class, 'create'])->name('festivals.create');
    Route::post('/festivals', [FestivalController::class, 'store'])->name('festivals.store');
    Route::get('/festivals/bulk-create', [BulkFestivalController::class, 'create'])->name('festivals.bulk-create');
    Route::post('/festivals/bulk-create', [BulkFestivalController::class, 'store'])->name('festivals.bulk-store');
    Route::get('/festivals/{festival}/edit', [FestivalController::class, 'edit'])->name('festivals.edit');
    Route::put('/festivals/{festival}', [FestivalController::class, 'update'])->name('festivals.update');
    Route::post('/festivals/{festival}/archive', [FestivalController::class, 'archive'])->name('festivals.archive');
    Route::post('/festivals/{festival}/restore', [FestivalController::class, 'restore'])->name('festivals.restore');

    Route::get('/console', [ConsoleController::class, 'index'])->name('console.index');
    Route::get('/console/learn', [ConsoleController::class, 'create'])->name('console.learn');
    Route::post('/console/learn', [ConsoleController::class, 'store'])->name('console.learn.store');
    Route::get('/console/snapshots/{snapshot}', [ConsoleController::class, 'showSnapshot'])->name('console.snapshots.show');
    Route::post('/console/snapshots/{snapshot}/save-baseline', [ConsoleController::class, 'saveBaseline'])->name('console.snapshots.save-baseline');
    Route::get('/console/baselines/{baseline}', [ConsoleController::class, 'showBaseline'])->name('console.baselines.show');
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
