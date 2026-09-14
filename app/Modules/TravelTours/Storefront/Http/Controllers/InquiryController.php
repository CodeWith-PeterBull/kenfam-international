<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Inquiries\Data\TourInquiryData;
use App\Modules\TravelTours\Inquiries\Enums\InquiryType;
use App\Modules\TravelTours\Inquiries\Services\TourInquiryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Validates and records public tour inquiries without exposing assignment controls. */
final class InquiryController extends Controller
{
    /** Validate and persist a public tour inquiry through the module boundary. */
    public function store(Request $request, TourInquiryService $inquiries): RedirectResponse
    {
        $data = $request->validate([
            'operation_key' => ['required', 'string', 'max:64'],
            'tour_id' => ['nullable', 'integer', 'exists:travel_tours,id'], 'departure_id' => ['nullable', 'integer', 'exists:travel_tour_departures,id'],
            'inquiry_type' => ['required', 'in:general,tour,private_tour,custom_tour'], 'contact_name' => ['required', 'string', 'max:190'],
            'contact_email' => ['nullable', 'email:rfc', 'max:254', 'required_without:contact_phone'],
            'contact_phone' => ['nullable', 'string', 'max:32', 'required_without:contact_email'], 'whatsapp_preferred' => ['nullable', 'boolean'],
            'requested_destinations' => ['nullable', 'string', 'max:2000'], 'preferred_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'preferred_end_date' => ['nullable', 'date', 'after_or_equal:preferred_start_date'], 'adult_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'child_count' => ['nullable', 'integer', 'min:0', 'max:50'], 'infant_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'budget_currency' => ['nullable', 'string', 'size:3'], 'budget_minor' => ['nullable', 'integer', 'min:0'],
            'message' => ['required', 'string', 'max:5000'], 'consent' => ['accepted'],
        ]);
        $inquiries->submit(new TourInquiryData(
            operationKey: $data['operation_key'],
            type: InquiryType::from($data['inquiry_type']),
            contactName: $data['contact_name'],
            contactEmail: $data['contact_email'] ?? null,
            contactPhone: $data['contact_phone'] ?? null,
            whatsappPreferred: (bool) ($data['whatsapp_preferred'] ?? false),
            requestedDestinations: array_values(array_filter(array_map('trim', explode(',', (string) ($data['requested_destinations'] ?? ''))))),
            preferredStartDate: isset($data['preferred_start_date']) ? CarbonImmutable::parse($data['preferred_start_date']) : null,
            preferredEndDate: isset($data['preferred_end_date']) ? CarbonImmutable::parse($data['preferred_end_date']) : null,
            adultCount: (int) ($data['adult_count'] ?? 0),
            childCount: (int) ($data['child_count'] ?? 0),
            infantCount: (int) ($data['infant_count'] ?? 0),
            budgetCurrency: $data['budget_currency'] ?? null,
            budgetMinor: isset($data['budget_minor']) ? (int) $data['budget_minor'] : null,
            message: $data['message'],
            tourId: isset($data['tour_id']) ? (int) $data['tour_id'] : null,
            departureId: isset($data['departure_id']) ? (int) $data['departure_id'] : null,
            consentIp: $request->ip(),
            consentPurpose: (string) config('travel-tours.inquiries.consent_purpose'),
            consentVersion: (string) config('travel-tours.inquiries.consent_version'),
        ));

        return back()->with('inquiry_submitted', 'Thank you. Our travel team will contact you shortly.');
    }
}
