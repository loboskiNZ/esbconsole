<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Person</h2>
            <a href="{{ route('people.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Band People</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('people.update', $person) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="first_name" value="First name" />
                                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $person->first_name)" required />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last name" />
                                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $person->last_name)" required />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="display_name" value="Display name" />
                            <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full" :value="old('display_name', $person->display_name)" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email (optional)" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $person->email)" />
                            <p class="text-xs text-gray-500 mt-1">Contact email only, unless login access is enabled below.</p>
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label value="Band roles" />
                            <div class="mt-2 grid sm:grid-cols-2 gap-2">
                                @foreach ($bandRoles as $role)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="band_roles[]"
                                            value="{{ $role->value }}"
                                            @checked(collect(old('band_roles', $person->bandRoleValues()))->contains($role->value))
                                        >
                                        {{ $role->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('band_roles')" class="mt-2" />
                            <x-input-error :messages="$errors->get('band_roles.*')" class="mt-2" />
                        </div>

                        @if ($person->hasBandRole(App\Enums\BandRole::Director) || collect(old('band_roles', []))->contains(App\Enums\BandRole::Director->value))
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="is_primary_director" value="1" @checked(old('is_primary_director', $isPrimaryDirector))>
                                <span class="text-sm text-gray-700">Primary director for this band (supports future directorship handover)</span>
                            </label>
                        @endif

                        @if ($person->user_id)
                            <p class="text-sm text-gray-600">Login account linked. Passwords cannot be viewed again from this screen.</p>
                        @else
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="create_login_account" value="1" @checked(old('create_login_account'))>
                                <span class="text-sm text-gray-700">Create login account (requires email; password generated automatically)</span>
                            </label>
                            <x-input-error :messages="$errors->get('create_login_account')" class="mt-2" />
                        @endif

                        <div>
                            <x-input-label for="notes" value="General notes (optional)" />
                            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $person->notes) }}</textarea>
                        </div>

                        <div class="border-t border-gray-200 pt-6 space-y-4">
                            <div>
                                <h3 class="font-semibold text-sm text-gray-900">Operational profile</h3>
                                <p class="text-xs text-gray-500 mt-1">Director-only fields for rider and travel planning. Not shown outside this management area.</p>
                            </div>

                            <div>
                                <x-input-label for="dietary_preferences" value="Dietary preferences" />
                                <textarea id="dietary_preferences" name="dietary_preferences" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('dietary_preferences', $person->dietary_preferences) }}</textarea>
                            </div>

                            <div>
                                <x-input-label for="allergies" value="Allergies" />
                                <textarea id="allergies" name="allergies" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('allergies', $person->allergies) }}</textarea>
                            </div>

                            <div>
                                <x-input-label for="accessibility_notes" value="Accessibility / disability notes" />
                                <textarea id="accessibility_notes" name="accessibility_notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('accessibility_notes', $person->accessibility_notes) }}</textarea>
                            </div>

                            <div>
                                <x-input-label for="travel_notes" value="Travel notes" />
                                <textarea id="travel_notes" name="travel_notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('travel_notes', $person->travel_notes) }}</textarea>
                            </div>

                            <div>
                                <x-input-label for="emergency_contact_notes" value="Emergency / contact notes" />
                                <textarea id="emergency_contact_notes" name="emergency_contact_notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('emergency_contact_notes', $person->emergency_contact_notes) }}</textarea>
                            </div>
                        </div>

                        <x-primary-button>Save Changes</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
