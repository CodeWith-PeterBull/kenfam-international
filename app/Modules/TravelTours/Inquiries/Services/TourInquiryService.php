<?php

/**
 * Owns idempotent validation and persistence of public TravelTours inquiries.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Services;

use App\Models\User;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Inquiries\Data\TourInquiryData;
use App\Modules\TravelTours\Inquiries\Enums\InquiryStatus;
use App\Modules\TravelTours\Inquiries\Exceptions\InquiryException;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Inquiries\Models\TourInquiryActivity;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Persist one public inquiry, then own its follow-up: assignment, lifecycle
 * state, scheduled follow-ups, and the activity trail operators leave.
 * Every operator write locks the inquiry and appends one activity row so
 * the timeline is the audit record.
 */
final class TourInquiryService
{
    /** Activity types an operator may record by hand. */
    public const NOTE_TYPES = ['note', 'call', 'email', 'whatsapp'];

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

    /** Hand the inquiry to an operator (or clear the owner) and record who did it. */
    public function assign(TourInquiry $inquiry, ?User $assignee, User $actor): TourInquiry
    {
        return DB::transaction(function () use ($inquiry, $assignee, $actor): TourInquiry {
            $locked = TourInquiry::query()->lockForUpdate()->findOrFail($inquiry->getKey());
            if ($locked->status->isTerminal()) {
                throw new InquiryException('Reopen this inquiry before assigning it.');
            }
            if ($assignee !== null && (! $assignee->is_active || ! $assignee->can(TravelToursPermission::MANAGE_INQUIRIES))) {
                throw new InquiryException('The chosen operator cannot manage inquiries.');
            }
            $attributes = ['assigned_to' => $assignee?->getKey()];
            if ($assignee !== null && $locked->status === InquiryStatus::New) {
                $attributes['status'] = InquiryStatus::Assigned;
            }
            $locked->forceFill($attributes)->save();
            $this->record($locked, 'assignment', $assignee === null ? 'Owner cleared.' : "Assigned to {$assignee->name}.", $actor);

            return $locked->refresh();
        }, 3);
    }

    /** Move the inquiry to another lifecycle state, stamping response and closure instants. */
    public function transition(TourInquiry $inquiry, InquiryStatus $target, User $actor, ?string $note = null): TourInquiry
    {
        return DB::transaction(function () use ($inquiry, $target, $actor, $note): TourInquiry {
            $locked = TourInquiry::query()->lockForUpdate()->findOrFail($inquiry->getKey());
            if ($locked->status === $target) {
                return $locked;
            }
            if ($target === InquiryStatus::Converted) {
                throw new InquiryException('An inquiry is marked converted by the booking it became, not by hand.');
            }
            if ($locked->status->isTerminal() && $target !== InquiryStatus::InProgress) {
                throw new InquiryException('Reopen a closed inquiry as "in progress" before changing it further.');
            }
            $attributes = ['status' => $target, 'closed_at' => $target->isTerminal() ? now() : null];
            if ($target === InquiryStatus::AwaitingCustomer && $locked->responded_at === null) {
                $attributes['responded_at'] = now();
            }
            if ($target->isTerminal()) {
                $attributes['follow_up_at'] = null;
            }
            $locked->forceFill($attributes)->save();
            $this->record($locked, 'status', trim("Status changed to {$target->label()}. ".(string) $note), $actor);

            return $locked->refresh();
        }, 3);
    }

    /** Schedule or clear the next follow-up. */
    public function scheduleFollowUp(TourInquiry $inquiry, ?CarbonImmutable $at, User $actor): TourInquiry
    {
        return DB::transaction(function () use ($inquiry, $at, $actor): TourInquiry {
            $locked = TourInquiry::query()->lockForUpdate()->findOrFail($inquiry->getKey());
            if ($locked->status->isTerminal()) {
                throw new InquiryException('Reopen this inquiry before scheduling a follow-up.');
            }
            $locked->forceFill(['follow_up_at' => $at])->save();
            $this->record($locked, 'follow_up', $at === null ? 'Follow-up cleared.' : 'Follow-up scheduled for '.$at->format('d M Y, H:i').' UTC.', $actor);

            return $locked->refresh();
        }, 3);
    }

    /** Record a note, call, email, or WhatsApp exchange; the first outbound contact stamps the response time. */
    public function addActivity(TourInquiry $inquiry, string $type, string $note, User $actor): TourInquiryActivity
    {
        if (! in_array($type, self::NOTE_TYPES, true)) {
            throw new InquiryException('Choose a note, call, email, or WhatsApp activity.');
        }
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 2000) {
            throw new InquiryException('An activity note needs between 1 and 2,000 characters.');
        }

        return DB::transaction(function () use ($inquiry, $type, $note, $actor): TourInquiryActivity {
            $locked = TourInquiry::query()->lockForUpdate()->findOrFail($inquiry->getKey());
            if ($type !== 'note' && $locked->responded_at === null) {
                $locked->forceFill(['responded_at' => now()])->save();
            }

            return $this->record($locked, $type, $note, $actor);
        }, 3);
    }

    /** Append one activity row to the inquiry timeline. */
    private function record(TourInquiry $inquiry, string $type, string $note, User $actor): TourInquiryActivity
    {
        $activity = $inquiry->activities()->make();
        $activity->forceFill([
            'activity_type' => $type,
            'note' => $note,
            'actor_id' => $actor->getKey(),
            'occurred_at' => now(),
        ])->save();

        return $activity;
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
