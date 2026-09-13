<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Models\SystemActivity;
use App\Models\User;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Verifies guest normalization, protected identity, lookup, and safe activity. */
final class GuestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_is_encrypted_fingerprinted_searchable_and_never_audited_raw(): void
    {
        $actor = User::factory()->create();
        $service = app(GuestService::class);
        $guest = $service->create([
            'first_name' => ' Amina ',
            'last_name' => ' Njeri ',
            'email' => 'AMINA@EXAMPLE.TEST',
            'phone' => '+254700000222',
            'country_code' => 'ke',
            'identity_type' => 'passport',
            'identity_number' => 'A 123-4567',
            'identity_country_code' => 'ke',
        ], $actor);

        $this->assertSame('Amina', $guest->first_name);
        $this->assertSame('amina@example.test', $guest->email);
        $this->assertSame('KE', $guest->country_code);
        $this->assertNotSame('A 123-4567', $guest->getRawOriginal('identity_number_ciphertext'));
        $this->assertSame(64, strlen($guest->getRawOriginal('identity_number_hash')));
        $this->assertSame('A 123-4567', $service->revealIdentity($guest));
        $this->assertTrue($service->findByIdentity('passport', 'a1234567')?->is($guest));

        $activity = SystemActivity::query()->where('activity_type', 'property-booking.guest.created')->sole();
        $this->assertStringNotContainsString('A 123-4567', json_encode($activity->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('a1234567', strtolower(json_encode($activity->toArray(), JSON_THROW_ON_ERROR)));
    }
}
