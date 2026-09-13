<?php

namespace App\Http\Controllers;

use App\Support\DashboardRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    /**
     * Fallback redirect; the route middleware normally resolves this first.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route(DashboardRegistry::routeName($request->user()?->user_type));
    }
}
