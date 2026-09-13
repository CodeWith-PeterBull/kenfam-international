<?php

namespace App\Enums;

enum SystemActivitySeverity: string
{
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Notice => 'Notice',
            self::Warning => 'Warning',
            self::Error => 'Error',
            self::Critical => 'Critical',
        };
    }
}
