<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.roles-and-permissions.index');
    }
}
