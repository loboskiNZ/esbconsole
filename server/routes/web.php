<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/invite/{token}', function (string $token) {
    $bandpicsDirectory = public_path('images/bandpics');

    $backgroundImages = is_dir($bandpicsDirectory)
        ? collect(scandir($bandpicsDirectory) ?: [])
            ->reject(fn (string $file) => in_array($file, ['.', '..'], true))
            ->filter(fn (string $file) => (bool) preg_match('/\.(jpe?g|png)$/i', $file))
            ->reject(fn (string $file) => str_contains(strtolower($file), '_logo'))
            ->sort()
            ->values()
            ->map(fn (string $file) => asset('images/bandpics/'.$file))
            ->all()
        : [];

    if ($backgroundImages === []) {
        $backgroundImages = [asset('images/portal/ESB-Lobofest3.jpg')];
    }

    return view('onboarding.invite', [
        'token' => $token,
        'backgroundImages' => $backgroundImages,
    ]);
})->where('token', '[A-Za-z0-9\-_]+');

Route::view('/studio', 'studio.index');
