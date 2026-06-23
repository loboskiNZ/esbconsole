<?php

namespace App\Http\Controllers;

use App\Services\PersonProfilePhotoService;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $person = $user?->load(['person.instruments'])->person;
        $photoService = app(PersonProfilePhotoService::class);

        return view('studio.index', [
            'user' => $user,
            'person' => $person,
            'photoInitials' => $person ? $photoService->initials($person) : '',
        ]);
    }
}
