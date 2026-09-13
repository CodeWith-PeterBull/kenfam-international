<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SystemActivityController extends Controller
{
    /**
     * Display the read-only system activity explorer.
     */
    public function index(): View
    {
        return view('admin.system-activity.index');
    }
}
