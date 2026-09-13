<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\PropertyStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/** Owns property-category hierarchy, activation, and category-image writes. */
final readonly class PropertyCategoryService
{
    /** Create the category service with transaction and activity dependencies. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): PropertyCategory
    {
        return $this->database->transaction(function () use ($attributes, $actor): PropertyCategory {
            $category = new PropertyCategory;
            $category->fill($this->payload($attributes));
            $this->assertValid($category);
            $this->assertValidParent($category, $category->parent_id);
            $category->save();

            $this->activities->record(
                activityType: 'property-booking.category.created',
                description: "Property category created: {$category->name}",
                actor: $actor,
                subject: $category,
                properties: ['parent_ulid' => $category->parent?->ulid],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-catalog',
            );

            return $category->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(PropertyCategory $category, array $attributes, User $actor): PropertyCategory
    {
        return $this->database->transaction(function () use ($category, $attributes, $actor): PropertyCategory {
            $category = PropertyCategory::query()->lockForUpdate()->findOrFail($category->getKey());
            $category->fill($this->payload($attributes));
            $this->assertValid($category);
            $this->assertValidParent($category, $category->parent_id);
            $changes = array_keys($category->getDirty());
            $category->save();

            $this->activities->record(
                activityType: 'property-booking.category.updated',
                description: "Property category updated: {$category->name}",
                actor: $actor,
                subject: $category,
                properties: ['changed_fields' => $changes],
                source: 'property-booking-catalog',
            );

            return $category->refresh();
        });
    }

    /** Change category visibility without destroying its historical relationships. */
    public function setActive(PropertyCategory $category, bool $active, User $actor): PropertyCategory
    {
        return $this->database->transaction(function () use ($category, $active, $actor): PropertyCategory {
            $category = PropertyCategory::query()->lockForUpdate()->findOrFail($category->getKey());
            if ($category->is_active === $active) {
                return $category;
            }

            if (! $active && $category->properties()->where('status', PropertyStatus::Published->value)->exists()) {
                throw new CatalogException('A category with published properties cannot be hidden. Move or unpublish those properties first.');
            }

            $category->forceFill(['is_active' => $active])->save();
            $this->activities->record(
                activityType: 'property-booking.category.visibility-changed',
                description: 'Property category '.($active ? 'activated' : 'hidden').": {$category->name}",
                actor: $actor,
                subject: $category,
                properties: ['is_active' => $active],
                source: 'property-booking-catalog',
            );

            return $category->refresh();
        });
    }

    /** Replace the optional category image and persist accessible alternative text. */
    public function replaceImage(PropertyCategory $category, UploadedFile $upload, string $altText, User $actor): PropertyCategory
    {
        $this->assertUpload($upload);
        $altText = $this->boundedText($altText, 180, 'Category image alternative text');
        if ($altText === null) {
            throw new CatalogException('Category image alternative text is required.');
        }

        $category = PropertyCategory::query()->findOrFail($category->getKey());
        $category->addMedia($upload)
            ->usingName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->withCustomProperties(['alt_text' => $altText])
            ->toMediaCollection('category_image');

        $this->activities->record(
            activityType: 'property-booking.category.image-replaced',
            description: "Property category image updated: {$category->name}",
            actor: $actor,
            subject: $category,
            source: 'property-booking-catalog',
        );

        return $category->refresh()->load('media');
    }

    /** Remove the replaceable category image while preserving the category. */
    public function removeImage(PropertyCategory $category, User $actor): PropertyCategory
    {
        $category = PropertyCategory::query()->findOrFail($category->getKey());
        if ($category->getFirstMedia('category_image') === null) {
            return $category->loadMissing('media');
        }

        $category->clearMediaCollection('category_image');
        $this->activities->record(
            activityType: 'property-booking.category.image-removed',
            description: "Property category image removed: {$category->name}",
            actor: $actor,
            subject: $category,
            source: 'property-booking-catalog',
        );

        return $category->refresh()->load('media');
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function payload(array $attributes): array
    {
        $payload = Arr::only($attributes, ['parent_id', 'name', 'slug', 'description', 'sort_order', 'is_active']);
        if (array_key_exists('name', $payload)) {
            $payload['name'] = trim((string) $payload['name']);
        }
        if (! array_key_exists('slug', $payload) && filled($payload['name'] ?? null)) {
            $payload['slug'] = Str::slug((string) $payload['name']);
        }
        if (array_key_exists('slug', $payload)) {
            $payload['slug'] = Str::slug((string) $payload['slug']);
        }
        if (array_key_exists('description', $payload)) {
            $payload['description'] = $this->boundedText((string) $payload['description'], 10_000, 'Category description');
        }

        return $payload;
    }

    /** Reject self-links, missing parents, and cycles at the service boundary. */
    private function assertValidParent(PropertyCategory $category, mixed $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentId = (int) $parentId;
        if (! PropertyCategory::query()->whereKey($parentId)->exists()) {
            throw new CatalogException('The selected parent category does not exist.');
        }
        if ($category->exists && $parentId === $category->getKey()) {
            throw new CatalogException('A category cannot be its own parent.');
        }

        $visited = [];
        while ($parentId > 0) {
            if (in_array($parentId, $visited, true) || ($category->exists && $parentId === $category->getKey())) {
                throw new CatalogException('The selected parent would create a category cycle.');
            }

            $visited[] = $parentId;
            $parentId = (int) (PropertyCategory::query()->whereKey($parentId)->value('parent_id') ?? 0);
        }
    }

    /** Assert required category identity values after normalization. */
    private function assertValid(PropertyCategory $category): void
    {
        if (blank($category->name) || blank($category->slug)) {
            throw new CatalogException('Property categories require a name and slug.');
        }
    }

    /** Assert that Media Library can consume the supplied upload. */
    private function assertUpload(UploadedFile $upload): void
    {
        if (! $upload->isValid()) {
            throw new CatalogException('The selected image could not be read.');
        }
        if (! in_array($upload->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new CatalogException('Category images must be JPEG, PNG, or WebP files.');
        }
        $maximumBytes = max(1, (int) config('property-booking.media.upload_max_kilobytes', 5120)) * 1024;
        if (($upload->getSize() ?: 0) > $maximumBytes) {
            throw new CatalogException('The category image exceeds the configured upload limit.');
        }
    }

    /** Normalize bounded optional text without retaining whitespace-only values. */
    private function boundedText(string $value, int $maximum, string $label): ?string
    {
        $value = trim($value);
        if (mb_strlen($value) > $maximum) {
            throw new CatalogException("{$label} cannot exceed {$maximum} characters.");
        }

        return $value === '' ? null : $value;
    }
}
