<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\PropertyBooking\Operations\Enums\OperationsDashboardRange;
use App\Modules\PropertyBooking\Operations\Services\PropertyBookingDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Renders the permission and property-scoped accommodation overview. */
final class OperationsDashboardController extends Controller
{
    /** Render a validated property-scoped operations snapshot. */
    public function __invoke(Request $request, PropertyBookingDashboardService $dashboard): View
    {
        $validated = $request->validate([
            'range' => ['nullable', 'integer', Rule::in(OperationsDashboardRange::values())],
        ]);
        $range = OperationsDashboardRange::tryFrom((int) ($validated['range'] ?? OperationsDashboardRange::default()->value))
            ?? OperationsDashboardRange::default();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return view('property-booking::admin.dashboard.index', [
            'ranges' => OperationsDashboardRange::cases(),
            'snapshot' => $dashboard->snapshot($actor, $range),
        ]);
    }
}
