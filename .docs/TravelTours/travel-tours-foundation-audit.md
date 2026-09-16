# TravelTours Foundation Audit

Reviewed 2026-09-13 against the approved revised plan and the inherited Property
Booking architecture, data-model and foundation implementation documents.
This audit supersedes any implication that the initial draft completed K1-K7.

## Findings And Required Disposition

| Finding | Evidence in the draft | Required correction |
| --- | --- | --- |
| No independent baseline commit | Unborn main, all files untracked | Completed: exact tracked Aureon subtree committed as `f4139b7`; draft remains separate |
| Insufficient specification depth | Seven module documents total approximately 13 KB | Replace summaries with requirements, field dictionaries, workflows, contracts and acceptance gates |
| Premature runtime surfaces | Controllers, routes and payment/booking services without TravelTours tests | Do not treat them as accepted implementations; gate writes until rebuilt and verified |
| Missing factories | Database/Factories has no concrete factories | Supply coherent factory graphs and per-model persistence tests |
| Foreign-key comments are misplaced | Later migrations call comment after constrained | Move modifiers to the column; test Blueprint metadata, not just source text |
| Timestamp comments absent | Migrations call timestamps() without documented columns | Explicit documented created_at/updated_at columns |
| Relationship coverage absent | No TravelTours relationship tests; compact model methods obscure review | Add explicit keys and persistence tests. Installed Eloquent infers belongsTo keys from method names, so customer() correctly uses customer_id |
| Sensitive fields serialize decrypted | Encrypted casts without hidden entries | Hide sensitive attributes and use purpose-limited document/view DTOs |
| PricingRule has wrong ownership | Scheduling/Models/PricingRule.php | Move to Pricing and update references as one scoped change |
| Identity matching unsafe | Customer service may select first row without identity predicates | Reject identity-less updates and separate anonymous booking contact from verified profile ownership |
| Lock ordering inconsistent | Holds and bookings acquire departure/hold in different order | One documented lock order and real database contention tests |
| Incomplete booking idempotency | Hold reuse, session ownership and client price snapshots not fully protected | Server repricing, unique operation key and atomic consumed-hold ownership |
| Retention relies on policies | Global admin Gate shortcut can bypass delete denial | Enforce retention in domain/model paths as well as permissions |
| Module directly reads Kenfam config | Generic module layouts use client-specific configuration | Use institution/brand adapters; keep client details in the host |
| Nonexistent middleware alias | Inquiry route uses honeypot alias not registered by host | Inspect installed package middleware and register actual class/alias before exposing route |
| Default seeder creates demo operators | Travel access seeder combines roles and people | Separate access catalogue from opt-in demonstration data |
| Notification/report adapters incomplete | Nested queued dispatch; raw models to documents | Dedicated event delivery and institutional, privacy-limited adapters |

## Resolution Checkpoint: 2026-09-14

Resolved in the K1 rewrite: complete planning set, exact independent baseline,
34 module factories, Blueprint-level column comments, explicit timestamp
comments, relationship/cast tests, hidden encrypted fields, Pricing-owned
`PricingRule`, conflict-safe customer matching, a departure-first hold/booking
lock order, booking/hold/payment idempotency, full commercial snapshots, and
direct use of Spatie's installed `ProtectAgainstSpam` middleware class.

The access seeder now creates capabilities and roles only. Optional local
operator identities live in `TravelToursDemoOperatorSeeder` and are not called
by `DatabaseSeeder`. Public inquiries now pass a typed DTO to an idempotent
service rather than writing a guarded model from a controller.

Still open: model-level retention enforcement, production-engine contention,
complete lifecycle/refund/register/shift services, recipient resolution and
delivery evidence, privacy-limited report projections, and alternate-brand
verification. Early controllers and views remain explicitly unaccepted shells.

## Reconciliation Checkpoint: 2026-09-16

The open items above are no longer treated as one K1 prerequisite bundle.
`travel-tours-k1-reconciliation.md` assigns each to the phase containing its
real workflow: K5 checkout, K6 booking desk, K7 communications/documents/audit,
and K8 adoption. Existing transactional, idempotency, encryption, signed-route,
and module-isolation safeguards remain in force. K1 closes at foundation scope;
K2 catalog administration is the next active phase.

## Baseline And Preservation

Source repository: `CodeWith-PeterBull/Custom-Templates-Builds`.
Source SHA: `c7aaddbb40e85dd1796576ce350c8a4a97af0189`.
Imported subtree: `laravel-aureon`; independent root commit: `f4139b7`.
The index-only import did not overwrite working files. The draft now lives on
`feature/travel-tours-foundation`; the original source worktree was not changed.
`aureon-upstream` has its push URL disabled. Remote publication is separately
verified in the ledger; a configured origin alone is not proof of publication.

## Acceptance Discipline

No syntax-only pass proves schema correctness, authorization, money accuracy or
concurrency. No draft file is counted as a delivered feature. Each correction
must record focused tests, an isolated autoload probe where useful and remaining
gaps. Never run migrate:fresh against an adopter's database to obtain evidence.

The original audit hypothesis that belongsTo(TourBooking::class) inside booking()
would infer tour_booking_id was disproved by the installed HasRelationships
implementation. It uses the relationship method name. This was a coverage and
readability gap, not evidence of a broken foreign key.
