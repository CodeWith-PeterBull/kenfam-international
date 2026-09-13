<?php

namespace App\Contracts;

use App\Data\RenderedPdf;
use App\Data\ReportContext;
use Symfony\Component\HttpFoundation\Response;

interface RendersPdfReports
{
    /** @param array<string, mixed> $data */
    public function render(string $view, array $data, ReportContext $context): RenderedPdf;

    /** @param array<string, mixed> $data */
    public function stream(string $view, array $data, ReportContext $context): Response;

    /** @param array<string, mixed> $data */
    public function download(string $view, array $data, ReportContext $context): Response;
}
