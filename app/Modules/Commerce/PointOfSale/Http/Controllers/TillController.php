<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the module-owned till lifecycle workspace.
 */
final class TillController extends Controller
{
    public function index(): View
    {
        return view('commerce::pos.admin.tills.index');
    }
}
