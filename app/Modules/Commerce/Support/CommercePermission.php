<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Support;

/**
 * Developer-owned capability catalogue for Commerce administration and POS.
 */
final class CommercePermission
{
    public const VIEW_DASHBOARD = 'view-commerce-dashboard';

    public const VIEW_PRODUCTS = 'view-products';

    public const MANAGE_PRODUCTS = 'manage-products';

    public const VIEW_INVENTORY = 'view-inventory';

    public const MANAGE_INVENTORY = 'manage-inventory';

    public const ACCESS_POS = 'access-pos';

    public const MANAGE_TILLS = 'manage-tills';

    public const VIEW_ORDERS = 'view-orders';

    public const MANAGE_ORDERS = 'manage-orders';

    public const MANAGE_CUSTOMERS = 'manage-customers';

    public const MANAGE_DEMO_DATA = 'manage-commerce-demo-data';

    /**
     * Return labels consumed by the shared role composer and seeders.
     *
     * @return array<string, array{label: string, group: string, description: string}>
     */
    public static function catalogue(): array
    {
        return [
            self::VIEW_DASHBOARD => [
                'label' => 'View Commerce dashboard',
                'group' => 'Commerce',
                'description' => 'View cross-channel sales, order, stock, customer, and till summaries.',
            ],
            self::VIEW_PRODUCTS => [
                'label' => 'View products',
                'group' => 'Commerce',
                'description' => 'Browse the administrative product and category catalogue.',
            ],
            self::MANAGE_PRODUCTS => [
                'label' => 'Manage products',
                'group' => 'Commerce',
                'description' => 'Create, update, publish, archive, and organize products and categories.',
            ],
            self::VIEW_INVENTORY => [
                'label' => 'View inventory',
                'group' => 'Commerce',
                'description' => 'Inspect stock projections and immutable movement history.',
            ],
            self::MANAGE_INVENTORY => [
                'label' => 'Manage inventory',
                'group' => 'Commerce',
                'description' => 'Post controlled manual stock adjustments.',
            ],
            self::ACCESS_POS => [
                'label' => 'Access point of sale',
                'group' => 'Commerce',
                'description' => 'Use the cashier terminal and complete authorized POS sales.',
            ],
            self::MANAGE_TILLS => [
                'label' => 'Manage tills',
                'group' => 'Commerce',
                'description' => 'Configure registers and open or reconcile till sessions.',
            ],
            self::VIEW_ORDERS => [
                'label' => 'View orders',
                'group' => 'Commerce',
                'description' => 'Inspect web orders, POS sales, and payment records.',
            ],
            self::MANAGE_ORDERS => [
                'label' => 'Manage orders',
                'group' => 'Commerce',
                'description' => 'Manage eligible order transitions and payment records.',
            ],
            self::MANAGE_CUSTOMERS => [
                'label' => 'Manage customers',
                'group' => 'Commerce',
                'description' => 'Create and maintain reusable customer identities and addresses.',
            ],
            self::MANAGE_DEMO_DATA => [
                'label' => 'Manage Commerce demo data',
                'group' => 'Commerce',
                'description' => 'Run the allowlisted Commerce demonstration-data command from the administration workspace.',
            ],
        ];
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::catalogue());
    }

    private function __construct() {}
}
