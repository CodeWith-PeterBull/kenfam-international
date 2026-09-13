@include('layouts.partials.sidebar-role', [
    'sidebarHomeRoute' => 'content-manager.dashboard',
    'sidebarRoleLabel' => 'Content manager',
    'sidebarSections' => [
        [
            'label' => 'Content workspace',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'content-manager.dashboard', 'active' => ['dashboard', 'content-manager.dashboard'], 'icon' => 'ti-layout-dashboard'],
                ['label' => 'Content queue', 'route' => 'content-manager.queue', 'active' => ['content-manager.queue'], 'icon' => 'ti-list-check'],
                ['label' => 'Editorial calendar', 'route' => 'content-manager.calendar', 'active' => ['content-manager.calendar'], 'icon' => 'ti-calendar-event'],
            ],
        ],
    ],
])

