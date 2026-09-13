<?php

namespace App\Enums;

enum UserType: string
{
    case SystemAdministrator = 'system-admin';
    case ContentManager = 'content-manager';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System administrator',
            self::ContentManager => 'Content manager',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'Full platform administration classification.',
            self::ContentManager => 'Content planning and publishing classification.',
            self::Editor => 'Editorial contribution and review classification.',
            self::Viewer => 'Read-only account classification.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
