# Mail Notification Templates

## Status

Complete and verified on `feature/institution-details-communications-foundation` on 15 July 2026.

This module owns the reusable Aureon HTML and plain-text Laravel notification shell. Domain modules continue to own recipients, subjects, business copy, actions, queue timing, and delivery policy; Institution Details supplies shared organization presentation.

## Factual baseline

- CSK's published mail/notification resources, Institution Details integration, and administration preview workflow were reviewed.
- Laravel notification guidance: <https://laravel.com/docs/12.x/notifications>.
- Laravel mail and Markdown customization guidance: <https://laravel.com/docs/12.x/mail>.
- Mail transport remains Laravel's environment-backed `config/mail.php`; no credential fields were added to the database.

## Architecture decisions

1. Laravel's mail and notification views are published into `resources/views/vendor`; vendor package files remain untouched.
2. `config/mail.php` selects the project-owned `aureon` Markdown theme.
3. Published Blade components resolve `ResolvesInstitutionProfile` directly. Blade component boundaries do not reliably inherit parent view variables, while the scoped resolver memoizes the actual lookup.
4. HTML mail uses an absolute institution logo URL and visible institution name. Plain text carries equivalent identity and contact information without local file paths.
5. The shared shell provides presentation only. Domain notifications remain ordinary `Notification` classes returning `MailMessage` instances.
6. Future transactional notifications should implement `ShouldQueue` and dispatch after commit where appropriate. The administration test-mail action is deliberately immediate for deterministic feedback.
7. Previewing and sending are separate permissions. Test delivery is limited to three attempts per ten minutes per administrator/IP.
8. Audit context stores only the recipient domain; the full recipient address is not duplicated in system activity properties.

## Implementation inventory

| Path | Action | Responsibility |
| --- | --- | --- |
| `resources/views/vendor/mail/html/*` | Publish/customize | Project-owned HTML Markdown components |
| `resources/views/vendor/mail/text/*` | Publish/customize | Equivalent plain-text Markdown components |
| `resources/views/vendor/mail/html/themes/aureon.css` | Create | Aureon wine theme and email-client-safe visual rules |
| `resources/views/vendor/notifications/email.blade.php` | Publish/customize | Default notification content wrapper and institution salutation |
| `config/mail.php` | Modify | Select the `aureon` theme and published component path |
| `app/Notifications/SystemTestMailNotification.php` | Create | Representative shared-template notification |
| `app/Http/Requests/Admin/SendTestMailRequest.php` | Create | Authorization and recipient validation |
| `app/Http/Controllers/Admin/CommunicationTemplateController.php` | Create | HTML preview, on-demand test send, and audited feedback |
| `resources/views/admin/communication-templates/index.blade.php` | Create | Mail preview, test-recipient control, and report previews |
| `app/Providers/AppServiceProvider.php` | Modify | Test-mail rate limiter and resolver binding |
| `routes/web.php` | Modify | Protected preview and throttled send routes |
| `tests/Feature/Communications/MailNotificationTemplateTest.php` | Create | Access, database branding, on-demand send, and audit coverage |

## Shared presentation

HTML and text outputs include:

- main logo where HTML is supported;
- institution name and optional descriptor;
- physical/postal identity, public email, telephone, website, and social summary where configured;
- current-year legal footer and automated-message attribution;
- domain notification greeting, lines, action button, salutation, and action fallback URL.

The template is intentionally restrained so password resets, email verification, two-factor codes, editorial notices, approvals, and future domain notifications can share it without carrying unrelated marketing content.

## Administration workflow

- `/admin/communication-templates` embeds a safe HTML mail preview and both report orientations.
- `/admin/communication-templates/mail-preview` renders `SystemTestMailNotification` but never sends it.
- `POST /admin/communication-templates/test-mail` validates the address, applies permission and transport throttles, sends an on-demand notification, and records `communication.test_mail_sent`.
- The page defaults the test recipient to the signed-in administrator but permits another validated address.

## Authorization matrix

| Surface | Required permission |
| --- | --- |
| Template page and mail preview | `preview_communication_templates` |
| Send test notification | `send_test_notifications` plus named rate limiter |

Guests are redirected by `auth`; authenticated users without the relevant permission receive HTTP 403. Only `system-admin` receives these permissions initially.

## Adoption contract

New domain modules should return a standard `MailMessage`; the shared shell is inherited automatically:

```php
return (new MailMessage)
    ->subject('Editorial review required')
    ->greeting("Hello {$recipient->name},")
    ->line('A post is ready for review.')
    ->action('Open review', route('admin.posts.edit', $post));
```

Use queues for business delivery, keep secrets in deployment config, provide plain-language content, and test each domain notification's recipient, subject, action URL, queue behavior, and authorization separately.

## Verification record

| Check | Result |
| --- | --- |
| Combined module tests | `12` tests and `67` assertions passed |
| Full Laravel suite | `83` tests and `370` assertions passed |
| Database branding | Mail preview rendered the database institution name and notification content |
| Delivery isolation | `Notification::fake()` verified on-demand mail without external transport |
| Audit | Test send persisted the actor, event, source, and recipient domain |
| Cached-config Tinker | Resolved subject: `Aureon communication template test` |
| Blade compilation | Published HTML/text mail resources compiled successfully |
| Authenticated Chromium QA | Mail preview and test control passed at `390x844` and `1440x1000` without overflow or failed resources |

Browser evidence is retained at `.docs/dev/dashboard-qa/communication-templates-mobile.png`, `.docs/dev/dashboard-qa/communication-templates-desktop.png`, and `.docs/dev/dashboard-qa/diagnostics.json`.

## Operational notes

- Production must configure a real Laravel mailer and queue worker according to the adopting site's deployment policy.
- The preview endpoint is authenticated administration content and must not be made public.
- The main logo URL must be publicly reachable by recipient email clients; text mail does not depend on it.
