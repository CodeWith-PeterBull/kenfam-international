<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Enums;

/**
 * Controls administrative lifecycle and storefront visibility for a product.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Return the human-readable administration label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    /**
     * Return a stable Bootstrap semantic color name.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Published => 'success',
            self::Archived => 'dark',
        };
    }
}
