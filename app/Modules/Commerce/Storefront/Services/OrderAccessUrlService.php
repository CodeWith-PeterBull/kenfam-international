<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Services;

use App\Enums\ReportOrientation;
use App\Modules\Commerce\Orders\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * Generates independent temporary signatures for every public order surface.
 */
final class OrderAccessUrlService
{
    public function confirmation(Order $order): string
    {
        $minutes = max(5, (int) config('commerce.storefront.confirmation_link_minutes', 120));

        return URL::temporarySignedRoute(
            'commerce.storefront.orders.confirmation',
            now()->addMinutes($minutes),
            ['order' => $order->getRouteKey()],
        );
    }

    public function tracking(Order $order): string
    {
        $days = max(1, (int) config('commerce.storefront.tracking_link_days', 90));

        return URL::temporarySignedRoute(
            'commerce.storefront.orders.track',
            now()->addDays($days),
            ['order' => $order->getRouteKey()],
        );
    }

    public function document(Order $order, ReportOrientation $orientation = ReportOrientation::Portrait): string
    {
        $days = max(1, (int) config('commerce.storefront.document_link_days', 30));

        return URL::temporarySignedRoute(
            'commerce.storefront.orders.document',
            now()->addDays($days),
            ['order' => $order->getRouteKey(), 'orientation' => $orientation->value],
        );
    }
}
