@extends('layouts.portal')

@section('title', 'Invitation Not Valid — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--desktop antialiased"
@endsection

@section('content')
    <main class="relative z-10 flex min-h-dvh flex-col items-center justify-center px-4 py-8 text-center sm:px-6">
        <div class="esb-portal__panel w-full max-w-lg rounded-2xl p-8 sm:p-10">
            <p class="esb-portal__eyebrow mb-3">Invitation</p>
            <h1 class="esb-portal__title mb-4">This invitation is no longer valid</h1>
            <p class="esb-portal__label text-balance">
                The link may have expired, been revoked, or is incorrect.
                If you believe you should have access, contact the band.
            </p>
        </div>
    </main>
@endsection
