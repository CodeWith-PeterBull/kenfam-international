<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;

/** Presents Kenfam's public institutional and travel discovery homepage. */
final class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'featuredTours' => $this->travelDataReady()
                ? Tour::query()->published()->with([
                    'destinations',
                    'categories',
                    'ratePlans' => fn ($query) => $query->publiclyAvailable()->with('participantRates'),
                    'departures' => fn ($query) => $query->bookable()->limit(1),
                ])->orderByDesc('is_featured')->orderBy('sort_order')->limit(6)->get()
                : collect(),
            'featuredDestinations' => $this->travelDataReady()
                ? Destination::query()->published()->where('is_featured', true)->orderBy('sort_order')->limit(6)->get()
                : collect(),
        ]);
    }

    private function travelDataReady(): bool
    {
        return config('travel-tours.enabled', false)
            && Schema::hasTable('travel_tours')
            && Schema::hasTable('travel_destinations');
    }
}
