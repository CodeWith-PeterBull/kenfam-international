<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Orders\Enums\OrderChannel;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Alerts order managers about one newly committed storefront order. */
final class NewWebOrderReceivedNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'web_order_placed';

    public function __construct(public readonly string $orderUlid)
    {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $order = $this->order();

        return $channel === 'mail'
            && $this->permittedStaff($notifiable, CommercePermission::MANAGE_ORDERS)
            && $order?->channel === OrderChannel::Web
            && $order->status !== OrderStatus::Cancelled;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->orderOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} new web order {$order->order_number}")
            ->greeting('New web order received')
            ->line("{$order->customer_display_name} placed order {$order->order_number} for ".MoneyFormatter::format($order->total_minor).'.')
            ->line("Fulfillment: {$order->fulfillment_type->label()}.")
            ->action('Review order', route('commerce.admin.orders.index'))
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
