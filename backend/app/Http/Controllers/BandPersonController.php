<?php

namespace App\Http\Controllers;

use App\Enums\BandRole;
use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Musician;
use App\Services\BandPersonRoleSync;
use App\Services\MusicianUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BandPersonController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly MusicianUserProvisioner $musicianUserProvisioner,
        private readonly BandPersonRoleSync $bandPersonRoleSync,
    ) {}

    public function index(): View
    {
        $band = $this->band();
        $activePeople = $band->musicians()
            ->where('active', true)
            ->with(['user', 'bandRoles'])
            ->orderBy('display_name')
            ->get();
        $archivedPeople = $band->musicians()
            ->where('active', false)
            ->with(['user', 'bandRoles'])
            ->orderBy('display_name')
            ->get();

        return view('people.index', [
            'band' => $band,
            'activePeople' => $activePeople,
            'archivedPeople' => $archivedPeople,
            'bandRoles' => BandRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();
        $validated = $this->validatePerson($request);

        $createLogin = $request->boolean('create_login_account');

        if ($createLogin && blank($validated['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Email is required when creating a login account.',
            ]);
        }

        $displayName = $this->displayNameFromValidated($validated);

        $person = Musician::create([
            'band_id' => $band->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => true,
        ]);

        $this->bandPersonRoleSync->sync($person, $validated['band_roles'] ?? []);

        $redirect = redirect()
            ->route('people.index')
            ->with('status', "Person \"{$displayName}\" created.");

        if ($createLogin && filled($validated['email'])) {
            $provisioned = $this->musicianUserProvisioner->provision($person, $validated['email']);

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

        $band = $this->band();
        $musician->load(['user', 'bandRoles']);

        return view('people.edit', [
            'band' => $band,
            'person' => $musician,
            'bandRoles' => BandRole::cases(),
            'isPrimaryDirector' => $band->primary_director_musician_id === $musician->id,
        ]);
    }

    public function update(Request $request, Musician $musician): RedirectResponse
    {
        $this->ensureBandOwns($musician);

        $band = $this->band();
        $validated = $this->validatePerson($request, includeOperationalProfile: true);

        $createLogin = $request->boolean('create_login_account');

        if ($createLogin && blank($validated['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Email is required when creating a login account.',
            ]);
        }

        $displayName = $this->displayNameFromValidated($validated);

        $musician->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'display_name' => $displayName,
            'email' => $validated['email'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'dietary_preferences' => $validated['dietary_preferences'] ?? null,
            'allergies' => $validated['allergies'] ?? null,
            'accessibility_notes' => $validated['accessibility_notes'] ?? null,
            'travel_notes' => $validated['travel_notes'] ?? null,
            'emergency_contact_notes' => $validated['emergency_contact_notes'] ?? null,
        ]);

        $this->bandPersonRoleSync->sync($musician, $validated['band_roles'] ?? []);
        $musician->load('bandRoles');

        $hasDirectorRole = $musician->hasBandRole(BandRole::Director);

        if ($request->boolean('is_primary_director') && $hasDirectorRole) {
            $band->update(['primary_director_musician_id' => $musician->id]);
        } elseif ($band->primary_director_musician_id === $musician->id && (! $hasDirectorRole || ! $request->boolean('is_primary_director'))) {
            $band->update(['primary_director_musician_id' => null]);
        }

        $redirect = redirect()
            ->route('people.index')
            ->with('status', "Person \"{$displayName}\" updated.");

        if ($createLogin && filled($validated['email']) && $musician->user_id === null) {
            $provisioned = $this->musicianUserProvisioner->provision($musician->fresh(), $validated['email']);

            $redirect->with(
                'generated_musician_password',
                "Login created for {$validated['email']}. One-time password: {$provisioned['plain_password']}"
            );
        }

        return $redirect;
    }

    public function archive(Musician $musician): RedirectResponse
    {
        $this->ensureBandOwns($musician);

        $musician->update(['active' => false]);

        return redirect()
            ->route('people.index')
            ->with('status', "Person \"{$musician->display_name}\" archived.");
    }

    public function restore(Musician $musician): RedirectResponse
    {
        $this->ensureBandOwns($musician);

        $musician->update(['active' => true]);

        return redirect()
            ->route('people.index')
            ->with('status', "Person \"{$musician->display_name}\" restored.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePerson(Request $request, bool $includeOperationalProfile = false): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'band_roles' => ['nullable', 'array', 'min:1'],
            'band_roles.*' => ['string', 'distinct', Rule::in(BandRole::values())],
            'create_login_account' => ['nullable', 'boolean'],
        ];

        if ($includeOperationalProfile) {
            $rules += [
                'dietary_preferences' => ['nullable', 'string'],
                'allergies' => ['nullable', 'string'],
                'accessibility_notes' => ['nullable', 'string'],
                'travel_notes' => ['nullable', 'string'],
                'emergency_contact_notes' => ['nullable', 'string'],
                'is_primary_director' => ['nullable', 'boolean'],
            ];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function displayNameFromValidated(array $validated): string
    {
        return filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : trim("{$validated['first_name']} {$validated['last_name']}");
    }
}
