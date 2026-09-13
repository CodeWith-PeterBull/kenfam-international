<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Contracts\RendersPdfReports;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\OrderItem;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Orders\Services\OrderDocumentDataFactory;
use App\Modules\Commerce\Orders\Services\OrderDocumentService;
use App\Modules\Commerce\PointOfSale\Data\Documents\PosReceiptData;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPaperWidth;
use App\Modules\Commerce\PointOfSale\Enums\ReceiptPrintMode;
use App\Modules\Commerce\PointOfSale\Livewire\Admin\RegisterManager;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Printing\Contracts\ReceiptPrinterDriver;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrinterSettingsData;
use App\Modules\Commerce\PointOfSale\Printing\Data\ReceiptPrintInstructionData;
use App\Modules\Commerce\PointOfSale\Printing\ReceiptPrinterManager;
use App\Modules\Commerce\PointOfSale\Services\PosReceiptDataFactory;
use App\Modules\Commerce\PointOfSale\Services\PosReceiptService;
use App\Modules\Commerce\Support\CommercePermission;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Verifies immutable document boundaries and register-owned receipt printing.
 */
final class CommerceDocumentAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_projections_include_financial_snapshots_and_exclude_private_fields(): void
    {
        [$order] = $this->completedPosOrder();
        $order->forceFill([
            'internal_note' => 'PRIVATE-INTERNAL-NOTE',
            'customer_note' => 'PRIVATE-CUSTOMER-NOTE',
            'customer_email' => 'private@example.test',
            'discount_minor' => 500,
            'delivery_fee_minor' => 250,
        ])->save();
        $order->items()->firstOrFail()->forceFill([
            'unit_cost_minor' => 1_234,
            'tax_minor' => 1_379,
            'line_total_minor' => 10_000,
        ])->save();
        $order->payments()->firstOrFail()->forceFill([
            'metadata' => ['credential' => 'PRIVATE-PAYMENT-METADATA'],
            'change_minor' => 750,
        ])->save();

        $receipt = app(PosReceiptDataFactory::class)->fromOrder($order->refresh());
        $summary = app(OrderDocumentDataFactory::class)->fromOrder($order);
        $encodedReceipt = json_encode($receipt, JSON_THROW_ON_ERROR);
        $encodedSummary = json_encode($summary, JSON_THROW_ON_ERROR);

        $this->assertSame(1_379, $summary->lines[0]->taxMinor);
        $this->assertSame(10_000, $summary->lines[0]->lineTotalMinor);
        $this->assertSame(500, $summary->discountMinor);
        $this->assertSame(250, $summary->deliveryFeeMinor);
        $this->assertSame(750, $receipt->payments[0]->changeMinor);
        $this->assertSame('POS-20260719-000001', $receipt->orderNumber);

        foreach ([
            'PRIVATE-INTERNAL-NOTE',
            'PRIVATE-CUSTOMER-NOTE',
            'private@example.test',
            'PRIVATE-PAYMENT-METADATA',
            'unitCostMinor',
            'metadata',
            'internalNote',
            'customerEmail',
            'customerPhone',
            'customerTaxIdentifier',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $encodedReceipt);
            $this->assertStringNotContainsString($privateValue, $encodedSummary);
        }
        $this->assertArrayNotHasKey('id', get_object_vars($receipt));
        $this->assertArrayNotHasKey('ulid', get_object_vars($receipt));
        $this->assertArrayNotHasKey('id', get_object_vars($summary));
        $this->assertArrayNotHasKey('ulid', get_object_vars($summary));
    }

    public function test_order_summary_adapters_have_sanitized_stream_download_and_orientation_parity(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'WEB 2026/0001',
            'discount_minor' => 500,
            'delivery_fee_minor' => 750,
            'subtotal_minor' => 200_000,
            'tax_minor' => 27_586,
            'total_minor' => 200_250,
        ]);
        OrderItem::factory()->count(45)->for($order)->create([
            'product_id' => null,
            'product_name' => str_repeat('Long document product name ', 4),
        ]);
        $documents = app(OrderDocumentService::class);

        foreach (ReportOrientation::cases() as $orientation) {
            $stream = $documents->stream($order, $orientation, 'Document operator');
            $download = $documents->download($order, $orientation, 'Document operator');

            $this->assertSame('application/pdf', $stream->headers->get('Content-Type'));
            $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
            $this->assertStringContainsString('inline;', (string) $stream->headers->get('Content-Disposition'));
            $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
            $this->assertStringContainsString(
                "web-2026-0001-{$orientation->value}.pdf",
                (string) $download->headers->get('Content-Disposition'),
            );
            $this->assertStringStartsWith('%PDF', $stream->getContent());
        }

        $projection = app(OrderDocumentDataFactory::class)->fromOrder($order);
        $rendered = app(RendersPdfReports::class)->render(
            'commerce::reports.order-summary',
            ['document' => $projection],
            new ReportContext(
                title: 'Multi-page order',
                subtitle: null,
                filename: 'multi-page-order.pdf',
                orientation: ReportOrientation::Portrait,
                generatedAt: CarbonImmutable::now(),
                generatedBy: 'Document test',
            ),
        );
        $this->assertGreaterThan(1, $rendered->pageCount);
    }

    public function test_receipt_adapter_has_stream_download_parity_and_rejects_landscape(): void
    {
        [$order] = $this->completedPosOrder();
        $receipts = app(PosReceiptService::class);
        $stream = $receipts->stream($order, 'Receipt operator');
        $download = $receipts->download($order, 'Receipt operator');

        $this->assertStringContainsString('inline;', (string) $stream->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('pos-20260719-000001-receipt.pdf', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $stream->getContent());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('portrait orientation only');
        $receipts->stream($order, 'Receipt operator', ReportOrientation::Landscape);
    }

    public function test_register_manager_persists_validated_receipt_printer_preferences(): void
    {
        $manager = $this->userWithPermission(CommercePermission::MANAGE_TILLS);

        Livewire::actingAs($manager)
            ->test(RegisterManager::class)
            ->call('openCreate')
            ->assertSeeHtml('id="register-print-driver"')
            ->assertSeeHtml('id="register-print-mode"')
            ->assertSeeHtml('id="register-paper-width"')
            ->assertSeeHtml('id="register-printer-name"')
            ->set('form.name', 'Front counter')
            ->set('form.code', 'front-print-01')
            ->set('form.receiptPrintDriver', 'browser')
            ->set('form.receiptPrintMode', ReceiptPrintMode::AutoPrompt->value)
            ->set('form.receiptPaperWidth', (string) ReceiptPaperWidth::Roll58->value)
            ->set('form.receiptPrinterName', 'Reception thermal printer')
            ->call('save')
            ->assertHasNoErrors();

        $register = Register::query()->sole();
        $this->assertSame('FRONT-PRINT-01', $register->code);
        $this->assertSame(ReceiptPrintMode::AutoPrompt, $register->receipt_print_mode);
        $this->assertSame(ReceiptPaperWidth::Roll58, $register->receipt_paper_width);
        $this->assertSame('Reception thermal printer', $register->receipt_printer_name);
    }

    public function test_printer_manager_resolves_a_configured_extension_driver(): void
    {
        [$order, , $register] = $this->completedPosOrder([
            'receipt_print_driver' => 'test-bridge',
            'receipt_print_mode' => ReceiptPrintMode::AutoPrompt,
        ]);
        config()->set('commerce.pos.receipt_printing.drivers.test-bridge', TestReceiptPrinterDriver::class);
        $receipt = app(PosReceiptDataFactory::class)->fromOrder($order);

        $instruction = app(ReceiptPrinterManager::class)->instruction($register, $receipt, true);

        $this->assertSame('test-bridge', $instruction->driver);
        $this->assertSame('test-local-bridge', $instruction->strategy);
        $this->assertTrue($instruction->autoPrompt);
        $this->assertTrue($instruction->supportsSilentPrinting);
    }

    public function test_receipt_page_uses_register_roll_profile_and_only_auto_prompts_after_checkout(): void
    {
        [$order, $cashier, $register] = $this->completedPosOrder([
            'receipt_print_mode' => ReceiptPrintMode::AutoPrompt,
            'receipt_paper_width' => ReceiptPaperWidth::Roll58,
            'receipt_printer_name' => 'Counter queue',
        ]);
        $route = route('commerce.pos.receipts.show', $order);

        $quiet = $this->actingAs($cashier)->get($route);
        $quiet->assertOk()
            ->assertSee('data-pos-paper-width="58"', false)
            ->assertSee('"autoPrompt":false', false)
            ->assertSee('"supportsSilentPrinting":false', false)
            ->assertDontSee('PRIVATE-PAYMENT-METADATA')
            ->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $quiet->headers->get('Cache-Control'));

        $this->actingAs($cashier)
            ->get($route.'?print=checkout')
            ->assertOk()
            ->assertSee('"autoPrompt":true', false)
            ->assertSee('"printerName":"Counter queue"', false);

        $register->forceFill(['receipt_print_mode' => ReceiptPrintMode::Manual])->save();
        $this->actingAs($cashier)
            ->get($route.'?print=checkout')
            ->assertOk()
            ->assertSee('"autoPrompt":false', false);
    }

    /**
     * @param  array<string, mixed>  $registerAttributes
     * @return array{0: Order, 1: User, 2: Register}
     */
    private function completedPosOrder(array $registerAttributes = []): array
    {
        $cashier = $this->userWithPermission(CommercePermission::ACCESS_POS);
        $register = Register::factory()->create($registerAttributes);
        $order = Order::factory()->create([
            'order_number' => 'POS-20260719-000001',
            'channel' => OrderChannel::PointOfSale,
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'fulfillment_type' => FulfillmentType::Counter,
            'preferred_payment_method' => PaymentMethod::Cash,
            'register_id' => $register->id,
            'cashier_id' => $cashier->id,
            'subtotal_minor' => 10_000,
            'discount_minor' => 0,
            'delivery_fee_minor' => 0,
            'tax_minor' => 1_379,
            'total_minor' => 10_000,
            'paid_minor' => 10_000,
            'customer_first_name' => 'Walk-in',
            'customer_last_name' => 'Customer',
            'internal_note' => 'PRIVATE-INTERNAL-NOTE',
            'completed_at' => now(),
        ]);
        OrderItem::factory()->for($order)->create([
            'product_id' => null,
            'product_name' => 'Aureon display',
            'sku' => 'AUR-DISPLAY-01',
            'quantity' => 2,
            'unit_price_minor' => 5_000,
            'unit_cost_minor' => 3_500,
            'line_subtotal_minor' => 10_000,
            'tax_minor' => 1_379,
            'line_total_minor' => 10_000,
        ]);
        Payment::factory()->completed()->for($order)->create([
            'method' => PaymentMethod::Cash,
            'amount_minor' => 10_000,
            'tendered_minor' => 10_750,
            'change_minor' => 750,
            'reference' => 'POS-CASH-001',
            'metadata' => ['credential' => 'PRIVATE-PAYMENT-METADATA'],
        ]);

        return [$order, $cashier, $register];
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }
}

/**
 * Test-only driver proving that printer implementations are config-resolved.
 */
final readonly class TestReceiptPrinterDriver implements ReceiptPrinterDriver
{
    public function instruction(
        ReceiptPrinterSettingsData $settings,
        PosReceiptData $receipt,
        bool $afterCheckout,
    ): ReceiptPrintInstructionData {
        return new ReceiptPrintInstructionData(
            driver: $settings->driver,
            strategy: 'test-local-bridge',
            mode: $settings->mode,
            paperWidth: $settings->paperWidth,
            printerName: $settings->printerName,
            autoPrompt: $afterCheckout,
            supportsSilentPrinting: true,
            receiptKey: $receipt->orderNumber,
        );
    }
}
