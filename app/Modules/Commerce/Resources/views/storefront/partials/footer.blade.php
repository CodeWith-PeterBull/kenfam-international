<footer class="commerce-footer">
    <div class="container-xxl commerce-footer__main">
        <div class="commerce-footer__brand">
            <a class="commerce-brand commerce-brand--footer" href="{{ route('commerce.storefront.catalog.index') }}" aria-label="{{ $profile->name }} store home">
                <img src="{{ $brandLogoLight }}" alt="{{ $profile->name }}" width="220" height="64"><span>Store</span>
            </a>
            <p>A clear, adaptable storefront for considered products and dependable service.</p>
        </div>

        <div class="commerce-footer__column">
            <h2>Shop</h2>
            <nav aria-label="Footer store navigation">
                <a href="{{ route('commerce.storefront.catalog.index') }}">All products</a>
                <a href="{{ route('commerce.storefront.catalog.index', ['sort' => 'newest']) }}">New arrivals</a>
                <a href="{{ route('commerce.storefront.catalog.index', ['availability' => 'on-sale']) }}">Current offers</a>
            </nav>
        </div>

        <div class="commerce-footer__column">
            <h2>Categories</h2>
            <nav aria-label="Footer category navigation">
                @foreach ($navigationCategories->take(5) as $category)
                    <a href="{{ route('commerce.storefront.catalog.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
                @endforeach
            </nav>
        </div>

        <div class="commerce-footer__column commerce-footer__contact">
            <h2>Contact</h2>
            @if ($profile->primaryEmail)<a href="mailto:{{ $profile->primaryEmail }}">{{ $profile->primaryEmail }}</a>@endif
            @if ($profile->primaryPhone)<a href="tel:{{ preg_replace('/[^+\d]/', '', $profile->primaryPhone) }}">{{ $profile->primaryPhone }}</a>@endif
            @if ($profile->address())<p>{{ $profile->address() }}</p>@endif
        </div>
    </div>
    <div class="commerce-footer__bottom">
        <div class="container-xxl">
            <p>&copy; <span data-current-year></span> {{ $profile->name }}. All rights reserved.</p>
            <p>Commerce engine by Meta Software Developers</p>
        </div>
    </div>
</footer>
