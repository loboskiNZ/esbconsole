<?php

namespace App\Http\Controllers;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $person = $user?->load(['person.instruments'])->person;

        return view('studio.index', [
            'user' => $user,
            'person' => $person,
        ]);
    }
}
