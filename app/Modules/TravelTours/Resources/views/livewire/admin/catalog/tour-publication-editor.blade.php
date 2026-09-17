<div class="travel-editor-form" aria-label="Tour publication">
    @if(session('success'))<div class="alert alert-success mx-4 mt-3 mb-0" role="status">{{ session('success') }}</div>@endif
    @error('publication')<div class="alert alert-danger mx-4 mt-3 mb-0" role="alert">{{ $message }}</div>@enderror
    <section class="travel-editor-section" aria-labelledby="tour-readiness-heading">
        <div class="travel-editor-section-heading"><span>01</span><div><h4 id="tour-readiness-heading">Publication readiness</h4><p>Complete each catalog essential before publishing.</p></div></div>
        @if($reasons === [])<div class="alert alert-success" role="status"><i class="ti ti-circle-check me-2" aria-hidden="true"></i>Tour content is ready for publication.</div>@else<ul class="travel-readiness-list">@foreach($reasons as $reason)<li><i class="ti ti-circle-dashed" aria-hidden="true"></i>{{ $reason }}</li>@endforeach</ul>@endif
    </section>
    <section class="travel-editor-section" aria-labelledby="tour-publish-heading">
        <div class="travel-editor-section-heading"><span>02</span><div><h4 id="tour-publish-heading">Visibility</h4><p>Preview privately, then publish when approved.</p></div></div>
        <div class="travel-publication-actions"><a class="btn btn-outline-secondary" href="{{ route('travel-tours.admin.catalog.tours.preview', $tour) }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-eye me-1" aria-hidden="true"></i>Preview tour</a>
            @if($tour->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Draft)<button type="button" class="btn btn-outline-secondary" wire:click="submitForReview">Submit for review</button>@endif
            @can('publish', $tour)
                @if($tour->status !== \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Archived)
                    @if($tour->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Published)<button type="button" class="btn btn-outline-secondary" wire:click="unpublish" wire:confirm="Unpublish this tour?">Unpublish</button>@endif
                    <div class="travel-publish-schedule"><label class="form-label" for="tour-publish-at">Schedule in {{ config('travel-tours.defaults.timezone') }} (optional)</label><input id="tour-publish-at" type="datetime-local" class="form-control @error('publishAt') is-invalid @enderror" wire:model="publishAt">@error('publishAt')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <button type="button" class="btn btn-primary" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" @disabled($reasons !== [])>{{ $tour->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Published ? 'Update publication' : 'Publish tour' }}</button>
                    <button type="button" class="btn btn-outline-danger" wire:click="archive" wire:confirm="Archive this tour and hide it from the public catalog?">Archive</button>
                @else<button type="button" class="btn btn-outline-secondary" wire:click="restoreDraft">Restore draft</button>@endif
            @endcan
        </div>
        @if($tour->status === \App\Modules\TravelTours\Catalog\Enums\PublicationStatus::Published && $tour->published_at)<p class="aureon-muted mt-3 mb-0">{{ $tour->published_at->isFuture() ? 'Scheduled for' : 'Published' }} {{ $tour->published_at->timezone(config('travel-tours.defaults.timezone'))->format('d M Y, H:i T') }}</p>@endif
    </section>
</div>
