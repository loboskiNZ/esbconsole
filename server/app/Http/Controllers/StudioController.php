<?php

namespace App\Http\Controllers;

class StudioController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('studio.index', [
            'user' => $user?->load(['person.instruments']),
        ]);
    }
}
