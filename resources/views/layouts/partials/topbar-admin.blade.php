@php(
    $topbarUser = auth()->user()->loadMissing(['profile', 'media'])
)
<div class="header">
    <div class="main-header">
        <div class="header-left active">
            <a href="{{ route('admin.dashboard') }}" class="logo logo-normal">
                <img src="{{ asset('aureon/assets/brand/logo.png') }}" alt="Aureon">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="logo logo-white">
                <img src="{{ asset('aureon/assets/brand/logo-light.png') }}" alt="Aureon">
            </a>
            <a href="{{ route('admin.dashboard') }}" class="logo-small">
                <img src="{{ asset('aureon/assets/brand/logo-icon.png') }}" alt="Aureon">
            </a>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#sidebar" aria-label="Open navigation">
            <span class="bar-icon" aria-hidden="true"><span></span><span></span><span></span></span>
        </a>

        <ul class="nav user-menu ms-auto">
            <li class="nav-item nav-searchinputs d-none d-lg-flex">
                <div class="top-nav-search">
                    <div class="searchinputs input-group">
                        <input type="search" aria-label="Search dashboard" placeholder="Search workspace">
                        <div class="search-addon"><span><i class="ti ti-search"></i></span></div>
                    </div>
                </div>
            </li>
            <li class="nav-item nav-item-box">
                <button type="button" class="btn border-0" data-dashboard-theme-toggle
                    aria-label="Toggle dashboard theme" aria-pressed="false" title="Use dark theme">
                    <i class="ti ti-moon"></i>
                </button>
            </li>
            <li class="nav-item nav-item-box">
                <button type="button" class="btn border-0" data-bs-toggle="offcanvas"
                    data-bs-target="#dashboard-settings" aria-controls="dashboard-settings"
                    aria-label="Open dashboard settings" title="Dashboard settings">
                    <i class="ti ti-settings"></i>
                </button>
            </li>
            <li class="nav-item nav-item-box d-none d-sm-flex">
                <a href="{{ url('/') }}" aria-label="View public site" title="View public site"><i
                        class="ti ti-world"></i></a>
            </li>
            <li class="nav-item dropdown has-arrow main-drop">
                <a href="javascript:void(0);" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <span class="user-info">
                        @if ($topbarUser->profilePhotoUrl())
                            <img src="{{ $topbarUser->profilePhotoUrl() }}" alt="Profile photo" class="aureon-avatar-img aureon-avatar-img--topbar">
                        @else
                            <span class="user-letter">{{ $topbarUser->initials }}</span>
                        @endif
                        <span class="user-detail d-none d-md-inline-block">
                            <span class="user-name">{{ $topbarUser->display_name }}</span>
                            <span class="user-role">{{ auth()->user()->getRoleNames()->first() ?? 'User' }}</span>
                        </span>
                    </span>
                </a>
                <div class="dropdown-menu menu-drop-user">
                    <div class="profilename">
                        <a class="dropdown-item" href="{{ route('profile.edit') }}"><i
                                class="ti ti-user-circle me-2"></i>Profile</a>
                        <a class="dropdown-item" href="{{ route('admin.blank') }}"><i
                                class="ti ti-layout me-2"></i>Blank page</a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item logout"><i class="ti ti-logout me-2"></i>Sign
                                out</button>
                        </form>
                    </div>
                </div>
            </li>
        </ul>

        <div class="dropdown mobile-user-menu">
            <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
                aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</div>
