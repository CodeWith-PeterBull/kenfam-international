<?php

/**
 * Owns TravelTours category hierarchy and visibility writes.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Services;

use App\Modules\TravelTours\Catalog\Data\TourCategoryData;
use App\Modules\TravelTours\Catalog\Enums\PublicationStatus;
use App\Modules\TravelTours\Catalog\Exceptions\CatalogException;
use App\Modules\TravelTours\Catalog\Exceptions\HierarchyCycle;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/** Keep category normalization, hierarchy validation, and activation atomic. */
final readonly class TourCategoryService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** Create one validated category node. */
    public function create(TourCategoryData $data): TourCategory
    {
        return $this->database->transaction(function () use ($data): TourCategory {
            $category = new TourCategory;
            $category->forceFill($this->payload($data));
            $this->assertIdentity($category);
            $this->assertUnique($category);
            $this->assertParent($category, $data->parentId);
            $category->save();

            return $category->refresh()->load('parent');
        });
    }

    /** Update one category while rejecting recursive hierarchy changes. */
    public function update(TourCategory $category, TourCategoryData $data): TourCategory
    {
        return $this->database->transaction(function () use ($category, $data): TourCategory {
            $category = TourCategory::query()->lockForUpdate()->findOrFail($category->getKey());
            $wasActive = $category->is_active;
            $category->forceFill($this->payload($data));
            $this->assertIdentity($category);
            $this->assertUnique($category);
            $this->assertParent($category, $data->parentId);
            if ($wasActive && ! $category->is_active) {
                $this->assertCanDeactivate($category);
            }
            $category->save();

            return $category->refresh()->load('parent');
        });
    }

    /** Toggle assignment visibility without deleting retained relationships. */
    public function setActive(TourCategory $category, bool $active): TourCategory
    {
        return $this->database->transaction(function () use ($category, $active): TourCategory {
            $category = TourCategory::query()->lockForUpdate()->findOrFail($category->getKey());
            if ($category->is_active === $active) {
                return $category;
            }

            if ($active) {
                $this->assertParent($category, $category->parent_id);
            } else {
                $this->assertCanDeactivate($category);
            }

            $category->forceFill(['is_active' => $active])->save();

            return $category->refresh()->load('parent');
        });
    }

    /** Convert the immutable input object into normalized persistence values. */
    private function payload(TourCategoryData $data): array
    {
        $name = trim($data->name);

        return [
            'parent_id' => $data->parentId,
            'name' => $name,
            'slug' => Str::slug($data->slug ?: $name),
            'description' => $this->optionalText($data->description, 10_000, 'Category description'),
            'icon_key' => $this->iconKey($data->iconKey),
            'sort_order' => max(0, $data->sortOrder),
            'is_active' => $data->isActive,
            'meta_title' => $this->optionalText($data->metaTitle, 160, 'Meta title'),
            'meta_description' => $this->optionalText($data->metaDescription, 320, 'Meta description'),
        ];
    }

    /** Assert required category identity fields after normalization. */
    private function assertIdentity(TourCategory $category): void
    {
        if (blank($category->name) || blank($category->slug)) {
            throw new CatalogException('Tour categories require a name and URL slug.');
        }
        if (mb_strlen($category->name) > 160 || mb_strlen($category->slug) > 180) {
            throw new CatalogException('The category name or URL slug exceeds its supported length.');
        }
    }

    /** Keep slugs deterministic even when services are called outside Livewire. */
    private function assertUnique(TourCategory $category): void
    {
        $exists = TourCategory::query()
            ->where('slug', $category->slug)
            ->when($category->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
            ->exists();

        if ($exists) {
            throw new CatalogException('Another tour category already uses this URL slug.');
        }
    }

    /** Reject deactivation while active descendants or public assignments depend on this node. */
    private function assertCanDeactivate(TourCategory $category): void
    {
        if ($category->children()->active()->exists()) {
            throw new CatalogException('Hide active child categories before hiding this category.');
        }
        if ($category->tours()->where('status', PublicationStatus::Published->value)->exists()) {
            throw new CatalogException('A category assigned to published tours cannot be hidden.');
        }
    }

    /** Reject missing, inactive, self-referential, and descendant parents. */
    private function assertParent(TourCategory $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($category->exists && $parentId === $category->getKey()) {
            throw new HierarchyCycle('A category cannot be its own parent.');
        }

        $visited = [];
        $currentId = $parentId;
        while ($currentId !== null) {
            if (in_array($currentId, $visited, true) || ($category->exists && $currentId === $category->getKey())) {
                throw new HierarchyCycle('The selected parent would create a category cycle.');
            }

            $visited[] = $currentId;
            $parent = TourCategory::query()->lockForUpdate()->find($currentId);
            if (! $parent instanceof TourCategory) {
                throw new CatalogException('The selected parent category does not exist.');
            }
            if (! $parent->is_active) {
                throw new CatalogException('The selected parent category must be active.');
            }
            $currentId = $parent->parent_id;
        }
    }

    /** Normalize and validate a trusted icon-library key. */
    private function iconKey(?string $value): ?string
    {
        $value = $this->optionalText($value, 80, 'Icon key');
        if ($value !== null && preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
            throw new CatalogException('The icon key may contain only lowercase letters, numbers, and hyphens.');
        }

        return $value;
    }

    /** Trim bounded optional content without retaining whitespace-only values. */
    private function optionalText(?string $value, int $maximum, string $label): ?string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) > $maximum) {
            throw new CatalogException("{$label} cannot exceed {$maximum} characters.");
        }

        return $value === '' ? null : $value;
    }
}
