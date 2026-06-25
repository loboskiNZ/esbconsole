<?php

namespace App\Services;

use App\Models\InviteLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudioBandInviteService
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     invite_url: string,
     *     expires_at_label: string,
     *     download_filename: string,
     * }>
     */
    public function shareableInvitesForDashboard(): Collection
    {
        if (! Schema::hasTable('invite_links')) {
            return collect();
        }

        return InviteLink::query()
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (InviteLink $invite): bool => $this->isShareable($invite))
            ->map(fn (InviteLink $invite): array => $this->toShareCardViewModel($invite))
            ->values();
    }

    public function legacyUnusableCount(): int
    {
        if (! Schema::hasTable('invite_links')) {
            return 0;
        }

        return InviteLink::query()
            ->get()
            ->filter(function (InviteLink $invite): bool {
                if ($this->isShareable($invite)) {
                    return false;
                }

                return $invite->token_ciphertext === null
                    || $invite->token_ciphertext === ''
                    || ! $invite->isValid();
            })
            ->count();
    }

    public function createInvite(string $name, int $days = 30): InviteLink
    {
        $rawToken = InviteLink::generateRawToken();

        return InviteLink::createWithToken(
            name: $name,
            rawToken: $rawToken,
            expiresAt: Carbon::now()->addDays($days),
        );
    }

    public function isShareable(InviteLink $invite): bool
    {
        if ($invite->token_ciphertext === null || $invite->token_ciphertext === '') {
            return false;
        }

        if (! $invite->isValid()) {
            return false;
        }

        return $invite->inviteUrl() !== null;
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     invite_url: string,
     *     expires_at_label: string,
     *     download_filename: string,
     * }
     */
    private function toShareCardViewModel(InviteLink $invite): array
    {
        return [
            'id' => $invite->id,
            'name' => $invite->name,
            'invite_url' => (string) $invite->inviteUrl(),
            'expires_at_label' => $invite->expires_at->format('d M Y'),
            'download_filename' => $this->downloadFilename($invite),
        ];
    }

    private function downloadFilename(InviteLink $invite): string
    {
        $slug = Str::slug($invite->name);

        if ($slug === '') {
            $slug = 'invite-'.$invite->id;
        }

        return 'band-invite-'.$slug.'.png';
    }
}
