<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/** Verifies module registration and the exhaustive foundation schema contract. */
final class PropertyBookingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_loads_configuration_and_every_documented_table_column(): void
    {
        $this->assertTrue(config('property-booking.enabled'));
        $this->assertSame('KES', config('property-booking.defaults.currency'));
        $this->assertSame('Africa/Nairobi', config('property-booking.defaults.timezone'));

        foreach ($this->expectedColumns() as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Expected Property Booking table [{$table}] was not loaded.");
            $this->assertEqualsCanonicalizing($columns, Schema::getColumnListing($table), "Unexpected column contract for [{$table}].");
        }
    }

    public function test_every_declared_migration_column_has_an_explanatory_comment(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/PropertyBooking/Database/Migrations')));
        $column = '/\$table->(?:id|ulid|foreignId|string|char|text|longText|json|boolean|decimal|date|time|unsignedInteger|unsignedSmallInteger|unsignedBigInteger|bigInteger|timestamp|softDeletes)\(/';

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (explode(';', (string) file_get_contents($file->getPathname())) as $statement) {
                if (preg_match($column, $statement) !== 1) {
                    continue;
                }
                $this->assertStringContainsString('->comment(', $statement, "Undocumented column in {$file->getFilename()}: ".trim($statement));
            }
        }
    }

    public function test_module_source_has_no_commerce_namespace_dependency(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/PropertyBooking')));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->assertStringNotContainsString('App\\Modules\\Commerce', (string) file_get_contents($file->getPathname()), $file->getPathname());
            }
        }
    }

    /** Ensure the module's named types and methods retain their maintenance context. */
    public function test_every_named_module_type_and_method_has_phpdoc(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Modules/PropertyBooking')));
        $missing = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $tokens = token_get_all((string) file_get_contents($file->getPathname()));

            foreach ($tokens as $index => $token) {
                if (! is_array($token)) {
                    continue;
                }

                if ($token[0] === T_FUNCTION) {
                    $name = $this->followingNamedToken($tokens, $index);
                    if ($name !== null && ! $this->hasPrecedingPhpDoc($tokens, $index)) {
                        $missing[] = "{$file->getFilename()}:{$token[2]} {$name}()";
                    }
                }

                if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)
                    && ! $this->isAnonymousClass($tokens, $index)
                    && ! $this->isClassConstant($tokens, $index)) {
                    $name = $this->followingNamedToken($tokens, $index);
                    if ($name !== null && ! $this->hasPrecedingPhpDoc($tokens, $index)) {
                        $missing[] = "{$file->getFilename()}:{$token[2]} {$name}";
                    }
                }
            }
        }

        $this->assertSame([], $missing, "Missing module PHPDoc:\n".implode("\n", $missing));
    }

    public function test_open_shift_guard_enforces_one_open_session_per_register(): void
    {
        $register = ReceptionRegister::factory()->create();
        ReceptionShift::factory()->for($register, 'register')->create();

        $this->expectException(QueryException::class);
        ReceptionShift::factory()->for($register, 'register')->create();
    }

    public function test_active_assignment_guard_enforces_one_concrete_unit_per_stay(): void
    {
        $assignment = UnitAssignment::factory()->create();
        $otherUnit = AccommodationUnit::factory()->forUnitType($assignment->bookingStay->unitType)->create();

        $this->expectException(QueryException::class);
        UnitAssignment::factory()->create([
            'booking_stay_id' => $assignment->booking_stay_id,
            'booking_id' => $assignment->booking_id,
            'property_id' => $assignment->property_id,
            'unit_id' => $otherUnit->id,
            'active_stay_guard' => $assignment->booking_stay_id,
        ]);
    }

    public function test_primary_guest_guard_enforces_one_primary_occupant_per_booking(): void
    {
        $booking = Booking::factory()->create();
        $stay = BookingStay::factory()->create(['booking_id' => $booking->id]);
        $first = Guest::factory()->create();
        $second = Guest::factory()->create();
        $booking->guests()->attach($first, [
            'booking_stay_id' => $stay->id, 'is_primary' => true,
            'primary_booking_guard' => $booking->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $booking->guests()->attach($second, [
            'booking_stay_id' => $stay->id, 'is_primary' => true,
            'primary_booking_guard' => $booking->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<string, list<string>> */
    private function expectedColumns(): array
    {
        return [
            'property_booking_categories' => ['id', 'ulid', 'parent_id', 'name', 'slug', 'description', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'property_booking_properties' => ['id', 'ulid', 'category_id', 'created_by', 'updated_by', 'code', 'slug', 'name', 'status', 'short_description', 'description', 'house_rules', 'cancellation_summary', 'email', 'phone', 'whatsapp_phone', 'website_url', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code', 'latitude', 'longitude', 'timezone', 'currency', 'check_in_from', 'check_in_until', 'check_out_from', 'check_out_until', 'minimum_notice_minutes', 'maximum_advance_days', 'turnover_minutes', 'is_featured', 'meta_title', 'meta_description', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_property_user' => ['property_id', 'user_id', 'assigned_by', 'is_default', 'created_at', 'updated_at'],
            'property_booking_amenities' => ['id', 'ulid', 'name', 'slug', 'scope', 'description', 'icon_key', 'sort_order', 'is_active', 'created_at', 'updated_at'],
            'property_booking_property_amenity' => ['property_id', 'amenity_id', 'detail', 'created_at', 'updated_at'],
            'property_booking_unit_types' => ['id', 'ulid', 'property_id', 'created_by', 'updated_by', 'code', 'slug', 'name', 'status', 'short_description', 'description', 'size_square_metres', 'bedroom_count', 'bathroom_count', 'living_room_count', 'bed_count', 'bed_configuration', 'maximum_guests', 'maximum_adults', 'maximum_children', 'maximum_infants', 'allows_infants_on_top', 'is_entire_unit', 'smoking_allowed', 'is_featured', 'meta_title', 'meta_description', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_unit_type_amenity' => ['unit_type_id', 'amenity_id', 'detail', 'created_at', 'updated_at'],
            'property_booking_units' => ['id', 'ulid', 'property_id', 'unit_type_id', 'created_by', 'updated_by', 'code', 'display_name', 'floor_label', 'location_note', 'operational_status', 'is_active', 'internal_note', 'last_ready_at', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_rate_plans' => ['id', 'ulid', 'property_id', 'unit_type_id', 'created_by', 'updated_by', 'code', 'name', 'description', 'status', 'pricing_unit', 'currency', 'base_rate_minor', 'included_adults', 'included_children', 'extra_adult_minor', 'extra_child_minor', 'minimum_units', 'maximum_units', 'minimum_advance_minutes', 'maximum_advance_days', 'tax_rate_bps', 'is_tax_inclusive', 'deposit_type', 'deposit_amount_minor', 'deposit_rate_bps', 'is_refundable', 'free_cancel_before_minutes', 'cancellation_terms', 'is_public', 'sort_order', 'published_at', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_rate_overrides' => ['id', 'ulid', 'rate_plan_id', 'created_by', 'updated_by', 'starts_on', 'ends_on', 'rate_minor', 'is_closed', 'closed_on_arrival', 'closed_on_departure', 'minimum_units', 'maximum_units', 'reason', 'created_at', 'updated_at'],
            'property_booking_availability_blocks' => ['id', 'ulid', 'property_id', 'unit_id', 'created_by', 'released_by', 'type', 'status', 'starts_at', 'ends_at', 'reason', 'internal_note', 'released_at', 'created_at', 'updated_at'],
            'property_booking_guests' => ['id', 'ulid', 'user_id', 'created_by', 'updated_by', 'title', 'first_name', 'middle_name', 'last_name', 'email', 'phone', 'alternate_phone', 'date_of_birth', 'nationality_country_code', 'identity_type', 'identity_number_ciphertext', 'identity_number_hash', 'identity_country_code', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code', 'emergency_contact_name', 'emergency_contact_phone', 'note', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_registers' => ['id', 'ulid', 'property_id', 'created_by', 'updated_by', 'code', 'name', 'location_label', 'description', 'receipt_print_driver', 'receipt_print_mode', 'receipt_paper_width', 'receipt_printer_name', 'is_active', 'created_at', 'updated_at', 'deleted_at'],
            'property_booking_shifts' => ['id', 'ulid', 'property_id', 'register_id', 'receptionist_id', 'register_open_guard', 'receptionist_open_guard', 'status', 'currency', 'opening_float_minor', 'expected_cash_minor', 'counted_cash_minor', 'variance_minor', 'opening_note', 'closing_note', 'opened_by', 'opened_at', 'closed_by', 'closed_at', 'created_at', 'updated_at'],
            'property_bookings' => ['id', 'ulid', 'booking_number', 'channel', 'property_id', 'primary_guest_id', 'register_id', 'reception_shift_id', 'receptionist_id', 'created_by', 'updated_by', 'status', 'stay_status', 'payment_status', 'starts_at', 'ends_at', 'property_timezone', 'adult_count', 'child_count', 'infant_count', 'currency', 'accommodation_subtotal_minor', 'charges_subtotal_minor', 'discount_minor', 'discount_reason', 'tax_minor', 'total_minor', 'required_deposit_minor', 'paid_minor', 'tax_inclusive', 'property_name', 'property_code', 'property_address_summary', 'guest_first_name', 'guest_middle_name', 'guest_last_name', 'guest_email', 'guest_phone', 'guest_country_code', 'special_requests', 'internal_note', 'external_reference', 'cancellation_reason', 'no_show_reason', 'hold_expires_at', 'pending_expires_at', 'placed_at', 'confirmed_at', 'cancelled_at', 'no_show_at', 'checked_in_at', 'checked_out_at', 'completed_at', 'hold_expiring_notified_at', 'arrival_due_notified_at', 'departure_due_notified_at', 'created_at', 'updated_at', 'preferred_payment_method', 'terms_accepted_at'],
            'property_booking_stays' => ['id', 'ulid', 'booking_id', 'unit_type_id', 'rate_plan_id', 'line_number', 'starts_at', 'ends_at', 'pricing_unit', 'billable_units', 'adult_count', 'child_count', 'infant_count', 'unit_type_name', 'unit_type_code', 'rate_plan_name', 'rate_plan_code', 'unit_rate_minor', 'extra_guest_minor', 'subtotal_minor', 'discount_minor', 'tax_rate_bps', 'tax_minor', 'total_minor', 'is_tax_inclusive', 'created_at', 'updated_at'],
            'property_booking_unit_assignments' => ['id', 'ulid', 'property_id', 'booking_id', 'booking_stay_id', 'unit_id', 'status', 'starts_at', 'ends_at', 'active_stay_guard', 'assigned_by', 'assigned_at', 'released_by', 'released_at', 'release_reason', 'created_at', 'updated_at'],
            'property_booking_guest_assignments' => ['booking_id', 'guest_id', 'booking_stay_id', 'is_primary', 'primary_booking_guard', 'checked_in_at', 'checked_out_at', 'created_at', 'updated_at'],
            'property_booking_charges' => ['id', 'ulid', 'booking_id', 'booking_stay_id', 'reception_shift_id', 'type', 'status', 'description', 'quantity', 'unit_amount_minor', 'subtotal_minor', 'tax_rate_bps', 'tax_minor', 'total_minor', 'is_tax_inclusive', 'posted_by', 'posted_at', 'voided_by', 'voided_at', 'void_reason', 'created_at', 'updated_at'],
            'property_booking_payments' => ['id', 'ulid', 'booking_id', 'reception_shift_id', 'recorded_by', 'method', 'status', 'currency', 'amount_minor', 'tendered_minor', 'change_minor', 'refunded_amount_minor', 'reference', 'metadata', 'paid_at', 'failed_at', 'refunded_at', 'created_at', 'updated_at'],
        ];
    }

    /** Find the next declared method or type name, excluding closures. */
    private function followingNamedToken(array $tokens, int $index): ?string
    {
        for ($offset = $index + 1; $offset < count($tokens); $offset++) {
            $candidate = $tokens[$offset];

            if (is_array($candidate) && $candidate[0] === T_STRING) {
                return $candidate[1];
            }

            if ($candidate === '(' || $candidate === '{') {
                return null;
            }
        }

        return null;
    }

    /** Determine whether a named declaration has an adjacent PHPDoc block. */
    private function hasPrecedingPhpDoc(array $tokens, int $index): bool
    {
        $skippable = [T_WHITESPACE, T_COMMENT, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_READONLY];

        for ($offset = $index - 1; $offset >= 0; $offset--) {
            $candidate = $tokens[$offset];

            if (is_array($candidate) && $candidate[0] === T_DOC_COMMENT) {
                return true;
            }

            if (is_array($candidate) && in_array($candidate[0], $skippable, true)) {
                continue;
            }

            if ($candidate === ']') {
                $offset = $this->attributeStartBefore($tokens, $offset);
                if ($offset >= 0) {
                    continue;
                }
            }

            return false;
        }

        return false;
    }

    /** Find the opening token for an attribute immediately preceding a declaration. */
    private function attributeStartBefore(array $tokens, int $closingIndex): int
    {
        $depth = 1;

        for ($offset = $closingIndex - 1; $offset >= 0; $offset--) {
            $candidate = $tokens[$offset];

            if ($candidate === ']') {
                $depth++;

                continue;
            }

            if ($candidate === '[') {
                $depth--;

                continue;
            }

            if (is_array($candidate) && $candidate[0] === T_ATTRIBUTE) {
                $depth--;

                return $depth === 0 ? $offset - 1 : -1;
            }
        }

        return -1;
    }

    /** Determine whether a class token belongs to an anonymous migration class. */
    private function isAnonymousClass(array $tokens, int $index): bool
    {
        for ($offset = $index - 1; $offset >= 0; $offset--) {
            $candidate = $tokens[$offset];

            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }

            return is_array($candidate) && $candidate[0] === T_NEW;
        }

        return false;
    }

    /** Distinguish `Model::class` constants from actual class declarations. */
    private function isClassConstant(array $tokens, int $index): bool
    {
        for ($offset = $index - 1; $offset >= 0; $offset--) {
            $candidate = $tokens[$offset];

            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }

            return is_array($candidate) && $candidate[0] === T_DOUBLE_COLON;
        }

        return false;
    }
}
