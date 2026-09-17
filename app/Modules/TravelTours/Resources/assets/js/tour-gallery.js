import Swiper from 'swiper';
import { A11y, Keyboard, Navigation, Zoom } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/zoom';

export function initTourGallery(root) {
    if (!root || root.dataset.galleryReady === 'true') return;
    const stage = root.querySelector('[data-tour-gallery-stage]');
    const lightbox = root.querySelector('[data-tour-lightbox]');
    const lightboxStage = root.querySelector('[data-tour-lightbox-stage]');
    if (!stage || !lightbox || !lightboxStage) return;
    root.dataset.galleryReady = 'true';

    const thumbs = [...root.querySelectorAll('[data-tour-gallery-thumb]')];
    const count = lightbox.querySelector('[data-tour-lightbox-count]');
    const multiple = stage.querySelectorAll('.swiper-slide').length > 1;
    const slider = new Swiper(stage, {
        modules: [Navigation, Keyboard, A11y],
        speed: 360,
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { enabled: true },
        navigation: multiple ? { prevEl: root.querySelector('[data-tour-gallery-prev]'), nextEl: root.querySelector('[data-tour-gallery-next]') } : false,
    });
    let viewer;
    let opener = null;
    const update = (index) => {
        thumbs.forEach((thumb, position) => {
            thumb.classList.toggle('is-active', position === index);
            thumb.setAttribute('aria-selected', position === index ? 'true' : 'false');
        });
        if (count) count.textContent = String(index + 1) + ' / ' + String(slider.slides.length);
    };
    slider.on('slideChange', () => {
        update(slider.activeIndex);
        if (!lightbox.hidden && viewer && viewer.activeIndex !== slider.activeIndex) viewer.slideTo(slider.activeIndex, 0);
    });
    thumbs.forEach((thumb, index) => thumb.addEventListener('click', () => slider.slideTo(index)));
    update(0);

    const close = () => {
        if (lightbox.hidden) return;
        lightbox.hidden = true;
        document.body.classList.remove('travel-tour-gallery-open');
        viewer?.keyboard.disable();
        opener?.focus();
    };
    const open = () => {
        opener = document.activeElement;
        lightbox.hidden = false;
        document.body.classList.add('travel-tour-gallery-open');
        if (!viewer) {
            viewer = new Swiper(lightboxStage, {
                modules: [Navigation, Keyboard, A11y, Zoom],
                speed: 360,
                keyboard: { enabled: true },
                zoom: { maxRatio: 3 },
                a11y: { enabled: true },
                navigation: multiple ? { prevEl: root.querySelector('[data-tour-lightbox-prev]'), nextEl: root.querySelector('[data-tour-lightbox-next]') } : false,
            });
            viewer.on('slideChange', () => {
                update(viewer.activeIndex);
                if (slider.activeIndex !== viewer.activeIndex) slider.slideTo(viewer.activeIndex, 0);
            });
        }
        viewer.update();
        viewer.slideTo(slider.activeIndex, 0);
        viewer.keyboard.enable();
        lightbox.querySelector('.travel-tour-lightbox__close')?.focus();
    };
    root.querySelector('[data-tour-gallery-expand]')?.addEventListener('click', open);
    lightbox.querySelectorAll('[data-tour-lightbox-close]').forEach((button) => button.addEventListener('click', close));
    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const controls = [...lightbox.querySelectorAll('button:not([disabled]):not([tabindex="-1"])')];
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
}
