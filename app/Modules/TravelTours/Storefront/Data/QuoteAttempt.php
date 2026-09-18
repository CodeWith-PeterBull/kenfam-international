<?php

/** Outcome of pricing a public selection: a quote, or the reason there is none. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Data;

use App\Modules\TravelTours\Pricing\Data\TourQuote;

/**
 * Always non-null so Livewire caches the attempt for the request; a bare null
 * quote would be recomputed on every access, re-running validation each time.
 */
final readonly class QuoteAttempt
{
    /** Create the attempt with either the quote or the reason it could not be produced. */
    private function __construct(public ?TourQuote $quote = null, public ?string $failure = null) {}

    /** The selection was priced. */
    public static function priced(TourQuote $quote): self
    {
        return new self(quote: $quote);
    }

    /** The selection cannot be priced for a reason worth showing. */
    public static function failed(string $reason): self
    {
        return new self(failure: $reason);
    }

    /** Nothing to price yet, or the reasons are already on the form fields. */
    public static function none(): self
    {
        return new self;
    }
}
