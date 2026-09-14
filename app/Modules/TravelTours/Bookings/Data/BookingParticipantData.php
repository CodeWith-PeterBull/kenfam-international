<?php

/**
 * Validated participant identity supplied to booking placement.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Data;

use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Carry participant data while excluding client-controlled prices and lifecycle fields. */
final readonly class BookingParticipantData
{
    /** Validate names, date order, nationality, and travel-document country codes. */
    public function __construct(
        public ParticipantType $type,
        public string $firstName,
        public string $lastName,
        public ?string $title = null,
        public ?string $middleName = null,
        public ?CarbonImmutable $dateOfBirth = null,
        public ?string $nationalityCode = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $identityNumber = null,
        public ?string $identityType = null,
        public ?string $passportIssuingCountry = null,
        public ?CarbonImmutable $passportExpiryDate = null,
        public ?string $dietaryRequirements = null,
        public ?string $accessibilityRequirements = null,
        public ?string $medicalNotes = null,
        public ?string $emergencyContactName = null,
        public ?string $emergencyContactPhone = null,
        public ?int $travelerId = null,
        public ?int $guardianSequence = null,
        public bool $isLead = false,
    ) {
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new InvalidArgumentException('Participant first and last names are required.');
        }

        if ($this->dateOfBirth?->isFuture()) {
            throw new InvalidArgumentException('Participant date of birth cannot be in the future.');
        }

        foreach ([$this->nationalityCode, $this->passportIssuingCountry] as $countryCode) {
            if ($countryCode !== null && preg_match('/^[A-Za-z]{2}$/', $countryCode) !== 1) {
                throw new InvalidArgumentException('Participant country codes must use ISO alpha-2 format.');
            }
        }
    }

    /**
     * Build the immutable persistence snapshot using a server-calculated allocation.
     *
     * @return array<string, mixed>
     */
    public function snapshot(int $sequence, CarbonImmutable $departureDate, int $allocatedPriceMinor): array
    {
        return [
            'traveler_id' => $this->travelerId,
            'sequence' => $sequence,
            'is_lead' => $this->isLead || $sequence === 1,
            'participant_type' => $this->type,
            'age_at_departure' => $this->dateOfBirth?->diffInYears($departureDate),
            'consumes_seat' => $this->type !== ParticipantType::Infant,
            'guardian_sequence' => $this->guardianSequence,
            'title' => $this->title,
            'first_name' => trim($this->firstName),
            'middle_name' => filled($this->middleName) ? trim((string) $this->middleName) : null,
            'last_name' => trim($this->lastName),
            'date_of_birth' => $this->dateOfBirth,
            'nationality_code' => $this->nationalityCode !== null ? mb_strtoupper($this->nationalityCode) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'identity_number' => $this->identityNumber,
            'identity_type' => $this->identityType,
            'passport_issuing_country' => $this->passportIssuingCountry !== null ? mb_strtoupper($this->passportIssuingCountry) : null,
            'passport_expiry_date' => $this->passportExpiryDate,
            'dietary_requirements' => $this->dietaryRequirements,
            'accessibility_requirements' => $this->accessibilityRequirements,
            'medical_notes' => $this->medicalNotes,
            'emergency_contact_name' => $this->emergencyContactName,
            'emergency_contact_phone' => $this->emergencyContactPhone,
            'allocated_price_minor' => $allocatedPriceMinor,
        ];
    }
}
