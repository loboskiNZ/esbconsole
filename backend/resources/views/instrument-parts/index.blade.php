<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Instrument Parts — {{ $band->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Add Instrument Part</h3>
                    <form method="POST" action="{{ route('instrument-parts.store') }}" class="flex flex-wrap items-end gap-4">
                        @csrf
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full min-w-[200px]" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <x-text-input id="description" name="description" type="text" class="mt-1 block w-full min-w-[240px]" :value="old('description')" />
                        </div>
                        <x-primary-button type="submit">Create</x-primary-button>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Catalog</h3>

                    @if ($instrumentParts->isEmpty())
                        <p class="text-sm text-gray-600">No instrument parts yet. Create roles like Lead Vocal, Guitar, Bass, etc.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($instrumentParts as $part)
                                <li class="py-3">
                                    <p class="font-medium">{{ $part->name }}</p>
                                    @if ($part->description)
                                        <p class="text-sm text-gray-500">{{ $part->description }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ $part->active ? 'Active' : 'Inactive' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
