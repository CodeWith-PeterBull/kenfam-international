<div class="commerce-add-to-cart {{ $compact ? 'commerce-add-to-cart--compact' : '' }}">
    @if (! $compact)
        <div class="commerce-quantity-control" role="group" aria-label="Quantity">
            <button type="button" wire:click="decrement" aria-label="Decrease quantity" @disabled($quantity <= $minimum)>
                <i data-lucide="minus" aria-hidden="true"></i>
            </button>
            <output aria-live="polite" aria-label="Selected quantity">{{ $quantity }}</output>
            <button type="button" wire:click="increment" aria-label="Increase quantity" @disabled($maximum !== null && $quantity >= $maximum)>
                <i data-lucide="plus" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    <button class="commerce-button {{ $compact ? 'commerce-button--compact' : '' }}" type="button" wire:click="add" wire:loading.attr="disabled" wire:target="add">
        <i data-lucide="shopping-bag" aria-hidden="true"></i>
        <span wire:loading.remove wire:target="add">Add to cart</span>
        <span wire:loading wire:target="add">Adding</span>
    </button>

    <p class="commerce-cart-feedback" aria-live="polite">
        @error('cart')<span class="is-error">{{ $message }}</span>@enderror
        @if ($feedback !== '')<span class="is-success">{{ $feedback }}</span>@endif
    </p>
</div>
