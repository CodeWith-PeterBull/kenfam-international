<?php

namespace App\Services;

use App\Contracts\RendersPdfReports;
use App\Contracts\ResolvesInstitutionProfile;
use App\Data\RenderedPdf;
use App\Data\ReportContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final readonly class PdfReportService implements RendersPdfReports
{
    public function __construct(private ResolvesInstitutionProfile $profiles) {}

    public function render(string $view, array $data, ReportContext $context): RenderedPdf
    {
        $viewData = $this->viewData($data, $context);
        $firstPass = Pdf::loadView($view, $viewData)->setPaper('a4', $context->orientation->value);
        $firstPass->render();
        $pageCount = max(1, $firstPass->getDomPDF()->getCanvas()->get_page_count());

        $finalPass = Pdf::loadView($view, [...$viewData, 'totalPages' => $pageCount])
            ->setPaper('a4', $context->orientation->value);
        $finalPass->render();

        return new RenderedPdf(
            contents: $finalPass->output(),
            filename: $context->filename,
            pageCount: $pageCount,
        );
    }

    public function stream(string $view, array $data, ReportContext $context): Response
    {
        return $this->response($this->render($view, $data, $context), 'inline');
    }

    public function download(string $view, array $data, ReportContext $context): Response
    {
        return $this->response($this->render($view, $data, $context), 'attachment');
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data, ReportContext $context): array
    {
        return [
            ...$data,
            'reportContext' => $context,
            'orientation' => $context->orientation->value,
            'title' => $context->title,
            'subtitle' => $context->subtitle,
            'institutionProfile' => $this->profiles->current(),
            'totalPages' => null,
        ];
    }

    private function response(RenderedPdf $pdf, string $disposition): Response
    {
        return response($pdf->contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('%s; filename="%s"', $disposition, $pdf->filename),
            'Content-Length' => (string) strlen($pdf->contents),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
