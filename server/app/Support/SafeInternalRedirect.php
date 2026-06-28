<?php

namespace App\Support;

class SafeInternalRedirect
{
    public function showPlaylistReturnPath(int $showId): string
    {
        return '/studio/shows/'.$showId.'#playlist';
    }

    public function songLibraryReturnPath(): string
    {
        return '/songs';
    }

    public function resolve(?string $returnTo, string $fallback): string
    {
        if ($returnTo === null || trim($returnTo) === '') {
            return $fallback;
        }

        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return $fallback;
        }

        if (preg_match('#^/[^/]*:#', $returnTo) === 1) {
            return $fallback;
        }

        if (str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
            return $fallback;
        }

        return $returnTo;
    }
}
