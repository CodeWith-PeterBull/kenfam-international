<div class="sidebar-contact d-none d-lg-block" aria-hidden="true">
    <button
        type="button"
        class="toggle-theme"
        data-bs-toggle="offcanvas"
        data-bs-target="#dashboard-settings"
        aria-controls="dashboard-settings"
        aria-label="Open dashboard settings"
        title="Dashboard settings"
        tabindex="-1"
    >
        <i class="ti ti-settings"></i>
    </button>
</div>

<aside
    class="offcanvas offcanvas-end aureon-theme-settings"
    tabindex="-1"
    id="dashboard-settings"
    aria-labelledby="dashboard-settings-title"
>
    <div class="offcanvas-header">
        <div>
            <p class="aureon-settings-eyebrow mb-1">Kenfam workspace</p>
            <h2 class="offcanvas-title" id="dashboard-settings-title">Dashboard settings</h2>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close settings"></button>
    </div>

    <div class="offcanvas-body">
        <div class="accordion aureon-settings-accordion" id="dashboard-settings-accordion">
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard-mode-settings" aria-expanded="true" aria-controls="dashboard-mode-settings">
                        <span>App mode</span><i class="ti ti-chevron-down aureon-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h3>
                <div id="dashboard-mode-settings" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <div class="aureon-segmented" role="radiogroup" aria-label="Application mode">
                            @foreach ([['light', 'sun', 'Light'], ['dark', 'moon', 'Dark'], ['system', 'device-laptop', 'System']] as [$value, $icon, $label])
                                <input class="btn-check" type="radio" name="aureon-theme-mode" id="aureon-mode-{{ $value }}" value="{{ $value }}" data-dashboard-setting="mode">
                                <label class="aureon-segmented__option" for="aureon-mode-{{ $value }}"><i class="ti ti-{{ $icon }}"></i>{{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard-layout-settings" aria-expanded="true" aria-controls="dashboard-layout-settings">
                        <span>Layout</span><i class="ti ti-chevron-down aureon-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h3>
                <div id="dashboard-layout-settings" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <div class="row g-2" role="radiogroup" aria-label="Dashboard layout">
                            @foreach ([['default', 'default.svg', 'Default'], ['mini', 'mini.svg', 'Compact'], ['detached', 'detached.svg', 'Detached']] as [$value, $image, $label])
                                <div class="col-4">
                                    <input class="btn-check" type="radio" name="aureon-layout" id="aureon-layout-{{ $value }}" value="{{ $value }}" data-dashboard-setting="layout">
                                    <label class="aureon-layout-choice" for="aureon-layout-{{ $value }}">
                                        <img src="{{ asset('build/img/theme/' . $image) }}" alt="" aria-hidden="true">
                                        <span>{{ $label }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="aureon-settings-row mt-3">
                            <span class="aureon-settings-label">Page width</span>
                            <div class="aureon-inline-options" role="radiogroup" aria-label="Page width">
                                <input class="btn-check" type="radio" name="aureon-width" id="aureon-width-fluid" value="fluid" data-dashboard-setting="width">
                                <label for="aureon-width-fluid"><i class="ti ti-arrows-maximize me-1"></i>Fluid</label>
                                <input class="btn-check" type="radio" name="aureon-width" id="aureon-width-box" value="box" data-dashboard-setting="width">
                                <label for="aureon-width-box"><i class="ti ti-box-align-top-left me-1"></i>Boxed</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard-sidebar-settings" aria-expanded="true" aria-controls="dashboard-sidebar-settings">
                        <span>Sidebar</span><i class="ti ti-chevron-down aureon-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h3>
                <div id="dashboard-sidebar-settings" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <span class="aureon-settings-label d-block mb-2">Surface</span>
                        <div class="aureon-sidebar-colors mb-4" role="radiogroup" aria-label="Sidebar surface">
                            @foreach ([['theme', 'Theme'], ['light', 'Light'], ['dark', 'Dark'], ['brand', 'Brand']] as [$value, $label])
                                <input class="btn-check" type="radio" name="aureon-sidebar" id="aureon-sidebar-{{ $value }}" value="{{ $value }}" data-dashboard-setting="sidebar">
                                <label class="aureon-sidebar-color aureon-sidebar-color--{{ $value }}" for="aureon-sidebar-{{ $value }}"><span></span>{{ $label }}</label>
                            @endforeach
                        </div>

                        <span class="aureon-settings-label d-block mb-2">Background image</span>
                        <div class="aureon-sidebar-backgrounds" role="radiogroup" aria-label="Sidebar background image">
                            <input class="btn-check" type="radio" name="aureon-sidebar-background" id="aureon-sidebar-background-none" value="none" data-dashboard-setting="sidebarBackground">
                            <label class="aureon-sidebar-background aureon-sidebar-background--none" for="aureon-sidebar-background-none"><i class="ti ti-ban"></i><span>None</span></label>
                            @foreach (range(1, 6) as $background)
                                <input class="btn-check" type="radio" name="aureon-sidebar-background" id="aureon-sidebar-background-{{ $background }}" value="sidebarbg{{ $background }}" data-dashboard-setting="sidebarBackground">
                                <label class="aureon-sidebar-background" for="aureon-sidebar-background-{{ $background }}">
                                    <img
                                        src="{{ asset(sprintf('build/img/theme/sidebar-thumb-%02d.webp', $background)) }}"
                                        width="160"
                                        height="120"
                                        loading="lazy"
                                        decoding="async"
                                        alt=""
                                        aria-hidden="true"
                                    >
                                    <span class="visually-hidden">Background {{ $background }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard-color-settings" aria-expanded="true" aria-controls="dashboard-color-settings">
                        <span>Theme color</span><i class="ti ti-chevron-down aureon-accordion-chevron" aria-hidden="true"></i>
                    </button>
                </h3>
                <div id="dashboard-color-settings" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <div class="aureon-palette-options" role="radiogroup" aria-label="Theme color">
                            @foreach ([['wine', '#9f2333', 'Kenfam red'], ['teal', '#28656b', 'Corporate teal'], ['gold', '#8a6427', 'Heritage gold'], ['graphite', '#40434a', 'Graphite']] as [$value, $color, $label])
                                <input class="btn-check" type="radio" name="aureon-palette" id="aureon-palette-{{ $value }}" value="{{ $value }}" data-dashboard-setting="palette">
                                <label class="aureon-palette" for="aureon-palette-{{ $value }}" title="{{ $label }}">
                                    <span style="--palette-color: {{ $color }}"></span>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                            <label class="aureon-palette aureon-palette--custom" for="aureon-custom-primary" title="Custom theme color">
                                <input type="color" id="aureon-custom-primary" value="#70233a" data-theme-custom-color aria-label="Custom theme color">
                                <span>Custom</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="aureon-settings-footer">
        <button type="button" class="btn btn-light" data-dashboard-settings-reset><i class="ti ti-restore me-1"></i>Reset</button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="offcanvas"><i class="ti ti-check me-1"></i>Done</button>
        <span class="visually-hidden" aria-live="polite" data-dashboard-settings-status></span>
    </div>
</aside>
