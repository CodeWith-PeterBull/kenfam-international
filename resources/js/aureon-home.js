const initializeIcons = () => {
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
};

const initializeGalleryLightbox = () => {
    const element = document.querySelector('[data-gallery-lightbox]');

    if (!element || !window.bootstrap?.Modal || element.dataset.initialized === 'true') {
        return null;
    }

    element.dataset.initialized = 'true';
    const modal = window.bootstrap.Modal.getOrCreateInstance(element);
    const image = element.querySelector('[data-lightbox-image]');
    const title = element.querySelector('[data-lightbox-title]');
    const role = element.querySelector('[data-lightbox-role]');
    const position = element.querySelector('[data-lightbox-position]');
    const previous = element.querySelector('[data-lightbox-previous]');
    const next = element.querySelector('[data-lightbox-next]');
    let activeGallery = null;
    let openingTrigger = null;

    const render = () => {
        if (!activeGallery || !image || !title || !role || !position) return;

        const option = activeGallery.options[activeGallery.currentIndex()];
        if (!option) return;

        image.src = option.dataset.gallerySrc ?? '';
        image.alt = option.dataset.galleryAlt ?? '';
        title.textContent = option.dataset.galleryLabel ?? '';
        role.textContent = option.dataset.galleryRole ?? '';
        position.textContent = `${activeGallery.currentIndex() + 1} / ${activeGallery.options.length}`;
    };

    const move = (offset) => {
        if (!activeGallery) return;
        activeGallery.activate(activeGallery.currentIndex() + offset);
    };

    previous?.addEventListener('click', () => move(-1));
    next?.addEventListener('click', () => move(1));
    element.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        move(event.key === 'ArrowRight' ? 1 : -1);
    });
    element.addEventListener('hidden.bs.modal', () => {
        openingTrigger?.focus();
        activeGallery = null;
        openingTrigger = null;
    });

    return {
        open(gallery, trigger) {
            activeGallery = gallery;
            openingTrigger = trigger;
            render();
            modal.show();
        },
        sync(gallery) {
            if (gallery === activeGallery) render();
        },
    };
};

const initializeGalleries = (lightbox) => {
    document.querySelectorAll('[data-module-gallery]').forEach((gallery) => {
        if (gallery.dataset.initialized === 'true') return;

        const stage = gallery.querySelector('[data-gallery-stage]');
        const stageFrame = gallery.querySelector('.home-module-gallery__stage');
        const caption = gallery.querySelector('[data-gallery-caption]');
        const role = gallery.querySelector('[data-gallery-role]');
        const position = gallery.querySelector('[data-gallery-position]');
        const previous = gallery.querySelector('[data-gallery-previous]');
        const next = gallery.querySelector('[data-gallery-next]');
        const expand = gallery.querySelector('[data-gallery-expand]');
        const rail = gallery.querySelector('.home-module-gallery__tabs');
        const options = [...gallery.querySelectorAll('[data-gallery-option]')];

        if (!stage || !caption || !role || !position || !rail || options.length === 0) {
            return;
        }

        gallery.dataset.initialized = 'true';

        const currentIndex = () => Math.max(0, options.findIndex((option) => option.classList.contains('is-active')));
        const state = { options, currentIndex, activate: null };

        const activate = (index) => {
            const normalizedIndex = (index + options.length) % options.length;
            const option = options[normalizedIndex];
            const source = option.dataset.gallerySrc;
            const alt = option.dataset.galleryAlt;
            const label = option.dataset.galleryLabel;
            const audience = option.dataset.galleryRole;
            if (!source || !alt || !label || !audience) return;

            stage.classList.add('is-changing');
            stage.addEventListener('load', () => stage.classList.remove('is-changing'), { once: true });
            stage.addEventListener('error', () => stage.classList.remove('is-changing'), { once: true });
            stage.src = source;
            stage.alt = alt;
            if (stage.complete) stage.classList.remove('is-changing');
            role.textContent = audience;
            caption.textContent = label;

            options.forEach((candidate) => {
                const active = candidate === option;
                candidate.classList.toggle('is-active', active);
                candidate.setAttribute('aria-pressed', String(active));
            });
            position.textContent = `${normalizedIndex + 1} / ${options.length}`;
            option.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            lightbox?.sync(state);
        };
        state.activate = activate;

        options.forEach((option, index) => {
            option.addEventListener('click', () => activate(index));
        });
        previous?.addEventListener('click', () => activate(currentIndex() - 1));
        next?.addEventListener('click', () => activate(currentIndex() + 1));
        expand?.addEventListener('click', () => lightbox?.open(state, expand));
        stageFrame?.addEventListener('click', (event) => {
            if (event.target.closest('button')) return;
            lightbox?.open(state, expand ?? stageFrame);
        });
        rail.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            activate(currentIndex() + (event.key === 'ArrowRight' ? 1 : -1));
        });
    });
};

const initializeScrollControls = () => {
    const header = document.querySelector('[data-home-header]');
    const backToTop = document.querySelector('[data-home-back-to-top]');
    const modules = document.querySelector('#modules');

    const sync = () => {
        const scrolled = window.scrollY > 24;
        header?.classList.toggle('is-scrolled', scrolled);
        backToTop?.classList.toggle('is-visible', window.scrollY > 520);
    };

    backToTop?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', sync, { passive: true });
    sync();

    if (backToTop && modules && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            backToTop.classList.toggle('is-suppressed', entry.isIntersecting);
        }, { threshold: 0.05 }).observe(modules);
    }
};

const initializeContactCard = () => {
    const modalElement = document.querySelector('[data-aureon-contact-card]');

    if (!modalElement || !window.bootstrap?.Modal) {
        return;
    }

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-aureon-contact-trigger]');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        const openMenu = trigger.closest('.offcanvas.show');
        if (!openMenu || !window.bootstrap?.Offcanvas) {
            modal.show();
            return;
        }

        openMenu.addEventListener('hidden.bs.offcanvas', () => modal.show(), { once: true });
        window.bootstrap.Offcanvas.getOrCreateInstance(openMenu).hide();
    });
};

const initializePage = () => {
    if (document.documentElement.dataset.aureonHomeInitialized === 'true') return;
    document.documentElement.dataset.aureonHomeInitialized = 'true';

    document.querySelectorAll('[data-current-year]').forEach((element) => {
        element.textContent = String(new Date().getFullYear());
    });

    let lightbox = null;
    try {
        lightbox = initializeGalleryLightbox();
    } catch (error) {
        console.error('Aureon gallery lightbox initializer failed.', error);
    }

    [
        () => initializeGalleries(lightbox),
        initializeScrollControls,
        initializeContactCard,
        initializeIcons,
    ].forEach((initializer) => {
        try {
            initializer();
        } catch (error) {
            console.error('Aureon homepage initializer failed.', error);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePage, { once: true });
} else {
    initializePage();
}
