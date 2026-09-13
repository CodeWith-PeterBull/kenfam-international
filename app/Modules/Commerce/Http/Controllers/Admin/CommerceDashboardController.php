<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Reporting\Enums\CommerceDashboardRange;
use App\Modules\Commerce\Reporting\Services\CommerceDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Renders the permission-scoped cross-domain Commerce overview.
 */
final class CommerceDashboardController extends Controller
{
    /**
     * Resolve a validated reporting range and render its immutable snapshot.
     */
    public function __invoke(Request $request, CommerceDashboardService $dashboard): View
    {
        $validated = $request->validate([
            'range' => ['nullable', 'integer', Rule::in(CommerceDashboardRange::values())],
        ]);
        $range = CommerceDashboardRange::tryFrom((int) ($validated['range'] ?? CommerceDashboardRange::default()->value))
            ?? CommerceDashboardRange::default();

        return view('commerce::admin.dashboard.index', [
            'ranges' => CommerceDashboardRange::cases(),
            'snapshot' => $dashboard->snapshot($range),
        ]);
    }
}
