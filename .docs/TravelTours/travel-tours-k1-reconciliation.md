# TravelTours K1 Reconciliation And Delivery Boundary

Status: accepted reconciliation, 2026-09-16.

## 1. Repository State

The independent Kenfam repository is published. `main` retains the imported
Aureon baseline and `feature/travel-tours-foundation` tracks the published
foundation commit `ecdc00c`. Git publication is closed and is not part of this
implementation increment.

## 2. Why The Uncommitted Closeout Was Discarded

The discarded tree attempted to complete K1C-01 through K1C-09 in one large,
uncommitted change. It mixed namespace relocation, an expanded permission
catalogue, configuration redesign, document projections, event delivery,
activity logging, lifecycle/refund/traveler/shift services, receipt-printer
abstractions, and a custom MariaDB contention harness.

That work was reset because it was not a reviewable foundation increment and
it changed contracts for feature surfaces that do not yet provide their basic
user workflows. In particular, the contention harness became a large K1 gate
before public checkout or booking-desk interfaces existed. Some cases proved
row-lock blocking rather than two complete domain requests resolving a race.
Keeping those claims would have created more maintenance than confidence.

The reset does **not** remove production security requirements. The existing
transactions, row locks, foreign keys, idempotency keys, signed URLs, encrypted
attributes, module isolation, and authorization middleware remain. Deferred
acceptance checks move to the phase that owns the complete behavior, where
they can verify real requests rather than unfinished foundations.

## 3. Prioritized Delivery Principle

Every phase must first deliver its usable vertical slice, then harden that
slice before it is accepted. A future concern may shape the current interface,
schema, or extension point, but it must not pull an unimplemented later module
into the current branch.

The practical order is:

1. complete catalog administration so staff can maintain the public offering;
2. complete departures and pricing so published tours can be sold accurately;
3. complete public discovery and inquiry experience;
4. complete checkout and booking before proving real booking races;
5. complete booking administration and desk operations before printer, drawer,
   and reconciliation acceptance;
6. complete notifications, reports, activity, and scheduled operations as one
   observable operational layer;
7. finish content, browser, accessibility, deployment, and adoption QA.

## 4. K1 Closeout Scope

K1 is the module and persistence foundation. Its accepted deliverables are the
provider/config boundary, 34 documented tables and models, factories, access
and opt-in demonstration seeders, enums, DTOs/contracts, foundational search,
quote, availability, booking, customer, payment, document, inquiry services,
public storefront foundation, disabled-module isolation, and baseline tests.

Two exposed-runtime corrections close K1:

- signed booking HTML and PDF responses carry private/no-store, noindex, and
  nosniff headers;
- `PrintsBookingReceipts` is no longer bound to a logger that could falsely
  imply physical or browser print delivery.

Purpose-specific document DTOs remain required before K7 document acceptance.
The present signed page deliberately renders booking snapshots and excludes
identity numbers, medical/accessibility data, provider metadata, operation
keys, and consent internals.

## 5. Phase Ownership

| Concern | Owning phase | Required acceptance evidence |
| --- | --- | --- |
| Catalog permissions, scoped queries, publication actions | K2 | Livewire action authorization, ULID tampering, hierarchy/publication tests |
| Departure/rate/rule/promotion configuration | K3 | Ownership, overlap, exact-money, effective-window tests |
| Advanced public discovery, maps, galleries, inquiries | K4 | Responsive browser, keyboard, SEO, publication tests |
| Quote expiry, holds, checkout, travelers, booking lifecycle/payments | K5 | Server repricing, signed access, idempotency and two-service capacity races |
| Registers, shifts, refunds, receipts, physical/browser printing | K6 | Operator ownership, reconciliation, print result, payment/refund races |
| Projection DTOs, PDFs, events, recipients, mail, activity and schedules | K7 | Privacy, queue, Mailpit, audit, document and command evidence |
| Approved content, deployment and full-system QA | K8 | Browser/accessibility/theme/deployment/adoption evidence |

## 6. Cross-Engineer Boundary

Each phase uses its own branch and implementation record. Engineers may use
models and contracts from earlier phases, but may not mark another phase
complete, redesign its public contract, or add placeholder UI and folders for
parity. Shared schema changes require an additive migration and a synchronized
update to the data-model and architecture documents. Security findings that
affect an already exposed route are fixed immediately; findings that depend on
an unbuilt workflow become explicit acceptance criteria for its owning phase.

The next branch is K2 catalog administration. K3 scheduling/pricing and K5
checkout must remain separate even where K2 views show read-only summaries.
