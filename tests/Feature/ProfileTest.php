<?php

namespace Tests\Feature;

use App\Enums\IdentificationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'Person',
                'phone' => '+254 700 000 001',
            ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Test', $user->profile->first_name);
        $this->assertSame('+254 700 000 001', $user->profile->phone);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', $this->profilePayload($user, [
                'name' => 'Test User',
                'email' => $user->email,
            ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_information_and_photo_can_be_created_and_replaced(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/profile', $this->profilePayload($user, [
            'first_name' => 'Amina',
            'middle_name' => 'Wanjiru',
            'last_name' => 'Kamau',
            'date_of_birth' => '1992-05-14',
            'identification_type' => IdentificationType::Passport->value,
            'identification_number' => 'P-AUREON-1001',
            'phone' => '+254 711 222 333',
            'job_title' => 'Communications Director',
            'bio' => 'Leads corporate communication programmes.',
            'profile_photo' => UploadedFile::fake()->image('profile.jpg', 300, 300),
        ]));

        $response->assertSessionHasNoErrors()->assertRedirect('/profile');
        $user->refresh()->load(['profile', 'media']);

        $this->assertSame('Amina Wanjiru Kamau', $user->display_name);
        $this->assertSame(IdentificationType::Passport, $user->profile->identification_type);
        $this->assertTrue($user->hasMedia('profile_photo'));
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'user.profile_updated',
            'user_id' => $user->id,
            'source' => 'profile',
        ]);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    /** @return array<string, mixed> */
    private function profilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'first_name' => 'Profile',
            'middle_name' => null,
            'last_name' => 'User',
            'date_of_birth' => null,
            'identification_type' => null,
            'identification_number' => null,
            'phone' => null,
            'job_title' => null,
            'bio' => null,
        ], $overrides);
    }
}
