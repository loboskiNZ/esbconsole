<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/invite/{token}', function (string $token) {
    $paths = glob(public_path('images/portal/ESB-*.jpg')) ?: [];
    $backgroundImages = collect($paths)
        ->map(fn (string $path) => asset('images/portal/'.basename($path)))
        ->values()
        ->all();

    if ($backgroundImages === []) {
        $backgroundImages = [asset('images/portal/ESB-Lobofest3.jpg')];
    }

    return view('onboarding.invite', [
        'token' => $token,
        'backgroundImages' => $backgroundImages,
    ]);
})->where('token', '[A-Za-z0-9\-_]+');

Route::view('/studio', 'studio.index');
