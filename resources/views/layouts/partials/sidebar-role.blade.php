@php
    $sidebarUser = auth()->user()->loadMissing(['profile', 'media']);
    $permissionTools = collect([
        ['label' => 'Users', 'route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'ti-users', 'permission' => \App\Support\CmsPermission::VIEW_USERS],
        ['label' => 'Roles & permissions', 'route' => 'admin.roles-and-permissions.index', 'active' => ['admin.roles-and-permissions.*'], 'icon' => 'ti-shield-lock', 'permission' => \App\Support\CmsPermission::VIEW_ROLES_AND_PERMISSIONS],
        ['label' => 'System activity', 'route' => 'admin.system-activity.index', 'active' => ['admin.system-activity.*'], 'icon' => 'ti-history', 'permission' => \App\Support\CmsPermission::VIEW_SYSTEM_ACTIVITIES],
        ['label' => 'Institution details', 'route' => 'admin.institution-details.index', 'active' => ['admin.institution-details.*'], 'icon' => 'ti-building', 'permission' => \App\Support\CmsPermission::VIEW_INSTITUTION_DETAILS],
        ['label' => 'Templates', 'route' => 'admin.communication-templates.index', 'active' => ['admin.communication-templates.*'], 'icon' => 'ti-template', 'permission' => \App\Support\CmsPermission::PREVIEW_COMMUNICATION_TEMPLATES],
        ['label' => 'Shop frontend', 'route' => 'commerce.storefront.catalog.index', 'active' => ['commerce.storefront.*'], 'icon' => 'ti-building-store', 'permission' => null, 'module' => 'commerce', 'public' => true, 'external' => true],
        ['label' => 'POS terminal', 'route' => 'commerce.pos.terminal', 'active' => ['commerce.pos.terminal', 'commerce.pos.receipts.*'], 'icon' => 'ti-device-desktop-dollar', 'permission' => \App\Modules\Commerce\Support\CommercePermission::ACCESS_POS],
        ['label' => 'Registers', 'route' => 'commerce.pos.admin.registers.index', 'active' => ['commerce.pos.admin.registers.*'], 'icon' => 'ti-building-store', 'permission' => \App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS],
        ['label' => 'Till sessions', 'route' => 'commerce.pos.admin.tills.index', 'active' => ['commerce.pos.admin.tills.*'], 'icon' => 'ti-cash-register', 'permission' => \App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS],
        ['label' => 'Accommodation overview', 'route' => 'property-booking.admin.dashboard', 'active' => ['property-booking.admin.dashboard'], 'icon' => 'ti-chart-dots-3', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_DASHBOARD, 'module' => 'property-booking'],
        ['label' => 'Accommodation properties', 'route' => 'property-booking.admin.properties.index', 'active' => ['property-booking.admin.properties.*'], 'icon' => 'ti-building-community', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_PROPERTIES, 'module' => 'property-booking'],
        ['label' => 'Accommodation amenities', 'route' => 'property-booking.admin.amenities.index', 'active' => ['property-booking.admin.amenities.*'], 'icon' => 'ti-sparkles', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_PROPERTIES, 'module' => 'property-booking'],
        ['label' => 'Accommodation units', 'route' => 'property-booking.admin.units.index', 'active' => ['property-booking.admin.units.*'], 'icon' => 'ti-door', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_PROPERTIES, 'module' => 'property-booking'],
        ['label' => 'Accommodation rates', 'route' => 'property-booking.admin.rates.index', 'active' => ['property-booking.admin.rates.*'], 'icon' => 'ti-receipt-2', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_RATES, 'module' => 'property-booking'],
        ['label' => 'Accommodation availability', 'route' => 'property-booking.admin.availability.index', 'active' => ['property-booking.admin.availability.*'], 'icon' => 'ti-calendar-search', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY, 'module' => 'property-booking'],
        ['label' => 'Accommodation bookings', 'route' => 'property-booking.admin.bookings.index', 'active' => ['property-booking.admin.bookings.*'], 'icon' => 'ti-calendar-check', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_BOOKINGS, 'module' => 'property-booking'],
        ['label' => 'Guest directory', 'route' => 'property-booking.admin.guests.index', 'active' => ['property-booking.admin.guests.*'], 'icon' => 'ti-users-group', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_GUESTS, 'module' => 'property-booking'],
        ['label' => 'Unit readiness', 'route' => 'property-booking.admin.readiness.index', 'active' => ['property-booking.admin.readiness.*'], 'icon' => 'ti-brush', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_READINESS, 'module' => 'property-booking'],
        ['label' => 'Point of Booking', 'route' => 'property-booking.pob.terminal', 'active' => ['property-booking.pob.terminal', 'property-booking.pob.receipts.*'], 'icon' => 'ti-device-desktop', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::ACCESS_POB, 'module' => 'property-booking'],
        ['label' => 'Reception registers', 'route' => 'property-booking.pob.admin.registers.index', 'active' => ['property-booking.pob.admin.registers.*'], 'icon' => 'ti-building-store', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_SHIFTS, 'module' => 'property-booking'],
        ['label' => 'Reception shifts', 'route' => 'property-booking.pob.admin.shifts.index', 'active' => ['property-booking.pob.admin.shifts.*'], 'icon' => 'ti-clock-dollar', 'permission' => \App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_SHIFTS, 'module' => 'property-booking'],
        ['label' => 'Stay storefront', 'route' => 'property-booking.storefront.catalog.index', 'active' => ['property-booking.storefront.*'], 'icon' => 'ti-world', 'permission' => null, 'module' => 'property-booking', 'public' => true, 'external' => true],
    ])->filter(function (array $item) use ($sidebarUser): bool {
        if (($item['module'] ?? null) === 'commerce' && ! config('commerce.enabled', true)) {
            return false;
        }

        if (($item['module'] ?? null) === 'property-booking' && ! config('property-booking.enabled', true)) {
            return false;
        }

        return Route::has($item['route']) && (($item['public'] ?? false) || $sidebarUser->can($item['permission']));
    });
@endphp
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route($sidebarHomeRoute) }}" class="logo logo-normal"><img src="{{ asset(config('kenfam.brand.logo_dark')) }}" alt="Kenfam International"></a>
        <a href="{{ route($sidebarHomeRoute) }}" class="logo logo-white"><img src="{{ asset(config('kenfam.brand.logo_light')) }}" alt="Kenfam International"></a>
        <a href="{{ route($sidebarHomeRoute) }}" class="logo-small"><img src="{{ asset(config('kenfam.brand.favicon')) }}" alt="Kenfam International"></a>
        <a id="toggle_btn" href="javascript:void(0);" aria-label="Collapse navigation"><i data-feather="chevrons-left" class="feather-16"></i></a>
    </div>

    <div class="modern-profile p-3 pb-0">
        <div class="aureon-user-panel rounded p-3 mb-3 text-center">
            @if ($sidebarUser->profilePhotoUrl())
                <img src="{{ $sidebarUser->profilePhotoUrl() }}" alt="Profile photo" class="aureon-avatar-img mb-2">
            @else
                <span class="aureon-user-avatar mb-2">{{ $sidebarUser->initials }}</span>
            @endif
            <h6 class="fs-14 fw-bold mb-1">{{ $sidebarUser->display_name }}</h6>
            <p class="fs-12 mb-0">{{ $sidebarRoleLabel }}</p>
        </div>
    </div>

    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                @foreach ($sidebarSections as $section)
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">{{ $section['label'] }}</h6>
                        <ul>
                            @foreach ($section['items'] as $item)
                                @php($active = request()->routeIs(...$item['active']))
                                <li class="{{ $active ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}"><i class="ti {{ $item['icon'] }} fs-16 me-2"></i><span>{{ $item['label'] }}</span></a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach

                @if ($permissionTools->isNotEmpty() || ($sidebarUser->can(\App\Support\CmsPermission::VIEW_APPLICATION_LOGS) && config('log-viewer.enabled')))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Assigned tools</h6>
                        <ul>
                            @foreach ($permissionTools as $item)
                                <li class="{{ request()->routeIs(...$item['active']) ? 'active' : '' }}">
                                    <a href="{{ route($item['route']) }}" @if($item['external'] ?? false) target="_blank" rel="noopener noreferrer" @endif><i class="ti {{ $item['icon'] }} fs-16 me-2"></i><span>{{ $item['label'] }}</span></a>
                                </li>
                            @endforeach
                            @if ($sidebarUser->can(\App\Support\CmsPermission::VIEW_APPLICATION_LOGS) && config('log-viewer.enabled'))
                                <li><a href="{{ url(config('log-viewer.route_path', 'logs')) }}"><i class="ti ti-file-search fs-16 me-2"></i><span>Application logs</span></a></li>
                            @endif
                        </ul>
                    </li>
                @endif

                <li class="submenu-open">
                    <h6 class="submenu-hdr">Account</h6>
                    <ul>
                        <li class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
                            <a href="{{ route('profile.edit') }}"><i class="ti ti-user-circle fs-16 me-2"></i><span>Profile</span></a>
                        </li>
                        <li><a href="{{ url('/') }}"><i class="ti ti-world fs-16 me-2"></i><span>Public site</span></a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>
