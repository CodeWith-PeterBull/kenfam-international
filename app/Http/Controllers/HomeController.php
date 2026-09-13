<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/** Presents the database-independent Aureon CMS module showcase. */
final class HomeController extends Controller
{
    /** Render supported modules with links resolved only from registered routes. */
    public function __invoke(): View
    {
        $modules = collect(config('aureon-home.modules', []))
            ->map(function (array $module): array {
                $routeName = (string) ($module['public_route'] ?? '');
                $available = (bool) ($module['enabled'] ?? true)
                    && $routeName !== ''
                    && Route::has($routeName);

                return [
                    ...$module,
                    'available' => $available,
                    'url' => $available ? route($routeName) : null,
                ];
            })
            ->values()
            ->all();

        return view('welcome', [
            'modules' => $modules,
            'productName' => (string) config('aureon-home.product_name', 'Aureon CMS'),
            'provider' => (array) config('aureon-home.provider', []),
            'seo' => (array) config('aureon-home.seo', []),
        ]);
    }
}
