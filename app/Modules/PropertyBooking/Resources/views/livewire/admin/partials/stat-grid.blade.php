<section class="pb-stat-grid" aria-label="Workspace summary">
    @foreach ($cards as $card)
        <div class="card aureon-panel pb-stat-card" style="--pb-stat-color: {{ $card['color'] ?? 'var(--aureon-primary)' }}">
            <span class="pb-stat-card__icon" aria-hidden="true"><i class="ti {{ $card['icon'] }}"></i></span>
            <span class="pb-stat-card__copy">
                <strong>{{ number_format($card['value']) }}</strong>
                <small>{{ $card['label'] }}</small>
            </span>
        </div>
    @endforeach
</section>
