<?php

/**
 * Verifies rate plan, fare, pricing rule, and promotion administration end to end.
 */

declare(strict_types=1);

namespace Tests\Feature\TravelTours;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Database\Seeders\TravelToursAccessSeeder;
use App\Modules\TravelTours\Pricing\Data\ParticipantRateData;
use App\Modules\TravelTours\Pricing\Data\PricingRuleData;
use App\Modules\TravelTours\Pricing\Data\PromotionData;
use App\Modules\TravelTours\Pricing\Data\RatePlanData;
use App\Modules\TravelTours\Pricing\Enums\AdjustmentType;
use App\Modules\TravelTours\Pricing\Enums\PricingRuleType;
use App\Modules\TravelTours\Pricing\Exceptions\InvalidRateConfiguration;
use App\Modules\TravelTours\Pricing\Livewire\Admin\PricingRuleManager;
use App\Modules\TravelTours\Pricing\Livewire\Admin\PromotionManager;
use App\Modules\TravelTours\Pricing\Livewire\Admin\RatePlanManager;
use App\Modules\TravelTours\Pricing\Models\ParticipantRate;
use App\Modules\TravelTours\Pricing\Models\PricingRule;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Pricing\Services\PricingRuleService;
use App\Modules\TravelTours\Pricing\Services\PromotionService;
use App\Modules\TravelTours\Pricing\Services\RatePlanService;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Storefront\Livewire\DepartureSelector;
use App\Modules\TravelTours\Support\TravelToursRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Prove that the services keep pricing coherent (one default, no overlapping
 * fares, ordered windows, unique codes), that the workspaces are authorized
 * per action, and that what an operator saves is what the storefront quotes.
 */
final class TravelToursPricingAdministrationTest extends TestCase
{
    use RefreshDatabase;

    /** Enable the module with the real role grants. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('r', 32)), 'travel-tours.enabled' => true]);
        $this->seed(TravelToursAccessSeeder::class);
    }

    /** One default per tour, unique codes, and a frozen currency once the plan sells. */
    public function test_rate_plans_keep_one_default_and_a_frozen_currency(): void
    {
        $tour = $this->tour();
        $service = app(RatePlanService::class);

        $standard = $service->create($tour, $this->plan('STANDARD', isDefault: true));
        $premium = $service->create($tour, $this->plan('PREMIUM', isDefault: true));

        $this->assertFalse($standard->fresh()->is_default, 'Making another plan the default clears the previous one.');
        $this->assertTrue($premium->fresh()->is_default);

        try {
            $service->create($tour, $this->plan('premium'));
            $this->fail('Duplicate codes are refused regardless of case.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertSame('Another plan on this tour already uses this code.', $exception->getMessage());
        }

        TourDeparture::factory()->create(['tour_id' => $tour->getKey(), 'rate_plan_id' => $standard->getKey()]);

        try {
            $service->update($standard, $this->plan('STANDARD', currency: 'USD'));
            $this->fail('A plan in use cannot change currency.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('cannot be changed', $exception->getMessage());
        }

        $this->expectException(InvalidRateConfiguration::class);
        $service->update($standard, $this->plan('STANDARD', isActive: false));
    }

    /** Fares are replaced atomically and overlapping windows of one type are refused. */
    public function test_fares_are_replaced_atomically_without_overlaps(): void
    {
        $tour = $this->tour();
        $service = app(RatePlanService::class);
        $plan = $service->create($tour, $this->plan('STANDARD', isDefault: true));

        $service->replaceRates($plan, [
            new ParticipantRateData(ParticipantType::Adult, 10_000_00, 18, null, null, CarbonImmutable::parse('2027-03-31')),
            new ParticipantRateData(ParticipantType::Adult, 12_000_00, 18, null, CarbonImmutable::parse('2027-04-01'), null),
            new ParticipantRateData(ParticipantType::Child, 5_000_00, 2, 17),
        ]);

        $this->assertSame(3, ParticipantRate::query()->where('rate_plan_id', $plan->getKey())->count());

        try {
            $service->replaceRates($plan, [
                new ParticipantRateData(ParticipantType::Adult, 10_000_00),
                new ParticipantRateData(ParticipantType::Adult, 12_000_00, activeFrom: CarbonImmutable::parse('2027-04-01')),
            ]);
            $this->fail('Overlapping adult fares must be refused.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('overlap', $exception->getMessage());
        }
        $this->assertSame(3, ParticipantRate::query()->where('rate_plan_id', $plan->getKey())->count(), 'A refused replacement leaves the previous fares intact.');

        $this->expectException(InvalidRateConfiguration::class);
        $service->replaceRates($plan, [new ParticipantRateData(ParticipantType::Child, 5_000_00)]);
    }

    /** A refused fare set never leaves a plan behind: plan and fares are saved as one unit. */
    public function test_plan_and_fares_are_saved_as_one_unit(): void
    {
        $tour = $this->tour();

        Livewire::actingAs($this->operator(TravelToursRole::MANAGER))
            ->test(RatePlanManager::class, ['tourId' => $tour->getKey()])
            ->call('openCreate')
            ->set('form.code', 'PEAK')
            ->set('form.name', 'Peak season')
            ->set('form.rates.0.amount', '150000.00')
            ->call('addRate', 'adult')
            ->set('form.rates.1.amount', '175000.00')
            ->call('save')
            ->assertHasErrors(['management'])
            ->assertSee('overlap in time')
            ->assertSet('dialog', 'form')
            ->set('form.rates.0.active_until', '2027-06-30')
            ->set('form.rates.1.active_from', '2027-07-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Rate plan created.');

        $this->assertSame(1, TourRatePlan::query()->where('code', 'PEAK')->count(), 'The refused first attempt created nothing.');
        $this->assertSame(2, TourRatePlan::query()->where('code', 'PEAK')->sole()->participantRates()->count());
    }

    /** Rules validate their windows and departure ownership, and are switched off rather than deleted. */
    public function test_pricing_rules_are_validated_and_deactivated_not_deleted(): void
    {
        $tour = $this->tour();
        $plan = app(RatePlanService::class)->create($tour, $this->plan('STANDARD', isDefault: true));
        $foreign = TourDeparture::factory()->create();
        $service = app(PricingRuleService::class);

        try {
            $service->create($plan, new PricingRuleData('Foreign', PricingRuleType::Seasonal, AdjustmentType::Percentage, -1000, departureId: $foreign->getKey()));
            $this->fail('A rule cannot target another tour\'s departure.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('does not belong to this tour', $exception->getMessage());
        }

        try {
            $service->create($plan, new PricingRuleData('Backwards', PricingRuleType::Seasonal, AdjustmentType::Percentage, -1000, travelStartsOn: CarbonImmutable::parse('2027-05-01'), travelEndsOn: CarbonImmutable::parse('2027-04-01')));
            $this->fail('A travel window must be ordered.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('travel window', $exception->getMessage());
        }

        $rule = $service->create($plan, new PricingRuleData('Low season', PricingRuleType::Seasonal, AdjustmentType::Percentage, -1500, priority: 10));
        $service->setActive($rule, false);

        $this->assertFalse($rule->fresh()->is_active);
        $this->assertSame(1, PricingRule::query()->count());
    }

    /** Promotions need a unique code, a currency for fixed discounts, and an explicit tour scope. */
    public function test_promotions_are_validated_and_scoped(): void
    {
        $tour = $this->tour();
        $other = $this->tour();
        $service = app(PromotionService::class);

        $promotion = $service->create(new PromotionData('save10', 'Ten off', AdjustmentType::Percentage, 1000, null, appliesToAllTours: true, excludedTourIds: [$other->getKey()]));

        $this->assertSame('SAVE10', $promotion->code);
        $this->assertSame(1, (int) $promotion->tours->first()->pivot->is_exclusion);

        try {
            $service->create(new PromotionData('SAVE10', 'Duplicate', AdjustmentType::Percentage, 500, null, appliesToAllTours: true));
            $this->fail('Duplicate codes are refused.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertSame('Another promotion already uses this code.', $exception->getMessage());
        }

        try {
            $service->create(new PromotionData('FIXED', 'No currency', AdjustmentType::Fixed, 5_000_00, null, appliesToAllTours: true));
            $this->fail('A fixed discount needs a currency.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('currency', $exception->getMessage());
        }

        try {
            $service->create(new PromotionData('SCOPED', 'No tours', AdjustmentType::Percentage, 500, null, appliesToAllTours: false));
            $this->fail('A scoped promotion needs tours.');
        } catch (InvalidRateConfiguration $exception) {
            $this->assertStringContainsString('Choose the tours', $exception->getMessage());
        }

        $scoped = $service->create(new PromotionData('SCOPED', 'Only one tour', AdjustmentType::Fixed, 5_000_00, 'kes', appliesToAllTours: false, includedTourIds: [$tour->getKey()]));

        $this->assertSame('KES', $scoped->currency);
        $this->assertSame(0, (int) $scoped->tours->first()->pivot->is_exclusion);
    }

    /** What the manager saves is what the storefront quotes, including the promotion. */
    public function test_saved_plan_and_promotion_price_the_storefront_selection(): void
    {
        $tour = $this->tour();
        $departure = TourDeparture::factory()->create(['tour_id' => $tour->getKey()]);
        $manager = $this->operator(TravelToursRole::MANAGER);

        Livewire::actingAs($manager)
            ->test(RatePlanManager::class, ['tourId' => $tour->getKey()])
            ->call('openCreate')
            ->set('form.code', 'STANDARD')
            ->set('form.name', 'Standard rate')
            ->set('form.currency', 'kes')
            ->set('form.depositValue', '30')
            ->set('form.isDefault', true)
            ->set('form.rates.0.amount', '10000.00')
            ->set('form.rates.0.minimum_age', '18')
            ->call('addRate', 'child')
            ->set('form.rates.1.amount', '5000.00')
            ->set('form.rates.1.minimum_age', '2')
            ->set('form.rates.1.maximum_age', '17')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Rate plan created.')
            ->assertSee('Standard rate');

        $plan = TourRatePlan::query()->sole();
        $this->assertSame(3000, $plan->deposit_value);
        $this->assertTrue($plan->is_default);
        $this->assertSame(2, $plan->participantRates()->count());

        Livewire::actingAs($manager)
            ->test(PromotionManager::class)
            ->call('openCreate')
            ->set('form.code', 'launch10')
            ->set('form.name', 'Launch offer')
            ->set('form.adjustmentValue', '10')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Promotion created.')
            ->assertSee('LAUNCH10');

        Livewire::test(DepartureSelector::class, ['tour' => $tour])
            ->assertSet('departureId', $departure->getKey())
            ->set('form.adults', '2')
            ->set('form.children', '1')
            ->set('form.promotionCode', 'launch10')
            ->assertHasNoErrors()
            ->assertSee('Launch offer')
            ->assertSee('KES 2,500.00')
            ->assertSee('KES 22,500.00');
    }

    /** The rule manager validates, creates, and toggles rules for managers; editors may look but not touch. */
    public function test_rule_manager_is_authorized_per_action(): void
    {
        $tour = $this->tour();
        $plan = app(RatePlanService::class)->create($tour, $this->plan('STANDARD', isDefault: true));

        Livewire::actingAs($this->operator(TravelToursRole::MANAGER))
            ->test(PricingRuleManager::class, ['tourId' => $tour->getKey()])
            ->call('openCreate')
            ->assertSet('form.ratePlanId', (string) $plan->getKey())
            ->set('form.name', 'Group discount')
            ->set('form.ruleType', 'group')
            ->set('form.adjustmentValue', '-12.5')
            ->set('form.minimumParticipants', '6')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Pricing rule created.')
            ->assertSee('−12.50%');

        $rule = PricingRule::query()->sole();
        $this->assertSame(-1250, $rule->adjustment_value);

        Livewire::actingAs($this->operator(TravelToursRole::TOUR_EDITOR))
            ->test(PricingRuleManager::class, ['tourId' => $tour->getKey()])
            ->assertSee('Group discount')
            ->assertDontSee('Add rule')
            ->call('toggleActive', $rule->getKey())
            ->assertForbidden();

        Livewire::actingAs($this->operator(TravelToursRole::BOOKING_AGENT))
            ->test(PricingRuleManager::class, ['tourId' => $tour->getKey()])
            ->call('openCreate')
            ->assertForbidden();
    }

    /** Create a published tour. */
    private function tour(): Tour
    {
        return Tour::factory()->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()]);
    }

    /** Build typed plan input. */
    private function plan(string $code, string $currency = 'KES', bool $isDefault = false, bool $isActive = true): RatePlanData
    {
        return new RatePlanData($code, ucfirst(strtolower($code)).' plan', $currency, true, 0, 'percentage', 3000, 45, true, $isActive, true, $isDefault, 1, 20);
    }

    /** Create an active non-administrator operator holding one module role. */
    private function operator(string $role): User
    {
        $user = User::factory()->create(['user_type' => UserType::Viewer, 'is_active' => true]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
