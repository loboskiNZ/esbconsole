@php
    $editorPortrait = $editorPortrait ?? false;
@endphp

<div @class([
    'esb-studio__identity-portrait',
    'esb-studio__identity-portrait--editor' => $editorPortrait,
])>
    <div class="esb-studio__identity-shine"></div>
    @if ($person->hasProfilePhoto())
        <img
            src="{{ route('studio.profile.photo') }}"
            alt=""
            class="esb-studio__identity-photo"
        >
    @else
        <div class="esb-studio__identity-placeholder esb-studio__identity-placeholder--no-image">
            <img
                src="{{ asset('images/portal/Logo_ESB_BLACKBG.png') }}"
                alt=""
                class="esb-studio__identity-placeholder-logo"
            >
            <div class="esb-studio__identity-placeholder-figure" aria-hidden="true"></div>
            <p class="esb-studio__identity-placeholder-label">No image</p>
        </div>
    @endif
</div>
