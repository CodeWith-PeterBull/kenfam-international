<?php

/** Owns experience copy, FAQs and optional priced extras for a tour. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Catalog\Data\TourContentItemData;
use App\Modules\TravelTours\Catalog\Data\TourExtraData;
use App\Modules\TravelTours\Catalog\Data\TourFaqData;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourContentItem;
use App\Modules\TravelTours\Catalog\Models\TourExtra;
use App\Modules\TravelTours\Catalog\Models\TourFaq;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Validate every child write and preserve ordered tour ownership. */
final readonly class TourContentService
{
    /** Inject the module's database transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Append or update a typed experience item. */
    public function saveItem(Tour $tour, TourContentItemData $data, ?int $itemId = null): TourContentItem
    {
        return $this->database->transaction(function () use ($tour, $data, $itemId): TourContentItem {
            $tour = $this->lockedTour($tour);
            $values = ['type' => $data->type->value, 'title' => $this->optional($data->title), 'content' => trim($data->content)];
            Validator::make($values, [
                'type' => ['required', 'string', 'max:24'],
                'title' => ['nullable', 'string', 'max:180'],
                'content' => ['required', 'string', 'max:10000'],
            ])->validate();
            $item = $itemId === null ? new TourContentItem : $tour->contentItems()->whereKey($itemId)->firstOrFail();
            if (! $item->exists || $item->type->value !== $data->type->value) {
                $item->sort_order = $this->nextOrder($tour->contentItems()->where('type', $data->type->value));
            }
            $item->forceFill(['tour_id' => $tour->id] + $values)->save();

            return $item->refresh();
        });
    }

    /** Remove an item only from its owning tour. */
    public function removeItem(Tour $tour, int $itemId): void
    {
        $this->database->transaction(function () use ($tour, $itemId): void {
            $tour = $this->lockedTour($tour);
            $tour->contentItems()->whereKey($itemId)->firstOrFail()->delete();
        });
    }

    /** Move an experience item within its content type. */
    public function moveItem(Tour $tour, int $itemId, string $direction): void
    {
        $this->database->transaction(function () use ($tour, $itemId, $direction): void {
            $tour = $this->lockedTour($tour);
            $item = $tour->contentItems()->whereKey($itemId)->firstOrFail();
            $this->move($tour->contentItems()->where('type', $item->type->value), $item, $direction);
        });
    }

    /** Append or update a FAQ without changing another tour's content. */
    public function saveFaq(Tour $tour, TourFaqData $data, ?int $faqId = null): TourFaq
    {
        return $this->database->transaction(function () use ($tour, $data, $faqId): TourFaq {
            $tour = $this->lockedTour($tour);
            $values = ['question' => trim($data->question), 'answer' => trim($data->answer), 'is_active' => $data->isActive];
            Validator::make($values, [
                'question' => ['required', 'string', 'max:320'],
                'answer' => ['required', 'string', 'max:50000'],
                'is_active' => ['boolean'],
            ])->validate();
            $faq = $faqId === null ? new TourFaq : $tour->faqs()->whereKey($faqId)->firstOrFail();
            if (! $faq->exists) {
                $faq->sort_order = $this->nextOrder($tour->faqs());
            }
            $faq->forceFill(['tour_id' => $tour->id] + $values)->save();

            return $faq->refresh();
        });
    }

    /** Remove a FAQ from its tour. */
    public function removeFaq(Tour $tour, int $faqId): void
    {
        $this->database->transaction(function () use ($tour, $faqId): void {
            $tour = $this->lockedTour($tour);
            $tour->faqs()->whereKey($faqId)->firstOrFail()->delete();
        });
    }

    /** Move a FAQ one position. */
    public function moveFaq(Tour $tour, int $faqId, string $direction): void
    {
        $this->database->transaction(function () use ($tour, $faqId, $direction): void {
            $tour = $this->lockedTour($tour);
            $faq = $tour->faqs()->whereKey($faqId)->firstOrFail();
            $this->move($tour->faqs(), $faq, $direction);
        });
    }

    /** Append or update a priced extra without rounding money through floats. */
    public function saveExtra(Tour $tour, TourExtraData $data, User $actor, ?int $extraId = null): TourExtra
    {
        return $this->database->transaction(function () use ($tour, $data, $actor, $extraId): TourExtra {
            $tour = $this->lockedTour($tour);
            $values = [
                'code' => strtoupper(trim($data->code)), 'name' => trim($data->name),
                'description' => $this->optional($data->description), 'pricing_unit' => $data->pricingUnit,
                'amount_minor' => $data->amountMinor, 'currency' => strtoupper(trim($data->currency)),
                'participant_types' => array_values(array_unique($data->participantTypes)),
                'minimum_quantity' => $data->minimumQuantity, 'maximum_quantity' => $data->maximumQuantity,
                'is_active' => $data->isActive, 'is_public' => $data->isPublic,
            ];
            Validator::make($values, [
                'code' => ['required', 'regex:/^[A-Z0-9][A-Z0-9-]{1,39}$/'],
                'name' => ['required', 'string', 'max:180'],
                'description' => ['nullable', 'string', 'max:10000'],
                'pricing_unit' => ['required', Rule::in(['per_person', 'per_booking'])],
                'amount_minor' => ['required', 'integer', 'between:0,999999999999'],
                'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
                'participant_types' => ['array', 'max:3'],
                'participant_types.*' => ['required', Rule::enum(ParticipantType::class)],
                'minimum_quantity' => ['required', 'integer', 'between:0,1000'],
                'maximum_quantity' => ['nullable', 'integer', 'between:1,1000', 'gte:minimum_quantity'],
                'is_active' => ['boolean'], 'is_public' => ['boolean'],
            ])->validate();
            $extra = $extraId === null ? new TourExtra : $tour->extras()->whereKey($extraId)->firstOrFail();
            if (TourExtra::withTrashed()->where('tour_id', $tour->id)->where('code', $values['code'])
                ->when($extra->exists, fn ($query) => $query->whereKeyNot($extra->id))->exists()) {
                throw new CatalogException('Another extra on this tour already uses that code.');
            }
            if (! $extra->exists) {
                $extra->sort_order = $this->nextOrder($tour->extras());
                $extra->created_by = $actor->id;
            }
            $extra->forceFill(['tour_id' => $tour->id, 'updated_by' => $actor->id] + $values)->save();

            return $extra->refresh();
        });
    }

    /** Archive an extra while retaining potential booking references. */
    public function removeExtra(Tour $tour, int $extraId): void
    {
        $this->database->transaction(function () use ($tour, $extraId): void {
            $tour = $this->lockedTour($tour);
            $tour->extras()->whereKey($extraId)->firstOrFail()->delete();
        });
    }

    /** Move an active extra one position. */
    public function moveExtra(Tour $tour, int $extraId, string $direction): void
    {
        $this->database->transaction(function () use ($tour, $extraId, $direction): void {
            $tour = $this->lockedTour($tour);
            $extra = $tour->extras()->whereKey($extraId)->firstOrFail();
            $this->move($tour->extras(), $extra, $direction);
        });
    }

    /** Swap adjacent sort positions within the provided tour-owned query. */
    private function move($query, Model $record, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw new CatalogException('Invalid content ordering direction.');
        }
        $ordered = $query->orderBy('sort_order')->orderBy('id')->get();
        $index = $ordered->search(fn (Model $candidate): bool => $candidate->getKey() === $record->getKey());
        $other = $ordered->get($index + ($direction === 'up' ? -1 : 1));
        if ($other === null) {
            return;
        }
        [$ordered[$index], $ordered[$index + ($direction === 'up' ? -1 : 1)]] = [$other, $record];
        foreach ($ordered->values() as $position => $entry) {
            $entry->forceFill(['sort_order' => $position + 1])->save();
        }
    }

    /** Return the next stable display position for a scoped child query. */
    private function nextOrder($query): int
    {
        return (int) $query->max('sort_order') + 1;
    }

    /** Lock the owning tour before any child mutation. */
    private function lockedTour(Tour $tour): Tour
    {
        return Tour::query()->lockForUpdate()->findOrFail($tour->id);
    }

    /** Normalize optional editorial text. */
    private function optional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
