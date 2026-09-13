<?php

namespace App\Data;

use App\Enums\ReportOrientation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ReportContext
{
    /** @param list<string> $filters */
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public string $filename,
        public ReportOrientation $orientation,
        public CarbonImmutable $generatedAt,
        public string $generatedBy,
        public array $filters = [],
    ) {}

    /** @param list<string> $filters */
    public static function forUser(
        User $user,
        string $title,
        ?string $subtitle,
        string $filename,
        ReportOrientation $orientation,
        array $filters = [],
    ): self {
        $role = $user->getRoleNames()->first();
        $role = $role ? Str::headline($role) : 'Authenticated User';

        return new self(
            title: $title,
            subtitle: $subtitle,
            filename: self::sanitizeFilename($filename),
            orientation: $orientation,
            generatedAt: CarbonImmutable::now(),
            generatedBy: "{$user->name} | {$role}",
            filters: $filters,
        );
    }

    /**
     * Normalize an externally visible PDF filename for safe response headers.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $safeFilename = Str::of($filename)
            ->replaceMatches('/[^A-Za-z0-9._-]/', '-')
            ->replaceMatches('/\.{2,}/', '-')
            ->trim('.-_')
            ->lower()
            ->value();
        $safeFilename = $safeFilename === '' ? 'report.pdf' : $safeFilename;

        return str_ends_with($safeFilename, '.pdf') ? $safeFilename : "{$safeFilename}.pdf";
    }
}
