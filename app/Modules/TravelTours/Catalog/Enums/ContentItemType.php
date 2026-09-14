<?php

/**
 * Defines controlled vocabulary for a persisted TravelTours domain state.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Enums;

use App\Modules\TravelTours\Support\Concerns\HasEnumLabel;

/** Enumerates the controlled content item type vocabulary used by TravelTours. */
enum ContentItemType: string
{
    use HasEnumLabel;
    case Highlight = 'highlight';
    case Inclusion = 'inclusion';
    case Exclusion = 'exclusion';
    case Requirement = 'requirement';
    case PackingNote = 'packing_note';
}
