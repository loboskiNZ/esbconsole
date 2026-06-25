<section
    class="esb-portal__panel esb-studio__card esb-studio__band-invites"
    aria-labelledby="studio-band-invites-title"
    x-data="studioBandInvites(@js($bandInvites))"
>
    <div class="esb-studio__band-invites-head">
        <h2 id="studio-band-invites-title" class="esb-studio__card-title">Band Invites</h2>

        <button
            type="button"
            class="esb-studio__band-invite-action esb-studio__band-invites-create-toggle"
            @click="showCreateForm = !showCreateForm"
            :aria-expanded="showCreateForm ? 'true' : 'false'"
        >
            Create invite
        </button>
    </div>

    <form
        class="esb-studio__band-invites-create mt-3"
        method="POST"
        action="{{ route('studio.invites.store') }}"
        x-show="showCreateForm"
        x-cloak
    >
        @csrf

        <label class="esb-studio__band-invites-create-label" for="studio-invite-name">Invite name</label>
        <input
            id="studio-invite-name"
            type="text"
            name="name"
            class="esb-portal__input esb-studio__band-invites-create-input"
            maxlength="120"
            required
            placeholder="Guitar Audition"
        >

        <label class="esb-studio__band-invites-create-label mt-3" for="studio-invite-days">Valid for (days)</label>
        <input
            id="studio-invite-days"
            type="number"
            name="days"
            class="esb-portal__input esb-studio__band-invites-create-input"
            min="1"
            max="365"
            value="30"
        >

        <button type="submit" class="esb-portal__button esb-portal__button--primary esb-studio__band-invites-create-submit mt-3">
            Create invite
        </button>
    </form>

    @if ($legacyUnusableInviteCount > 0)
        <p class="esb-studio__band-invites-note mt-3">
            {{ number_format($legacyUnusableInviteCount) }} older invite{{ $legacyUnusableInviteCount === 1 ? '' : 's' }} cannot be shared from Studio.
        </p>
    @endif

    @if ($bandInvites->isEmpty())
        <p class="esb-studio__card-body mt-3">No active band invites.</p>
    @else
        <ul class="esb-studio__band-invites-list mt-3">
            @foreach ($bandInvites as $invite)
                <li
                    class="esb-studio__band-invite-share"
                    data-invite-id="{{ $invite['id'] }}"
                    data-invite-url="{{ $invite['invite_url'] }}"
                >
                    <div class="esb-studio__band-invite-share-copy">
                        <p class="esb-studio__band-invite-name">{{ $invite['name'] }}</p>
                        <p class="esb-studio__band-invite-expiry">Expires {{ $invite['expires_at_label'] }}</p>
                    </div>

                    <div class="esb-studio__band-invite-qr-wrap">
                        <canvas
                            class="esb-studio__band-invite-qr"
                            data-invite-qr="{{ $invite['id'] }}"
                            aria-label="QR code for {{ $invite['name'] }}"
                            role="img"
                        ></canvas>
                    </div>

                    <p class="esb-studio__band-invite-url">{{ $invite['invite_url'] }}</p>

                    <div class="esb-studio__band-invite-actions">
                        <button
                            type="button"
                            class="esb-studio__band-invite-action"
                            @click="copyUrl(@js($invite['invite_url']), {{ $invite['id'] }})"
                        >
                            <span x-show="copiedId !== {{ $invite['id'] }}">Copy link</span>
                            <span x-show="copiedId === {{ $invite['id'] }}" x-cloak>Copied</span>
                        </button>

                        <a
                            href="{{ $invite['invite_url'] }}"
                            class="esb-studio__band-invite-action esb-studio__band-invite-action--link"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Open
                        </a>

                        <button
                            type="button"
                            class="esb-studio__band-invite-action"
                            @click="downloadQr({{ $invite['id'] }}, @js($invite['download_filename']))"
                        >
                            Download QR
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
