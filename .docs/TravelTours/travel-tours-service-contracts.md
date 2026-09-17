# TravelTours Service And DTO Implementation Contract

Status: design contract, not a claim that draft implementations satisfy it.
Implement only after the schema/model foundation gate. Read the workflow
document for algorithms and locks; this document assigns responsibility.

## 1. Common Input/Output Rules

Use final readonly DTOs with typed properties, enums and documented collection
shapes. Use named fields, not positional opaque arrays. Normalize request input
in Forms/requests; services repeat domain and ownership validation. Inputs never
accept generated IDs, actor IDs, lifecycle state, aggregate totals or unrestricted
metadata. ActorContext contains the authenticated user or an explicitly scoped
anonymous owner token. OperationKey identifies a retry, not authorization.

MoneyData carries minor amount, ISO currency and exponent; reject mismatched
currencies and overflow. ParticipantMix carries nonnegative counts and explicit
seat policy. TravelerInput carries names, DOB and purpose-limited sensitive data;
never accept a client-allocated price. Clock inputs make dates/pricing testable.

Results are immutable data, not raw models serialized into public UI. Services
may return an aggregate internally, but presentation adapters project it into
safe DTOs. Expected failures use typed exceptions and stable reason codes.

## 2. Core Public Contracts

| Contract | Input | Output | Invariant |
| --- | --- | --- | --- |
| SearchesTours | TourSearchData with bounded filters/sort/page | Paginated public cards | Publication and related destination scope |
| CalculatesTourQuotes | TourQuoteRequest, actor and quote instant | TourQuote with lines/version/fingerprint/expiry | Deterministic integer calculation, no persistence |
| ChecksDepartureAvailability | Departure ID, party, optional owned hold, instant | AvailabilityData | Counts current commitments; never bypasses placement lock |
| PlacesTourBookings | PlaceTourBookingData + ActorContext + operation key | BookingPlacementResult | Atomic snapshots, capacity, redemption and hold consumption |
| ProcessesBookingPayments | Record/ConfirmPaymentData + actor | PaymentResult | Currency, ownership, retry and confirmed-total integrity |
| RendersBookingDocuments | Authorized document request + orientation | RenderedPdf/HTTP response through host adapter | Privacy and institutional layout parity |
| PrintsBookingReceipts | ReceiptData + validated PrinterSettings | PrintInstruction/PrintResult | No fake success or implicit silent printing |

Do not register a contract binding until its concrete implementation passes
focused tests. Container resolvability alone is not behavioral acceptance.

## 3. Implementation Inventory

| Service | Methods / DTO families | Transaction and side effects |
| --- | --- | --- |
| TourCategoryService | Implemented K2B: create/update/setActive using TourCategoryData | Transactional hierarchy locks; cycles, inactive parents, active children, and published-tour dependencies rejected; activity belongs to K7 |
| DestinationService | Implemented K2B: create/update/setActive using DestinationData | Draft/review metadata only; validates levels, geography, timezone, coordinates, cycles, active parents, and deactivation dependencies; publication belongs to K2E |
| CatalogMediaService | Implemented K2B: destination cover replace/remove; gallery add/reorder/remove; metadata update | Owned media only, accessible alt text, configured MIME/size/count limits, deterministic gallery order; tour media follows in K2E |
| TourService | Implemented K2C/K2D: create draft/update basics using TourData | Transactional identity, participant, coordinate, uniqueness, actor and state checks; duration cannot contract below existing itinerary |
| TourAssignmentService | Implemented K2C: replace categories/destinations using TourAssignmentData | Validates active targets, one primary category, unique ordered route, and no client pivot IDs before transactional replacement |
| TourPublicationService | Planned K2E: preview/publish/unpublish/archive | Separate capability and readiness workflow; not exposed by K2C |
| TourItineraryService | Implemented K2D: save/reorder/remove days and activities | Typed DTOs; same-tour/day ownership, consecutive order, duration, active destination, local-time and coordinate checks |
| TourContentService | Implemented K2D: save/remove/reorder content, FAQs and extras | Typed DTOs; tour ownership, controlled content types, deterministic order, integer minor units, ISO currency and soft-deleted extra retention |
| TourMediaService | attach/reorder/replace/remove | Authorized MediaInput; limits, collections, conversion evidence |
| DepartureService | schedule/update/open/close/cancel/assign staff | DepartureData; UTC conversion, capacity and impacted bookings |
| RatePlanService | save/default/deactivate, participant rate changes | RatePlanData/ParticipantRateData; age/window overlap validation |
| PricingRuleService | save/deactivate | PricingRuleData; stage/unit/stacking/scope validation |
| PromotionService | save/validate/redeem/release where policy permits | PromotionData; lock limits and unique redemption |
| TourQuoteCalculator | quote and allocate rounding | Pure deterministic output; no emails, database writes or models to browser |
| DepartureAvailabilityService | inspect/assert availability | Read projection plus transaction-aware locked check |
| AvailabilityHoldService | create/replace/release/expire | HoldRequest; owner binding, departure-first lock, retry/expiry |
| TravelCustomerService | create/update verified profile, resolve booking contact | CustomerData; never blind email overwrite |
| TravelerService | create/update/archive and controlled document access | TravelerData; encryption, ownership, audited downloads |
| TourBookingService | place | PlacementData; single commitment transaction |
| BookingLifecycleService | approve/reject/cancel/expire/complete | TransitionData; history, capacity and after-commit events |
| BookingPaymentService | record/confirm/fail/reverse where allowed | PaymentData; same booking/schedule/shift, idempotency |
| PaymentScheduleService | create/allocate/adjust schedule | ScheduleData; sum and rounding preservation |
| BookingRefundService | request/process/fail | RefundData; no over-refund, compensating movement |
| BookingNumberService | allocate channel reference | Unique collision-safe number; never max(id)+1 |
| BookingAccessService | issue/validate recipient-scoped signed access | Expiry/version/privacy; no profile mutation entitlement |
| InquiryService | submit/assign/note/transition/convert | InquiryData; consent, ownership and conversion transaction |
| BookingRegisterService | create/update/deactivate | RegisterData; driver/options allowlist |
| BookingShiftService | open/close/reconcile, cash-in/out | ShiftData; operator/register guard and cash ledger |
| BookingDocumentDataFactory | customer summary/manifest/receipt projection | Immutable snapshots; sensitive fields only for authorized purpose |
| BookingDocumentService | render/stream/download | Host RendersPdfReports and ReportContext, no bespoke competing renderer |
| TravelOperationsService | overview/trends/queues/reports | Authorized aggregate queries, no hard-coded demo counts |
| TravelRecipientResolver | resolve per-event customer/operator recipients | Preferences, scope, disabled flags, no generic broadcast list |
| NotificationDispatcher | enqueue dedicated notification | After commit and durable retry/delivery identity |

## 4. Host Adapters: Verified Interface Shape

- App\Contracts\ResolvesInstitutionProfile::current(): InstitutionProfileData;
  forget(): void. Resolve once for a document/email render, not from client config.
- App\Contracts\RendersPdfReports::render(view, data, ReportContext): RenderedPdf;
  stream/download return Symfony Response. Preserve the existing orientation
  and layout/template data contract.
- App\Contracts\RecordsSystemActivity::record(activityType, description, actor,
  subject, properties, severity, source, batchUuid): SystemActivity. Properties
  are an allowlisted safe projection, not DTO->toArray() of private input.

## 5. Error Taxonomy

InvalidCatalogStructure, InvalidDepartureWindow, InvalidRateConfiguration,
PromotionNotApplicable, DepartureUnavailable, HoldExpired, HoldOwnershipMismatch,
QuoteChanged, DuplicateOperationConflict, InvalidBookingTransition,
PaymentCurrencyMismatch, PaymentAmountExceeded, RefundAmountExceeded,
BookingShiftClosed and HistoricalRecordDeletionDenied are domain failure
categories. Final class names follow the owning context's Exceptions folder.
Do not swallow QueryException into a success response. Retry known deadlocks at
the transaction boundary, not partially completed side effects.

## 6. Service Delivery Order And Evidence

1. Catalog CRUD/media with ownership/publication tests. K2B completes category
   and destination metadata/media. K2C completes tour basics and ordered route
   assignment. K2D adds tour children; K2E completes media and publication.
2. Scheduling/rate validation plus pure exact-money calculation fixtures.
3. Holds and booking placement with real concurrent-connection tests.
4. Identity/lifecycle/payment/refund and signed privacy projections.
5. Shift/cash/printing operations and reconciliation.
6. Events, reports, operational queues and delivery verification.

Each implementation note names its DTOs, methods, policies, schema dependencies,
failure branches, transaction/lock order, events, tests and known limitations.
Documented signatures are target contracts; do not present incomplete draft
service methods as accepted implementations.
