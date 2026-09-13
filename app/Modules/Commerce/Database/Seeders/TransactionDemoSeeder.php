<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Database\Seeders;

use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Data\PaymentData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Orders\Services\CartCalculator;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Services\PosCheckoutService;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Seeds coherent web and POS transaction examples through domain services.
 */
final class TransactionDemoSeeder extends Seeder
{
    private const WEB_MARKER = 'commerce-demo:web-order';

    private const POS_MARKER = 'commerce-demo:pos-order';

    private const TILL_MARKER = 'Commerce demonstration till session';

    /**
     * Seed transaction history after the host accounts and demo catalog exist.
     */
    public function run(
        string $webOrderSku = 'AUR-PH-X101',
        array $posOrderSkus = ['AUR-AU-ANC5', 'AUR-AU-SP06'],
    ): void {
        if (count($posOrderSkus) !== 2) {
            throw new LogicException('Commerce demonstration POS transactions require exactly two product SKUs.');
        }

        $administrator = User::query()->where('email', 'admin@aureon.test')->first()
            ?? User::query()->first();
        if (! $administrator instanceof User) {
            throw new LogicException('Seed the host application accounts before Commerce demonstration transactions.');
        }

        $cashier = User::query()->where('email', CommerceAccessDemoSeeder::CASHIER_EMAIL)->first();
        if (! $cashier instanceof User) {
            throw new LogicException('Seed the Commerce access demonstration account before Commerce transactions.');
        }

        $register = $this->seedRegisters();
        $this->seedWebOrder($administrator, $webOrderSku);
        $this->seedPosOrder($cashier, $register, array_values($posOrderSkus));
    }

    /**
     * Create two stable register records and return the demonstration counter.
     */
    private function seedRegisters(): Register
    {
        $counter = Register::query()->updateOrCreate(
            ['code' => 'DEMO-COUNTER-01'],
            [
                'name' => 'Main demonstration counter',
                'location_label' => 'Aureon flagship store',
                'description' => 'Primary register used by the optional Commerce demonstration dataset.',
                'receipt_print_driver' => 'browser',
                'receipt_print_mode' => 'auto_prompt',
                'receipt_paper_width' => 80,
                'receipt_printer_name' => 'Main counter receipt printer',
                'is_active' => true,
            ],
        );

        Register::query()->updateOrCreate(
            ['code' => 'DEMO-MOBILE-02'],
            [
                'name' => 'Mobile demonstration register',
                'location_label' => 'Events and pop-up counter',
                'description' => 'Secondary active register ready for later POS interface demonstrations.',
                'receipt_print_driver' => 'browser',
                'receipt_print_mode' => 'manual',
                'receipt_paper_width' => 58,
                'receipt_printer_name' => 'Mobile receipt printer',
                'is_active' => true,
            ],
        );

        return $counter;
    }

    /**
     * Place one pickup order through the trusted web-order service exactly once.
     */
    private function seedWebOrder(User $actor, string $productSku): void
    {
        if (Order::query()->where('internal_note', self::WEB_MARKER)->exists()) {
            return;
        }

        $customer = Customer::query()->where('email', 'amina.njoroge@example.test')->firstOrFail();
        $product = Product::query()->where('sku', $productSku)->firstOrFail();
        $data = new OrderPlacementData(
            items: [new CartItemData($product->getKey(), 1)],
            customer: $this->snapshot($customer),
            fulfillmentType: FulfillmentType::Pickup,
            preferredPaymentMethod: PaymentMethod::MobileMoney,
            customerNote: 'Please confirm when the order is ready for collection.',
            internalNote: self::WEB_MARKER,
        );

        app(OrderService::class)->placeWebOrder($data, $customer, actor: $actor);
    }

    /**
     * Complete one split-tender counter sale and reconcile its till exactly once.
     */
    private function seedPosOrder(User $actor, Register $register, array $productSkus): void
    {
        $order = Order::query()->where('internal_note', self::POS_MARKER)->first();

        if (! $order instanceof Order) {
            $session = TillSession::query()
                ->where('opening_note', self::TILL_MARKER)
                ->where('status', TillSessionStatus::Open->value)
                ->first();
            $session ??= app(TillService::class)->open($register, $actor, 5_000_00, self::TILL_MARKER);

            $customer = Customer::query()->where('email', 'brian.otieno@example.test')->firstOrFail();
            $products = Product::query()
                ->whereIn('sku', $productSkus)
                ->orderBy('id')
                ->get();
            if ($products->count() !== 2) {
                throw new LogicException('The Commerce demonstration POS products are unavailable.');
            }

            $data = new OrderPlacementData(
                items: $products->map(static fn (Product $product): CartItemData => new CartItemData($product->getKey(), 1))->all(),
                customer: $this->snapshot($customer),
                fulfillmentType: FulfillmentType::Counter,
                preferredPaymentMethod: PaymentMethod::Cash,
                internalNote: self::POS_MARKER,
            );
            $calculation = app(CartCalculator::class)->calculate($data, $products);
            $mobileAmount = intdiv($calculation->totalMinor, 2);
            $cashAmount = $calculation->totalMinor - $mobileAmount;
            $cashTendered = intdiv($cashAmount + 99_999, 100_000) * 100_000;

            $order = app(PosCheckoutService::class)->checkout(
                data: $data,
                payments: [
                    new PaymentData(PaymentMethod::MobileMoney, $mobileAmount, reference: 'DEMO-MPESA-001'),
                    new PaymentData(PaymentMethod::Cash, $cashAmount, tenderedMinor: $cashTendered),
                ],
                session: $session,
                cashier: $actor,
                customer: $customer,
            );
        }

        $session = $order->tillSession;
        if ($session instanceof TillSession && $session->isOpen()) {
            $completedCash = (int) Payment::query()
                ->whereBelongsTo($session)
                ->where('status', PaymentStatus::Completed->value)
                ->where('method', PaymentMethod::Cash->value)
                ->sum('amount_minor');

            app(TillService::class)->close(
                session: $session,
                actor: $actor,
                countedCashMinor: $session->opening_float_minor + $completedCash,
                note: 'Balanced demonstration close.',
            );
        }
    }

    /**
     * Copy a reusable customer into the immutable order snapshot contract.
     */
    private function snapshot(Customer $customer): CustomerSnapshotData
    {
        return new CustomerSnapshotData(
            firstName: $customer->first_name,
            lastName: $customer->last_name,
            company: $customer->company,
            taxIdentifier: $customer->tax_identifier,
            email: $customer->email,
            phone: $customer->phone,
            addressLine1: $customer->address_line_1,
            addressLine2: $customer->address_line_2,
            city: $customer->city,
            region: $customer->region,
            postalCode: $customer->postal_code,
            countryCode: $customer->country_code,
        );
    }
}
