<?php

namespace App\Http\Middleware;

use App\Support\DashboardRegistry;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectToRoleDashboard
{
    /**
     * Redirect the neutral dashboard endpoint to the user's base workspace.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        return new RedirectResponse(route(DashboardRegistry::routeName($user->user_type)));
    }
}
