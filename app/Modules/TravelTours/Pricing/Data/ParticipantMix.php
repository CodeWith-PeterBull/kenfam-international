<?php

/**
 * Defines immutable typed input or output data for a TravelTours operation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Data;

use InvalidArgumentException;

/** Validated participant counts shared by availability and pricing. */
final readonly class ParticipantMix
{
    /** Initialize the ParticipantMix with its required dependencies or immutable state. */
    public function __construct(public int $adults, public int $children = 0, public int $infants = 0)
    {
        $maximum = (int) config('travel-tours.booking.maximum_participants', 20);

        if ($adults < 1 || $children < 0 || $infants < 0 || $this->participants() > $maximum) {
            throw new InvalidArgumentException("A booking requires an adult and cannot exceed {$maximum} participants.");
        }
    }

    /** Return the total number of people represented by this participant mix. */
    public function participants(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    /** Return the capacity seats consumed by this participant mix. */
    public function seats(): int
    {
        return $this->adults + $this->children;
    }

    /** @return array{adult: int, child: int, infant: int} */
    public function byType(): array
    {
        return ['adult' => $this->adults, 'child' => $this->children, 'infant' => $this->infants];
    }
}
