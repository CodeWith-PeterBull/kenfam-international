@php
    $cover = $galleryModel->getFirstMedia($coverCollection);
    $images = collect([$cover])->filter()->merge($galleryModel->getMedia($galleryCollection))->unique('id')->values();
@endphp
<section class="pb-stay-gallery" data-stay-gallery aria-label="{{ $galleryLabel }} image gallery">
    @if($images->isNotEmpty())
        <div class="pb-stay-gallery__stage swiper" data-stay-gallery-stage><div class="swiper-wrapper">@foreach($images as $image)<figure class="swiper-slide"><img src="{{ $image->getUrl('detail') }}" alt="{{ $image->getCustomProperty('alt_text', $galleryLabel) }}" width="1600" height="1200" @if(!$loop->first) loading="lazy" @endif><figcaption>{{ $image->getCustomProperty('caption', '') }}</figcaption></figure>@endforeach</div><div class="pb-stay-gallery__pagination" data-stay-gallery-pagination></div>@if($images->count() > 1)<button class="pb-stay-gallery__nav pb-stay-gallery__nav--prev" type="button" data-stay-gallery-prev aria-label="Previous image"><i data-lucide="chevron-left" aria-hidden="true"></i></button><button class="pb-stay-gallery__nav pb-stay-gallery__nav--next" type="button" data-stay-gallery-next aria-label="Next image"><i data-lucide="chevron-right" aria-hidden="true"></i></button>@endif<button class="pb-stay-gallery__expand" type="button" data-stay-gallery-expand aria-label="Open full-screen image gallery" title="View full screen"><i data-lucide="maximize-2" aria-hidden="true"></i></button></div>
        @if($images->count() > 1)<div class="pb-stay-gallery__thumbs" role="tablist" aria-label="Choose gallery image">@foreach($images as $image)<button type="button" role="tab" aria-label="View image {{ $loop->iteration }} of {{ $loop->count }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-stay-gallery-thumb><img src="{{ $image->getUrl('thumb') }}" alt="" width="160" height="120" loading="lazy"></button>@endforeach</div>@endif
    @else
        <div class="pb-stay-gallery__placeholder"><i data-lucide="image" aria-hidden="true"></i><span>Photography coming soon</span></div>
    @endif
</section>
