<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\ReportOrientation;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Services\GuestService;
use App\Modules\PropertyBooking\PointOfBooking\Data\PobTenderData;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPaperWidth;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceiptPrintMode;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Printing\ReceiptPrinterManager;
use App\Modules\PropertyBooking\PointOfBooking\Services\BookingReceiptDataFactory;
use App\Modules\PropertyBooking\PointOfBooking\Services\BookingReceiptService;
use App\Modules\PropertyBooking\PointOfBooking\Services\PobCheckoutService;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionShiftService;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/** Verifies private receipt access, sanitized projection, PDF, and printer adapters. */
final class PropertyBookingPobReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** Seed shared roles and permissions for receipt authorization tests. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** Keep the canonical receipt privacy-safe and honor register print settings. */
    public function test_receipt_projection_masks_contact_and_builds_thermal_print_instruction(): void
    {
        [$booking, , $register] = $this->paidBooking();
        $receipt = app(BookingReceiptDataFactory::class)->fromBooking($booking);
        $serialized = json_encode($receipt, JSON_THROW_ON_ERROR);

        $this->assertSame('a***@example.test', $receipt->guestContact);
        $this->assertStringNotContainsString('amina.receipt@example.test', $serialized);
        $this->assertStringNotContainsString('+254700000777', $serialized);
        $this->assertStringNotContainsString('Staff-only arrival note', $serialized);
        $this->assertSame($booking->total_minor, $receipt->totalMinor);
        $this->assertSame($booking->paid_minor, $receipt->paidMinor);

        $instruction = app(ReceiptPrinterManager::class)->instruction($register, $receipt, true);
        $this->assertSame(ReceiptPrintMode::AutoPrompt, $instruction->mode);
        $this->assertSame(ReceiptPaperWidth::Roll58, $instruction->paperWidth);
        $this->assertTrue($instruction->autoPrompt);
        $this->assertFalse($instruction->supportsSilentPrinting);
        $this->assertSame('browser-dialog', $instruction->strategy);
        $this->assertFalse(app(ReceiptPrinterManager::class)->instruction($register, $receipt, false)->autoPrompt);
    }

    /** Restrict browser and PDF receipts to owners or property-scoped reviewers. */
    public function test_receipt_routes_enforce_ownership_scope_and_no_store_delivery(): void
    {
        [$booking, $owner, , $property] = $this->paidBooking();
        $otherReceptionist = $this->assignedUser($property, PropertyBookingPermission::ACCESS_POB);
        $reviewer = $this->assignedUser($property, PropertyBookingPermission::VIEW_BOOKINGS);
        $receiptUrl = route('property-booking.pob.receipts.show', [
            'booking' => $booking,
            'print' => 'checkout',
        ]);
        $pdfUrl = route('property-booking.pob.receipts.pdf', $booking);

        $this->get($receiptUrl)->assertRedirect(route('login'));
        $this->actingAs($otherReceptionist)->get($receiptUrl)->assertForbidden();
        $ownerResponse = $this->actingAs($owner)->get($receiptUrl)
            ->assertOk()
            ->assertSee($booking->booking_number)
            ->assertSee('data-pob-paper-width="58"', false)
            ->assertSee('"autoPrompt":true', false);
        $this->assertStringContainsString('no-store', (string) $ownerResponse->headers->get('Cache-Control'));
        $this->actingAs($reviewer)->get($receiptUrl)->assertOk();
        $this->actingAs($owner)->get($pdfUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'property-booking.pob-receipt.rendered',
            'subject_id' => $booking->id,
        ]);
    }

    /** Reject landscape output because thermal POB receipts are portrait-only. */
    public function test_receipt_pdf_adapter_rejects_landscape_orientation(): void
    {
        [$booking] = $this->paidBooking();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('portrait orientation only');
        app(BookingReceiptService::class)->stream($booking, orientation: ReportOrientation::Landscape);
    }

    /** @return array{Booking, User, ReceptionRegister, Property} */
    private function paidBooking(): array
    {
        $property = Property::factory()->published()->create([
            'timezone' => 'Africa/Nairobi',
            'currency' => 'KES',
            'minimum_notice_minutes' => 0,
            'maximum_advance_days' => 365,
            'turnover_minutes' => 0,
        ]);
        $unitType = UnitType::factory()->for($property)->published()->create([
            'maximum_guests' => 4,
            'maximum_adults' => 2,
            'maximum_children' => 2,
        ]);
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create([
            'base_rate_minor' => 95_000,
            'included_adults' => 2,
            'included_children' => 2,
            'extra_adult_minor' => 0,
            'extra_child_minor' => 0,
            'tax_rate_bps' => 0,
        ]);
        AccommodationUnit::factory()->forUnitType($unitType)->create();
        $manager = $this->administrator();
        $owner = $this->assignedUser(
            $property,
            PropertyBookingPermission::ACCESS_POB,
            PropertyBookingPermission::MANAGE_GUESTS,
        );
        $register = ReceptionRegister::factory()->for($property)->create([
            'receipt_print_mode' => ReceiptPrintMode::AutoPrompt,
            'receipt_paper_width' => ReceiptPaperWidth::Roll58,
            'receipt_printer_name' => 'Reception thermal printer',
        ]);
        $shift = app(ReceptionShiftService::class)->open($register, $owner, $manager, 5_000);
        $guest = app(GuestService::class)->create([
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'email' => 'amina.receipt@example.test',
            'phone' => '+254700000777',
            'country_code' => 'KE',
            'identity_type' => 'national_id',
            'identity_number' => 'RECEIPT-ID-0001',
            'identity_country_code' => 'KE',
        ]);
        $startsAt = CarbonImmutable::now($property->timezone)->addDays(14)->setTime(15, 0)->utc();
        $quote = app(BookingQuoteService::class)->quote($ratePlan, $startsAt, $startsAt->addDay()->subHours(5), 2, 0);
        $booking = app(PobCheckoutService::class)->checkout(
            quote: $quote,
            ratePlan: $ratePlan,
            guest: $guest,
            tenders: [new PobTenderData(BookingPaymentMethod::Cash, $quote->calculation->totalMinor, $quote->calculation->totalMinor)],
            shift: $shift,
            receptionist: $owner,
            specialRequests: 'A quiet room, please.',
            internalNote: 'Staff-only arrival note',
        );

        return [$booking, $owner, $register, $property];
    }

    /** Create a globally authorized active system administrator. */
    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
            'is_active' => true,
        ]);
        $administrator->assignRole(UserType::SystemAdministrator->value);

        return $administrator;
    }

    /** Create a property-scoped active operator. */
    private function assignedUser(Property $property, string ...$permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo($permissions);
        DB::table('property_booking_property_user')->insert([
            'property_id' => $property->id,
            'user_id' => $user->id,
            'assigned_by' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
