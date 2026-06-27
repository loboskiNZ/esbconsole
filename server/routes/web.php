<?php

use App\Http\Controllers\CheckOnboardingUsernameController;
use App\Http\Controllers\InviteOnboardingController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\OnboardingRegistrationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudioBandController;
use App\Http\Controllers\StudioBandInviteController;
use App\Http\Controllers\StudioCalendarController;
use App\Http\Controllers\StudioChartSearchController;
use App\Http\Controllers\StudioShowPlaylistChartController;
use App\Http\Controllers\StudioShowPlaylistController;
use App\Http\Controllers\StudioChartsController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\StudioPerformancesController;
use App\Http\Controllers\StudioShowsController;
use App\Http\Controllers\StudioSongsController;
use App\Http\Controllers\StudioSongInstrumentPartController;
use App\Http\Controllers\StudioUsersController;
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
    Route::get('/studio/shows', [StudioShowsController::class, 'index'])->name('studio.shows.index');
    Route::get('/studio/shows/archived', [StudioShowsController::class, 'archived'])
        ->middleware('studio.director')
        ->name('studio.shows.archived');
    Route::get('/studio/shows/create', [StudioShowsController::class, 'create'])
        ->middleware('studio.director')
        ->name('studio.shows.create');
    Route::post('/studio/shows', [StudioShowsController::class, 'store'])
        ->middleware('studio.director')
        ->name('studio.shows.store');
    Route::middleware('studio.director')->group(function () {
        Route::get('/songs/{song}/edit', [StudioSongsController::class, 'edit'])->name('songs.edit');
        Route::put('/songs/{song}', [StudioSongsController::class, 'update'])->name('songs.update');
        Route::post('/songs/{song}/instrument-parts', [StudioSongInstrumentPartController::class, 'store'])
            ->name('songs.instrument-parts.store');

        Route::get('/studio/shows/{show}/edit', [StudioShowsController::class, 'edit'])->name('studio.shows.edit');
        Route::put('/studio/shows/{show}', [StudioShowsController::class, 'update'])->name('studio.shows.update');
        Route::patch('/studio/shows/{show}/archive', [StudioShowsController::class, 'archive'])->name('studio.shows.archive');
        Route::patch('/studio/shows/{show}/restore', [StudioShowsController::class, 'restore'])->name('studio.shows.restore');
        Route::post('/studio/shows/{show}/playlist', [StudioShowPlaylistController::class, 'store'])->name('studio.shows.playlist.store');
        Route::patch('/studio/shows/{show}/playlist/{playlistItem}/notes', [StudioShowPlaylistController::class, 'updateNotes'])->name('studio.shows.playlist.notes');
        Route::patch('/studio/shows/{show}/playlist/{playlistItem}/archive', [StudioShowPlaylistController::class, 'archive'])->name('studio.shows.playlist.archive');
        Route::patch('/studio/shows/{show}/playlist/{playlistItem}/move-up', [StudioShowPlaylistController::class, 'moveUp'])->name('studio.shows.playlist.move-up');
        Route::patch('/studio/shows/{show}/playlist/{playlistItem}/move-down', [StudioShowPlaylistController::class, 'moveDown'])->name('studio.shows.playlist.move-down');
        Route::get('/studio/shows/{show}/playlist/songs/{song}/parts/{songInstrumentPart}/chart/upload', [StudioShowPlaylistChartController::class, 'create'])
            ->name('studio.shows.playlist.chart.upload.create');
        Route::post('/studio/shows/{show}/playlist/songs/{song}/parts/{songInstrumentPart}/chart/upload', [StudioShowPlaylistChartController::class, 'store'])
            ->name('studio.shows.playlist.chart.upload.store');
    });
    Route::get('/studio/shows/{show}', [StudioShowsController::class, 'show'])->name('studio.shows.show');
    Route::get('/studio/calendar', [StudioCalendarController::class, 'index'])->name('studio.calendar.index');
    Route::get('/studio/performances', [StudioPerformancesController::class, 'index'])->name('studio.performances.index');
    Route::get('/studio/performances/{performance}/calendar.ics', [StudioPerformancesController::class, 'calendar'])
        ->name('studio.performances.calendar');
    Route::post('/studio/performances/{performance}/rsvp', [StudioPerformancesController::class, 'rsvp'])
        ->name('studio.performances.rsvp');
    Route::middleware('studio.director')->group(function () {
        Route::get('/studio/performances/create', [StudioPerformancesController::class, 'create'])->name('studio.performances.create');
        Route::post('/studio/performances', [StudioPerformancesController::class, 'store'])->name('studio.performances.store');
        Route::get('/studio/performances/{performance}/edit', [StudioPerformancesController::class, 'edit'])->name('studio.performances.edit');
        Route::put('/studio/performances/{performance}', [StudioPerformancesController::class, 'update'])->name('studio.performances.update');
    });
    Route::get('/studio/performances/{performance}', [StudioPerformancesController::class, 'show'])->name('studio.performances.show');
    Route::post('/studio/invites', [StudioBandInviteController::class, 'store'])
        ->middleware('studio.director')
        ->name('studio.invites.store');

    Route::middleware('studio.director')->group(function () {
        Route::get('/studio/band', [StudioBandController::class, 'edit'])->name('studio.band.edit');
        Route::put('/studio/band', [StudioBandController::class, 'update'])->name('studio.band.update');
        Route::get('/studio/band/logo', [StudioBandController::class, 'logo'])->name('studio.band.logo');
        Route::get('/studio/band/photo', [StudioBandController::class, 'photo'])->name('studio.band.photo');
        Route::get('/studio/band/hero', [StudioBandController::class, 'hero'])->name('studio.band.hero');
        Route::get('/studio/band/press', [StudioBandController::class, 'press'])->name('studio.band.press');
        Route::get('/studio/users', [StudioUsersController::class, 'index'])->name('studio.users.index');
        Route::patch('/studio/users/{user}/activate', [StudioUsersController::class, 'activate'])->name('studio.users.activate');
        Route::patch('/studio/users/{user}/deactivate', [StudioUsersController::class, 'deactivate'])->name('studio.users.deactivate');
        Route::put('/studio/users/{user}/roles', [StudioUsersController::class, 'updateRoles'])->name('studio.users.roles.update');
    });

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
