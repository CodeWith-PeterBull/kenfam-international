<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Database\Seeders;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChargeType;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Seeds a deterministic cross-model booking and reception demonstration graph. */
final class PropertyBookingOperationsDemoSeeder extends Seeder
{
    private const ACTIVE_BLOCK_REASON = 'Demonstration preventive maintenance';

    private const CLOSED_SHIFT_NOTE = 'Property Booking demonstration reception shift';

    private const WEB_BOOKING_NUMBER = 'DEMO-WEB-0001';

    private const POB_BOOKING_NUMBER = 'DEMO-POB-0001';

    /** Seed operational examples only after the catalog and access fixtures exist. */
    public function run(): void
    {
        $property = Property::query()->where('code', 'AUREON-CITY')->firstOrFail();
        $manager = User::query()->where('email', PropertyBookingAccessDemoSeeder::MANAGER_EMAIL)->firstOrFail();
        $receptionist = User::query()->where('email', PropertyBookingAccessDemoSeeder::RECEPTIONIST_EMAIL)->firstOrFail();
        $register = ReceptionRegister::query()->where('property_id', $property->getKey())->where('code', 'MAIN-DESK')->firstOrFail();

        DB::transaction(function () use ($property, $manager, $receptionist, $register): void {
            $this->seedRateOverrides($property, $manager);
            $this->seedAvailabilityBlock($property, $manager);
            $guests = $this->seedGuests($manager);
            $shift = $this->seedClosedShift($property, $register, $receptionist, $manager);

            $webBooking = $this->seedBooking(
                number: self::WEB_BOOKING_NUMBER,
                property: $property,
                guest: $guests['web'],
                startsAt: now()->addDays(14)->setTime(11, 0),
                endsAt: now()->addDays(16)->setTime(8, 0),
                channel: BookingChannel::Web,
                status: BookingStatus::Confirmed,
                stayStatus: StayStatus::Expected,
                paymentStatus: BookingPaymentStatus::Partial,
                accommodationSubtotalMinor: 2_500_000,
                chargesSubtotalMinor: 0,
                totalMinor: 2_500_000,
                requiredDepositMinor: 1_250_000,
                paidMinor: 1_250_000,
                manager: $manager,
            );
            $webStay = $this->seedStay($webBooking, 'DELUXE-KING', 2, 1_250_000);
            $this->seedGuestAssignment($webBooking, $webStay, $guests['web']);
            $this->seedUnitAssignment($webBooking, $webStay, '101', $manager, false);
            $this->seedPayment($webBooking, null, $manager, 'DEMO-WEB-DEPOSIT-0001', 1_250_000, BookingPaymentMethod::MobileMoney);

            $pobBooking = $this->seedBooking(
                number: self::POB_BOOKING_NUMBER,
                property: $property,
                guest: $guests['desk'],
                startsAt: now()->subDays(4)->setTime(11, 0),
                endsAt: now()->subDays(3)->setTime(8, 0),
                channel: BookingChannel::PointOfBooking,
                status: BookingStatus::Completed,
                stayStatus: StayStatus::CheckedOut,
                paymentStatus: BookingPaymentStatus::Paid,
                accommodationSubtotalMinor: 1_750_000,
                chargesSubtotalMinor: 250_000,
                totalMinor: 2_000_000,
                requiredDepositMinor: 875_000,
                paidMinor: 2_000_000,
                manager: $manager,
                register: $register,
                shift: $shift,
                receptionist: $receptionist,
            );
            $pobStay = $this->seedStay($pobBooking, 'CITY-STUDIO', 1, 1_750_000);
            $this->seedGuestAssignment($pobBooking, $pobStay, $guests['desk'], true);
            $this->seedUnitAssignment($pobBooking, $pobStay, '201', $receptionist, true);
            $this->seedCharge($pobBooking, $pobStay, $shift, $receptionist);
            $this->seedPayment($pobBooking, $shift, $receptionist, 'DEMO-POB-PAYMENT-0001', 2_000_000, BookingPaymentMethod::Card);
        });
    }

    /** Seed one bounded seasonal override for every demonstration rate. */
    private function seedRateOverrides(Property $property, User $actor): void
    {
        foreach ($property->ratePlans()->get() as $ratePlan) {
            $reason = "Demonstration weekend rate: {$ratePlan->unitType->code}";
            $override = RateOverride::query()->where('rate_plan_id', $ratePlan->getKey())->where('reason', $reason)->first() ?? new RateOverride;
            $override->fill([
                'rate_plan_id' => $ratePlan->getKey(),
                'starts_on' => today()->addDays(45)->toDateString(),
                'ends_on' => today()->addDays(48)->toDateString(),
                'rate_minor' => (int) round($ratePlan->base_rate_minor * 1.12),
                'is_closed' => false,
                'closed_on_arrival' => false,
                'closed_on_departure' => false,
                'minimum_units' => 2,
                'maximum_units' => null,
                'reason' => $reason,
            ]);
            $override->forceFill([
                'created_by' => $override->created_by ?? $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();
        }
    }

    /** Seed one active maintenance block away from the demonstration booking. */
    private function seedAvailabilityBlock(Property $property, User $actor): void
    {
        $unit = AccommodationUnit::query()->where('property_id', $property->getKey())->where('code', '302')->firstOrFail();
        $block = AvailabilityBlock::query()->where('unit_id', $unit->getKey())->where('reason', self::ACTIVE_BLOCK_REASON)->first() ?? new AvailabilityBlock;
        $block->fill([
            'property_id' => $property->getKey(),
            'unit_id' => $unit->getKey(),
            'type' => AvailabilityBlockType::Maintenance,
            'starts_at' => now()->addDays(21)->setTime(6, 0),
            'ends_at' => now()->addDays(22)->setTime(15, 0),
            'reason' => self::ACTIVE_BLOCK_REASON,
            'internal_note' => 'Fixture demonstrating an auditable concrete-unit inventory block.',
        ]);
        $block->forceFill([
            'status' => AvailabilityBlockStatus::Active,
            'created_by' => $actor->getKey(),
            'released_by' => null,
            'released_at' => null,
        ])->save();
    }

    /** @return array{web: Guest, desk: Guest} */
    private function seedGuests(User $actor): array
    {
        return [
            'web' => $this->seedGuest($actor, [
                'title' => 'Ms', 'first_name' => 'Wanjiku', 'middle_name' => 'Njeri', 'last_name' => 'Kamau',
                'email' => 'wanjiku.kamau@example.test', 'phone' => '+254711000101',
                'date_of_birth' => '1991-04-18', 'nationality_country_code' => 'KE',
                'address_line_1' => '14 Riverside Drive', 'city' => 'Nairobi', 'region' => 'Nairobi',
                'postal_code' => '00100', 'country_code' => 'KE',
                'emergency_contact_name' => 'Daniel Kamau', 'emergency_contact_phone' => '+254711000102',
                'note' => 'Public storefront demonstration guest.',
            ]),
            'desk' => $this->seedGuest($actor, [
                'title' => 'Mr', 'first_name' => 'David', 'middle_name' => null, 'last_name' => 'Otieno',
                'email' => 'david.otieno@example.test', 'phone' => '+254722000201',
                'date_of_birth' => '1987-09-03', 'nationality_country_code' => 'KE',
                'address_line_1' => '22 Oginga Odinga Street', 'city' => 'Kisumu', 'region' => 'Kisumu',
                'postal_code' => '40100', 'country_code' => 'KE',
                'emergency_contact_name' => 'Achieng Otieno', 'emergency_contact_phone' => '+254722000202',
                'note' => 'Reception booking demonstration guest.',
            ]),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function seedGuest(User $actor, array $attributes): Guest
    {
        $guest = Guest::withTrashed()->where('email', $attributes['email'])->first() ?? new Guest;
        if ($guest->trashed()) {
            $guest->restore();
        }
        $guest->fill([
            'user_id' => null,
            ...$attributes,
            'alternate_phone' => null,
            'identity_type' => null,
            'identity_country_code' => null,
            'address_line_2' => null,
        ]);
        $guest->forceFill([
            'created_by' => $guest->created_by ?? $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ])->save();

        return $guest;
    }

    /** Seed a reconciled shift so POB relationships are realistic without blocking a live register. */
    private function seedClosedShift(Property $property, ReceptionRegister $register, User $receptionist, User $manager): ReceptionShift
    {
        $shift = ReceptionShift::query()->where('opening_note', self::CLOSED_SHIFT_NOTE)->first() ?? new ReceptionShift;
        $shift->forceFill([
            'property_id' => $property->getKey(),
            'register_id' => $register->getKey(),
            'receptionist_id' => $receptionist->getKey(),
            'register_open_guard' => null,
            'receptionist_open_guard' => null,
            'status' => ReceptionShiftStatus::Closed,
            'currency' => $property->currency,
            'opening_float_minor' => 100_000,
            'expected_cash_minor' => 100_000,
            'counted_cash_minor' => 100_000,
            'variance_minor' => 0,
            'opening_note' => self::CLOSED_SHIFT_NOTE,
            'closing_note' => 'Balanced demonstration shift.',
            'opened_by' => $manager->getKey(),
            'opened_at' => now()->subDays(4)->setTime(6, 0),
            'closed_by' => $manager->getKey(),
            'closed_at' => now()->subDays(3)->setTime(18, 0),
        ])->save();

        return $shift;
    }

    /** Seed one complete booking aggregate snapshot. */
    private function seedBooking(
        string $number,
        Property $property,
        Guest $guest,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        BookingChannel $channel,
        BookingStatus $status,
        StayStatus $stayStatus,
        BookingPaymentStatus $paymentStatus,
        int $accommodationSubtotalMinor,
        int $chargesSubtotalMinor,
        int $totalMinor,
        int $requiredDepositMinor,
        int $paidMinor,
        User $manager,
        ?ReceptionRegister $register = null,
        ?ReceptionShift $shift = null,
        ?User $receptionist = null,
    ): Booking {
        $booking = Booking::query()->where('booking_number', $number)->first() ?? new Booking;
        $completed = $status === BookingStatus::Completed;
        $booking->forceFill([
            'booking_number' => $number,
            'channel' => $channel,
            'property_id' => $property->getKey(),
            'primary_guest_id' => $guest->getKey(),
            'register_id' => $register?->getKey(),
            'reception_shift_id' => $shift?->getKey(),
            'receptionist_id' => $receptionist?->getKey(),
            'created_by' => $channel === BookingChannel::Web ? null : $manager->getKey(),
            'updated_by' => $manager->getKey(),
            'status' => $status,
            'stay_status' => $stayStatus,
            'payment_status' => $paymentStatus,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'property_timezone' => $property->timezone,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'currency' => $property->currency,
            'accommodation_subtotal_minor' => $accommodationSubtotalMinor,
            'charges_subtotal_minor' => $chargesSubtotalMinor,
            'discount_minor' => 0,
            'discount_reason' => null,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'required_deposit_minor' => $requiredDepositMinor,
            'paid_minor' => $paidMinor,
            'tax_inclusive' => true,
            'property_name' => $property->name,
            'property_code' => $property->code,
            'property_address_summary' => collect([$property->address_line_1, $property->city, $property->country_code])->filter()->join(', '),
            'guest_first_name' => $guest->first_name,
            'guest_middle_name' => $guest->middle_name,
            'guest_last_name' => $guest->last_name,
            'guest_email' => $guest->email,
            'guest_phone' => $guest->phone,
            'guest_country_code' => $guest->country_code,
            'special_requests' => $channel === BookingChannel::Web ? 'A quiet room away from the lift, where available.' : null,
            'internal_note' => $channel === BookingChannel::Web ? 'Deterministic storefront fixture.' : 'Deterministic reception fixture.',
            'external_reference' => null,
            'cancellation_reason' => null,
            'no_show_reason' => null,
            'hold_expires_at' => null,
            'pending_expires_at' => null,
            'placed_at' => $completed ? $startsAt->copy()->subDays(2) : now()->subDay(),
            'confirmed_at' => $completed ? $startsAt->copy()->subDays(2) : now()->subDay(),
            'cancelled_at' => null,
            'no_show_at' => null,
            'checked_in_at' => $completed ? $startsAt : null,
            'checked_out_at' => $completed ? $endsAt : null,
            'completed_at' => $completed ? $endsAt : null,
            'hold_expiring_notified_at' => null,
            'arrival_due_notified_at' => null,
            'departure_due_notified_at' => null,
        ])->save();

        return $booking;
    }

    /** Seed one immutable stay/rate snapshot under a booking. */
    private function seedStay(Booking $booking, string $unitTypeCode, int $billableUnits, int $unitRateMinor): BookingStay
    {
        $rate = RatePlan::query()
            ->where('property_id', $booking->property_id)
            ->whereHas('unitType', static fn ($query) => $query->where('code', $unitTypeCode))
            ->where('code', 'FLEX-NIGHT')
            ->firstOrFail();
        $stay = BookingStay::query()->where('booking_id', $booking->getKey())->where('line_number', 1)->first() ?? new BookingStay;
        $stay->forceFill([
            'booking_id' => $booking->getKey(),
            'unit_type_id' => $rate->unit_type_id,
            'rate_plan_id' => $rate->getKey(),
            'line_number' => 1,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'pricing_unit' => $rate->pricing_unit,
            'billable_units' => $billableUnits,
            'adult_count' => 2,
            'child_count' => 0,
            'infant_count' => 0,
            'unit_type_name' => $rate->unitType->name,
            'unit_type_code' => $rate->unitType->code,
            'rate_plan_name' => $rate->name,
            'rate_plan_code' => $rate->code,
            'unit_rate_minor' => $unitRateMinor,
            'extra_guest_minor' => 0,
            'subtotal_minor' => $unitRateMinor * $billableUnits,
            'discount_minor' => 0,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => $unitRateMinor * $billableUnits,
            'is_tax_inclusive' => true,
        ])->save();

        return $stay;
    }

    /** Seed the primary occupant pivot and its database uniqueness guard. */
    private function seedGuestAssignment(Booking $booking, BookingStay $stay, Guest $guest, bool $completed = false): void
    {
        DB::table('property_booking_guest_assignments')->updateOrInsert(
            ['booking_id' => $booking->getKey(), 'guest_id' => $guest->getKey()],
            [
                'booking_stay_id' => $stay->getKey(),
                'is_primary' => true,
                'primary_booking_guard' => $booking->getKey(),
                'checked_in_at' => $completed ? $booking->starts_at : null,
                'checked_out_at' => $completed ? $booking->ends_at : null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /** Seed either an active future allocation or a released historical allocation. */
    private function seedUnitAssignment(Booking $booking, BookingStay $stay, string $unitCode, User $actor, bool $released): void
    {
        $unit = AccommodationUnit::query()->where('property_id', $booking->property_id)->where('code', $unitCode)->firstOrFail();
        $assignment = UnitAssignment::query()->where('booking_stay_id', $stay->getKey())->first() ?? new UnitAssignment;
        $assignment->forceFill([
            'property_id' => $booking->property_id,
            'booking_id' => $booking->getKey(),
            'booking_stay_id' => $stay->getKey(),
            'unit_id' => $unit->getKey(),
            'status' => $released ? UnitAssignmentStatus::Released : UnitAssignmentStatus::Active,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'active_stay_guard' => $released ? null : $stay->getKey(),
            'assigned_by' => $actor->getKey(),
            'assigned_at' => $booking->confirmed_at,
            'released_by' => $released ? $actor->getKey() : null,
            'released_at' => $released ? $booking->ends_at : null,
            'release_reason' => $released ? 'Guest checked out from demonstration stay.' : null,
        ])->save();
    }

    /** Seed one posted ancillary charge attached to the reception booking. */
    private function seedCharge(Booking $booking, BookingStay $stay, ReceptionShift $shift, User $actor): void
    {
        $description = 'Demonstration airport transfer';
        $charge = BookingCharge::query()->where('booking_id', $booking->getKey())->where('description', $description)->first() ?? new BookingCharge;
        $charge->forceFill([
            'booking_id' => $booking->getKey(),
            'booking_stay_id' => $stay->getKey(),
            'reception_shift_id' => $shift->getKey(),
            'type' => BookingChargeType::Service,
            'status' => BookingChargeStatus::Posted,
            'description' => $description,
            'quantity' => 1,
            'unit_amount_minor' => 250_000,
            'subtotal_minor' => 250_000,
            'tax_rate_bps' => 0,
            'tax_minor' => 0,
            'total_minor' => 250_000,
            'is_tax_inclusive' => true,
            'posted_by' => $actor->getKey(),
            'posted_at' => $booking->checked_in_at,
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ])->save();
    }

    /** Seed one completed sanitized payment record. */
    private function seedPayment(
        Booking $booking,
        ?ReceptionShift $shift,
        User $actor,
        string $reference,
        int $amountMinor,
        BookingPaymentMethod $method,
    ): void {
        $payment = BookingPayment::query()->where('reference', $reference)->first() ?? new BookingPayment;
        $payment->forceFill([
            'booking_id' => $booking->getKey(),
            'reception_shift_id' => $shift?->getKey(),
            'recorded_by' => $actor->getKey(),
            'method' => $method,
            'status' => BookingPaymentRecordStatus::Completed,
            'currency' => $booking->currency,
            'amount_minor' => $amountMinor,
            'tendered_minor' => null,
            'change_minor' => 0,
            'refunded_amount_minor' => 0,
            'reference' => $reference,
            'metadata' => ['source' => 'property-booking-demo'],
            'paid_at' => $booking->placed_at,
            'failed_at' => null,
            'refunded_at' => null,
        ])->save();
    }
}
