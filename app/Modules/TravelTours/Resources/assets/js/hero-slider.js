import Swiper from 'swiper';
import { A11y, Autoplay, EffectFade, Keyboard, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/effect-fade';
import 'swiper/css/pagination';

/** Progressively enhance the informational-first homepage hero. */
export function initHeroSlider(root) {
    if (!root || root.dataset.heroReady === 'true') return;

    const slides = root.querySelectorAll('.travel-hero__slide');
    if (slides.length < 2) return;

    root.dataset.heroReady = 'true';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const previous = root.querySelector('[data-travel-hero-prev]');
    const next = root.querySelector('[data-travel-hero-next]');
    const slider = new Swiper(root, {
        modules: [A11y, Autoplay, EffectFade, Keyboard, Pagination],
        effect: 'fade',
        fadeEffect: { crossFade: true },
        speed: reducedMotion ? 0 : 720,
        loop: true,
        loopPreventsSliding: false,
        watchOverflow: true,
        keyboard: { enabled: true, onlyInViewport: true },
        pagination: {
            el: root.querySelector('[data-travel-hero-pagination]'),
            clickable: true,
        },
        autoplay: reducedMotion ? false : {
            delay: 7000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        },
        a11y: {
            enabled: true,
            prevSlideMessage: 'Previous hero slide',
            nextSlideMessage: 'Next hero slide',
            paginationBulletMessage: 'Go to hero slide {{index}}',
        },
    });

    previous?.addEventListener('click', (event) => {
        event.preventDefault();
        slider.slidePrev();
    });
    next?.addEventListener('click', (event) => {
        event.preventDefault();
        slider.slideNext();
    });

    if (!reducedMotion) {
        root.addEventListener('focusin', () => slider.autoplay.pause());
        root.addEventListener('focusout', (event) => {
            if (!root.contains(event.relatedTarget)) slider.autoplay.resume();
        });
    }
}
