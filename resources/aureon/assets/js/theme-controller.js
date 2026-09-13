(function () {
  "use strict";

  function initializeThemeController() {
    var api = window.AureonTheme;
    var controller = document.querySelector("[data-theme-controller]");
    if (!api || !controller) return;

    var form = controller.querySelector("[data-theme-form]");
    var customToggle = controller.querySelector("[data-theme-custom-toggle]");
    var customFields = controller.querySelector("[data-theme-custom-fields]");
    var primaryInput = controller.querySelector("[data-theme-primary]");
    var secondaryInput = controller.querySelector("[data-theme-secondary]");
    var primaryOutput = controller.querySelector("[data-theme-primary-output]");
    var secondaryOutput = controller.querySelector("[data-theme-secondary-output]");
    var resetButton = controller.querySelector("[data-theme-reset]");

    function setRadio(name, value) {
      var input = form.querySelector('input[name="' + name + '"][value="' + value + '"]');
      if (input) input.checked = true;
    }

    function updateCustomState(enabled) {
      customFields.classList.toggle("disabled", !enabled);
      customFields.querySelectorAll("input").forEach(function (input) {
        input.disabled = !enabled;
      });
      customFields.setAttribute("aria-disabled", String(!enabled));
    }

    function sync(settings) {
      setRadio("theme-mode", settings.mode);
      setRadio("theme-palette", settings.palette);
      setRadio("theme-type-scale", settings.typeScale);
      form.elements["theme-font-body"].value = settings.fontBody;
      form.elements["theme-font-heading"].value = settings.fontHeading;
      customToggle.checked = settings.customEnabled;
      primaryInput.value = settings.customPrimary;
      secondaryInput.value = settings.customSecondary;
      primaryOutput.textContent = settings.customPrimary.toUpperCase();
      secondaryOutput.textContent = settings.customSecondary.toUpperCase();
      updateCustomState(settings.customEnabled);
    }

    function selected(name) {
      var input = form.querySelector('input[name="' + name + '"]:checked');
      return input ? input.value : "";
    }

    function collect() {
      return {
        mode: selected("theme-mode"),
        palette: selected("theme-palette"),
        typeScale: selected("theme-type-scale"),
        fontBody: form.elements["theme-font-body"].value,
        fontHeading: form.elements["theme-font-heading"].value,
        customEnabled: customToggle.checked,
        customPrimary: primaryInput.value,
        customSecondary: secondaryInput.value
      };
    }

    function commit() {
      var settings = api.save(collect());
      sync(settings);
    }

    form.addEventListener("change", commit);
    primaryInput.addEventListener("input", commit);
    secondaryInput.addEventListener("input", commit);

    resetButton.addEventListener("click", function () {
      var settings = api.save(api.defaults);
      sync(settings);
    });

    sync(api.read());
  }

  document.addEventListener("DOMContentLoaded", initializeThemeController);
}());
