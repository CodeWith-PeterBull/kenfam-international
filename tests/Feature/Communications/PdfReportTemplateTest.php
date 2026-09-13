<?php

namespace Tests\Feature\Communications;

use App\Contracts\RendersPdfReports;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfReportTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create();
        $this->administrator->assignRole('system-admin');
    }

    public function test_report_preview_and_download_routes_are_protected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.communication-templates.report-preview', ReportOrientation::Portrait))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.communication-templates.report-download', ReportOrientation::Landscape))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->get('/admin/communication-templates/report-preview/square')
            ->assertNotFound();
    }

    public function test_both_report_orientations_render_valid_pdf_responses(): void
    {
        foreach (ReportOrientation::cases() as $orientation) {
            $preview = $this->actingAs($this->administrator)
                ->get(route('admin.communication-templates.report-preview', $orientation));

            $preview
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            $this->assertStringStartsWith('%PDF', $preview->getContent());

            $download = $this->get(route('admin.communication-templates.report-download', $orientation));
            $download
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');

            $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
            $this->assertStringContainsString($orientation->value, (string) $download->headers->get('Content-Disposition'));
        }

        $this->assertSame(2, $this->administrator->systemActivities()->count());
    }

    public function test_two_pass_renderer_reports_a_real_page_count(): void
    {
        $records = array_map(static fn (int $index): array => [
            'title' => "Record {$index}",
            'reference' => "AUR-{$index}",
            'module' => 'Posts',
            'status' => $index % 2 === 0 ? 'Published' : 'Review',
            'owner' => 'Content Manager',
            'updated' => '15 Jul 2026',
        ], range(1, 80));

        $context = ReportContext::forUser(
            user: $this->administrator,
            title: 'Pagination Verification',
            subtitle: 'Two-pass PDF rendering test',
            filename: 'pagination-verification.pdf',
            orientation: ReportOrientation::Portrait,
        );

        $rendered = app(RendersPdfReports::class)->render(
            'reports.pdf.sample-register',
            ['records' => $records],
            $context,
        );

        $this->assertStringStartsWith('%PDF', $rendered->contents);
        $this->assertGreaterThan(1, $rendered->pageCount);
        $this->assertSame('pagination-verification.pdf', $rendered->filename);
    }

    public function test_report_context_sanitizes_and_normalizes_download_filenames(): void
    {
        $context = ReportContext::forUser(
            user: $this->administrator,
            title: 'Filename Verification',
            subtitle: null,
            filename: '../../Quarterly Report',
            orientation: ReportOrientation::Landscape,
        );

        $this->assertSame('quarterly-report.pdf', $context->filename);
    }
}
