<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Musicians — {{ $band->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('generated_musician_password'))
                <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                    {{ session('generated_musician_password') }}
                    <p class="mt-2 text-xs text-amber-800">Copy this password now. It will not be shown again.</p>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Add Musician</h3>
                    <form method="POST" action="{{ route('musicians.store') }}" class="space-y-4">
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

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="create_login_account" value="1" @checked(old('create_login_account'))>
                            <span class="text-sm text-gray-700">Create login account (requires email; password generated automatically)</span>
                        </label>
                        <x-input-error :messages="$errors->get('create_login_account')" class="mt-2" />

                        <x-primary-button type="submit">Create Musician</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Musicians</h3>

                    @if ($musicians->isEmpty())
                        <p class="text-sm text-gray-600">No musicians yet.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($musicians as $musician)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $musician->display_name }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ $musician->first_name }} {{ $musician->last_name }}
                                            @if ($musician->email) · {{ $musician->email }} @endif
                                            · {{ $musician->active ? 'Active' : 'Inactive' }}
                                            @if ($musician->user_id) · Login enabled @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('musicians.edit', $musician) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
