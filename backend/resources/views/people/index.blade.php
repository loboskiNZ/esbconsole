<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Band People — {{ $band->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('generated_musician_password'))
                <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                    {{ session('generated_musician_password') }}
                    <p class="mt-2 text-xs text-amber-800">Copy this password now. It will not be shown again.</p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Add Person</h3>
                    <form method="POST" action="{{ route('people.store') }}" class="space-y-4">
                        @csrf
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <x-input-label for="first_name" value="First name" />
                                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name')" required />
                                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last name" />
                                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name')" required />
                                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="display_name" value="Display name (optional)" />
                                <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full" :value="old('display_name')" />
                            </div>
                            <div>
                                <x-input-label for="email" value="Email (optional)" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                                <p class="text-xs text-gray-500 mt-1">Contact email only, unless login access is enabled below.</p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Band roles" />
                            <div class="mt-2 grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach ($bandRoles as $role)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="band_roles[]"
                                            value="{{ $role->value }}"
                                            @checked(collect(old('band_roles', [App\Enums\BandRole::Musician->value]))->contains($role->value))
                                        >
                                        {{ $role->label() }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('band_roles')" class="mt-2" />
                            <x-input-error :messages="$errors->get('band_roles.*')" class="mt-2" />
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="create_login_account" value="1" @checked(old('create_login_account'))>
                            <span class="text-sm text-gray-700">Create login account (requires email; password generated automatically)</span>
                        </label>
                        <x-input-error :messages="$errors->get('create_login_account')" class="mt-2" />

                        <x-primary-button type="submit">Create Person</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Active Band People</h3>

                    @if ($activePeople->isEmpty())
                        <p class="text-sm text-gray-600">No active people yet.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($activePeople as $person)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $person->display_name }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ $person->first_name }} {{ $person->last_name }}
                                            @if ($person->email) · {{ $person->email }} @endif
                                            @if ($person->user_id) · Login enabled @endif
                                        </p>
                                        @if ($person->bandRoleLabels() !== [])
                                            <p class="text-sm text-indigo-700 mt-1">{{ implode(' · ', $person->bandRoleLabels()) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('people.edit', $person) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('people.archive', $person) }}" onsubmit="return confirm('Archive {{ $person->display_name }}? Their profile will be hidden from the active list but not deleted.');">
                                            @csrf
                                            <button type="submit" class="text-sm text-amber-700 hover:text-amber-900">Archive</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            @if ($archivedPeople->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold mb-1">Archived Band People</h3>
                        <p class="text-sm text-gray-500 mb-4">Archived profiles are hidden from active lists. Login accounts are preserved.</p>

                        <ul class="divide-y divide-gray-200">
                            @foreach ($archivedPeople as $person)
                                <li class="py-3 flex items-center justify-between gap-4 opacity-80">
                                    <div>
                                        <p class="font-medium">{{ $person->display_name }} <span class="text-xs font-normal text-gray-500">(Archived)</span></p>
                                        <p class="text-sm text-gray-500">
                                            {{ $person->first_name }} {{ $person->last_name }}
                                            @if ($person->email) · {{ $person->email }} @endif
                                            @if ($person->user_id) · Login preserved @endif
                                        </p>
                                        @if ($person->bandRoleLabels() !== [])
                                            <p class="text-sm text-gray-600 mt-1">{{ implode(' · ', $person->bandRoleLabels()) }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <a href="{{ route('people.edit', $person) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                        <form method="POST" action="{{ route('people.restore', $person) }}">
                                            @csrf
                                            <button type="submit" class="text-sm text-green-700 hover:text-green-900">Restore</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
