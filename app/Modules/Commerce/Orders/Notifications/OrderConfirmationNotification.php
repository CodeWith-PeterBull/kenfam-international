<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued customer acknowledgement for a committed storefront order.
 */
final class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $orderUlid)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = Order::query()->where('ulid', $this->orderUlid)->firstOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();
        $urls = app(OrderAccessUrlService::class);

        return (new MailMessage)
            ->subject("{$profile->shortName} order {$order->order_number} received")
            ->greeting("Hello {$order->customer_first_name},")
            ->line("We have received order {$order->order_number} for ".MoneyFormatter::format($order->total_minor).'.')
            ->line("Fulfillment: {$order->fulfillment_type->label()}. Payment preference: ".($order->preferred_payment_method?->label() ?? 'To be confirmed').'.')
            ->action('Track your order', $urls->tracking($order))
            ->line('[Download your printable order summary]('.$urls->document($order).')')
            ->line('Keep this message private because its links provide temporary access to your order details.')
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{order_ulid: string} */
    public function toArray(object $notifiable): array
    {
        return ['order_ulid' => $this->orderUlid];
    }
}
