<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the authorized product and category administration workspace.
 */
final class CatalogController extends Controller
{
    /**
     * Render the module-owned catalog administration view.
     */
    public function index(): View
    {
        return view('commerce::admin.catalog.index');
    }
}
