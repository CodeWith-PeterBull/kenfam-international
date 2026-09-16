# Travel & Tours: Master Delivery Plan

Status: K1 foundation reconciled and K2 ready, 2026-09-16. This remains the
target contract, while the implementation ledger records actual tests and
delivered behavior. The 34-model persistence/factory boundary and the first
transactional service slices are implemented; unverified UI shells do not
advance K2-K7.

## 1. Requirement Sources And Boundaries

The approved revised platform plan defines scope. Aureon's Property Booking
architecture, data model, workflows and verification documents define comparable
engineering depth. They are not travel tables to copy. Kenfam's existing website
is an institutional-content reference only: no WordPress structure, legacy tour
price, checkout behavior or URL dictates the new architecture.

Kenfam is the independent deployable application. TravelTours is the reusable
business capability. Reuse the host's authentication, institution details,
dashboard, theming, media, activity, mail and report contracts. Commerce and
PropertyBooking remain source references with their runtime flags disabled.

Source: Aureon subtree at c7aaddbb40e85dd1796576ce350c8a4a97af0189; independent
baseline f4139b7. The lock file contains Laravel 12.64.0 and Livewire 4.3.3.
Do not silently upgrade to Laravel 13 or introduce a second module framework.

## 2. Domain Meaning

A tour is an editorial product. A departure is dated seat inventory. An inquiry
does not reserve inventory. A quote is a server calculation with expiry, not a
browser price. A hold temporarily reserves seats. A booking preserves the
commercial agreement and participant snapshots. Confirmation and payment are
separate state machines. A desk shift reconciles money physically received,
not every booking attributed to its operator.

Initial scope includes taxonomy, destinations, itinerary, activities, content,
FAQs, extras, media, departure scheduling, staffing, rates, adjustments,
promotions, holds, bookings, customers/travelers, instalments, payments/refunds,
inquiries, POB registers/shifts, reports and focused operational notifications.

Gateways, live FX, airline/hotel integrations, agent commissions, multi-tenancy,
customer portals, reviews, wishlists and supplier accounting are future phases.
Do not create speculative foundation tables for these. Waitlisting initially
means a capacity-neutral inquiry, not automatic conversion to a reservation.
Card recording stores a method/reference only, never card numbers or CVVs.

## 3. Traceable Acceptance Matrix

| ID | Requirement | Phase | Required evidence |
| --- | --- | --- | --- |
| TT-01 | Independent private repo, source provenance | K0 | Parentless baseline and remote private/pushed SHA |
| TT-02 | Disabled legacy modules have no runtime activity | K0/K1 | Fresh boot, routes, migrations, listeners, commands, menus, seeder checks |
| TT-03 | Client-neutral module using host contracts | K1 | Dependency scan and alternate-brand rendering |
| TT-04 | Complete 34-model foundation | K1 | Fields, FKs, comments, indexes, ULIDs, casts, relationships and factories |
| TT-05 | Catalog, taxonomy, itinerary, FAQ, extras and media CRUD | K2 | Authorized Livewire actions, ownership, cycles and publication tests |
| TT-06 | Departure scheduling/capacity/staff | K3 | UTC/local date windows, limits and last-seat contention |
| TT-07 | Adult/child/infant and seasonal/group/early-bird pricing | K3 | Exact-money examples and deterministic precedence |
| TT-08 | Promotions/deposits/instalments | K3/K5 | Scope, limits, rounding, atomic redemptions and schedule totals |
| TT-09 | Search/filter/detail/SEO and Aureon UI | K4 | Public-only scopes, canonical/schema, responsive and keyboard QA |
| TT-10 | Custom/private inquiries and WhatsApp | K4 | Consent, throttling, publication, safe links and assignment |
| TT-11 | Holds/checkout/hybrid confirmation/signed access | K5 | Ownership, replay, stale quote, oversell, expiry and signatures |
| TT-12 | Customer/traveler identity and snapshots | K5 | Verified matching, encryption, serialization and private media |
| TT-13 | Payment/refund/history integrity | K5/K6 | Currency, duplicate operation, amount and lifecycle tests |
| TT-14 | Dashboard/POB/register/shift operations | K6 | Permissions, ownership, open-shift uniqueness and reconciliation |
| TT-15 | Institutional PDF and receipt adapters | K6/K7 | Portrait/landscape, 58/80 mm, privacy and print-theme parity |
| TT-16 | Dedicated events/notifications/audit | K7 | After-commit, recipients, retries, delivery and Mailpit |
| TT-17 | Client content, fixtures, adoption and hosting | K8 | Opt-in demo, approved copy, asset delivery and full regression |

## 4. Delivery Gates

| Phase | Work and dependencies | Exit evidence | Status |
| --- | --- | --- | --- |
| K0 | Clean source import; private repo; metadata, config/env, branding, README/deployment; independent homepage shell | Published baseline, secret scan, application boot and build parity | Complete and published |
| K1 | Provider/config, permissions, enums, DTO/contracts, all models/migrations/factories | Fresh/rollback/disabled-module tests; field/relationship/privacy review | Complete at foundation scope; later operational hardening is assigned to its owning phase in the K1 reconciliation |
| K2 | Category, destination, tour, itinerary, content, FAQ, extras, media administration | Real CRUD Forms, action authorization, publication and validation tests | Ready; execution contract documented |
| K3 | Departures, rate plans, participant prices, rules, promotions, availability and quotes | Exact-integer tests and real-database contention proof | Planned |
| K4 | Homepage integration, discovery, filters, tour details, maps/galleries and inquiries | Public-only data, SEO, accessible/responsive/theme evidence | Public foundation accepted; advanced filters, maps/galleries, and complete inquiry UX remain |
| K5 | Holds, checkout, customer/traveler capture, lifecycle, manual payments and signed pages | Transaction, replay, concurrency, privacy and confirmation tests | Planned |
| K6 | Booking administration, overview, POB, registers, shifts, receipt/reconciliation | End-to-end ownership, cash, printing and operational tests | Planned |
| K7 | Reports, institutional adapters, event-specific mail, schedules and audit | Queue/delivery/document parity and privacy evidence | Planned |
| K8 | Kenfam editorial approval, opt-in fixtures, comprehensive release hardening | Regression, migration, browser, accessibility and deployment checklist | Planned |

Draft pages and incomplete services do not constitute K2-K7 delivery. Each phase
gets its own implementation note, focused commit, evidence and master status
update. A skeleton method returning a placeholder is not an implemented contract.

## 5. Active Delivery Sequence

1. K2 catalog administration and publication.
2. K3 departure scheduling and deterministic pricing administration.
3. K4 advanced discovery, galleries, maps, SEO, and inquiry operations.
4. K5 checkout, travelers, booking lifecycle, and payment capture.
5. K6 booking administration, booking desk, registers, shifts, and receipts.
6. K7 notifications, documents, reports, activity, and scheduled operations.
7. K8 approved content, regression, accessibility, deployment, and adoption.

Each phase completes its useful vertical slice and its directly applicable
hardening. Cross-phase concerns are retained as explicit acceptance criteria,
not pulled into the current branch as unfinished infrastructure.

## 6. Engineering Definition Of Done

PHP uses strict types, file-purpose documentation, class contracts and useful
method PHPDoc including shapes, invariants and meaningful failures. Migration
comments cover identities, domain fields, actors and timestamps. Modifiers go
before constrained(); indexes have short explicit names and rollback follows
reverse FK dependency. Models have safe assignment, strict casts, explicit
relationships, privacy rules, scopes and real factories.

Services receive immutable DTOs and own transactions. Browser/Livewire state is
untrusted; hidden IDs, totals, participant prices and signatures do not replace
authorization. Events occur after commit. Logs exclude identity numbers, health
notes, session secrets, signed URLs and raw payment payloads.

Tests and autoload probes use disposable databases. A syntax check is not a
schema or concurrency test. SQLite cannot prove MySQL row-lock behavior.
Do not fresh-migrate or reseed an adopter's database for verification.

## 7. Documentation Ownership

| Document | Purpose |
| --- | --- |
| travel-tours-module-architecture.md | Boundaries, foldering, provider, integration, auth, assets and operations |
| travel-tours-data-model.md | Field dictionaries, identities, constraints, relations and lifecycle |
| travel-tours-domain-workflows.md | State machines, calculations and lock ordering |
| travel-tours-service-contracts.md | DTO/service signatures, failures and side effects |
| travel-tours-module-diagrams.md | Dependency, entity, sequence and state diagrams |
| travel-tours-verification-plan.md | Focused tests and phase/release gates |
| travel-tours-foundation-audit.md | Factual initial findings, disposition and remaining risk |
| travel-tours-foundation-implementation.md | Implemented schema, model, factory and service evidence |
| travel-tours-implementation-ledger.md | Changes, commands, results and remaining gaps |
| travel-tours-k1-reconciliation.md | Reset rationale, proportional delivery rules and concern ownership |
| travel-tours-k2-catalog-administration-plan.md | Next-phase files, workflows, boundaries, increments and acceptance matrix |
| ../Kenfam/ | Client provenance, content, assets, public architecture and deployment |

Client decisions requiring explicit confirmation: brand assets, child/infant age
bands, pending approval timeout, booking/cancellation/refund terms, official
receipt identity and operational mail recipients. Safe defaults are documented
as defaults, not as client-approved commercial policy.
