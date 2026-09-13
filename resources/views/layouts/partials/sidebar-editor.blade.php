@include('layouts.partials.sidebar-role', [
    'sidebarHomeRoute' => 'editor.dashboard',
    'sidebarRoleLabel' => 'Editor',
    'sidebarSections' => [
        [
            'label' => 'Editorial workspace',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'editor.dashboard', 'active' => ['dashboard', 'editor.dashboard'], 'icon' => 'ti-layout-dashboard'],
                ['label' => 'My drafts', 'route' => 'editor.drafts', 'active' => ['editor.drafts'], 'icon' => 'ti-pencil'],
                ['label' => 'Review queue', 'route' => 'editor.reviews', 'active' => ['editor.reviews'], 'icon' => 'ti-eye-check'],
            ],
        ],
    ],
])

