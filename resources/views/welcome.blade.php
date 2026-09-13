@php
    $pageTitle = (string) ($seo['title'] ?? $productName);
    $metaDescription = (string) ($seo['description'] ?? 'A modular business operations platform.');
    $canonicalUrl = route('home');
    $socialImage = asset((string) ($seo['social_image'] ?? 'aureon/assets/brand/twitter-card.png'));
    $faviconUrl = asset('aureon/assets/brand/favicon.png').'?v='.rawurlencode((string) config('aureon-home.asset_version'));
    $availableModules = collect($modules)->where('available', true);
    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $canonicalUrl.'#provider',
                'name' => $provider['name'],
                'url' => $provider['website'],
                'logo' => asset('aureon/assets/brand/logo.png'),
                'email' => $provider['emails'][0]['address'],
                'telephone' => $provider['phones'][0]['e164'],
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Nairobi',
                    'addressCountry' => 'KE',
                ],
            ],
            [
                '@type' => 'SoftwareApplication',
                '@id' => $canonicalUrl.'#software',
                'name' => $productName,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web',
                'url' => $canonicalUrl,
                'description' => $metaDescription,
                'provider' => ['@id' => $canonicalUrl.'#provider'],
                'featureList' => collect($modules)->pluck('label')->values()->all(),
            ],
            [
                '@type' => 'ItemList',
                'name' => $productName.' modules',
                'itemListElement' => collect($modules)->values()->map(fn (array $module, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $module['name'],
                    'url' => $module['url'] ?? $canonicalUrl.'#'.$module['key'],
                ])->all(),
            ],
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="application-name" content="{{ $productName }}">
    <meta name="theme-color" content="#70233a">
    <meta property="og:site_name" content="{{ $productName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $socialImage }}">
    <meta property="og:image:alt" content="{{ $productName }} modular operations platform">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $socialImage }}">
    <title>{{ $pageTitle }}</title>
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ $faviconUrl }}" type="image/png" sizes="64x64">
    <link rel="shortcut icon" href="{{ $faviconUrl }}" type="image/png">
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    <script src="{{ asset('aureon/assets/js/theme-init.js') }}"></script>
    @include('layouts.partials.loader-bootstrap')
    <link href="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('aureon/assets/css/theme.css') }}" rel="stylesheet">
    <link href="{{ asset('aureon/assets/css/theme-controller.css') }}" rel="stylesheet">
    @vite('resources/css/aureon-home.css')
</head>
<body class="aureon-home">
    <x-loader />
    <a class="home-skip-link" href="#main-content">Skip to main content</a>

    <header class="home-header" data-home-header>
        <div class="container-xxl home-header__desktop d-none d-xl-grid">
            <nav class="home-header__nav" aria-label="Primary navigation">
                <a href="#modules">Modules</a>
                <a href="#capabilities">Platform</a>
                <a href="#pricing">Pricing</a>
            </nav>
            <a class="home-brand" href="{{ route('home') }}" aria-label="{{ $productName }} home">
                <img class="home-brand__default" src="{{ asset('aureon/assets/brand/logo.png') }}" width="220" height="64" alt="Aureon Group">
                <img class="home-brand__dark" src="{{ asset('aureon/assets/brand/logo-light.png') }}" width="220" height="64" alt="" aria-hidden="true">
                <span>CMS</span>
            </a>
            <div class="home-header__actions">
                <button class="home-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
                @auth
                    <a class="home-button home-button--quiet" href="{{ route('dashboard') }}"><i data-lucide="layout-dashboard" aria-hidden="true"></i>Workspace</a>
                @else
                    <a class="home-button home-button--quiet" href="{{ route('login') }}"><i data-lucide="log-in" aria-hidden="true"></i>Sign in</a>
                @endauth
                <button class="home-button" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">Get a quote</button>
            </div>
        </div>

        <div class="container-xxl home-header__compact d-grid d-xl-none">
            <button class="home-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#homeMobileMenu" aria-controls="homeMobileMenu" aria-label="Open navigation" title="Menu"><i data-lucide="menu" aria-hidden="true"></i></button>
            <a class="home-brand" href="{{ route('home') }}" aria-label="{{ $productName }} home">
                <img class="home-brand__default" src="{{ asset('aureon/assets/brand/logo.png') }}" width="220" height="64" alt="Aureon Group">
                <img class="home-brand__dark" src="{{ asset('aureon/assets/brand/logo-light.png') }}" width="220" height="64" alt="" aria-hidden="true">
                <span>CMS</span>
            </a>
            <button class="home-icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeController" aria-controls="themeController" aria-label="Open theme settings" title="Theme settings"><i data-lucide="palette" aria-hidden="true"></i></button>
        </div>
    </header>

    <aside class="offcanvas offcanvas-start home-mobile-menu" tabindex="-1" id="homeMobileMenu" aria-labelledby="homeMobileMenuTitle">
        <div class="offcanvas-header home-mobile-menu__header">
            <a class="home-brand" href="{{ route('home') }}" id="homeMobileMenuTitle">
                <img class="home-brand__default" src="{{ asset('aureon/assets/brand/logo.png') }}" width="220" height="64" alt="Aureon Group">
                <img class="home-brand__dark" src="{{ asset('aureon/assets/brand/logo-light.png') }}" width="220" height="64" alt="" aria-hidden="true">
                <span>CMS</span>
            </a>
            <button class="home-icon-button" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation" title="Close"><i data-lucide="x" aria-hidden="true"></i></button>
        </div>
        <div class="offcanvas-body home-mobile-menu__body">
            <nav aria-label="Mobile navigation">
                <a href="#modules" data-bs-dismiss="offcanvas">Modules <i data-lucide="boxes" aria-hidden="true"></i></a>
                <a href="#capabilities" data-bs-dismiss="offcanvas">Platform <i data-lucide="layers-3" aria-hidden="true"></i></a>
                <a href="#pricing" data-bs-dismiss="offcanvas">Pricing <i data-lucide="badge-dollar-sign" aria-hidden="true"></i></a>
                @foreach ($availableModules as $module)
                    <a href="{{ $module['url'] }}">Open {{ $module['name'] }} <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                @endforeach
            </nav>
            <div class="home-mobile-menu__actions">
                @auth
                    <a class="home-button home-button--quiet" href="{{ route('dashboard') }}">Open workspace</a>
                @else
                    <a class="home-button home-button--quiet" href="{{ route('login') }}">Sign in</a>
                @endauth
                <button class="home-button" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">Get a quote</button>
            </div>
        </div>
    </aside>

    <main id="main-content">
        <section class="home-hero" aria-labelledby="home-hero-title">
            <img class="home-hero__media" src="{{ asset('aureon/assets/images/home/aureon-cms-hero.webp') }}" alt="Business leaders reviewing operations together" width="1920" height="1080" fetchpriority="high">
            <div class="home-hero__overlay" aria-hidden="true"></div>
            <div class="container-xxl home-hero__content">
                <p class="home-eyebrow">Modular business operations</p>
                <h1 id="home-hero-title">{{ $productName }}</h1>
                <p>One adaptable foundation for the systems that run your organization, from sales and stock to accommodation and guest operations.</p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--light" href="#modules">Explore modules <i data-lucide="arrow-down" aria-hidden="true"></i></a>
                    <button class="home-text-link" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">Plan your implementation <i data-lucide="arrow-up-right" aria-hidden="true"></i></button>
                </div>
                <dl class="home-hero__facts">
                    <div><dt>{{ count($availableModules) }}</dt><dd>Ready modules</dd></div>
                    <div><dt>Laravel</dt><dd>Modular foundation</dd></div>
                    <div><dt>Livewire</dt><dd>Responsive workflows</dd></div>
                </dl>
            </div>
        </section>

        <section class="home-modules" id="modules" aria-labelledby="modules-title">
            <div class="container-xxl">
                <header class="home-section-heading">
                    <div><p class="home-eyebrow">Available now</p><h2 id="modules-title">Operational modules, ready to adapt.</h2></div>
                    <p>Start with the capability your organization needs today. Every module shares the same identity, access, audit, communication, document, and theme foundation.</p>
                </header>

                <div class="home-module-list">
                    @foreach ($modules as $module)
                        @php($firstScreenshot = $module['screenshots'][0])
                        <article class="home-module-card" id="{{ $module['key'] }}">
                            <div class="home-module-card__content">
                                <div class="home-module-card__meta">
                                    <span>{{ $module['number'] }}</span>
                                    <span class="home-status {{ $module['available'] ? 'is-ready' : '' }}"><i aria-hidden="true"></i>{{ $module['available'] ? $module['status'] : 'Available for adoption' }}</span>
                                </div>
                                <div class="home-module-card__icon"><i data-lucide="{{ $module['icon'] }}" aria-hidden="true"></i></div>
                                <p class="home-eyebrow">{{ $module['label'] }}</p>
                                <h3>{{ $module['name'] }}</h3>
                                <p class="home-module-card__description">{{ $module['description'] }}</p>
                                <ul class="home-module-card__capabilities">
                                    @foreach ($module['capabilities'] as $capability)
                                        <li><i data-lucide="check" aria-hidden="true"></i><span>{{ $capability }}</span></li>
                                    @endforeach
                                </ul>
                                <div class="home-module-card__scenarios" aria-label="Suitable scenarios">
                                    @foreach ($module['scenarios'] as $scenario)<span>{{ $scenario }}</span>@endforeach
                                </div>
                            </div>

                            <div class="home-module-gallery" data-module-gallery>
                                <figure class="home-module-gallery__stage">
                                    <img src="{{ asset($firstScreenshot['src']) }}" alt="{{ $firstScreenshot['alt'] }}" width="1280" height="800" loading="lazy" data-gallery-stage>
                                    <span class="home-module-gallery__role" data-gallery-role>{{ $firstScreenshot['role'] }}</span>
                                    <figcaption data-gallery-caption>{{ $firstScreenshot['label'] }}</figcaption>
                                    <button class="home-module-gallery__expand" type="button" data-gallery-expand aria-label="Open {{ $module['name'] }} gallery full screen" title="View full screen"><i data-lucide="maximize-2" aria-hidden="true"></i></button>
                                </figure>
                                <div class="home-module-gallery__toolbar">
                                    <span aria-live="polite" aria-atomic="true" data-gallery-position>1 / {{ count($module['screenshots']) }}</span>
                                    <div>
                                        <button type="button" data-gallery-previous aria-label="View previous {{ $module['name'] }} screenshot" title="Previous screenshot"><i data-lucide="chevron-left" aria-hidden="true"></i></button>
                                        <button type="button" data-gallery-next aria-label="View next {{ $module['name'] }} screenshot" title="Next screenshot"><i data-lucide="chevron-right" aria-hidden="true"></i></button>
                                    </div>
                                </div>
                                <div class="home-module-gallery__tabs" role="group" aria-label="{{ $module['name'] }} screenshots" tabindex="0">
                                    @foreach ($module['screenshots'] as $index => $screenshot)
                                        <button class="{{ $index === 0 ? 'is-active' : '' }}" type="button" data-gallery-option data-gallery-src="{{ asset($screenshot['src']) }}" data-gallery-alt="{{ $screenshot['alt'] }}" data-gallery-label="{{ $screenshot['label'] }}" data-gallery-role="{{ $screenshot['role'] }}" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}">
                                            <img src="{{ asset($screenshot['src']) }}" alt="" width="240" height="150" loading="lazy" aria-hidden="true"><span><small>{{ $screenshot['role'] }}</small>{{ $screenshot['label'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <footer class="home-module-card__footer">
                                <div><small>Implementation pricing</small><strong>{{ $module['pricing'] }}</strong><p>{{ $module['pricing_note'] }}</p></div>
                                <div class="home-module-card__actions">
                                    @if ($module['available'])
                                        <a class="home-button home-button--quiet" href="{{ $module['url'] }}">View live module <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                                    @endif
                                    <button class="home-button" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">Get a quote</button>
                                </div>
                            </footer>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="home-platform" id="capabilities" aria-labelledby="platform-title">
            <div class="container-xxl home-platform__grid">
                <div class="home-platform__intro">
                    <p class="home-eyebrow">Shared platform</p>
                    <h2 id="platform-title">A consistent operating layer beneath every module.</h2>
                    <p>Adopters gain one coherent workspace instead of disconnected tools, while each domain remains structured enough to extend independently.</p>
                </div>
                <div class="home-platform__capabilities">
                    @foreach ([
                        ['shield-check', 'Access and accountability', 'Roles, permissions, two-factor readiness, system activity, and application logs.'],
                        ['mail-check', 'Operational communication', 'Institution-aware mail, dedicated notifications, PDFs, receipts, and reports.'],
                        ['blocks', 'Modular Laravel architecture', 'Module-owned routes, models, Livewire components, resources, tests, and documentation.'],
                        ['palette', 'Adoption-ready interface', 'Responsive light and dark themes, centralized brand assets, colors, and typography.'],
                    ] as [$icon, $title, $description])
                        <article><i data-lucide="{{ $icon }}" aria-hidden="true"></i><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div></article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="home-pricing" id="pricing" aria-labelledby="pricing-title">
            <div class="container-xxl home-pricing__inner" id="request-quote">
                <div>
                    <p class="home-eyebrow">Adoption and pricing</p>
                    <h2 id="pricing-title">Price the implementation you actually need.</h2>
                    <p>Module selection, migration, branding, integrations, hosting, training, and operational requirements are scoped together before a proposal is prepared.</p>
                </div>
                <div class="home-pricing__action">
                    <span>Module pricing</span>
                    <strong>Custom quote</strong>
                    <p>No generic bundle, and no hidden assumptions.</p>
                    <button class="home-button" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard"><i data-lucide="send" aria-hidden="true"></i>Request a scoped quote</button>
                    <small>{{ $provider['emails'][0]['address'] }} | {{ $provider['phones'][0]['display'] }}</small>
                </div>
            </div>
        </section>
    </main>

    <footer class="home-footer">
        <div class="container-xxl home-footer__main">
            <div class="home-footer__brand">
                <a class="home-brand" href="{{ route('home') }}"><img src="{{ asset('aureon/assets/brand/logo-light.png') }}" width="220" height="64" alt="Aureon Group"><span>CMS</span></a>
                <p>A modular corporate operations engine built for focused, maintainable adoption.</p>
            </div>
            <div><h2>Modules</h2><nav aria-label="Footer modules">@foreach ($availableModules as $module)<a href="{{ $module['url'] }}">{{ $module['name'] }}</a>@endforeach</nav></div>
            <div><h2>Platform</h2><nav aria-label="Footer platform navigation"><a href="#capabilities">Capabilities</a><a href="#pricing">Pricing</a><a href="{{ route('login') }}">Sign in</a></nav></div>
            <div><h2>Implementation</h2><p>Configure branding, workflows, permissions, documents, and integrations around your operation.</p><button class="home-footer__quote" type="button" data-aureon-contact-trigger aria-haspopup="dialog" aria-controls="aureonContactCard">Get a quote <i data-lucide="arrow-up-right" aria-hidden="true"></i></button></div>
        </div>
        <div class="home-footer__bottom"><div class="container-xxl"><p>&copy; <span data-current-year>{{ now()->year }}</span> {{ $productName }}.</p><p>Aureon corporate engine by Meta Software Developers</p></div></div>
    </footer>

    @include('partials.aureon-home-theme-controller')
    <x-aureon-contact-card :contact="$provider" :product-name="$productName" />
    <div class="modal fade home-gallery-lightbox" tabindex="-1" aria-labelledby="homeGalleryLightboxTitle" aria-modal="true" role="dialog" data-gallery-lightbox>
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <header class="home-gallery-lightbox__header">
                    <div><span data-lightbox-role>Module view</span><h2 id="homeGalleryLightboxTitle" data-lightbox-title>Module screenshot</h2></div>
                    <div class="home-gallery-lightbox__actions"><span aria-live="polite" aria-atomic="true" data-lightbox-position></span><button type="button" data-bs-dismiss="modal" aria-label="Close full-screen gallery" title="Close"><i data-lucide="x" aria-hidden="true"></i></button></div>
                </header>
                <div class="home-gallery-lightbox__stage">
                    <button type="button" data-lightbox-previous aria-label="View previous screenshot" title="Previous screenshot"><i data-lucide="chevron-left" aria-hidden="true"></i></button>
                    <figure><img src="" alt="" width="1280" height="800" data-lightbox-image></figure>
                    <button type="button" data-lightbox-next aria-label="View next screenshot" title="Next screenshot"><i data-lucide="chevron-right" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
    </div>
    <button class="home-back-to-top" type="button" data-home-back-to-top aria-label="Back to top" title="Back to top"><i data-lucide="arrow-up" aria-hidden="true"></i></button>

    <script src="{{ asset('aureon/assets/vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/vendor/lucide/lucide.min.js') }}"></script>
    <script src="{{ asset('aureon/assets/js/theme-controller.js') }}"></script>
    @vite('resources/js/aureon-home.js')
</body>
</html>
