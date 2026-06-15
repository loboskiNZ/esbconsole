<?php

namespace Tests\Feature;

use App\Contracts\X32\X32ConsoleSnapshotReaderInterface;
use App\Contracts\X32\X32OscConsoleClientInterface;
use App\Enums\ConsoleLearningStatus;
use App\Models\Band;
use App\Models\ConsoleLearningSnapshot;
use App\Models\IntegrationConnectionProfile;
use App\Models\IntegrationDevice;
use App\Models\Show;
use App\Models\ShowConsoleBaseline;
use App\Services\Console\ShowConsoleBaselineService;
use App\Services\Console\X32ConsoleLearningService;
use App\Services\X32\FakeX32ConsoleSnapshotReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDirectorUser;
use Tests\TestCase;

class ConsoleLearningTest extends TestCase
{
    use CreatesDirectorUser;
    use RefreshDatabase;

    public function test_show_page_contains_console_link(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'ESB Lo-Fi']);

        $response = $this->actingAs($user)->get(route('shows.show', $show));

        $response->assertOk()
            ->assertSeeInOrder(['Edit Show', 'Manage Playlist', 'Console'], false)
            ->assertSee(route('shows.console', $show, false));
    }

    public function test_show_console_with_no_baseline_redirects_to_learn_flow(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'No Baseline Show']);
        $this->createX32Device($band);

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertRedirect(route('shows.console.learn', $show));

        $this->actingAs($user)
            ->get(route('shows.console.learn', $show))
            ->assertOk()
            ->assertSee('Which scene should be used as the baseline for this show?')
            ->assertDontSee('name="show_id"', false);
    }

    public function test_show_console_with_active_baseline_renders_workspace(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Baseline Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Active Baseline');

        $response = $this->actingAs($user)
            ->get(route('shows.console', $show));

        $response->assertOk()
            ->assertSee('ESB Console')
            ->assertSee('CH 1-32')
            ->assertSee('Kick')
            ->assertSee('Learn')
            ->assertSee('data-channel-number=', false)
            ->assertDontSee('CH 33')
            ->assertDontSee('CH 33-64')
            ->assertDontSee('Show Console Baseline');

        $html = $response->getContent() ?? '';
        $this->assertSame(32, substr_count($html, 'data-channel-number="'));
    }

    public function test_show_console_workspace_shows_overview_layer_only(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Strip Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Strip Baseline');

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertOk()
            ->assertSee('OVERVIEW')
            ->assertSee('FX RETURNS')
            ->assertDontSee('Buses / Monitors');
    }

    public function test_fader_parameter_update_persists_to_baseline_and_osc_client(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Fader Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Fader Baseline');

        $this->actingAs($user)
            ->postJson(route('shows.console.parameters.update', $show), [
                'osc_path' => '/ch/01/mix/fader',
                'parameter' => 'fader',
                'value' => 0.82,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fader', 0.82);

        $baseline = ShowConsoleBaseline::query()->where('show_id', $show->id)->where('active', true)->firstOrFail();
        $this->assertSame(0.82, $baseline->baseline_json['channels'][0]['fader']);

        $client = app(X32OscConsoleClientInterface::class);
        $this->assertNotEmpty($client->writes());
        $this->assertSame('/ch/01/mix/fader', $client->writes()[0]['path']);
    }

    public function test_learn_redirects_to_show_console_workspace(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Learning Show']);
        $device = $this->createX32Device($band);

        $response = $this->actingAs($user)->post(route('shows.console.learn.store', $show), [
            'integration_device_id' => $device->id,
            'requested_scene_number' => '01',
        ]);

        $snapshot = ConsoleLearningSnapshot::query()->firstOrFail();
        $response->assertRedirect(route('shows.console', $show));

        $this->assertSame($show->id, $snapshot->show_id);
        $this->assertSame(ConsoleLearningStatus::Review, $snapshot->learning_status);

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertOk()
            ->assertSee('ESB Console')
            ->assertSee('Learning Show')
            ->assertSee('Unsaved preview')
            ->assertSee('Save')
            ->assertSee('Learn');
    }

    public function test_saving_from_console_workspace_persists_baseline(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Saved Baseline Show']);
        $device = $this->createX32Device($band);

        app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');

        $this->actingAs($user)->post(route('shows.console.save', $show), [
            'baseline_name' => 'My Active Baseline',
        ])->assertRedirect(route('shows.console', $show));

        $this->actingAs($user)
            ->get(route('shows.console', $show))
            ->assertOk()
            ->assertSee('ESB Console')
            ->assertSee('Saved Baseline Show')
            ->assertSee('Active')
            ->assertDontSee('Unsaved preview');
    }

    public function test_fader_updates_work_before_console_is_saved(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Preview Fader Show']);
        $device = $this->createX32Device($band);

        app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');

        $this->actingAs($user)
            ->postJson(route('shows.console.parameters.update', $show), [
                'osc_path' => '/ch/01/mix/fader',
                'parameter' => 'fader',
                'value' => 0.77,
            ])
            ->assertOk()
            ->assertJsonPath('fader', 0.77);

        $snapshot = ConsoleLearningSnapshot::query()->where('show_id', $show->id)->firstOrFail();
        $this->assertSame(0.77, $snapshot->learned_summary_json['channels'][0]['fader']);
    }

    public function test_snapshot_route_redirects_to_show_console(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');

        $this->actingAs($user)
            ->get(route('console.snapshots.show', $snapshot))
            ->assertRedirect(route('shows.console', $show));
    }

    public function test_admin_baseline_record_page_still_loads(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create(['name' => 'Admin Show']);
        $device = $this->createX32Device($band);
        $snapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $baseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($snapshot, 'Admin Baseline');

        $this->actingAs($user)
            ->get(route('console.baselines.show', $baseline))
            ->assertOk()
            ->assertSee('Console Baseline Record (Admin)')
            ->assertSee('Admin/debug view');
    }

    public function test_fake_transport_returns_channel_bus_and_dca_data(): void
    {
        $band = Band::factory()->create();
        $device = $this->createX32Device($band);

        $result = (new FakeX32ConsoleSnapshotReader)->learnScene(
            new \App\DataTransferObjects\X32\X32ConsoleLearnCommand(
                device: $device,
                requestedSceneNumber: '01',
                host: '127.0.0.1',
                port: 10023,
            ),
        );

        $this->assertTrue($result->success);
        $this->assertCount(32, $result->summary['channels']);
        $this->assertCount(16, $result->summary['buses']);
        $this->assertCount(8, $result->summary['dcas']);
    }

    public function test_failed_learning_stores_failed_status_and_error(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);

        $this->app->instance(
            X32ConsoleSnapshotReaderInterface::class,
            new FakeX32ConsoleSnapshotReader(shouldFail: true, failureMessage: 'Bridge unavailable'),
        );

        $response = $this->actingAs($user)->post(route('shows.console.learn.store', $show), [
            'integration_device_id' => $device->id,
            'requested_scene_number' => '02',
        ]);

        $snapshot = ConsoleLearningSnapshot::query()->firstOrFail();
        $response->assertRedirect(route('shows.console.learn', $show));

        $this->assertSame(ConsoleLearningStatus::Failed, $snapshot->learning_status);
    }

    public function test_saving_new_baseline_deactivates_previous_active_baseline(): void
    {
        $band = Band::factory()->create();
        $show = Show::factory()->forBand($band)->create();
        $device = $this->createX32Device($band);

        $firstSnapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '01');
        $firstBaseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($firstSnapshot, 'First Baseline');

        $secondSnapshot = app(X32ConsoleLearningService::class)->startLearning($show, $device, '02');
        $secondBaseline = app(ShowConsoleBaselineService::class)->saveFromSnapshot($secondSnapshot, 'Second Baseline');

        $this->assertFalse($firstBaseline->fresh()->active);
        $this->assertTrue($secondBaseline->fresh()->active);
        $this->assertSame(1, ShowConsoleBaseline::query()->where('show_id', $show->id)->where('active', true)->count());
        $this->assertNotSame(
            $firstBaseline->baseline_json['channels'][0]['fader'],
            $secondBaseline->baseline_json['channels'][0]['fader'],
        );
    }

    public function test_standalone_console_dashboard_still_loads(): void
    {
        $user = $this->createDirectorUser();
        $band = Band::factory()->create(['name' => 'Test Band']);

        $this->actingAs($user)
            ->get(route('console.index'))
            ->assertOk()
            ->assertSee('Console — Test Band');
    }

    private function createX32Device(Band $band): IntegrationDevice
    {
        $device = IntegrationDevice::factory()->forBand($band)->create([
            'device_key' => 'foh-x32',
            'name' => 'FOH X32',
            'device_type' => IntegrationDevice::TYPE_X32,
            'enabled' => true,
        ]);

        IntegrationConnectionProfile::factory()->create([
            'integration_device_id' => $device->id,
            'profile_name' => 'osc-main',
            'protocol' => IntegrationConnectionProfile::PROTOCOL_OSC,
            'host' => '127.0.0.1',
            'port' => 10023,
            'enabled' => true,
        ]);

        return $device;
    }
}
