# TravelTours K2E Catalog Completion

Status: complete and reviewed. Feature commit `527d752` was fast-forwarded
into `main`. Base commit: `a17e937`.

## Delivered

- Staff tour editor tabs now cover Base pricing, Media, and Publication in
  addition to the K2D itinerary/experience surfaces.
- Base pricing creates or updates one default public rate plan per tour.
  Adult is required, child and infant are optional; decimal input converts
  exactly to integer minor units. Existing advanced policy values are
  preserved. Currency cannot silently change after departure or booking use.
  Tour editors may inspect pricing; managers hold the write permission.
- Tour cover, ordered gallery, and public document operations remain in the
  catalog media service. Ownership, upload types/sizes, limits, order, and
  public-document boundaries are enforced. A published tour cannot lose its
  only cover without first being unpublished.
- Tour and destination cover/gallery uploads show a temporary image preview.
  Selecting a file suggests alt text from its humanized filename. The
  suggestion remains editable before save and can be revised later through
  the image metadata control. Cover and gallery drafts have separate fields.
- The publication tab reports concrete readiness gaps and offers review,
  publish/schedule, unpublish, archive, and restore actions subject to policy.
  Preview is authenticated, no-store, and noindex; public routes retain their
  published-only scope.
- Staff catalog rows show readiness, base price, and preview/public links.
  The public catalog adds category, tour type, and maximum-duration filters.
  Tour details expose owned gallery media and public attachments.
- The public gallery follows the Property Booking slider/lightbox pattern:
  in-page arrows/thumbnails, a full-window image viewer, persistent arrows,
  image title and caption, current/total count, Escape/close, focus return,
  and no body scrolling while open. Viewer initialization waits until open so
  hidden Swiper sizing cannot overflow narrow screens.

## Ownership And Boundaries

The module provider registers K2E Livewire components and the private preview
route; catalog services own writes and publication transitions. Controllers
and Blade views only coordinate or present results. No new migrations or
default seeder changes were made. The QA browser used an isolated SQLite
database with opt-in demo fixtures and two extra gallery images attached to
its first tour; the development database was not reseeded.

K3 still owns departure scheduling/capacity, public availability, non-default
and date-specific rate plans, seasonal/group/early-bird adjustments,
promotions, and price quote integration. K5 owns checkout and payment UX.

## Verification

| Gate | Result |
| --- | --- |
| K2E focused feature tests | 9 passed, 70 assertions |
| TravelTours module | 57 passed, 1,945 assertions |
| Full application | 180 passed, 2,604 assertions |
| PHPDoc audit | Passed |
| Migration metadata probe | 34 tables, 645 documented columns, zero missing comments |
| Pint | Passed for module and feature tests |
| Blade compilation | Passed |
| Vite production build | Passed; inherited absolute asset references remain runtime-resolved |
| Admin browser QA | 12 desktop/tablet/mobile captures in light/dark and reduced-motion states |
| Public browser QA | 12 inspections and nine captures; arrows change the image/title, overlay closes with Escape and returns focus; no runtime/network/overflow failures |

Admin captures: `.docs/TravelTours/qa/admin-k2e/`.
Public captures and diagnostics: `.docs/TravelTours/qa/storefront-k2e/`.
The focused tests cover Livewire upload previews and editable filename
suggestions, scoped media, exact base prices, publication blockers, private
preview, and public gallery/document markup.

## Review And Next Phase

The editor tabs, public catalog/detail, pricing authority, publication
readiness text, and desktop/mobile screenshots were reviewed. K3 should
branch from the merged K2 commit and deliver, in order:

1. Departure CRUD with timezone-aware booking windows, status, staff
   assignment, and capacity controls.
2. Availability and quote behavior bound to a selected departure, retaining
   integer money and the existing booking/hold transaction boundary.
3. Rate-plan and participant-price administration beyond the default plan,
   followed by seasonal/group/early-bird rules and promotions with explicit
   precedence.
4. Focused pricing/capacity tests and operator/browser QA for these new
   workflows. Do not pull K5 checkout or gateway work into K3.
