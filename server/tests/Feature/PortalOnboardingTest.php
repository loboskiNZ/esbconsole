<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_route_loads_for_scaffold_token(): void
    {
        $response = $this->get('/invite/test-token');

        $response->assertOk();
        $response->assertSee('Someone believes you belong here', false);
        $response->assertSee('Begin Your Journey', false);
        $response->assertSee('portalOnboarding', false);
    }

    public function test_studio_route_loads(): void
    {
        $response = $this->get('/studio');

        $response->assertOk();
        $response->assertSee('The Studio', false);
        $response->assertSee('Profile tasks', false);
        $response->assertSee('Upcoming shows', false);
        $response->assertSee('Band notices', false);
    }

    public function test_invite_route_does_not_create_database_records(): void
    {
        $tables = ['users', 'sessions', 'cache', 'jobs'];

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Expected zero rows in {$table} before request.");
        }

        $this->get('/invite/test-token')->assertOk();

        foreach ($tables as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Expected zero rows in {$table} after invite scaffold request.");
        }
    }

    public function test_no_public_registration_route_is_introduced(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertNull(Route::getRoutes()->getByName('register'));

        $this->get('/register')->assertNotFound();
        $this->get('/signup')->assertNotFound();
    }

    public function test_onboarding_scaffold_includes_journey_steps_and_validation_copy(): void
    {
        $response = $this->get('/invite/test-token');

        $response->assertSee('Claim Your Identity', false);
        $response->assertSee('Your True Name', false);
        $response->assertSee('Choose Your Persona', false);
        $response->assertSee('Choose Your Weapon', false);
        $response->assertSee('Find Your Way Home', false);
        $response->assertSee('The Road Ahead', false);
        $response->assertSee('Enter the Studio', false);
        $response->assertSee('temporary UI scaffold only', false);
        $response->assertSee('3–32 characters', false);
        $response->assertSee('8–50 characters', false);
    }

    public function test_onboarding_scaffold_includes_back_navigation_and_weapon_refinements(): void
    {
        $response = $this->get('/invite/test-token');

        $response->assertSee('goBack()', false);
        $response->assertSee('canGoBack', false);
        $response->assertSee('Primary weapon', false);
        $response->assertSee('Additional weapons', false);
        $response->assertSee('Start again', false);
        $response->assertSee('setPrimaryWeapon', false);

        $scaffoldData = file_get_contents(resource_path('js/onboarding-scaffold-data.js'));
        $this->assertNotFalse($scaffoldData);
        $this->assertStringContainsString('Tenor Sax', $scaffoldData);
        $this->assertStringContainsString('Cuatro', $scaffoldData);
    }

    public function test_onboarding_scaffold_includes_country_typeahead_and_background_rotation(): void
    {
        $response = $this->get('/invite/test-token');

        $response->assertSee('selectCountry', false);
        $response->assertSee('countryQuery', false);
        $response->assertSee('backgroundImages', false);
        $response->assertSee('bandpics', false);
        $response->assertSee('ESB-Lobofest3.jpg', false);

        $scaffoldData = file_get_contents(resource_path('js/onboarding-scaffold-data.js'));
        $onboardingJs = file_get_contents(resource_path('js/onboarding.js'));

        $this->assertNotFalse($scaffoldData);
        $this->assertNotFalse($onboardingJs);
        $this->assertStringContainsString('New Zealand', $scaffoldData);
        $this->assertStringContainsString('NZL', $scaffoldData);
        $this->assertStringContainsString('scheduleBackgroundRotation', $onboardingJs);
    }

    public function test_no_demo_seeders_were_added_for_onboarding_scaffold(): void
    {
        $this->assertFileDoesNotExist(database_path('seeders/OnboardingScaffoldSeeder.php'));
        $this->assertFileDoesNotExist(database_path('seeders/DemoUserSeeder.php'));

        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertNotFalse($seeder);
        $this->assertStringNotContainsString('OnboardingScaffoldSeeder', $seeder);
        $this->assertStringNotContainsString('DemoUserSeeder', $seeder);
    }

    public function test_onboarding_styles_include_reduced_motion_support(): void
    {
        $css = file_get_contents(resource_path('css/portal.css'));

        $this->assertNotFalse($css);
        $this->assertStringContainsString('esb-onboarding__progress-fill', $css);
        $this->assertStringContainsString('esb-portal__background-layer', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }
}
