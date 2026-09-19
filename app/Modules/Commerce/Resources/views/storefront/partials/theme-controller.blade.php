<button class="theme-controller-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings">
    <i data-lucide="palette" aria-hidden="true"></i>
</button>

<aside class="offcanvas offcanvas-end theme-controller" tabindex="-1" id="themeController" data-theme-controller aria-labelledby="themeControllerTitle">
    <div class="offcanvas-header theme-controller-header">
        <div><p class="theme-controller-kicker">Appearance</p><h2 id="themeControllerTitle">Theme settings</h2></div>
        <button class="commerce-icon-button" type="button" data-bs-dismiss="offcanvas" aria-label="Close theme settings" title="Close"><i data-lucide="x" aria-hidden="true"></i></button>
    </div>
    <div class="offcanvas-body theme-controller-body">
        <form data-theme-form>
            <fieldset class="theme-control-group">
                <legend>Mode</legend>
                <div class="theme-segmented theme-segmented-two">
                    <input class="visually-hidden" type="radio" name="theme-mode" id="theme-mode-light" value="light"><label for="theme-mode-light"><i data-lucide="sun" aria-hidden="true"></i>Light</label>
                    <input class="visually-hidden" type="radio" name="theme-mode" id="theme-mode-dark" value="dark"><label for="theme-mode-dark"><i data-lucide="moon" aria-hidden="true"></i>Dark</label>
                </div>
            </fieldset>
            <fieldset class="theme-control-group">
                <legend>Color palette</legend>
                <div class="theme-palette-grid">
                    @foreach ([
                        ['olive', 'Olive', ['#6a753d', '#70233a', '#b28a4b']],
                        ['wine', 'Aureon wine', ['#70233a', '#28656b', '#b28a4b']],
                        ['cobalt', 'Cobalt', ['#245aa5', '#287369', '#c18a32']],
                        ['forest', 'Forest', ['#275b45', '#75603e', '#bb8d3b']],
                        ['graphite', 'Graphite', ['#343941', '#8a3d54', '#ad7e35']],
                    ] as [$value, $label, $swatches])
                        <input class="visually-hidden" type="radio" name="theme-palette" id="theme-palette-{{ $value }}" value="{{ $value }}">
                        <label class="theme-palette-option" for="theme-palette-{{ $value }}">
                            <span class="theme-palette-swatches" aria-hidden="true">@foreach ($swatches as $swatch)<i style="--swatch: {{ $swatch }}"></i>@endforeach</span><span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <section class="theme-control-group">
                <div class="theme-control-heading"><label for="theme-custom-toggle">Custom colors</label><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="theme-custom-toggle" data-theme-custom-toggle></div></div>
                <div class="theme-custom-colors" data-theme-custom-fields>
                    <label for="theme-primary-color"><span>Primary</span><output data-theme-primary-output>#6A753D</output></label><input type="color" id="theme-primary-color" value="#6a753d" data-theme-primary>
                    <label for="theme-secondary-color"><span>Secondary</span><output data-theme-secondary-output>#70233A</output></label><input type="color" id="theme-secondary-color" value="#70233a" data-theme-secondary>
                </div>
            </section>
            <fieldset class="theme-control-group">
                <legend>Typography</legend>
                <div class="theme-select-grid">
                    <label for="theme-font-body">Body font</label><select id="theme-font-body" name="theme-font-body"><option value="poppins">Poppins</option><option value="jost">Jost</option><option value="montserrat">Montserrat</option><option value="system">System UI</option></select>
                    <label for="theme-font-heading">Heading font</label><select id="theme-font-heading" name="theme-font-heading"><option value="poppins">Poppins</option><option value="jost">Jost</option><option value="montserrat">Montserrat</option><option value="system">System UI</option></select>
                </div>
            </fieldset>
            <fieldset class="theme-control-group">
                <legend>Type scale</legend>
                <div class="theme-segmented theme-segmented-three">
                    <input class="visually-hidden" type="radio" name="theme-type-scale" id="theme-scale-compact" value="compact"><label for="theme-scale-compact">Compact</label>
                    <input class="visually-hidden" type="radio" name="theme-type-scale" id="theme-scale-standard" value="standard"><label for="theme-scale-standard">Standard</label>
                    <input class="visually-hidden" type="radio" name="theme-type-scale" id="theme-scale-large" value="large"><label for="theme-scale-large">Large</label>
                </div>
            </fieldset>
            <button class="theme-reset-button" type="button" data-theme-reset><i data-lucide="rotate-ccw" aria-hidden="true"></i>Reset defaults</button>
        </form>
    </div>
</aside>
