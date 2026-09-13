<?php

declare(strict_types=1);

namespace App\Modules\Commerce\DemoData\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the permission-gated Commerce demonstration-data workspace.
 */
final class DemoDataController extends Controller
{
    public function __invoke(): View
    {
        return view('commerce::admin.demo-data.index');
    }
}
