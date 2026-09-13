<?php

namespace Tests\Feature\InstitutionDetails;

use App\Livewire\Admin\InstitutionDetailsEditor;
use App\Models\InstitutionDetail;
use App\Models\SystemActivity;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionDetailsManagementTest extends TestCase
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

    public function test_guests_and_users_without_permission_cannot_access_institution_details(): void
    {
        $this->get(route('admin.institution-details.index'))
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.institution-details.index'))
            ->assertForbidden();

        Livewire::actingAs($user)
            ->test(InstitutionDetailsEditor::class)
            ->assertForbidden();
    }

    public function test_administrator_can_view_and_update_institution_details_with_brand_media(): void
    {
        Storage::fake('public');

        $component = Livewire::actingAs($this->administrator)
            ->test(InstitutionDetailsEditor::class)
            ->set('form.name', 'Aureon Group Limited')
            ->set('form.shortName', 'Aureon')
            ->set('form.descriptor', 'Corporate leadership and advisory')
            ->set('form.primaryEmail', 'hello@aureon.test')
            ->set('form.website', 'https://aureon.test')
            ->set('form.city', 'Nairobi')
            ->set('form.socialMedia', [[
                'platform' => 'LinkedIn',
                'handle' => '@aureon',
                'url' => 'https://www.linkedin.com/company/aureon',
            ]])
            ->set('mainLogoUpload', UploadedFile::fake()->image('aureon-logo.png', 420, 120))
            ->call('save');

        $component
            ->assertHasNoErrors()
            ->assertSee('Institution details saved successfully.');

        $detail = InstitutionDetail::query()->findOrFail(InstitutionDetail::PRIMARY_ID);

        $this->assertSame('Aureon Group Limited', $detail->name);
        $this->assertSame('hello@aureon.test', $detail->primary_email);
        $this->assertSame('LinkedIn', $detail->social_media[0]['platform']);
        $this->assertTrue($detail->hasMedia('main_logo'));
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'institution_detail.created',
            'user_id' => $this->administrator->id,
            'source' => 'institution-details',
        ]);
    }

    public function test_social_profile_urls_are_validated(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(InstitutionDetailsEditor::class)
            ->set('form.name', 'Aureon Group')
            ->set('form.shortName', 'Aureon')
            ->set('form.socialMedia', [[
                'platform' => 'LinkedIn',
                'handle' => '@aureon',
                'url' => 'not-a-url',
            ]])
            ->call('save')
            ->assertHasErrors(['form.socialMedia.0.url']);

        $this->assertSame(0, SystemActivity::query()->count());
    }
}
