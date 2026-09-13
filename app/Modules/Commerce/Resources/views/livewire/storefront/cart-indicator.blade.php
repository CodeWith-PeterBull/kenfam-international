<a class="commerce-icon-button commerce-cart-indicator" href="{{ route('commerce.storefront.cart.index') }}" aria-label="Cart with {{ $cartCount }} {{ \Illuminate\Support\Str::plural('item', $cartCount) }}" title="Cart">
    <i data-lucide="shopping-bag" aria-hidden="true"></i>
    @if ($cartCount > 0)<span aria-hidden="true">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>@endif
</a>
