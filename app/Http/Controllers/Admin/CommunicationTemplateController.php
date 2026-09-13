<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\RecordsSystemActivity;
use App\Contracts\RendersPdfReports;
use App\Data\ReportContext;
use App\Enums\ReportOrientation;
use App\Enums\SystemActivitySeverity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendTestMailRequest;
use App\Models\User;
use App\Notifications\SystemTestMailNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

class CommunicationTemplateController extends Controller
{
    public function index(): View
    {
        return view('admin.communication-templates.index');
    }

    public function mailPreview(Request $request): MailMessage
    {
        return (new SystemTestMailNotification($request->user()->name))->toMail($request->user());
    }

    public function sendTestMail(SendTestMailRequest $request, RecordsSystemActivity $activities): RedirectResponse
    {
        $email = $request->validated('email');
        Notification::route('mail', $email)
            ->notify(new SystemTestMailNotification($request->user()->name));

        $activities->record(
            activityType: 'communication.test_mail_sent',
            description: 'Institution communication template test mail sent',
            actor: $request->user(),
            properties: ['recipient_domain' => str($email)->after('@')->lower()->value()],
            severity: SystemActivitySeverity::Notice,
            source: 'mail-notifications',
        );

        return back()->with('success', 'Test notification sent successfully.');
    }

    public function reportPreview(
        Request $request,
        ReportOrientation $orientation,
        RendersPdfReports $reports,
    ): Response {
        return $reports->stream(
            view: 'reports.pdf.sample-register',
            data: ['records' => $this->sampleRecords()],
            context: $this->reportContext($request->user(), $orientation),
        );
    }

    public function reportDownload(
        Request $request,
        ReportOrientation $orientation,
        RendersPdfReports $reports,
        RecordsSystemActivity $activities,
    ): Response {
        $context = $this->reportContext($request->user(), $orientation);
        $report = $reports->download(
            view: 'reports.pdf.sample-register',
            data: ['records' => $this->sampleRecords()],
            context: $context,
        );

        $activities->record(
            activityType: 'communication.report_template_downloaded',
            description: "{$orientation->label()} PDF report template downloaded",
            actor: $request->user(),
            properties: [
                'filename' => $context->filename,
                'orientation' => $orientation->value,
            ],
            severity: SystemActivitySeverity::Info,
            source: 'pdf-reports',
        );

        return $report;
    }

    private function reportContext(User $user, ReportOrientation $orientation): ReportContext
    {
        return ReportContext::forUser(
            user: $user,
            title: 'CMS Content Register',
            subtitle: 'Aureon communications foundation preview',
            filename: "aureon-cms-register-{$orientation->value}.pdf",
            orientation: $orientation,
            filters: [
                "Orientation: {$orientation->label()}",
                'Dataset: Representative CMS records',
            ],
        );
    }

    /** @return list<array{title: string, reference: string, module: string, status: string, owner: string, updated: string}> */
    private function sampleRecords(): array
    {
        $modules = ['Posts', 'Events', 'Teams', 'Media', 'Pages', 'Expenditure'];
        $statuses = ['Published', 'Review', 'Draft'];
        $owners = ['Content Manager', 'Events Editor', 'Operations Team', 'Aureon Administrator'];

        return array_map(static function (int $index) use ($modules, $owners, $statuses): array {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);

            return [
                'title' => "Representative content record {$number}",
                'reference' => "AUR-CMS-{$number}",
                'module' => $modules[($index - 1) % count($modules)],
                'status' => $statuses[($index - 1) % count($statuses)],
                'owner' => $owners[($index - 1) % count($owners)],
                'updated' => now()->subHours($index)->format('d M Y'),
            ];
        }, range(1, 18));
    }
}
