<?php

/** Validates a typed tour experience item. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Livewire\Forms;

use App\Modules\TravelTours\Catalog\Data\TourContentItemData;
use App\Modules\TravelTours\Catalog\Enums\ContentItemType;
use App\Modules\TravelTours\Catalog\Models\TourContentItem;
use Illuminate\Validation\Rule;
use Livewire\Form;

/** Collect controlled type and public copy for a single item. */
final class TourContentItemForm extends Form
{
    public string $type = 'highlight';

    public string $title = '';

    public string $content = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContentItemType::class)],
            'title' => ['nullable', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:10000'],
        ];
    }

    /** Load an authorized content item for editing. */
    public function fillFromItem(TourContentItem $item): void
    {
        $this->type = $item->type->value;
        $this->title = (string) $item->title;
        $this->content = $item->content;
    }

    /** Restore the default content-item state. */
    public function resetForCreate(): void
    {
        $this->reset();
        $this->type = ContentItemType::Highlight->value;
    }

    /** Convert validated input to a typed content item. */
    public function toData(): TourContentItemData
    {
        return new TourContentItemData(ContentItemType::from($this->type), $this->content, trim($this->title) ?: null);
    }
}
