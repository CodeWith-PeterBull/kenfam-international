<?php

namespace Tests\Feature\SystemActivity;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\SystemActivity;
use App\Models\User;
use App\Services\SystemActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class SystemActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recorder_contract_resolves_to_the_default_service(): void
    {
        $recorder = app(RecordsSystemActivity::class);

        $this->assertInstanceOf(SystemActivityService::class, $recorder);
        $this->assertSame($recorder, app(RecordsSystemActivity::class));
    }

    public function test_it_records_web_context_subjects_and_sanitized_properties(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $batchUuid = (string) Str::uuid();

        Route::post('/_activity-test', function (RecordsSystemActivity $recorder) use ($actor, $batchUuid, $subject) {
            $activity = $recorder->record(
                activityType: 'user.profile_updated',
                description: 'Administrator updated a user profile',
                actor: $actor,
                subject: $subject,
                properties: [
                    'changes' => ['name' => ['before' => 'Old', 'after' => 'New']],
                    'password' => 'plain-text-password',
                    'nested' => ['access_token' => 'secret-token', 'safe' => 'retained'],
                ],
                severity: SystemActivitySeverity::Notice,
                batchUuid: $batchUuid,
            );

            return response()->json(['activity_id' => $activity->getKey()]);
        })->name('test.activity.store');

        $response = $this
            ->actingAs($actor)
            ->withHeader('User-Agent', 'Aureon Activity Test Agent')
            ->postJson('/_activity-test?token=must-not-be-persisted');

        $response->assertOk();

        $activity = SystemActivity::query()->findOrFail($response->json('activity_id'));

        $this->assertSame($actor->getKey(), $activity->user_id);
        $this->assertSame('user.profile_updated', $activity->activity_type);
        $this->assertSame(SystemActivitySeverity::Notice, $activity->severity);
        $this->assertSame('web', $activity->source);
        $this->assertSame($subject->getMorphClass(), $activity->subject_type);
        $this->assertSame((string) $subject->getKey(), $activity->subject_id);
        $this->assertSame('POST', $activity->request_method);
        $this->assertSame('test.activity.store', $activity->route_name);
        $this->assertSame(rtrim(config('app.url'), '/').'/_activity-test', $activity->request_url);
        $this->assertSame('Aureon Activity Test Agent', $activity->user_agent);
        $this->assertSame($batchUuid, $activity->batch_uuid);
        $this->assertSame('[REDACTED]', $activity->properties['password']);
        $this->assertSame('[REDACTED]', $activity->properties['nested']['access_token']);
        $this->assertSame('retained', $activity->properties['nested']['safe']);
        $this->assertSame('New', $activity->properties['changes']['name']['after']);
        $this->assertTrue($activity->actor->is($actor));
        $this->assertTrue($activity->subject->is($subject));
    }

    public function test_it_records_console_safe_system_activity_without_request_metadata(): void
    {
        $activity = app(RecordsSystemActivity::class)->record(
            activityType: 'scheduler.completed',
            description: 'Scheduled content maintenance completed',
            properties: ['processed' => 12],
        );

        $this->assertNull($activity->user_id);
        $this->assertSame('console', $activity->source);
        $this->assertNull($activity->ip_address);
        $this->assertNull($activity->request_method);
        $this->assertNull($activity->request_url);
        $this->assertSame(12, $activity->properties['processed']);
    }

    public function test_it_rejects_invalid_event_names_and_unpersisted_subjects(): void
    {
        $recorder = app(RecordsSystemActivity::class);

        try {
            $recorder->record('Invalid Event Name', 'Invalid event');
            $this->fail('An invalid event name was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('lowercase dot notation', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be persisted');

        $recorder->record(
            activityType: 'user.created',
            description: 'Attempted to record an unpersisted subject',
            subject: new User,
        );
    }

    public function test_model_scopes_filter_the_activity_stream(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Auditor']);
        $bob = User::factory()->create(['name' => 'Bob Publisher']);

        SystemActivity::factory()->forActor($alice)->create([
            'activity_type' => 'post.published',
            'severity' => SystemActivitySeverity::Notice,
            'description' => 'Annual report published',
            'created_at' => now(),
        ]);
        SystemActivity::factory()->forActor($bob)->create([
            'activity_type' => 'event.deleted',
            'severity' => SystemActivitySeverity::Warning,
            'description' => 'Old event removed',
            'created_at' => now()->subDays(10),
        ]);

        $matches = SystemActivity::query()
            ->search('Alice')
            ->ofType('post.published')
            ->ofSeverity(SystemActivitySeverity::Notice->value)
            ->byActor($alice->getKey())
            ->occurredBetween(now()->subDay()->toDateString(), now()->toDateString())
            ->get();

        $this->assertCount(1, $matches);
        $this->assertSame('Annual report published', $matches->first()->description);
    }
}
