<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Enums\ReportOrientation;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Services\BookingDocumentDataFactory;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Storefront\Services\BookingAccessUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Verifies signed access, document orientation, and guest-data minimization. */
final class PropertyBookingStorefrontAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Require exact temporary signatures and apply private response controls. */
    public function test_confirmation_tracking_and_documents_require_exact_signatures(): void
    {
        $booking = $this->booking();
        $urls = app(BookingAccessUrlService::class);

        $this->get(route('property-booking.storefront.bookings.confirmation', $booking))->assertForbidden();
        $confirmation = $this->get($urls->confirmation($booking));
        $confirmation->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee($booking->booking_number)
            ->assertDontSee('PRIVATE-INTERNAL-NOTE')
            ->assertDontSee('ROOM-PRIVATE-204');
        $this->assertStringContainsString('private', (string) $confirmation->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $confirmation->headers->get('Cache-Control'));

        $this->get($urls->tracking($booking))
            ->assertOk()
            ->assertSee('Current status')
            ->assertDontSee('PRIVATE-INTERNAL-NOTE');

        $tampered = str_replace($booking->ulid, (string) Str::ulid(), $urls->tracking($booking));
        $this->get($tampered)->assertNotFound();
        $expiring = $urls->confirmation($booking);
        $this->travel(3)->hours();
        $this->get($expiring)->assertForbidden();
        $this->travelBack();
    }

    /** Render both shared institutional orientations as valid private PDFs. */
    public function test_booking_summary_supports_portrait_and_landscape_pdf_output(): void
    {
        $booking = $this->booking();
        $urls = app(BookingAccessUrlService::class);

        foreach (ReportOrientation::cases() as $orientation) {
            $response = $this->get($urls->document($booking, $orientation));
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', (string) $response->getContent());
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    /** Keep operational, protected identity, and payment-record values outside the document DTO. */
    public function test_document_projection_excludes_internal_and_operational_fields(): void
    {
        $booking = $this->booking();
        $document = app(BookingDocumentDataFactory::class)->fromBooking($booking);
        $encoded = json_encode($document, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString((string) $booking->booking_number, $encoded);
        $this->assertStringContainsString('Public guest request', $encoded);
        foreach (['PRIVATE-INTERNAL-NOTE', 'ROOM-PRIVATE-204', 'PASSPORT-PRIVATE-987', 'PRIVATE-PAYMENT-METADATA'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
    }

    /** Reject signed public access for non-web bookings even when the signature is valid. */
    public function test_signed_access_is_restricted_to_placed_web_bookings(): void
    {
        $booking = $this->booking();
        $booking->forceFill(['channel' => BookingChannel::Admin])->save();

        $this->get(app(BookingAccessUrlService::class)->tracking($booking))->assertNotFound();
    }

    /** Create one complete privacy-test aggregate without exposing an allocation relation. */
    private function booking(): Booking
    {
        $property = Property::factory()->published()->create();
        $unitType = UnitType::factory()->for($property)->published()->create();
        $ratePlan = RatePlan::factory()->forUnitType($unitType)->active()->create(['base_rate_minor' => 180_000, 'tax_rate_bps' => 0]);
        $guest = Guest::factory()->create([
            'first_name' => 'Amina',
            'last_name' => 'Otieno',
            'email' => 'amina@example.test',
            'identity_number_ciphertext' => encrypt('PASSPORT-PRIVATE-987'),
            'identity_number_hash' => hash('sha256', 'PASSPORT-PRIVATE-987'),
        ]);
        $booking = Booking::factory()->forProperty($property)->forGuest($guest)->pending()->create([
            'preferred_payment_method' => BookingPaymentMethod::MobileMoney,
            'terms_accepted_at' => now(),
            'internal_note' => 'PRIVATE-INTERNAL-NOTE',
            'special_requests' => 'Public guest request',
            'accommodation_subtotal_minor' => 180_000,
            'tax_minor' => 0,
            'total_minor' => 180_000,
            'required_deposit_minor' => 90_000,
        ]);
        BookingStay::factory()->create([
            'booking_id' => $booking->id,
            'rate_plan_id' => $ratePlan->id,
            'unit_type_id' => $unitType->id,
            'unit_type_code' => 'ROOM-PRIVATE-204',
            'subtotal_minor' => 180_000,
            'total_minor' => 180_000,
        ]);

        return $booking->refresh()->load('stays');
    }
}
