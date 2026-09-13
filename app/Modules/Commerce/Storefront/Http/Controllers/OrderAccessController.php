<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Enums\ReportOrientation;
use App\Http\Controllers\Controller;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Services\OrderDocumentService;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use App\Modules\Commerce\Storefront\Services\StorefrontNavigation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves private-by-signature public order confirmation and tracking surfaces.
 */
final class OrderAccessController extends Controller
{
    public function __construct(
        private readonly StorefrontNavigation $navigation,
        private readonly OrderAccessUrlService $urls,
        private readonly OrderDocumentService $documents,
    ) {}

    public function confirmation(Order $order): Response
    {
        $order = $this->publicAggregate($order);

        return response()->view('commerce::storefront.orders.confirmation', [
            'order' => $order,
            'trackingUrl' => $this->urls->tracking($order),
            'documentUrl' => $this->urls->document($order),
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ])->withHeaders($this->privateHeaders());
    }

    public function track(Order $order): Response
    {
        $order = $this->publicAggregate($order);

        return response()->view('commerce::storefront.orders.track', [
            'order' => $order,
            'documentUrl' => $this->urls->document($order),
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ])->withHeaders($this->privateHeaders());
    }

    public function document(Order $order, ReportOrientation $orientation): Response
    {
        $response = $this->documents->stream($this->publicAggregate($order), $orientation);
        foreach ($this->privateHeaders() as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    private function publicAggregate(Order $order): Order
    {
        abort_unless($order->channel === OrderChannel::Web && filled($order->order_number), 404);

        return $order->loadMissing('items');
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
