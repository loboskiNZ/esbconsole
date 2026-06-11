<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Musicians — {{ $band->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Add Musician</h3>
                    <form method="POST" action="{{ route('musicians.store') }}" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        @csrf
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
