<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Festival — {{ $festival->name }}</h2>
            <a href="{{ route('festivals.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Festivals</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('festivals.update', $festival) }}" class="space-y-6">
                        @csrf
                        @method('PUT')
                        @include('festivals._form', ['festival' => $festival])
                        <x-primary-button>Save Changes</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
