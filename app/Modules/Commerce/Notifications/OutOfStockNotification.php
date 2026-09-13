<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Notifications\Messages\MailMessage;

/** Alerts inventory managers when a positive stock balance is depleted. */
final class OutOfStockNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'out_of_stock';

    public function __construct(
        public readonly string $productUlid,
        public readonly int $balanceAfter,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $product = $this->product();

        return $channel === 'mail'
            && $this->permittedStaff($notifiable, CommercePermission::MANAGE_INVENTORY)
            && $product?->track_stock === true
            && $product->stock?->on_hand !== null
            && $product->stock->on_hand <= 0;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->productOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} out of stock: {$product->name}")
            ->greeting('Stock depleted')
            ->line("{$product->name} ({$product->sku}) has reached {$this->balanceAfter} available units.")
            ->action('Review inventory', route('commerce.admin.inventory.index'))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{product_ulid: string, balance_after: int} */
    public function toArray(object $notifiable): array
    {
        return ['product_ulid' => $this->productUlid, 'balance_after' => $this->balanceAfter];
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
