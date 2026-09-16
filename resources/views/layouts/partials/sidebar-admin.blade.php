@php($sidebarUser = auth()->user()->loadMissing(['profile', 'media']))
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
            <img src="{{ asset(config('kenfam.brand.logo_dark')) }}" alt="Kenfam International">
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo logo-white">
            <img src="{{ asset(config('kenfam.brand.logo_light')) }}" alt="Kenfam International">
        </a>
        <a href="{{ route('admin.dashboard') }}" class="logo-small">
            <img src="{{ asset(config('kenfam.brand.favicon')) }}" alt="Kenfam International">
        </a>
        <a id="toggle_btn" href="javascript:void(0);" aria-label="Collapse navigation">
            <i data-feather="chevrons-left" class="feather-16"></i>
        </a>
    </div>

    <div class="modern-profile p-3 pb-0">
        <div class="aureon-user-panel rounded p-3 mb-3 text-center">
            @if ($sidebarUser->profilePhotoUrl())
                <img src="{{ $sidebarUser->profilePhotoUrl() }}" alt="" class="aureon-avatar-img mb-2">
            @else
                <span class="aureon-user-avatar mb-2">{{ $sidebarUser->initials }}</span>
            @endif
            <h6 class="fs-14 fw-bold mb-1">{{ $sidebarUser->display_name }}</h6>
            <p class="fs-12 mb-0 text-capitalize">
                {{ str_replace('-', ' ', auth()->user()->getRoleNames()->first() ?? 'User') }}</p>
        </div>
    </div>

    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Workspace</h6>
                    <ul>
                        <li class="{{ request()->routeIs('dashboard', 'admin.dashboard') ? 'active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}"><i
                                    class="ti ti-layout-dashboard fs-16 me-2"></i><span>Dashboard</span></a>
                        </li>
                        <li class="{{ request()->routeIs('admin.blank') ? 'active' : '' }}">
                            <a href="{{ route('admin.blank') }}"><i class="ti ti-file fs-16 me-2"></i><span>Blank
                                    page</span></a>
                        </li>
                    </ul>
                </li>
                @if (auth()->user()->can(\App\Support\CmsPermission::VIEW_USERS) ||
                        auth()->user()->can(\App\Support\CmsPermission::VIEW_ROLES_AND_PERMISSIONS))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Users and access</h6>
                        <ul>
                            @can(\App\Support\CmsPermission::VIEW_USERS)
                                <li class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                    <a href="{{ route('admin.users.index') }}"><i class="ti ti-users fs-16 me-2"></i><span>Users</span></a>
                                </li>
                            @endcan
                            @can(\App\Support\CmsPermission::VIEW_ROLES_AND_PERMISSIONS)
                                <li class="{{ request()->routeIs('admin.roles-and-permissions.*') ? 'active' : '' }}">
                                    <a href="{{ route('admin.roles-and-permissions.index') }}"><i class="ti ti-shield-lock fs-16 me-2"></i><span>Roles &amp; permissions</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
                @if (auth()->user()->can(\App\Support\CmsPermission::VIEW_SYSTEM_ACTIVITIES) ||
                        auth()->user()->can(\App\Support\CmsPermission::VIEW_APPLICATION_LOGS))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Governance</h6>
                        <ul>
                            @can(\App\Support\CmsPermission::VIEW_SYSTEM_ACTIVITIES)
                                <li class="{{ request()->routeIs('admin.system-activity.*') ? 'active' : '' }}">
                                    <a href="{{ route('admin.system-activity.index') }}"><i
                                            class="ti ti-history fs-16 me-2"></i><span>System activity</span></a>
                                </li>
                            @endcan
                            @can(\App\Support\CmsPermission::VIEW_APPLICATION_LOGS)
                                @if (config('log-viewer.enabled'))
                                    <li>
                                        <a href="{{ url(config('log-viewer.route_path', 'logs')) }}"><i
                                                class="ti ti-file-search fs-16 me-2"></i><span>Application logs</span></a>
                                    </li>
                                @endif
                            @endcan
                        </ul>
                    </li>
                @endif
                @if (auth()->user()->can(\App\Support\CmsPermission::VIEW_INSTITUTION_DETAILS) ||
                        auth()->user()->can(\App\Support\CmsPermission::PREVIEW_COMMUNICATION_TEMPLATES))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Configuration</h6>
                        <ul>
                            @can(\App\Support\CmsPermission::VIEW_INSTITUTION_DETAILS)
                                <li class="{{ request()->routeIs('admin.institution-details.*') ? 'active' : '' }}">
                                    <a href="{{ route('admin.institution-details.index') }}"><i
                                            class="ti ti-building fs-16 me-2"></i><span>Institution details</span></a>
                                </li>
                            @endcan
                            @can(\App\Support\CmsPermission::PREVIEW_COMMUNICATION_TEMPLATES)
                                <li class="{{ request()->routeIs('admin.communication-templates.*') ? 'active' : '' }}">
                                    <a href="{{ route('admin.communication-templates.index') }}"><i
                                            class="ti ti-template fs-16 me-2"></i><span>Templates</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
                @if (config('commerce.enabled', true) &&
                        (auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::VIEW_DASHBOARD) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::VIEW_PRODUCTS) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::VIEW_INVENTORY) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::VIEW_ORDERS) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_CUSTOMERS) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_DEMO_DATA) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::ACCESS_POS) ||
                        auth()->user()->can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS)))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Commerce</h6>
                        <ul>
                            @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_DASHBOARD)
                                <li class="{{ request()->routeIs('commerce.admin.dashboard') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.dashboard') }}"><i
                                            class="ti ti-chart-dots-3 fs-16 me-2"></i><span>Commerce overview</span></a>
                                </li>
                            @endcan
                            <li>
                                <a href="{{ route('commerce.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i
                                        class="ti ti-building-store fs-16 me-2"></i><span>Shop frontend</span></a>
                            </li>
                            @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_PRODUCTS)
                                <li class="{{ request()->routeIs('commerce.admin.catalog.index') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.catalog.index') }}"><i
                                            class="ti ti-package fs-16 me-2"></i><span>Product catalog</span></a>
                                </li>
                                <li class="{{ request()->routeIs('commerce.admin.catalog.barcodes') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.catalog.barcodes') }}"><i
                                            class="ti ti-barcode fs-16 me-2"></i><span>Print barcodes</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_INVENTORY)
                                <li class="{{ request()->routeIs('commerce.admin.inventory.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.inventory.index') }}"><i
                                            class="ti ti-building-warehouse fs-16 me-2"></i><span>Inventory</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::VIEW_ORDERS)
                                <li class="{{ request()->routeIs('commerce.admin.orders.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.orders.index') }}"><i
                                            class="ti ti-receipt fs-16 me-2"></i><span>Orders</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_CUSTOMERS)
                                <li class="{{ request()->routeIs('commerce.admin.customers.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.customers.index') }}"><i
                                            class="ti ti-user-dollar fs-16 me-2"></i><span>Customers</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_DEMO_DATA)
                                <li class="{{ request()->routeIs('commerce.admin.demo-data.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.admin.demo-data.index') }}"><i
                                            class="ti ti-database-import fs-16 me-2"></i><span>Demo data</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::ACCESS_POS)
                                <li class="{{ request()->routeIs('commerce.pos.terminal', 'commerce.pos.receipts.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.pos.terminal') }}"><i
                                            class="ti ti-device-desktop-dollar fs-16 me-2"></i><span>POS terminal</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\Commerce\Support\CommercePermission::MANAGE_TILLS)
                                <li class="{{ request()->routeIs('commerce.pos.admin.registers.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.pos.admin.registers.index') }}"><i
                                            class="ti ti-building-store fs-16 me-2"></i><span>Registers</span></a>
                                </li>
                                <li class="{{ request()->routeIs('commerce.pos.admin.tills.*') ? 'active' : '' }}">
                                    <a href="{{ route('commerce.pos.admin.tills.index') }}"><i
                                            class="ti ti-cash-register fs-16 me-2"></i><span>Till sessions</span></a>
                                </li>
                            @endcan
                        </ul>
                    </li>
                @endif
                @if (config('property-booking.enabled', true) &&
                        Route::has('property-booking.admin.properties.index') &&
                        (auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_DASHBOARD) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_PROPERTIES) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_RATES) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_BOOKINGS) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_GUESTS) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_READINESS) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::ACCESS_POB) ||
                        auth()->user()->can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_SHIFTS)))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Accommodation</h6>
                        <ul>
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_DASHBOARD)
                                <li class="{{ request()->routeIs('property-booking.admin.dashboard') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.dashboard') }}"><i class="ti ti-chart-dots-3 fs-16 me-2"></i><span>Operations overview</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_PROPERTIES)
                                <li class="{{ request()->routeIs('property-booking.admin.properties.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.properties.index') }}"><i class="ti ti-building-community fs-16 me-2"></i><span>Properties</span></a>
                                </li>
                                <li class="{{ request()->routeIs('property-booking.admin.amenities.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.amenities.index') }}"><i class="ti ti-sparkles fs-16 me-2"></i><span>Amenities</span></a>
                                </li>
                                <li class="{{ request()->routeIs('property-booking.admin.units.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.units.index') }}"><i class="ti ti-door fs-16 me-2"></i><span>Units</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_RATES)
                                <li class="{{ request()->routeIs('property-booking.admin.rates.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.rates.index') }}"><i class="ti ti-receipt-2 fs-16 me-2"></i><span>Rates</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_AVAILABILITY)
                                <li class="{{ request()->routeIs('property-booking.admin.availability.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.availability.index') }}"><i class="ti ti-calendar-search fs-16 me-2"></i><span>Availability</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::VIEW_BOOKINGS)
                                <li class="{{ request()->routeIs('property-booking.admin.bookings.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.bookings.index') }}"><i class="ti ti-calendar-check fs-16 me-2"></i><span>Bookings</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_GUESTS)
                                <li class="{{ request()->routeIs('property-booking.admin.guests.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.guests.index') }}"><i class="ti ti-users-group fs-16 me-2"></i><span>Guests</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_READINESS)
                                <li class="{{ request()->routeIs('property-booking.admin.readiness.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.admin.readiness.index') }}"><i class="ti ti-brush fs-16 me-2"></i><span>Unit readiness</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::ACCESS_POB)
                                <li class="{{ request()->routeIs('property-booking.pob.terminal', 'property-booking.pob.receipts.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.pob.terminal') }}"><i class="ti ti-device-desktop fs-16 me-2"></i><span>Point of Booking</span></a>
                                </li>
                            @endcan
                            @can(\App\Modules\PropertyBooking\Support\PropertyBookingPermission::MANAGE_SHIFTS)
                                <li class="{{ request()->routeIs('property-booking.pob.admin.registers.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.pob.admin.registers.index') }}"><i class="ti ti-building-store fs-16 me-2"></i><span>Reception registers</span></a>
                                </li>
                                <li class="{{ request()->routeIs('property-booking.pob.admin.shifts.*') ? 'active' : '' }}">
                                    <a href="{{ route('property-booking.pob.admin.shifts.index') }}"><i class="ti ti-clock-dollar fs-16 me-2"></i><span>Reception shifts</span></a>
                                </li>
                            @endcan
                            @if (Route::has('property-booking.storefront.catalog.index'))
                                <li>
                                    <a href="{{ route('property-booking.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-world fs-16 me-2"></i><span>Stay storefront</span></a>
                                </li>
                            @endif
                        </ul>
                    </li>
                @endif
                @if (config('travel-tours.enabled', false) &&
                        Route::has('travel-tours.admin.dashboard') &&
                        (auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_DASHBOARD) ||
                        auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_CATALOG) ||
                        auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_DEPARTURES) ||
                        auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_BOOKINGS) ||
                        auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_INQUIRIES) ||
                        auth()->user()->can(\App\Modules\TravelTours\Support\TravelToursPermission::ACCESS_POB)))
                    <li class="submenu-open">
                        <h6 class="submenu-hdr">Travel and tours</h6>
                        <ul>
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_DASHBOARD)
                                <li class="{{ request()->routeIs('travel-tours.admin.dashboard') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.dashboard') }}"><i class="ti ti-chart-dots-3 fs-16 me-2"></i><span>Travel overview</span></a></li>
                            @endcan
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_CATALOG)
                                <li class="{{ request()->routeIs('travel-tours.admin.catalog.index') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.catalog.index') }}"><i class="ti ti-map-route fs-16 me-2"></i><span>Tour catalog</span></a></li>
                                <li class="{{ request()->routeIs('travel-tours.admin.catalog.categories') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.catalog.categories') }}"><i class="ti ti-category fs-16 me-2"></i><span>Tour categories</span></a></li>
                                <li class="{{ request()->routeIs('travel-tours.admin.catalog.destinations') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.catalog.destinations') }}"><i class="ti ti-map-pin fs-16 me-2"></i><span>Destinations</span></a></li>
                            @endcan
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_DEPARTURES)
                                <li class="{{ request()->routeIs('travel-tours.admin.departures.*') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.departures.index') }}"><i class="ti ti-calendar-event fs-16 me-2"></i><span>Departures</span></a></li>
                            @endcan
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_BOOKINGS)
                                <li class="{{ request()->routeIs('travel-tours.admin.bookings.*') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.bookings.index') }}"><i class="ti ti-ticket fs-16 me-2"></i><span>Bookings</span></a></li>
                            @endcan
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::VIEW_INQUIRIES)
                                <li class="{{ request()->routeIs('travel-tours.admin.inquiries.*') ? 'active' : '' }}"><a href="{{ route('travel-tours.admin.inquiries.index') }}"><i class="ti ti-messages fs-16 me-2"></i><span>Inquiries</span></a></li>
                            @endcan
                            @can(\App\Modules\TravelTours\Support\TravelToursPermission::ACCESS_POB)
                                <li class="{{ request()->routeIs('travel-tours.pob.*') ? 'active' : '' }}"><a href="{{ route('travel-tours.pob.terminal') }}"><i class="ti ti-device-desktop fs-16 me-2"></i><span>Booking desk</span></a></li>
                            @endcan
                            <li><a href="{{ route('travel-tours.storefront.catalog.index') }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-world fs-16 me-2"></i><span>Public tours</span></a></li>
                        </ul>
                    </li>
                @endif
                <li class="submenu-open">
                    <h6 class="submenu-hdr">Account</h6>
                    <ul>
                        <li class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
                            <a href="{{ route('profile.edit') }}"><i
                                    class="ti ti-user-circle fs-16 me-2"></i><span>Profile</span></a>
                        </li>
                        <li>
                            <a href="{{ url('/') }}"><i class="ti ti-world fs-16 me-2"></i><span>Public
                                    site</span></a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>
