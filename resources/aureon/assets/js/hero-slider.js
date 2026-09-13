(function (document, window) {
  "use strict";

  function initializeHeroSlider(root) {
    if (typeof window.Swiper !== "function") {
      root.classList.add("hero-slider-fallback");
      return;
    }

    var pageRoot = document.documentElement;
    var pageLoader = document.querySelector("[data-page-loader]");
    var timeline = root.querySelector("[data-hero-timeline]");
    var autoplayButton = root.querySelector("[data-hero-autoplay]");
    var pauseIcon = root.querySelector("[data-hero-pause-icon]");
    var playIcon = root.querySelector("[data-hero-play-icon]");
    var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    var autoplayDelay = Number(root.dataset.autoplayDelay) || 7000;
    var transitionSpeed = Number(root.dataset.transitionSpeed) || 850;
    var contextPauses = new Set();
    var userPaused = false;
    var swiper;
    var pageReady = !pageLoader || (
      pageLoader.hidden &&
      pageRoot.dataset.pageLoaderState === "ready" &&
      !pageRoot.classList.contains("page-loader-enabled")
    );

    function setProgress(value) {
      if (!timeline) return;
      var normalized = Math.max(0, Math.min(1, value));
      timeline.style.setProperty("--hero-progress", normalized.toFixed(4));
    }

    function updateHeaderTone(instance) {
      var activeSlide = instance.slides[instance.activeIndex];
      document.body.dataset.heroHeaderTone = activeSlide ? activeSlide.dataset.headerTone || "inverse" : "inverse";
    }

    function updateAutoplayButton() {
      if (!autoplayButton) return;
      var label = userPaused ? "Resume automatic slide rotation" : "Pause automatic slide rotation";
      autoplayButton.setAttribute("aria-pressed", String(userPaused));
      autoplayButton.setAttribute("aria-label", label);
      autoplayButton.setAttribute("title", label);
      root.classList.toggle("is-user-paused", userPaused);
      if (pauseIcon) pauseIcon.hidden = userPaused;
      if (playIcon) playIcon.hidden = !userPaused;
    }

    function canAutoplay() {
      return pageReady && !reducedMotion.matches && !userPaused && contextPauses.size === 0;
    }

    function syncAutoplay() {
      if (!swiper || !swiper.autoplay) return;

      if (canAutoplay()) {
        if (!swiper.autoplay.running) {
          swiper.autoplay.start();
        } else if (swiper.autoplay.paused) {
          swiper.autoplay.resume();
        }
        return;
      }

      if (swiper.autoplay.running && !swiper.autoplay.paused) swiper.autoplay.pause();
    }

    function setContextPause(reason, paused) {
      if (paused) contextPauses.add(reason);
      else contextPauses.delete(reason);
      syncAutoplay();
    }

    function revealSlider() {
      if (pageReady && root.classList.contains("hero-slider-motion-ready")) return;
      pageReady = true;
      root.classList.add("hero-slider-motion-ready");
      syncAutoplay();
    }

    swiper = new window.Swiper(root, {
      speed: transitionSpeed,
      rewind: true,
      keyboard: {
        enabled: true,
        onlyInViewport: true
      },
      navigation: {
        prevEl: root.querySelector("[data-hero-prev]"),
        nextEl: root.querySelector("[data-hero-next]")
      },
      pagination: {
        el: root.querySelector("[data-hero-pagination]"),
        clickable: true,
        bulletClass: "hero-slider-dot",
        bulletActiveClass: "is-active",
        renderBullet: function (index, className) {
          return '<button class="' + className + '" type="button" aria-label="Go to slide ' + (index + 1) + '"></button>';
        }
      },
      autoplay: {
        delay: autoplayDelay,
        disableOnInteraction: false,
        pauseOnMouseEnter: false,
        waitForTransition: true
      },
      a11y: {
        enabled: true,
        containerMessage: "Featured perspectives",
        containerRole: "region",
        containerRoleDescriptionMessage: "carousel",
        itemRoleDescriptionMessage: "slide",
        prevSlideMessage: "Previous slide",
        nextSlideMessage: "Next slide",
        paginationBulletMessage: "Go to slide {{index}}",
        slideLabelMessage: "{{index}} of {{slidesLength}}"
      },
      on: {
        init: function (instance) {
          root.classList.add("hero-slider-ready");
          updateHeaderTone(instance);
          if (!pageReady || reducedMotion.matches) instance.autoplay.stop();
        },
        slideChange: function (instance) {
          updateHeaderTone(instance);
          setProgress(0);
        },
        slideChangeTransitionEnd: function () {
          syncAutoplay();
        },
        touchEnd: function () {
          window.requestAnimationFrame(syncAutoplay);
        },
        autoplayTimeLeft: function (instance, timeLeft, percentage) {
          setProgress(1 - percentage);
        }
      }
    });

    updateAutoplayButton();
    root.classList.toggle("hero-slider-reduced-motion", reducedMotion.matches);

    if (pageReady) {
      revealSlider();
    } else {
      window.addEventListener("aureon:page-loader-dismissed", revealSlider, { once: true });
    }

    if (autoplayButton) {
      autoplayButton.addEventListener("click", function () {
        userPaused = !userPaused;
        updateAutoplayButton();
        syncAutoplay();
      });
    }

    root.addEventListener("mouseenter", function () {
      setContextPause("hover", true);
    });

    root.addEventListener("mouseleave", function () {
      setContextPause("hover", false);
    });

    root.addEventListener("focusin", function (event) {
      if (event.target.closest(".hero-slide-copy")) setContextPause("focus", true);
    });

    root.addEventListener("focusout", function () {
      window.setTimeout(function () {
        var focusedCopy = document.activeElement && document.activeElement.closest
          ? document.activeElement.closest(".hero-slide-copy")
          : null;
        if (!focusedCopy || !root.contains(focusedCopy)) setContextPause("focus", false);
      }, 0);
    });

    document.addEventListener("visibilitychange", function () {
      setContextPause("visibility", document.hidden);
    });

    function handleMotionPreference() {
      root.classList.toggle("hero-slider-reduced-motion", reducedMotion.matches);
      if (reducedMotion.matches) {
        swiper.autoplay.stop();
        setProgress(0);
      } else {
        syncAutoplay();
      }
    }

    if (typeof reducedMotion.addEventListener === "function") {
      reducedMotion.addEventListener("change", handleMotionPreference);
    } else if (typeof reducedMotion.addListener === "function") {
      reducedMotion.addListener(handleMotionPreference);
    }
  }

  function initializeAllHeroSliders() {
    document.querySelectorAll("[data-hero-slider]").forEach(initializeHeroSlider);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeAllHeroSliders, { once: true });
  } else {
    initializeAllHeroSliders();
  }
}(document, window));
