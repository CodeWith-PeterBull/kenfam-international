<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents current stock projections and immutable movement history.
 */
final class InventoryController extends Controller
{
    /**
     * Render the module-owned inventory administration view.
     */
    public function index(): View
    {
        return view('commerce::admin.inventory.index');
    }
}
