<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Contracts\RecordsSystemActivity;
use App\Enums\ReportOrientation;
use App\Enums\SystemActivitySeverity;
use App\Http\Controllers\Controller;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Services\OrderDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Presents order administration and audited document downloads.
 */
final class OrderController extends Controller
{
    public function index(): View
    {
        return view('commerce::admin.orders.index');
    }

    public function document(
        Request $request,
        Order $order,
        ReportOrientation $orientation,
        OrderDocumentService $documents,
        RecordsSystemActivity $activities,
    ): Response {
        Gate::authorize('view', $order);
        $response = $documents->download($order, $orientation, $request->user()->display_name);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        $activities->record(
            activityType: 'commerce.order.document_downloaded',
            description: "Order {$order->order_number} document downloaded",
            actor: $request->user(),
            subject: $order,
            properties: ['orientation' => $orientation->value],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-orders',
        );

        return $response;
    }
}
