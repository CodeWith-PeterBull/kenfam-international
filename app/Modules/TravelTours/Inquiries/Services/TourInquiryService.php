<?php

/**
 * Owns idempotent validation and persistence of public TravelTours inquiries.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Services;

use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Inquiries\Data\TourInquiryData;
use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Persist one public inquiry without exposing assignment or lifecycle controls. */
final class TourInquiryService
{
    /** Submit an inquiry once and return the original record on a compatible retry. */
    public function submit(TourInquiryData $data): TourInquiry
    {
        return DB::transaction(function () use ($data): TourInquiry {
            $existing = TourInquiry::query()->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                $this->assertCompatibleRetry($existing, $data);

                return $existing;
            }

            $this->assertPublicContext($data);
            $inquiry = new TourInquiry;
            $inquiry->forceFill([
                'reference' => 'INQ-'.now()->utc()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'operation_key' => $data->operationKey,
                'tour_id' => $data->tourId,
                'departure_id' => $data->departureId,
                'inquiry_type' => $data->type,
                'contact_name' => trim($data->contactName),
                'contact_email' => $data->contactEmail === null ? null : Str::lower(trim($data->contactEmail)),
                'contact_phone' => $data->contactPhone === null ? null : trim($data->contactPhone),
                'whatsapp_preferred' => $data->whatsappPreferred,
                'requested_destinations' => $data->requestedDestinations,
                'preferred_start_date' => $data->preferredStartDate,
                'preferred_end_date' => $data->preferredEndDate,
                'adult_count' => $data->adultCount,
                'child_count' => $data->childCount,
                'infant_count' => $data->infantCount,
                'budget_currency' => $data->budgetCurrency === null ? null : Str::upper($data->budgetCurrency),
                'budget_minor' => $data->budgetMinor,
                'message' => trim($data->message),
                'source' => $data->source,
                'status' => InquiryStatus::New,
                'consent_ip' => $data->consentIp,
                'consent_recorded_at' => now(),
                'consent_purpose' => $data->consentPurpose,
                'consent_version' => $data->consentVersion,
            ])->save();

            return $inquiry;
        }, 3);
    }

    /** Reject retries that reuse an operation key for materially different input. */
    private function assertCompatibleRetry(TourInquiry $inquiry, TourInquiryData $data): void
    {
        if ($inquiry->tour_id !== $data->tourId
            || $inquiry->departure_id !== $data->departureId
            || $inquiry->inquiry_type !== $data->type
            || ! hash_equals(Str::lower((string) $inquiry->contact_email), Str::lower((string) $data->contactEmail))) {
            throw ValidationException::withMessages([
                'operation_key' => 'This inquiry submission token has already been used with different details.',
            ]);
        }
    }

    /** Ensure public submissions can reference only published tours and their departures. */
    private function assertPublicContext(TourInquiryData $data): void
    {
        if ($data->tourId === null && $data->departureId !== null) {
            throw ValidationException::withMessages(['departure_id' => 'A departure requires a selected tour.']);
        }
        if ($data->tourId === null) {
            return;
        }

        $tour = Tour::query()->published()->find($data->tourId);
        if ($tour === null) {
            throw ValidationException::withMessages(['tour_id' => 'The selected tour is not currently available.']);
        }
        if ($data->departureId !== null
            && ! TourDeparture::query()->whereKey($data->departureId)->where('tour_id', $tour->id)->exists()) {
            throw ValidationException::withMessages(['departure_id' => 'The selected departure does not belong to this tour.']);
        }
    }
}
