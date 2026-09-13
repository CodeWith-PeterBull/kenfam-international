<?php

namespace Tests\Feature\UserManagement;

use App\Enums\IdentificationType;
use App\Enums\UserType;
use App\Livewire\Admin\UserManagement;
use App\Models\User;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->withProfile([
            'first_name' => 'Aureon',
            'last_name' => 'Administrator',
        ])->create(['user_type' => UserType::SystemAdministrator]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_user_directory_requires_the_view_permission(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        Livewire::actingAs($user)->test(UserManagement::class)->assertForbidden();

        $user->givePermissionTo(CmsPermission::VIEW_USERS);
        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
        Livewire::actingAs($user)->test(UserManagement::class)->assertOk()->assertSee('Account directory');
        Livewire::actingAs($user)->test(UserManagement::class)->call('openCreate')->assertForbidden();
    }

    public function test_administrator_can_create_a_profiled_user_with_photo_and_custom_role(): void
    {
        Storage::fake('public');
        Role::findOrCreate('communications-lead', 'web');

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->set('form.name', 'aureon.editor')
            ->set('form.email', 'editor@aureon.test')
            ->set('form.password', 'AureonPassword123!')
            ->set('form.password_confirmation', 'AureonPassword123!')
            ->set('form.userType', UserType::Editor->value)
            ->set('form.firstName', 'Njeri')
            ->set('form.middleName', 'Wambui')
            ->set('form.lastName', 'Mwangi')
            ->set('form.dateOfBirth', '1990-08-21')
            ->set('form.identificationType', IdentificationType::NationalId->value)
            ->set('form.identificationNumber', 'AUR-USER-001')
            ->set('form.phone', '+254 700 100 200')
            ->set('form.jobTitle', 'Corporate editor')
            ->set('form.bio', 'Maintains corporate editorial standards.')
            ->set('form.additionalRoles', ['communications-lead'])
            ->set('profilePhotoUpload', UploadedFile::fake()->image('editor.png', 320, 320))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('User account created successfully.');

        $user = User::query()->where('email', 'editor@aureon.test')->firstOrFail();
        $this->assertSame(UserType::Editor, $user->user_type);
        $this->assertTrue($user->two_factor_enabled);
        $this->assertSame('Njeri Wambui Mwangi', $user->load('profile')->display_name);
        $this->assertTrue($user->hasExactRoles(['editor', 'communications-lead']));
        $this->assertTrue($user->hasMedia('profile_photo'));
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'user.created',
            'user_id' => $this->administrator->id,
            'subject_id' => (string) $user->id,
            'source' => 'user-management',
        ]);
    }

    public function test_two_factor_is_enabled_by_default_and_can_be_managed_safely(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->assertSet('form.twoFactorEnabled', true);

        $user = User::factory()->twoFactorEnabled()->withProfile()->create([
            'user_type' => UserType::Viewer,
            'two_factor_code_hash' => str_repeat('a', 64),
            'two_factor_code_expires_at' => now()->addMinutes(10),
        ]);
        $user->assignRole(UserType::Viewer->value);
        $user->twoFactorSessions()->create([
            'session_id' => str_repeat('s', 40),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Aureon test suite',
            'verified_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openEdit', $user->id)
            ->assertSet('form.twoFactorEnabled', true)
            ->set('form.twoFactorEnabled', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('User account updated successfully.');

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_code_hash);
        $this->assertNull($user->two_factor_code_expires_at);
        $this->assertDatabaseMissing('two_factor_sessions', ['user_id' => $user->id]);
    }

    public function test_administrator_can_update_profile_access_and_email_verification_state(): void
    {
        $user = User::factory()->withProfile([
            'first_name' => 'Initial',
            'last_name' => 'Person',
        ])->create(['user_type' => UserType::Viewer]);
        $user->assignRole(UserType::Viewer->value);

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openEdit', $user->id)
            ->set('form.email', 'updated@aureon.test')
            ->set('form.firstName', 'Updated')
            ->set('form.middleName', '')
            ->set('form.lastName', 'Professional')
            ->set('form.userType', UserType::ContentManager->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('User account updated successfully.');

        $user->refresh()->load(['profile', 'roles']);
        $this->assertSame('updated@aureon.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(UserType::ContentManager, $user->user_type);
        $this->assertSame(['content-manager'], $user->getRoleNames()->all());
        $this->assertSame('Updated Professional', $user->display_name);
    }

    public function test_validation_rejects_duplicate_identity_and_unsupported_photo(): void
    {
        User::factory()->withProfile(['identification_number' => 'DUPLICATE-ID'])->create();

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openCreate')
            ->set('form.name', 'validation.user')
            ->set('form.email', 'validation@aureon.test')
            ->set('form.password', 'AureonPassword123!')
            ->set('form.password_confirmation', 'AureonPassword123!')
            ->set('form.firstName', 'Validation')
            ->set('form.lastName', 'User')
            ->set('form.identificationType', IdentificationType::NationalId->value)
            ->set('form.identificationNumber', 'DUPLICATE-ID')
            ->set('profilePhotoUpload', UploadedFile::fake()->create('payload.pdf', 50, 'application/pdf'))
            ->assertHasErrors('profilePhotoUpload')
            ->set('profilePhotoUpload', null)
            ->call('save')
            ->assertHasErrors('form.identificationNumber');

        $this->assertDatabaseMissing('users', ['email' => 'validation@aureon.test']);
    }

    public function test_administrator_can_replace_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->withProfile()->create();
        $user->addMedia(UploadedFile::fake()->image('existing.jpg', 200, 200))->toMediaCollection('profile_photo');

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openEdit', $user->id)
            ->set('profilePhotoUpload', UploadedFile::fake()->image('replacement.webp', 240, 240))
            ->call('save')
            ->assertHasNoErrors();

        $replacement = $user->refresh()->getFirstMedia('profile_photo');
        $this->assertNotNull($replacement);
        $this->assertSame('webp', pathinfo($replacement->file_name, PATHINFO_EXTENSION));
        $this->assertSame(1, $user->getMedia('profile_photo')->count());

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openEdit', $user->id)
            ->set('form.removeProfilePhoto', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($user->refresh()->hasMedia('profile_photo'));
    }

    public function test_own_account_and_last_administrator_safeguards_are_enforced(): void
    {
        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('toggleStatus', $this->administrator->id)
            ->assertHasErrors('management')
            ->call('confirmDelete', $this->administrator->id)
            ->call('delete')
            ->assertHasErrors('management');

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('openEdit', $this->administrator->id)
            ->set('form.userType', UserType::Viewer->value)
            ->call('save')
            ->assertHasErrors('management');

        $this->assertTrue($this->administrator->refresh()->is_active);
        $this->assertSame(UserType::SystemAdministrator, $this->administrator->user_type);
        $this->assertNotNull($this->administrator->fresh());
    }

    public function test_administrator_can_deactivate_and_delete_another_user_with_audit_history(): void
    {
        $user = User::factory()->withProfile()->create();
        $user->assignRole(UserType::Viewer->value);

        Livewire::actingAs($this->administrator)
            ->test(UserManagement::class)
            ->call('toggleStatus', $user->id)
            ->assertHasNoErrors()
            ->call('confirmDelete', $user->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'user.deactivated']);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'user.deleted']);
    }
}
