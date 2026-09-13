(function () {
  "use strict";

  var storageKey = "aureon-theme-v1";
  var defaults = Object.freeze({
    mode: "light",
    palette: "wine",
    fontBody: "poppins",
    fontHeading: "poppins",
    typeScale: "standard",
    customEnabled: false,
    customPrimary: "#70233a",
    customSecondary: "#28656b"
  });
  var options = {
    mode: ["light", "dark"],
    palette: ["wine", "cobalt", "forest", "graphite"],
    fontBody: ["poppins", "jost", "montserrat", "system"],
    fontHeading: ["poppins", "jost", "montserrat", "system"],
    typeScale: ["compact", "standard", "large"]
  };

  function validHex(value) {
    return typeof value === "string" && /^#[0-9a-f]{6}$/i.test(value);
  }

  function relativeLuminance(hex) {
    var channels = [1, 3, 5].map(function (index) {
      var value = parseInt(hex.slice(index, index + 2), 16) / 255;
      return value <= 0.03928 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
    });
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
  }

  function contrastRatio(first, second) {
    var lighter = Math.max(relativeLuminance(first), relativeLuminance(second));
    var darker = Math.min(relativeLuminance(first), relativeLuminance(second));
    return (lighter + 0.05) / (darker + 0.05);
  }

  function contrastColor(hex) {
    var dark = "#17151d";
    var light = "#ffffff";
    return contrastRatio(hex, dark) >= contrastRatio(hex, light) ? dark : light;
  }

  function normalize(candidate) {
    var source = candidate && typeof candidate === "object" ? candidate : {};
    var settings = {};

    Object.keys(options).forEach(function (key) {
      settings[key] = options[key].includes(source[key]) ? source[key] : defaults[key];
    });

    settings.customEnabled = source.customEnabled === true;
    settings.customPrimary = validHex(source.customPrimary) ? source.customPrimary : defaults.customPrimary;
    settings.customSecondary = validHex(source.customSecondary) ? source.customSecondary : defaults.customSecondary;
    return settings;
  }

  function read() {
    try {
      return normalize(JSON.parse(window.localStorage.getItem(storageKey)));
    } catch (error) {
      return normalize(defaults);
    }
  }

  function apply(settings) {
    var normalized = normalize(settings);
    var root = document.documentElement;

    root.dataset.theme = normalized.mode;
    root.dataset.palette = normalized.palette;
    root.dataset.fontBody = normalized.fontBody;
    root.dataset.fontHeading = normalized.fontHeading;
    root.dataset.typeScale = normalized.typeScale;
    root.dataset.customTheme = String(normalized.customEnabled);
    root.style.setProperty("--custom-primary", normalized.customPrimary);
    root.style.setProperty("--custom-secondary", normalized.customSecondary);
    root.style.setProperty("--custom-primary-contrast", contrastColor(normalized.customPrimary));
    return normalized;
  }

  function save(settings) {
    var normalized = apply(settings);
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(normalized));
    } catch (error) {
      // The theme still applies when storage is unavailable.
    }
    window.dispatchEvent(new CustomEvent("aureonthemechange", { detail: normalized }));
    return normalized;
  }

  window.AureonTheme = {
    storageKey: storageKey,
    defaults: defaults,
    normalize: normalize,
    read: read,
    apply: apply,
    save: save
  };

  apply(read());
}());
