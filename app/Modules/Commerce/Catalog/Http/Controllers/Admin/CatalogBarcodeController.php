<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the authorized barcode label print workspace.
 */
final class CatalogBarcodeController extends Controller
{
    public function index(): View
    {
        return view('commerce::admin.catalog.barcodes.index');
    }
}
