<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Notifications\Messages\MailMessage;

/** Alerts inventory managers when stock first enters the positive low band. */
final class LowStockNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'low_stock';

    public function __construct(
        public readonly string $productUlid,
        public readonly int $balanceAfter,
        public readonly int $threshold,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $product = $this->product();
        $onHand = $product?->stock?->on_hand;

        return $channel === 'mail'
            && $this->permittedStaff($notifiable, CommercePermission::MANAGE_INVENTORY)
            && $product?->track_stock === true
            && is_int($onHand)
            && $onHand > 0
            && $onHand <= $product->stock->low_stock_threshold;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->productOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} low stock: {$product->name}")
            ->greeting('Low-stock threshold reached')
            ->line("{$product->name} ({$product->sku}) now has {$this->balanceAfter} units available.")
            ->line("Configured threshold: {$this->threshold} units.")
            ->action('Review inventory', route('commerce.admin.inventory.index'))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{product_ulid: string, balance_after: int, threshold: int} */
    public function toArray(object $notifiable): array
    {
        return [
            'product_ulid' => $this->productUlid,
            'balance_after' => $this->balanceAfter,
            'threshold' => $this->threshold,
        ];
    }

    private function product(): ?Product
    {
        return Product::query()->with('stock')->where('ulid', $this->productUlid)->first();
    }

    private function productOrFail(): Product
    {
        return Product::query()->with('stock')->where('ulid', $this->productUlid)->firstOrFail();
    }
}
