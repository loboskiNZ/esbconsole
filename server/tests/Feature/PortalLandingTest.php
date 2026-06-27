<?php

namespace Tests\Feature;

use Tests\TestCase;

class PortalLandingTest extends TestCase
{
    public function test_portal_landing_page_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_portal_landing_page_uses_supplied_assets_and_welcome_copy(): void
    {
        $response = $this->get('/');

        $response->assertSee('Welcome to the Ed and the Shadow Boys Portal', false);
        $response->assertSee(asset('images/portal/ESB-Lobofest3.jpg'), false);
        $response->assertSee(asset('images/portal/Logo_ESB_BLACKBG.png'), false);
    }

    public function test_portal_landing_page_scaffolds_staged_login_journey(): void
    {
        $response = $this->get('/');

        $response->assertSee('Enter your username', false);
        $response->assertSee('Continue', false);
        $response->assertSee('loginStep === \'username\'', false);
        $response->assertSee('loginStep === \'password\'', false);
        $response->assertSee('showLoginButton', false);
        $response->assertSee('showForgotPassword', false);
        $response->assertSee('Forgot your password?', false);
        $response->assertSee(route('password.request'), false);
        $response->assertSee('<template x-if="loginStep === \'password\'">', false);
    }

    public function test_portal_styles_include_logo_rotation_and_reduced_motion_support(): void
    {
        $css = file_get_contents(resource_path('css/portal.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('esb-logo-rotate', $css);
        $this->assertStringContainsString('600s linear infinite', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }
}
