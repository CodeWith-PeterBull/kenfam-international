<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the dedicated full-width cashier terminal.
 */
final class TerminalController extends Controller
{
    public function index(): View
    {
        return view('commerce::pos.terminal.index');
    }
}
