<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the permission-protected reusable customer workspace.
 */
final class CustomerController extends Controller
{
    public function index(): View
    {
        return view('commerce::admin.customers.index');
    }
}
