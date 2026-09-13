<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells a customer that one web order is ready for fulfillment. */
final class CustomerOrderReadyNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'order_ready';

    public function __construct(public readonly string $orderUlid)
    {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $order = $this->order();

        return $channel === 'mail'
            && $order?->channel === OrderChannel::Web
            && $order->status === OrderStatus::Ready
            && $this->matchesOrderEmail($notifiable, $order->customer_email);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} order {$order->order_number} is ready")
            ->greeting("Hello {$order->customer_first_name},")
            ->line("Your order {$order->order_number} is ready for {$order->fulfillment_type->label()}.")
            ->action('View order status', app(OrderAccessUrlService::class)->tracking($order))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{order_ulid: string} */
    public function toArray(object $notifiable): array
    {
        return ['order_ulid' => $this->orderUlid];
    }

    private function order(): ?Order
    {
        return Order::query()->where('ulid', $this->orderUlid)->first();
    }

    private function orderOrFail(): Order
    {
        return Order::query()->where('ulid', $this->orderUlid)->firstOrFail();
    }
}
