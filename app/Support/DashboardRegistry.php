<?php

namespace App\Support;

use App\Enums\UserType;

final class DashboardRegistry
{
    /**
     * Resolve the shared shell and destination owned by a base account type.
     *
     * @return array{route: string, label: string, topbar: string, sidebar: string}
     */
    public static function for(UserType|string|null $type): array
    {
        $resolved = $type instanceof UserType ? $type : UserType::tryFrom((string) $type);
        $resolved ??= UserType::Viewer;

        return match ($resolved) {
            UserType::SystemAdministrator => [
                'route' => 'admin.dashboard',
                'label' => 'Aureon administration',
                'topbar' => 'layouts.partials.topbar-admin',
                'sidebar' => 'layouts.partials.sidebar-admin',
            ],
            UserType::ContentManager => [
                'route' => 'content-manager.dashboard',
                'label' => 'Content management',
                'topbar' => 'layouts.partials.topbar-content-manager',
                'sidebar' => 'layouts.partials.sidebar-content-manager',
            ],
            UserType::Editor => [
                'route' => 'editor.dashboard',
                'label' => 'Editorial workspace',
                'topbar' => 'layouts.partials.topbar-editor',
                'sidebar' => 'layouts.partials.sidebar-editor',
            ],
            UserType::Viewer => [
                'route' => 'viewer.dashboard',
                'label' => 'Viewer workspace',
                'topbar' => 'layouts.partials.topbar-viewer',
                'sidebar' => 'layouts.partials.sidebar-viewer',
            ],
        };
    }

    public static function routeName(UserType|string|null $type): string
    {
        return self::for($type)['route'];
    }

    private function __construct() {}
}
