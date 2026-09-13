<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Confirms one completed web-order payment to the snapshot email address. */
final class CustomerPaymentConfirmedNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'payment_confirmed';

    public function __construct(
        public readonly string $orderUlid,
        public readonly string $paymentUlid,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $payment = $this->payment();

        return $channel === 'mail'
            && $payment?->status === PaymentStatus::Completed
            && $payment->order?->ulid === $this->orderUlid
            && $payment->order->channel === OrderChannel::Web
            && $this->matchesOrderEmail($notifiable, $payment->order->customer_email);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->paymentOrFail();
        $order = $payment->order;
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} payment confirmed for {$order->order_number}")
            ->greeting("Hello {$order->customer_first_name},")
            ->line('We confirmed your '.MoneyFormatter::format($payment->amount_minor)." payment for order {$order->order_number}.")
            ->line("Payment method: {$payment->method->label()}.")
            ->action('Track your order', app(OrderAccessUrlService::class)->tracking($order))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{order_ulid: string, payment_ulid: string} */
    public function toArray(object $notifiable): array
    {
        return ['order_ulid' => $this->orderUlid, 'payment_ulid' => $this->paymentUlid];
    }

    private function payment(): ?Payment
    {
        return Payment::query()->with('order')->where('ulid', $this->paymentUlid)->first();
    }

    private function paymentOrFail(): Payment
    {
        return Payment::query()->with('order')->where('ulid', $this->paymentUlid)->firstOrFail();
    }
}
