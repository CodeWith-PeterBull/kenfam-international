<?php

namespace Tests\Feature\Communications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\InstitutionDetail;
use App\Models\User;
use App\Notifications\SystemTestMailNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MailNotificationTemplateTest extends TestCase
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

    public function test_template_page_and_mail_preview_require_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.communication-templates.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.communication-templates.mail-preview'))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->get(route('admin.communication-templates.index'))
            ->assertOk()
            ->assertSee('Communication templates')
            ->assertSee('Portrait report')
            ->assertSee('Landscape report');
    }

    public function test_mail_preview_uses_the_database_driven_institution_identity(): void
    {
        InstitutionDetail::factory()->create([
            'id' => InstitutionDetail::PRIMARY_ID,
            'name' => 'Aureon Prime Holdings',
            'short_name' => 'Aureon Prime',
            'primary_email' => 'hello@aureon-prime.test',
        ]);
        app(ResolvesInstitutionProfile::class)->forget();

        $this->actingAs($this->administrator)
            ->get(route('admin.communication-templates.mail-preview'))
            ->assertOk()
            ->assertSee('Aureon Prime Holdings')
            ->assertSee('Hello '.$this->administrator->name)
            ->assertSee('Institution Details integration');
    }

    public function test_authorized_administrator_can_send_an_audited_on_demand_test_notification(): void
    {
        Notification::fake();

        $this->actingAs($this->administrator)
            ->post(route('admin.communication-templates.test-mail'), ['email' => 'qa@aureon.test'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Test notification sent successfully.');

        Notification::assertSentOnDemand(
            SystemTestMailNotification::class,
            static fn (
                SystemTestMailNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ): bool => $channels === ['mail'] && $notifiable->routes['mail'] === 'qa@aureon.test',
        );

        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'communication.test_mail_sent',
            'user_id' => $this->administrator->id,
            'source' => 'mail-notifications',
        ]);
    }
}
