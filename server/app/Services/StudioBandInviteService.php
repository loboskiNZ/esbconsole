<?php

namespace App\Services;

use App\Models\InviteLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudioBandInviteService
{
    /**
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     slug: string|null,
     *     invite_url: string|null,
     *     expiry_label: string,
     *     is_active: bool,
     *     can_copy: bool,
     * }>
     */
    public function invitesForDashboard(): Collection
    {
        if (! Schema::hasTable('invite_links')) {
            return collect();
        }

        return InviteLink::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (InviteLink $invite): array => $this->toViewModel($invite));
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string|null,
     *     invite_url: string|null,
     *     expiry_label: string,
     *     is_active: bool,
     *     can_copy: bool,
     * }
     */
    private function toViewModel(InviteLink $invite): array
    {
        $slug = $invite->revealToken();
        $isActive = $invite->isValid();

        return [
            'id' => $invite->id,
            'name' => $invite->name,
            'slug' => $slug,
            'invite_url' => $invite->inviteUrl(),
            'expiry_label' => $this->expiryLabel($invite),
            'is_active' => $isActive,
            'can_copy' => $slug !== null && $invite->inviteUrl() !== null,
        ];
    }

    private function expiryLabel(InviteLink $invite): string
    {
        if ($invite->revoked_at !== null || $invite->expires_at->isPast()) {
            return '(Expired)';
        }

        return '(expires '.$invite->expires_at->format('d M Y').')';
    }
}
