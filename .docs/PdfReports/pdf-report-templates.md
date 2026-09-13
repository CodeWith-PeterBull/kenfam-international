# PDF Report Templates

## Status

Complete and verified on `feature/institution-details-communications-foundation` on 15 July 2026.

This module owns the reusable A4 report engine and explicit portrait/landscape Blade layouts. Domain modules own authorization, query/filter construction, report titles, filenames, body views, and audit semantics.

## Factual baseline

- CSK's portrait/landscape report layouts, report body semantics, and two-pass page-total implementation were reviewed.
- The installed wrapper is `barryvdh/laravel-dompdf 3.1.2`; upstream usage and security defaults were checked at <https://github.com/barryvdh/laravel-dompdf>.
- Institution identity is consumed through `ResolvesInstitutionProfile`; PDF templates do not query the database directly.

## Architecture decisions

1. `ReportOrientation` is a backed enum accepting only `portrait` or `landscape` and maps each value to an explicit layout.
2. `ReportContext` carries title, subtitle, normalized `.pdf` filename, orientation, immutable generation time, actor/role label, and applied filters.
3. `RendersPdfReports` is the reusable boundary; `PdfReportService` is the default implementation for render, inline stream, and attachment download.
4. Rendering is deliberately two-pass. The first DOMPDF pass obtains the canvas page count; the second injects `totalPages` so the footer can render `Page X of Y`.
5. Portrait and landscape share header/footer/style partials but retain separate layout files and orientation-aware report columns.
6. DOMPDF remote retrieval and JavaScript are disabled. Institution logos are validated local PNG/JPEG data URIs, keeping report generation deterministic and network-independent.
7. Filenames are lowercased, stripped to safe characters, trimmed, defaulted when empty, and forced to a `.pdf` suffix before response headers are created.
8. Stream/download responses set `application/pdf`, explicit disposition, content length, and `X-Content-Type-Options: nosniff`.

## Implementation inventory

| Path | Action | Responsibility |
| --- | --- | --- |
| `config/dompdf.php` | Publish/harden | A4 defaults, DejaVu Sans, JavaScript off, remote access off |
| `app/Enums/ReportOrientation.php` | Create | Typed orientation and layout mapping |
| `app/Data/ReportContext.php` | Create | Immutable report metadata and safe filename construction |
| `app/Data/RenderedPdf.php` | Create | PDF bytes, filename, and computed page count |
| `app/Contracts/RendersPdfReports.php` | Create | Render/stream/download contract |
| `app/Services/PdfReportService.php` | Create | Two-pass DOMPDF orchestration and hardened responses |
| `resources/views/reports/layouts/pdf-portrait.blade.php` | Create | Explicit portrait document shell |
| `resources/views/reports/layouts/pdf-landscape.blade.php` | Create | Explicit landscape document shell |
| `resources/views/reports/partials/styles.blade.php` | Create | DOMPDF-safe shared and orientation-aware CSS |
| `resources/views/reports/partials/header.blade.php` | Create | Local logo, institution identity, report title, and subtitle |
| `resources/views/reports/partials/footer.blade.php` | Create | Generator, timestamp, `Page X of Y`, and institution short name |
| `resources/views/reports/pdf/sample-register.blade.php` | Create | Representative shared-dataset portrait/landscape body |
| `app/Http/Controllers/Admin/CommunicationTemplateController.php` | Modify | Protected inline previews and audited downloads |
| `tests/Feature/Communications/PdfReportTemplateTest.php` | Create | Access, orientation, signature, headers, pagination, and filename tests |

## Layout semantics

### Portrait

- A4 portrait paper with condensed font/padding.
- Combines owner and update date into one column when horizontal space is constrained.
- Suitable for letters, summaries, approvals, and records with fewer fields.

### Landscape

- A4 landscape paper with extended table width.
- Separates owner and update date and is ready for additional domain columns.
- Suitable for registers, exports, audit summaries, and comparative reports.

Both layouts use the same dataset and context. Domain body views may branch on `$orientation` only where column semantics genuinely differ.

## Administration workflow

- `/admin/communication-templates/report-preview/{orientation}` streams inline PDF output.
- `/admin/communication-templates/report-download/{orientation}` returns an attachment and records `communication.report_template_downloaded` with filename and orientation.
- Invalid orientation values do not reach DOMPDF; route constraints and backed-enum binding return HTTP 404.
- The preview page embeds one portrait and one landscape document and exposes icon controls for separate opening and download.

## Adoption contract

Domain services/controllers should build a `ReportContext`, pass already-authorized/query-filtered data, and depend on `RendersPdfReports`:

```php
$context = ReportContext::forUser(
    user: $request->user(),
    title: 'Event Register',
    subtitle: 'Confirmed events',
    filename: 'event-register',
    orientation: ReportOrientation::Landscape,
    filters: ['Status: Confirmed'],
);

return $reports->download(
    view: 'reports.pdf.events.register',
    data: ['events' => $events],
    context: $context,
);
```

Keep database queries out of Blade, bound result sizes, eager-load required relations, authorize before rendering, and write a module-specific activity only after a successful export response is prepared.

## Authorization matrix

| Surface | Required permission |
| --- | --- |
| Portrait/landscape preview | `preview_communication_templates` |
| Representative template download | `preview_communication_templates` |

Future domain reports must define their own permissions rather than inheriting this template-preview permission.

## Verification record

| Check | Result |
| --- | --- |
| Combined module tests | `12` tests and `67` assertions passed |
| Full Laravel suite | `83` tests and `370` assertions passed |
| PDF payloads | Portrait and landscape responses start with `%PDF` |
| Multi-page proof | An 80-row portrait fixture produced more than one computed page |
| Two-pass footer data | Final render received the first-pass `totalPages` value |
| Response hardening | MIME, inline/attachment disposition, length, and `nosniff` headers verified |
| Filename boundary | Traversal-like input normalized to `quarterly-report.pdf` |
| Cached-config Tinker | Valid one-page `tinker-verification.pdf` generated through the bound contract |
| Authenticated Chromium QA | Both report preview frames and download controls remained contained at `390x844` and `1440x1000` |

Browser evidence is retained in the Communication Templates captures and `.docs/dev/dashboard-qa/diagnostics.json`.

## Operational notes

- Keep remote asset access disabled unless a later threat-reviewed requirement explicitly changes the policy.
- Large exports should move to queued generation and temporary authorized storage rather than blocking an HTTP request.
- Every new report body needs tests for empty data, representative data, long values, page breaks, both supported orientations where applicable, and authorization.
