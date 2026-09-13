<?php

namespace App\Contracts;

use App\Enums\SystemActivitySeverity;
use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface RecordsSystemActivity
{
    /**
     * Persist one structured, append-only activity record.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $activityType,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        array $properties = [],
        SystemActivitySeverity $severity = SystemActivitySeverity::Info,
        ?string $source = null,
        ?string $batchUuid = null,
    ): SystemActivity;
}
