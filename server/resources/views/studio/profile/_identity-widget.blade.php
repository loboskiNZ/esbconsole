<section class="esb-portal__panel esb-studio__identity-widget" aria-label="My Profile">
    @include('studio.profile._portrait', ['person' => $person])

    <div class="esb-studio__identity-body">
        <p class="esb-portal__eyebrow">My Profile</p>
        <h2 class="esb-studio__identity-name">{{ $person->artistic_name }}</h2>

        @if ($person->instrumentSummary() !== '')
            <p class="esb-studio__identity-instruments">{{ $person->instrumentSummary() }}</p>
        @endif

        @if ($person->country)
            <p class="esb-studio__identity-country">{{ $person->country }}</p>
        @endif
    </div>

    <div class="esb-studio__identity-edit">
        <a href="{{ route('studio.profile.edit') }}" class="esb-studio__identity-edit-link">
            Edit
        </a>
    </div>
</section>
