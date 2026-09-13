<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the module-owned register configuration workspace.
 */
final class RegisterController extends Controller
{
    public function index(): View
    {
        return view('commerce::pos.admin.registers.index');
    }
}
