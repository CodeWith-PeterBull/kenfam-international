<?php

namespace Tests\Feature\SystemActivity;

use App\Enums\SystemActivitySeverity;
use App\Livewire\Admin\SystemActivityIndex;
use App\Models\SystemActivity;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemActivityLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('system-admin');
    }

    public function test_component_renders_filters_statistics_and_activity_rows(): void
    {
        SystemActivity::factory()->forActor($this->administrator)->create([
            'activity_type' => 'post.published',
            'description' => 'Annual outlook was published',
            'severity' => SystemActivitySeverity::Notice,
            'created_at' => now(),
        ]);

        Livewire::actingAs($this->administrator)
            ->test(SystemActivityIndex::class)
            ->assertOk()
            ->assertSee('Matching activities')
            ->assertSee('post.published')
            ->assertSee('Annual outlook was published')
            ->assertSee($this->administrator->name);
    }

    public function test_component_filters_and_clears_the_activity_stream(): void
    {
        $editor = User::factory()->create(['name' => 'Editorial Manager']);

        SystemActivity::factory()->forActor($editor)->create([
            'activity_type' => 'post.published',
            'description' => 'Target editorial activity',
            'severity' => SystemActivitySeverity::Notice,
            'created_at' => now(),
        ]);
        SystemActivity::factory()->forActor($this->administrator)->create([
            'activity_type' => 'event.deleted',
            'description' => 'Unrelated event activity',
            'severity' => SystemActivitySeverity::Warning,
            'created_at' => now()->subDays(5),
        ]);

        Livewire::actingAs($this->administrator)
            ->test(SystemActivityIndex::class)
            ->set('search', 'Target editorial')
            ->set('activityType', 'post.published')
            ->set('severity', SystemActivitySeverity::Notice->value)
            ->set('actorId', (string) $editor->getKey())
            ->set('dateFrom', now()->subDay()->toDateString())
            ->assertSee('Target editorial activity')
            ->assertDontSee('Unrelated event activity')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('activityType', '')
            ->assertSet('severity', '')
            ->assertSet('actorId', '')
            ->assertSee('Unrelated event activity');
    }

    public function test_component_guards_page_size_and_displays_activity_details(): void
    {
        $activity = SystemActivity::factory()->forActor($this->administrator)->create([
            'activity_type' => 'settings.updated',
            'description' => 'Workspace settings updated',
            'properties' => ['palette' => 'teal'],
        ]);

        Livewire::actingAs($this->administrator)
            ->test(SystemActivityIndex::class)
            ->set('perPage', 999)
            ->assertSet('perPage', 25)
            ->call('openDetails', $activity->getKey())
            ->assertSet('showDetails', true)
            ->assertSet('selectedActivityId', $activity->getKey())
            ->assertSee('Activity details')
            ->assertSee('Workspace settings updated')
            ->assertSee('teal')
            ->call('closeDetails')
            ->assertSet('showDetails', false)
            ->assertSet('selectedActivityId', null);
    }
}
