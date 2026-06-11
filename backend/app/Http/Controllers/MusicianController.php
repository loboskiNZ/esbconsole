<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Musician;
use App\Services\MusicianUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MusicianController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly MusicianUserProvisioner $musicianUserProvisioner,
    ) {}

    public function index(): View
    {
        $band = $this->band();
        $musicians = $band->musicians()->with('user')->orderBy('display_name')->get();

        return view('musicians.index', [
            'band' => $band,
            'musicians' => $musicians,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'create_login_account' => ['nullable', 'boolean'],
        ]);

        $createLogin = $request->boolean('create_login_account');

        if ($createLogin && blank($validated['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Email is required when creating a login account.',
            ]);
        }

        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : trim("{$validated['first_name']} {$validated['last_name']}");

        $musician = Musician::create([
            'band_id' => $band->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => true,
        ]);

        $redirect = redirect()
            ->route('musicians.index')
            ->with('status', "Musician \"{$displayName}\" created.");

        if ($createLogin && filled($validated['email'])) {
            $provisioned = $this->musicianUserProvisioner->provision($musician, $validated['email']);

            $redirect->with(
                'generated_musician_password',
                "Login created for {$validated['email']}. One-time password: {$provisioned['plain_password']}"
            );
        }

        return $redirect;
    }

    public function edit(Musician $musician): View
    {
        $this->ensureBandOwns($musician);

        $musician->load('user');

        return view('musicians.edit', [
            'band' => $this->band(),
            'musician' => $musician,
        ]);
    }

    public function update(Request $request, Musician $musician): RedirectResponse
    {
        $this->ensureBandOwns($musician);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'create_login_account' => ['nullable', 'boolean'],
        ]);

        $createLogin = $request->boolean('create_login_account');

        if ($createLogin && blank($validated['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Email is required when creating a login account.',
            ]);
        }

        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : trim("{$validated['first_name']} {$validated['last_name']}");

        $musician->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $request->boolean('active'),
        ]);

        $redirect = redirect()
            ->route('musicians.index')
            ->with('status', "Musician \"{$displayName}\" updated.");

        if ($createLogin && filled($validated['email']) && $musician->user_id === null) {
            $provisioned = $this->musicianUserProvisioner->provision($musician->fresh(), $validated['email']);

            $redirect->with(
                'generated_musician_password',
                "Login created for {$validated['email']}. One-time password: {$provisioned['plain_password']}"
            );
        }

        return $redirect;
    }
}
