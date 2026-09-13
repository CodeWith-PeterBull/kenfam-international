<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Http\Controllers;

use App\Contracts\RecordsSystemActivity;
use App\Contracts\ResolvesInstitutionProfile;
use App\Enums\SystemActivitySeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Printing\ReceiptPrinterManager;
use App\Modules\Commerce\PointOfSale\Services\PosReceiptDataFactory;
use App\Modules\Commerce\PointOfSale\Services\PosReceiptService;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Presents printable POS receipts without exposing another cashier's sales.
 */
final class ReceiptController extends Controller
{
    public function show(
        Request $request,
        Order $order,
        PosReceiptDataFactory $documents,
        ReceiptPrinterManager $printers,
        ResolvesInstitutionProfile $profiles,
    ): Response {
        $this->authorizeReceipt($request, $order);
        $receipt = $documents->fromOrder($order);
        $printInstruction = $printers->instruction(
            register: $order->register,
            receipt: $receipt,
            afterCheckout: $request->query('print') === 'checkout',
        );

        $response = response()->view('commerce::pos.receipts.show', [
            'receipt' => $receipt,
            'institutionProfile' => $profiles->current(),
            'printInstruction' => $printInstruction,
            'receiptPdfUrl' => route('commerce.pos.receipts.pdf', $order),
        ]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    public function pdf(
        Request $request,
        Order $order,
        PosReceiptService $receipts,
        RecordsSystemActivity $activities,
    ): Response {
        $user = $this->authorizeReceipt($request, $order);
        $response = $receipts->stream($order, $user->display_name);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        $activities->record(
            activityType: 'commerce.pos.receipt_printed',
            description: "POS receipt {$order->order_number} rendered",
            actor: $user,
            subject: $order,
            properties: ['format' => 'pdf'],
            severity: SystemActivitySeverity::Info,
            source: 'commerce-pos',
        );

        return $response;
    }

    private function authorizeReceipt(Request $request, Order $order): User
    {
        abort_unless(
            $order->channel === OrderChannel::PointOfSale && $order->status === OrderStatus::Completed,
            404,
        );

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $ownsSale = $order->cashier_id === $user->getKey()
            && $user->can(CommercePermission::ACCESS_POS);
        $canReviewSales = $user->can(CommercePermission::VIEW_ORDERS)
            || $user->can(CommercePermission::MANAGE_TILLS);
        abort_unless($ownsSale || $canReviewSales, 403);

        return $user;
    }
}
