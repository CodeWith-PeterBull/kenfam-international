<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Services;

use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Storefront\Data\BookingSelectionData;
use App\Modules\PropertyBooking\Storefront\Exceptions\StorefrontException;
use Illuminate\Contracts\Session\Session;

/** Owns the minimal session selection and re-quotes it on every read. */
final readonly class BookingSelectionSession
{
    /** Create the session boundary with the authoritative quote reader. */
    public function __construct(private Session $session, private BookingQuoteService $quotes) {}

    /** Validate, quote, and store a selection without monetary values. */
    public function select(BookingSelectionData $selection): BookingQuote
    {
        $quote = $this->quote($selection);
        $this->session->put($this->key(), $selection->toSession());

        return $quote;
    }

    /** Return the safe scalar selection or null when absent/corrupt. */
    public function selection(): ?BookingSelectionData
    {
        $payload = $this->session->get($this->key());

        return is_array($payload) ? BookingSelectionData::fromSession($payload) : null;
    }

    /** Rehydrate and re-quote the current selection. */
    public function current(): BookingQuote
    {
        $selection = $this->selection();
        if (! $selection instanceof BookingSelectionData) {
            $this->clear();
            throw new StorefrontException('Your stay selection is missing or no longer valid.');
        }

        return $this->quote($selection);
    }

    /** Re-quote the current selection and collapse recoverable failures to null. */
    public function currentOrNull(): ?BookingQuote
    {
        try {
            return $this->current();
        } catch (\Throwable) {
            $this->clear();

            return null;
        }
    }

    /** Remove the current selection. */
    public function clear(): void
    {
        $this->session->forget($this->key());
    }

    /** Resolve the configured stable session key. */
    private function key(): string
    {
        $key = trim((string) config('property-booking.storefront.selection_session_key', 'property_booking.selection'));

        return $key !== '' ? $key : 'property_booking.selection';
    }

    /** Generate a fresh quote for a selection after public catalog checks. */
    private function quote(BookingSelectionData $selection): BookingQuote
    {
        $ratePlan = RatePlan::query()
            ->publiclyBookable()
            ->where('ulid', $selection->ratePlanUlid)
            ->whereHas('property', static fn ($query) => $query
                ->published()
                ->whereHas('category', static fn ($category) => $category->active()))
            ->whereHas('unitType', static fn ($query) => $query->published())
            ->with(['property', 'unitType'])
            ->first();

        if (! $ratePlan instanceof RatePlan) {
            throw new StorefrontException('The selected accommodation rate is no longer available.');
        }

        return $this->quotes->quote(
            $ratePlan,
            $selection->startsAt,
            $selection->endsAt,
            $selection->adults,
            $selection->children,
            $selection->infants,
        );
    }
}
