<?php

/** Presents authorized K2C tour draft and edit pages. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Keep route-model binding and page authorization outside Livewire. */
final class TourEditorController extends Controller
{
    /** Render a new tour editor for catalog authors. */
    public function create(): View
    {
        Gate::authorize('create', Tour::class);

        return view('travel-tours::admin.catalog.tour-editor', ['tour' => null]);
    }

    /** Render an existing tour editor after ULID binding and authorization. */
    public function edit(Tour $tour): View
    {
        Gate::authorize('update', $tour);

        return view('travel-tours::admin.catalog.tour-editor', compact('tour'));
    }
}
