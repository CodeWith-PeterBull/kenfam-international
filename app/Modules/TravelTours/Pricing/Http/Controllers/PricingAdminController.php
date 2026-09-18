<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Presents the pricing pages; the workspaces themselves are Livewire managers. */
final class PricingAdminController extends Controller
{
    /** List tours with their pricing coverage so an operator can pick one to price. */
    public function index(): View
    {
        Gate::authorize('viewAny', TourRatePlan::class);

        return view('travel-tours::admin.pricing.index', [
            'tours' => Tour::query()
                ->withCount(['ratePlans', 'ratePlans as public_rate_plans_count' => fn ($query) => $query->publiclyAvailable(), 'departures'])
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    /** Render one tour's plan and rule workspaces. */
    public function tour(Tour $tour): View
    {
        Gate::authorize('viewAny', TourRatePlan::class);

        return view('travel-tours::admin.pricing.tour', compact('tour'));
    }

    /** Render the promotion workspace. */
    public function promotions(): View
    {
        Gate::authorize('viewAny', TourRatePlan::class);

        return view('travel-tours::admin.pricing.promotions');
    }
}
