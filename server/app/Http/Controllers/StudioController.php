<?php

namespace App\Http\Controllers;

class StudioController extends Controller
{
    public function index()
    {
        return view('studio.index', [
            'user' => auth()->user()?->load('person'),
        ]);
    }
}
