<?php

namespace App\Services\Ticketing;

use App\Models\Performance;

/**
 * One fixed private-offer link for a Performance.
 *
 * Same signature as the public site's PrivateOfferCampaign: public id,
 * campaign, and source, signed with the public site's application key.
 */
class PrivateOfferLink
{
    public const CAMPAIGN = 'meta';

    public const SOURCE = 'meta';

    public function url(Performance $performance): ?string
    {
        $publicId = trim((string) $performance->public_id);
        $key = (string) config('ticketing.campaign_key');

        if ($publicId === '' || $key === '') {
            return null;
        }

        $entry = hash_hmac(
            'sha256',
            $publicId."\n".self::CAMPAIGN."\n".self::SOURCE,
            $key,
        );

        $base = rtrim((string) config('ticketing.public_base_url'), '/');

        return $base.'/performances/'.$publicId.'?'.http_build_query([
            'campaign' => self::CAMPAIGN,
            'entry' => $entry,
            'source' => self::SOURCE,
        ]);
    }
}
