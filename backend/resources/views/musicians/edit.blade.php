<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Musician</h2>
            <a href="{{ route('musicians.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Musicians</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('musicians.update', $musician) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="first_name" value="First name" />
                                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" :value="old('first_name', $musician->first_name)" required />
                            </div>
                            <div>
                                <x-input-label for="last_name" value="Last name" />
                                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" :value="old('last_name', $musician->last_name)" required />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="display_name" value="Display name" />
                            <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block w-full" :value="old('display_name', $musician->display_name)" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email (optional)" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $musician->email)" />
                            <p class="text-xs text-gray-500 mt-1">Contact email only, unless login access is enabled below.</p>
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        @if ($musician->user_id)
                            <p class="text-sm text-gray-600">Login account linked. Passwords cannot be viewed again from this screen.</p>
                        @else
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="create_login_account" value="1" @checked(old('create_login_account'))>
                                <span class="text-sm text-gray-700">Create login account (requires email; password generated automatically)</span>
                            </label>
                            <x-input-error :messages="$errors->get('create_login_account')" class="mt-2" />
                        @endif

                        <div>
                            <x-input-label for="notes" value="Notes (optional)" />
                            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $musician->notes) }}</textarea>
                        </div>

                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="active" value="1" @checked(old('active', $musician->active))>
                            <span class="text-sm text-gray-700">Active</span>
                        </label>

                        <x-primary-button>Save Changes</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
