(function () {
  "use strict";

  function createIcons() {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons({ attrs: { "stroke-width": 1.8 } });
    }
  }

  function setCurrentYear() {
    document.querySelectorAll("[data-current-year]").forEach(function (element) {
      element.textContent = String(new Date().getFullYear());
    });
  }

  function markActiveNavigation() {
    var section = document.body.dataset.nav;
    if (!section) return;

    document.querySelectorAll('[data-nav-link="' + section + '"]').forEach(function (link) {
      link.classList.add("active");
      if (link.matches("a")) link.setAttribute("aria-current", "page");

      if (link.classList.contains("mobile-nav-toggle")) {
        var target = document.querySelector(link.getAttribute("data-bs-target"));
        link.setAttribute("aria-expanded", "true");
        if (target) target.classList.add("show");
      }
    });
  }

  function initializeMegaMenus() {
    var header = document.querySelector("[data-site-header]");
    var items = Array.from(document.querySelectorAll("[data-mega-menu]"));
    if (!header || !items.length) return;

    var closeTimer;
    var suppressFocusOpen = false;

    function closeAll() {
      window.clearTimeout(closeTimer);
      items.forEach(function (item) {
        item.classList.remove("is-open");
        var trigger = item.querySelector("[data-mega-trigger]");
        if (trigger) trigger.setAttribute("aria-expanded", "false");
      });
      header.classList.remove("mega-open");
    }

    function open(item) {
      window.clearTimeout(closeTimer);
      items.forEach(function (candidate) {
        var trigger = candidate.querySelector("[data-mega-trigger]");
        var isCurrent = candidate === item;
        candidate.classList.toggle("is-open", isCurrent);
        if (trigger) trigger.setAttribute("aria-expanded", String(isCurrent));
      });
      header.classList.add("mega-open");
    }

    function scheduleClose() {
      window.clearTimeout(closeTimer);
      closeTimer = window.setTimeout(closeAll, 140);
    }

    items.forEach(function (item) {
      var trigger = item.querySelector("[data-mega-trigger]");
      if (!trigger) return;

      item.addEventListener("mouseenter", function () { open(item); });
      item.addEventListener("mouseleave", scheduleClose);
      trigger.addEventListener("focus", function () {
        if (!suppressFocusOpen) open(item);
      });
      item.addEventListener("focusout", function (event) {
        if (!item.contains(event.relatedTarget)) scheduleClose();
      });
    });

    document.addEventListener("keydown", function (event) {
      if (event.key !== "Escape" || !header.classList.contains("mega-open")) return;
      var openTrigger = header.querySelector(".mega-nav-item.is-open [data-mega-trigger]");
      suppressFocusOpen = true;
      closeAll();
      if (openTrigger) openTrigger.focus();
      window.setTimeout(function () { suppressFocusOpen = false; }, 0);
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth < 1200) closeAll();
    }, { passive: true });
  }

  function initializeFloatingHeader() {
    var header = document.querySelector("[data-site-header]");
    if (!header) return;

    var RELEASE_AT = 8;
    var EXIT_FALLBACK = 380;
    var state = "static";
    var staticHeight = 0;
    var trigger = 68;
    var placeholder = null;
    var exitTimer = 0;
    var ticking = false;
    var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function readTrigger() {
      var value = parseInt(window.getComputedStyle(header).getPropertyValue("--float-trigger"), 10);
      if (!isNaN(value)) trigger = value;
    }

    function removePlaceholder() {
      if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
      placeholder = null;
    }

    function enter() {
      staticHeight = header.offsetHeight;
      if (window.getComputedStyle(header).position !== "absolute") {
        placeholder = document.createElement("div");
        placeholder.setAttribute("aria-hidden", "true");
        placeholder.style.height = staticHeight + "px";
        header.insertAdjacentElement("afterend", placeholder);
      }
      if (window.scrollY >= staticHeight && !reducedMotion.matches) {
        header.classList.add("site-header-floating", "site-header-hidden");
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            header.classList.remove("site-header-hidden");
          });
        });
      } else {
        header.classList.add("site-header-floating");
      }
      state = "floating";
    }

    function settle() {
      if (state !== "expanding") return;
      window.clearTimeout(exitTimer);
      header.classList.remove("site-header-floating", "site-header-expanding");
      removePlaceholder();
      state = "static";
    }

    function exit() {
      if (reducedMotion.matches) {
        header.classList.remove("site-header-floating", "site-header-expanding");
        removePlaceholder();
        state = "static";
        return;
      }
      state = "expanding";
      header.classList.add("site-header-expanding");
      exitTimer = window.setTimeout(settle, EXIT_FALLBACK);
    }

    function update() {
      var offset = window.scrollY;
      if (state === "static") {
        if (offset > trigger) enter();
      } else if (state === "floating") {
        if (offset < RELEASE_AT) exit();
      } else if (state === "expanding" && offset > trigger) {
        window.clearTimeout(exitTimer);
        header.classList.remove("site-header-expanding");
        state = "floating";
      }
    }

    function requestUpdate() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () {
        ticking = false;
        update();
      });
    }

    window.addEventListener("scroll", requestUpdate, { passive: true });
    window.addEventListener("resize", function () {
      if (state !== "static") {
        window.clearTimeout(exitTimer);
        header.classList.remove("site-header-floating", "site-header-expanding", "site-header-hidden");
        removePlaceholder();
        state = "static";
      }
      readTrigger();
      requestUpdate();
    }, { passive: true });
    readTrigger();
    update();
  }

  function initializeBackToTop() {
    var button = document.querySelector("[data-back-to-top]");
    if (!button) return;

    function updateVisibility() {
      button.classList.toggle("visible", window.scrollY > 600);
    }

    window.addEventListener("scroll", updateVisibility, { passive: true });
    button.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
    updateVisibility();
  }

  function initializeReveals() {
    var items = document.querySelectorAll(".reveal");
    if (!items.length) return;

    if (!("IntersectionObserver" in window)) {
      items.forEach(function (item) { item.classList.add("visible"); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.12 });

    items.forEach(function (item) { observer.observe(item); });
  }

  function initializeCounters() {
    var counters = document.querySelectorAll("[data-counter]");
    if (!counters.length) return;

    function animate(counter) {
      var target = Number(counter.dataset.counter || 0);
      var suffix = counter.dataset.suffix || "";
      var duration = 1100;
      var start = performance.now();

      function tick(now) {
        var progress = Math.min((now - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        counter.textContent = Math.round(target * eased) + suffix;
        if (progress < 1) requestAnimationFrame(tick);
      }

      requestAnimationFrame(tick);
    }

    if (!("IntersectionObserver" in window)) {
      counters.forEach(animate);
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        animate(entry.target);
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.6 });

    counters.forEach(function (counter) { observer.observe(counter); });
  }

  function initializeProjectFilters() {
    var buttons = document.querySelectorAll("[data-project-filter]");
    var cards = document.querySelectorAll("[data-project-card]");
    if (!buttons.length || !cards.length) return;

    buttons.forEach(function (button) {
      button.addEventListener("click", function () {
        var filter = button.dataset.projectFilter;
        buttons.forEach(function (item) { item.classList.remove("active"); });
        button.classList.add("active");
        cards.forEach(function (card) {
          card.hidden = filter !== "all" && card.dataset.category !== filter;
        });
      });
    });
  }

  function initializeCatalogSearch() {
    var input = document.querySelector("[data-catalog-search]");
    var items = document.querySelectorAll("[data-catalog-item]");
    if (!input || !items.length) return;

    input.addEventListener("input", function () {
      var query = input.value.trim().toLowerCase();
      items.forEach(function (item) {
        item.hidden = query.length > 0 && !item.textContent.toLowerCase().includes(query);
      });
    });
  }

  function initializeDemoForms() {
    document.querySelectorAll("[data-demo-form]").forEach(function (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }

        var status = form.querySelector(".form-status");
        if (status) status.textContent = "Thanks. Your request has been received.";
        form.reset();
      });
    });
  }

  function initializeTabShowcase() {
    document.querySelectorAll("[data-tab-showcase]").forEach(function (showcase) {
      var mediaItems = showcase.querySelectorAll("[data-showcase-media]");
      showcase.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener("shown.bs.tab", function (event) {
          var id = (event.target.getAttribute("data-bs-target") || "").replace("#showcase-pane-", "");
          mediaItems.forEach(function (item) {
            item.classList.toggle("active", item.getAttribute("data-showcase-media") === id);
          });
        });
      });
    });

    var modal = document.getElementById("showcaseMedia");
    if (!modal) return;
    modal.addEventListener("show.bs.modal", function (event) {
      var trigger = event.relatedTarget;
      if (!trigger) return;
      var image = modal.querySelector("[data-showcase-modal-image]");
      var caption = modal.querySelector("[data-showcase-modal-caption]");
      var title = trigger.getAttribute("data-showcase-title") || "";
      image.src = trigger.getAttribute("data-showcase-image");
      image.alt = title ? "Aureon " + title.toLowerCase() + " chapter still" : "";
      if (caption) caption.textContent = title + " — corporate film placeholder. Connect your production to this control before launch.";
    });
  }

  function initializeGallery() {
    var modalImage = document.querySelector("[data-gallery-modal-image]");
    var modalTitle = document.querySelector("[data-gallery-modal-title]");
    var items = document.querySelectorAll("[data-gallery-image]");
    if (!modalImage || !items.length) return;

    items.forEach(function (item) {
      item.addEventListener("click", function () {
        modalImage.src = item.dataset.galleryImage;
        modalImage.alt = item.dataset.galleryTitle || "Aureon gallery image";
        if (modalTitle) modalTitle.textContent = item.dataset.galleryTitle || "";
      });
    });
  }

  function initializeSearchQuery() {
    var params = new URLSearchParams(window.location.search);
    var query = params.get("q");
    if (!query) return;

    var heading = document.querySelector(".page-hero-content > p:last-child");
    if (heading && document.body.dataset.nav === "insights") {
      heading.textContent = 'Showing the insight archive for "' + query + '".';
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    createIcons();
    setCurrentYear();
    markActiveNavigation();
    initializeMegaMenus();
    initializeFloatingHeader();
    initializeBackToTop();
    initializeReveals();
    initializeCounters();
    initializeProjectFilters();
    initializeCatalogSearch();
    initializeDemoForms();
    initializeTabShowcase();
    initializeGallery();
    initializeSearchQuery();
  });
}());
