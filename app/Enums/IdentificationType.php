<?php

namespace App\Enums;

enum IdentificationType: string
{
    case NationalId = 'national-id';
    case Passport = 'passport';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID',
            self::Passport => 'Passport',
            self::Other => 'Other identification',
        };
    }
}
