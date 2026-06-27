<?php

namespace Tests\Unit;

use App\Support\SafeInternalRedirect;
use Tests\TestCase;

class SafeInternalRedirectTest extends TestCase
{
    public function test_accepts_internal_relative_paths(): void
    {
        $redirects = app(SafeInternalRedirect::class);

        $this->assertSame(
            '/studio/shows/5#playlist',
            $redirects->resolve('/studio/shows/5#playlist', '/studio'),
        );
    }

    public function test_rejects_external_urls(): void
    {
        $redirects = app(SafeInternalRedirect::class);

        $this->assertSame(
            '/studio/charts/1',
            $redirects->resolve('https://evil.example/phish', '/studio/charts/1'),
        );
    }

    public function test_rejects_protocol_relative_urls(): void
    {
        $redirects = app(SafeInternalRedirect::class);

        $this->assertSame(
            '/studio',
            $redirects->resolve('//evil.example/phish', '/studio'),
        );
    }

    public function test_show_playlist_return_path_includes_anchor(): void
    {
        $this->assertSame(
            '/studio/shows/12#playlist',
            app(SafeInternalRedirect::class)->showPlaylistReturnPath(12),
        );
    }
}
