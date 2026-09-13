@include('layouts.partials.sidebar-role', [
    'sidebarHomeRoute' => 'viewer.dashboard',
    'sidebarRoleLabel' => 'Viewer',
    'sidebarSections' => [
        [
            'label' => 'My workspace',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'viewer.dashboard', 'active' => ['dashboard', 'viewer.dashboard'], 'icon' => 'ti-layout-dashboard'],
                ['label' => 'Content library', 'route' => 'viewer.library', 'active' => ['viewer.library'], 'icon' => 'ti-books'],
                ['label' => 'Saved items', 'route' => 'viewer.saved', 'active' => ['viewer.saved'], 'icon' => 'ti-bookmark'],
            ],
        ],
    ],
])

