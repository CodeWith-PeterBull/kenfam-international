import Swiper from 'swiper';
import { A11y, Keyboard, Navigation, Pagination, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/zoom';

function escapeAttribute(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export function initStayGallery(root) {
    if (!root || root.dataset.galleryReady === 'true') return;
    root.dataset.galleryReady = 'true';

    const stage = root.querySelector('[data-stay-gallery-stage]');
    if (!stage) return;

    const images = [...stage.querySelectorAll('.swiper-slide img')].map((image) => ({
        src: image.currentSrc || image.src,
        alt: image.getAttribute('alt') || '',
    }));
    const multiple = images.length > 1;
    const slider = new Swiper(stage, {
        modules: [Navigation, Pagination, Keyboard, A11y],
        slidesPerView: 1,
        speed: 360,
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { enabled: true },
        navigation: multiple ? { prevEl: root.querySelector('[data-stay-gallery-prev]'), nextEl: root.querySelector('[data-stay-gallery-next]') } : false,
        pagination: multiple ? { el: root.querySelector('[data-stay-gallery-pagination]'), clickable: true } : false,
    });

    const thumbnails = [...root.querySelectorAll('[data-stay-gallery-thumb]')];
    const activateThumbnail = (index) => thumbnails.forEach((thumbnail, position) => {
        const active = position === index;
        thumbnail.classList.toggle('is-active', active);
        thumbnail.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    thumbnails.forEach((thumbnail, index) => thumbnail.addEventListener('click', () => slider.slideTo(index)));
    slider.on('slideChange', () => activateThumbnail(slider.activeIndex));
    activateThumbnail(0);

    let lightbox;
    let lightboxSlider;
    let restoreFocus;
    const close = () => {
        if (!lightbox || lightbox.hidden) return;
        lightbox.classList.remove('is-open');
        document.body.classList.remove('pb-stay-scroll-lock');
        document.removeEventListener('keydown', onKeydown);
        window.setTimeout(() => { if (!lightbox.classList.contains('is-open')) lightbox.hidden = true; }, 240);
        restoreFocus?.focus?.();
    };
    const onKeydown = (event) => { if (event.key === 'Escape') close(); };

    const build = () => {
        lightbox = document.createElement('div');
        lightbox.className = 'pb-stay-lightbox';
        lightbox.hidden = true;
        lightbox.innerHTML = `<button class="pb-stay-lightbox__backdrop" type="button" data-lightbox-close aria-label="Close image viewer"></button><div class="pb-stay-lightbox__dialog" role="dialog" aria-modal="true" aria-label="Accommodation image viewer"><button class="pb-stay-lightbox__close" type="button" data-lightbox-close aria-label="Close image viewer"><i data-lucide="x" aria-hidden="true"></i></button><div class="swiper pb-stay-lightbox__swiper"><div class="swiper-wrapper">${images.map((image) => `<div class="swiper-slide"><div class="swiper-zoom-container"><img src="${escapeAttribute(image.src)}" alt="${escapeAttribute(image.alt)}"></div></div>`).join('')}</div><div class="pb-stay-lightbox__pagination"></div></div>${multiple ? '<button class="pb-stay-lightbox__nav pb-stay-lightbox__nav--prev" type="button" aria-label="Previous image"><i data-lucide="chevron-left" aria-hidden="true"></i></button><button class="pb-stay-lightbox__nav pb-stay-lightbox__nav--next" type="button" aria-label="Next image"><i data-lucide="chevron-right" aria-hidden="true"></i></button>' : ''}</div>`;
        document.body.appendChild(lightbox);
        lightbox.querySelectorAll('[data-lightbox-close]').forEach((button) => button.addEventListener('click', close));
        lightboxSlider = new Swiper(lightbox.querySelector('.pb-stay-lightbox__swiper'), {
            modules: [Navigation, Pagination, Keyboard, A11y, Zoom],
            slidesPerView: 1,
            speed: 360,
            zoom: { maxRatio: 3 },
            keyboard: { enabled: true },
            a11y: { enabled: true },
            navigation: multiple ? { prevEl: lightbox.querySelector('.pb-stay-lightbox__nav--prev'), nextEl: lightbox.querySelector('.pb-stay-lightbox__nav--next') } : false,
            pagination: multiple ? { el: lightbox.querySelector('.pb-stay-lightbox__pagination'), clickable: true } : false,
        });
        lightboxSlider.on('slideChange', () => slider.slideTo(lightboxSlider.activeIndex, 0));
        window.lucide?.createIcons?.({ attrs: { 'stroke-width': 1.8 } });
    };

    root.querySelector('[data-stay-gallery-expand]')?.addEventListener('click', () => {
        if (!lightbox) build();
        restoreFocus = document.activeElement;
        lightboxSlider.slideTo(slider.activeIndex, 0);
        lightbox.hidden = false;
        requestAnimationFrame(() => lightbox.classList.add('is-open'));
        document.body.classList.add('pb-stay-scroll-lock');
        document.addEventListener('keydown', onKeydown, { once: false });
        lightbox.querySelector('.pb-stay-lightbox__close')?.focus();
    });
}
