<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Reports;

use App\Contracts\RendersPdfReports;

/**
 * Shared boundary for Commerce list/register PDF reports.
 *
 * Synchronous exports are bounded to a safe row count; larger exports are a
 * deferred queued-generation concern per the PDF report operational notes.
 */
abstract class CommerceListReport
{
    protected const MAX_ROWS = 2000;

    public function __construct(protected readonly RendersPdfReports $reports) {}

    protected function wasTruncated(int $total, int $shown): bool
    {
        return $total > $shown;
    }
}
