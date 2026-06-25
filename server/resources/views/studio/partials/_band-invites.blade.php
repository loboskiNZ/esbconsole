<section
    class="esb-portal__panel esb-studio__card esb-studio__band-invites"
    aria-labelledby="studio-band-invites-title"
    x-data="studioBandInvites()"
>
    <h2 id="studio-band-invites-title" class="esb-studio__card-title">Band Invites</h2>

    @if ($bandInvites->isEmpty())
        <p class="esb-studio__card-body mt-3">No active band invites.</p>
    @else
        <ul class="esb-studio__band-invites-list mt-3">
            @foreach ($bandInvites as $invite)
                <li
                    class="esb-studio__band-invite{{ $invite['is_active'] ? '' : ' esb-studio__band-invite--inactive' }}"
                >
                    <p class="esb-studio__band-invite-name">{{ $invite['name'] }}</p>

                    @if ($invite['slug'])
                        <button
                            type="button"
                            class="esb-studio__band-invite-slug"
                            @if ($invite['can_copy'])
                                @click="copySlug(@js($invite['slug']), {{ $invite['id'] }})"
                                :aria-label="'Copy invite slug for {{ $invite['name'] }}'"
                            @else
                                disabled
                            @endif
                        >
                            {{ $invite['slug'] }}
                        </button>
                    @else
                        <p class="esb-studio__band-invite-slug-unavailable">Slug unavailable</p>
                    @endif

                    <p class="esb-studio__band-invite-expiry">{{ $invite['expiry_label'] }}</p>

                    @if ($invite['can_copy'])
                        <div class="esb-studio__band-invite-actions">
                            <button
                                type="button"
                                class="esb-studio__band-invite-action"
                                @click="copyUrl(@js($invite['invite_url']), {{ $invite['id'] }})"
                            >
                                <span x-show="copiedId !== {{ $invite['id'] }}">Copy</span>
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
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
