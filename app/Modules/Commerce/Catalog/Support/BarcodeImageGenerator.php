<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Catalog\Support;

use App\Modules\Commerce\Exceptions\CatalogException;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Throwable;

/**
 * Renders scanner-ready Code 128 barcode images for on-screen preview and PDF labels.
 *
 * A single generation point keeps the management preview and the printed label
 * pixel-identical: both consume the same PNG produced here. DOMPDF cannot run
 * JavaScript, so a server-side raster is the only way to embed a scannable
 * barcode into a generated PDF.
 */
final readonly class BarcodeImageGenerator
{
    /**
     * Return a base64 PNG data URI for the supplied value.
     *
     * @param  int  $widthFactor  Pixel width of the narrowest bar; larger values
     *                            print wider, more forgiving barcodes.
     * @param  int  $height  Barcode height in pixels.
     */
    public function pngDataUri(string $value, int $widthFactor = 2, int $height = 60): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($value, $widthFactor, $height));
    }

    /**
     * Return the raw Code 128 PNG bytes for the supplied value.
     */
    public function png(string $value, int $widthFactor = 2, int $height = 60): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new CatalogException('A barcode value is required to render a label.');
        }

        try {
            return (new BarcodeGeneratorPNG)->getBarcode(
                $value,
                BarcodeGeneratorPNG::TYPE_CODE_128,
                max(1, $widthFactor),
                max(20, $height),
            );
        } catch (Throwable $exception) {
            throw new CatalogException("The barcode value [{$value}] could not be encoded.", previous: $exception);
        }
    }
}
