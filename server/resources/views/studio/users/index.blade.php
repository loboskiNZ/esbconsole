@extends('layouts.portal')

@section('title', 'Users — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        @include('studio.partials._chrome-header', [
            'pageTitle' => 'Users',
            'pageLead' => 'Manage Studio accounts, access, and roles.',
            'breadcrumbs' => [
                ['label' => 'Studio', 'url' => route('studio')],
                ['label' => 'Users'],
            ],
        ])

        <div class="esb-studio__shell-body">
            @if (session('user_updated'))
                <p class="esb-portal__success mb-4" role="status">
                    Updated {{ session('user_updated') }}.
                </p>
            @endif

            @if ($errors->any())
                <div class="esb-portal__error mb-4" role="alert">
                    <ul class="esb-studio__users-error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="esb-portal__panel esb-studio__card esb-studio__users">
                <div class="esb-studio__users-table-wrap">
                    <table class="esb-studio__users-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Username</th>
                                <th scope="col">Email</th>
                                <th scope="col">Status</th>
                                <th scope="col">Roles</th>
                                <th scope="col">Person</th>
                                <th scope="col">Created</th>
                                <th scope="col">Updated</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $entry)
                                @php($managedUser = $entry['user'])
                                <tr>
                                    <td>{{ $managedUser->name ?: '—' }}</td>
                                    <td>{{ $managedUser->username }}</td>
                                    <td>{{ $managedUser->email ?: '—' }}</td>
                                    <td>
                                        <span class="esb-studio__users-status {{ $managedUser->is_active ? 'esb-studio__users-status--active' : 'esb-studio__users-status--inactive' }}">
                                            {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($entry['role_labels'] === [])
                                            <span class="esb-studio__users-muted">None</span>
                                        @else
                                            {{ implode(', ', $entry['role_labels']) }}
                                        @endif
                                    </td>
                                    <td>{{ $entry['person_name'] ?: '—' }}</td>
                                    <td>{{ $managedUser->created_at?->format('d M Y') ?: '—' }}</td>
                                    <td>{{ $managedUser->updated_at?->format('d M Y') ?: '—' }}</td>
                                    <td class="esb-studio__users-actions">
                                        @if ($managedUser->is_active)
                                            <form method="POST" action="{{ route('studio.users.deactivate', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="esb-studio__users-action">Deactivate</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('studio.users.activate', $managedUser) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="esb-studio__users-action">Activate</button>
                                            </form>
                                        @endif

                                        <details class="esb-studio__users-roles-editor">
                                            <summary class="esb-studio__users-action">Edit roles</summary>
                                            <form
                                                method="POST"
                                                action="{{ route('studio.users.roles.update', $managedUser) }}"
                                                class="esb-studio__users-roles-form"
                                            >
                                                @csrf
                                                @method('PUT')

                                                <fieldset class="esb-studio__users-roles-fieldset">
                                                    <legend class="sr-only">Roles for {{ $managedUser->username }}</legend>
                                                    @foreach ($manageableRoleKeys as $roleKey)
                                                        <label class="esb-studio__users-role-option">
                                                            <input
                                                                type="checkbox"
                                                                name="roles[]"
                                                                value="{{ $roleKey }}"
                                                                @checked(in_array($roleKey, $entry['role_keys'], true))
                                                            >
                                                            <span>{{ str($roleKey)->replace('_', ' ')->title() }}</span>
                                                        </label>
                                                    @endforeach
                                                </fieldset>

                                                <button type="submit" class="esb-portal__button esb-portal__button--secondary esb-studio__users-roles-save">
                                                    Save roles
                                                </button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <footer class="esb-studio__chrome-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                    Log out
                </button>
            </form>
        </footer>
    </main>
@endsection
