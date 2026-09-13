<?php

namespace Tests\Feature\InstitutionDetails;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\InstitutionDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionProfileResolverTest extends TestCase
{
    use RefreshDatabase;

    private ResolvesInstitutionProfile $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiles = app(ResolvesInstitutionProfile::class);
        $this->profiles->forget();
    }

    public function test_config_values_are_used_before_the_database_profile_exists(): void
    {
        config([
            'institution.defaults.name' => 'Fallback Institution',
            'institution.defaults.short_name' => 'Fallback',
            'institution.defaults.primary_email' => 'fallback@example.test',
        ]);

        $profile = $this->profiles->current();

        $this->assertNull($profile->id);
        $this->assertSame('Fallback Institution', $profile->name);
        $this->assertSame('Fallback', $profile->shortName);
        $this->assertSame('fallback@example.test', $profile->primaryEmail);
    }

    public function test_database_values_override_fallbacks_and_can_be_refreshed_explicitly(): void
    {
        $detail = InstitutionDetail::factory()->create([
            'id' => InstitutionDetail::PRIMARY_ID,
            'name' => 'Aureon Prime',
            'short_name' => 'Aureon',
            'physical_address' => '1 Corporate Way',
            'city' => 'Nairobi',
            'county' => 'Nairobi County',
        ]);

        $this->assertSame('Aureon Prime', $this->profiles->current()->name);
        $this->assertSame('1 Corporate Way, Nairobi, Nairobi County', $this->profiles->current()->address());

        $detail->update(['name' => 'Aureon Group']);
        $this->assertSame('Aureon Prime', $this->profiles->current()->name);

        $this->profiles->forget();

        $this->assertSame('Aureon Group', $this->profiles->current()->name);
    }
}
