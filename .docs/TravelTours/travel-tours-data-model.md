# Travel & Tours Data Model And Migration Contract

Status: implemented K1 persistence dictionary, 2026-09-14. Seven ordered module
migrations now create all 34 documented model tables and 645 commented columns.
SQLite execution and metadata tests verify that implementation; production-
engine DDL and contention remain separate acceptance gates. Future wishlist,
review, and portal features deliberately have no tables.

## 1. Common Persistence Rules

All tables use travel_ prefixes. Integer unsigned bigint primary keys and
foreign keys remain internal. Externally addressable records receive a unique,
immutable 26-character ULID. Pure assignment rows may use internal IDs only;
existing tour/destination/staff assignment ULIDs are retained for explicit
editing. PromotionTourAssignment remains an internal pivot without ULID.

Every column, including id, ulid, timestamps, deletion and actor fields, has a
meaningful migration comment. Use explicit created_at and updated_at timestamp
columns; modifiers, including comment and nullable, precede constrained().
Indexes and foreign-key names must fit MySQL's 64-character limit. Prefer short
explicit names. Test actual Blueprint metadata and MySQL DDL, not regex alone.

Money: signed/unsigned bigint minor units according to economic meaning;
currency char(3), validated ISO code, exponent snapshotted on a booking/shift.
Percentages: integer basis points (10000 = 100%). Never float casts for money.
Dates: date for local calendar concepts; immutable UTC datetime for instants.
Coordinates: signed decimal(10,7), service validated latitude/longitude ranges.
JSON: explicitly shaped values, not an unvalidated extension payload.
Text marked sensitive is encrypted and hidden from serialization.

All models require table, safe assignment, complete primitive/enum/date/JSON
casts, explicit foreign keys, inverse relationships, typed relation PHPDoc,
scopes appropriate to their lifecycle, and a concrete factory. Factory graphs
must not create unrelated parents for a single booking's tour/departure/rate.
Global mass assignment of status, totals, actors or identifiers is prohibited.

Soft deletion is catalog/profile archival, not a substitute for financial
retention. Bookings, prices, payments, refunds, cash movements and histories
have no ordinary delete workflow. Historical FKs use restrict or nullable links
with immutable snapshots; cascades are limited to true disposable catalog
children/assignments. Domain/model deletion guards supplement policies.

## 2. Model Registry

All rows below include id and documented created_at/updated_at unless noted.

| # | Context / Model | Table | Public ULID | Soft delete |
| --- | --- | --- | --- | --- |
| 1 | Catalog/TourCategory | travel_tour_categories | Yes | No; deactivate |
| 2 | Catalog/Destination | travel_destinations | Yes | Yes |
| 3 | Catalog/Tour | travel_tours | Yes | Yes |
| 4 | Catalog/TourCategoryAssignment | travel_tour_category | Yes | No |
| 5 | Catalog/TourDestinationAssignment | travel_tour_destination | Yes | No |
| 6 | Catalog/ItineraryDay | travel_itinerary_days | Yes | No |
| 7 | Catalog/ItineraryActivity | travel_itinerary_activities | Yes | No |
| 8 | Catalog/TourContentItem | travel_tour_content_items | Yes | No |
| 9 | Catalog/TourFaq | travel_tour_faqs | Yes | No |
| 10 | Catalog/TourExtra | travel_tour_extras | Yes | Deactivate |
| 11 | Scheduling/TourDeparture | travel_tour_departures | Yes | Yes |
| 12 | Scheduling/DepartureStaffAssignment | travel_departure_staff | Yes | No |
| 13 | Scheduling/AvailabilityHold | travel_availability_holds | Yes | No |
| 14 | Pricing/TourRatePlan | travel_tour_rate_plans | Yes | Deactivate |
| 15 | Pricing/ParticipantRate | travel_participant_rates | Yes | Deactivate |
| 16 | Pricing/PricingRule | travel_pricing_rules | Yes | Deactivate |
| 17 | Pricing/Promotion | travel_promotions | Yes | Deactivate |
| 18 | Pricing/PromotionTourAssignment | travel_promotion_tour | No | No |
| 19 | Pricing/PromotionRedemption | travel_promotion_redemptions | Yes | No |
| 20 | Customers/TravelCustomer | travel_customers | Yes | Yes |
| 21 | Customers/Traveler | travel_travelers | Yes | Yes |
| 22 | Bookings/TourBooking | travel_tour_bookings | Yes | No |
| 23 | Bookings/BookingParticipant | travel_booking_participants | Yes | No |
| 24 | Bookings/BookingExtra | travel_booking_extras | Yes | No |
| 25 | Bookings/BookingPriceLine | travel_booking_price_lines | Yes | No |
| 26 | Bookings/BookingStatusHistory | travel_booking_status_history | Yes | No |
| 27 | Bookings/PaymentSchedule | travel_payment_schedules | Yes | No |
| 28 | Bookings/BookingPayment | travel_booking_payments | Yes | No |
| 29 | Bookings/BookingRefund | travel_booking_refunds | Yes | No |
| 30 | PointOfBooking/BookingRegister | travel_booking_registers | Yes | Deactivate |
| 31 | PointOfBooking/BookingShift | travel_booking_shifts | Yes | No |
| 32 | PointOfBooking/BookingShiftMovement | travel_booking_shift_movements | Yes | No |
| 33 | Inquiries/TourInquiry | travel_tour_inquiries | Yes | No |
| 34 | Inquiries/TourInquiryActivity | travel_tour_inquiry_activities | Yes | No |

## 3. Catalog Dictionary

### 3.1 TourCategory

Fields: nullable parent_id; name varchar(160); unique slug varchar(180);
nullable description text, icon_key varchar(80), meta_title varchar(160),
meta_description varchar(320); sort_order unsigned int default 0;
is_active boolean default true; ULID and common timestamps.
Parent deletion nulls the relationship. Navigation index
(parent_id,is_active,sort_order). Relationships: parent, children, tours through
custom assignment pivot. Reject self-parent and all indirect cycles under
serialized hierarchy edits. Inactive ancestry is excluded from public filters.
Media: category_image, one approved image. Icon is a library key, not markup.

### 3.2 Destination

Fields: nullable parent_id, created_by, updated_by; type varchar(24) enum;
nullable country_code char(2), unique code varchar(40); name/unique slug
varchar(180); short_description varchar(320), description longText nullable;
latitude/longitude decimal(10,7), timezone varchar(64) nullable;
is_featured false, is_active true; status draft; sort_order;
meta_title/meta_description, published_at nullable; soft-delete timestamp.
Indexes: hierarchy(parent_id,type,is_active), publication(status,published_at),
country/featured. Parent/actor deletion nulls links.
Relations: parent/children, assigned tours, itinerary start/end days, actors.
Country/type consistency, coordinate pairs and IANA timezone are validated.
Media: destination_cover (single), destination_gallery (ordered).

### 3.3 Tour

Identity: unique code varchar(40), unique slug varchar(180), name varchar(200),
type and status enums; nullable created_by/updated_by.
Editorial: tagline(240), short_description(360), description longText;
duration_days positive smallint, duration_nights nonnegative smallint,
minimum_age, difficulty enum, minimum_participants >=1, nullable maximum;
languages JSON array of validated locale codes.
Booking: mode instant/approval; meeting_point_name(200), meeting_point_details,
meeting_latitude/longitude; end_point_name/details and target end coordinates;
terms longText, cancellation_summary text, policy version; publication flags,
sort_order, SEO fields, published_at and deleted_at.
Indexes: publication, type/status, featured/status/order, duration/party.
Relations: creator/updater; categories/destinations; itinerary/content/FAQs/extras;
rates/departures/bookings/inquiries/promotions. Historical tour FK is restricted.
Media: tour_cover, tour_gallery and approved public tour_documents.
Public scope requires published status, nondeleted row and eligible publication
time. Publishing validates required content, destination and accessible cover.
Selling additionally requires an eligible dated departure/rate; published does
not imply currently bookable. Editing a tour never rewrites booking snapshots.

### 3.4 TourCategoryAssignment

Fields: tour_id/category_id, is_primary false, sort_order; unique pair.
Catalog-parent deletion cascades assignment only. One primary per tour is a
service invariant under tour lock; changing it clears the previous primary.
Custom pivot declares incrementing id and explicit keys.

### 3.5 TourDestinationAssignment

Fields: tour_id/destination_id, role varchar(24) (start/visit/overnight/end),
sequence unsigned smallint, is_overnight boolean. Index tour/sequence.
A tour may visit one destination repeatedly; final unique key must allow
different visit sequences rather than banning repeat visits by role.
Relations resolve tour and destination explicitly. Tour deletion may cascade;
historical itinerary snapshots remain in bookings.

### 3.6 ItineraryDay

Fields: tour_id; day_number positive smallint; title(200), narrative longText;
meals JSON array, accommodation text; nullable start_destination_id and
end_destination_id; sort_order. Unique tour/day_number; ordered relation.
Destination deletion nulls optional references; tour deletion cascades.
Validate day within tour duration and renumber atomically without collisions.
Media: itinerary_images with caption/alt metadata.

### 3.7 ItineraryActivity

Fields: itinerary_day_id; sequence; optional local starts_at/ends_at time;
title(200), description text, location_name, latitude/longitude;
is_included true, is_optional false; common identities/timestamps.
Unique day/sequence; day deletion cascades. Times are itinerary-local labels,
not substitute UTC departure timestamps. Overnight time ranges require an
explicit day relationship; reject impossible same-day end/start assumptions.

### 3.8 TourContentItem

Fields: tour_id, item_type enum (highlight/inclusion/exclusion/requirement/
packing_note), content text, sort_order. Index tour/type/order.
Ordered child of tour; sanitized public content. No private internal notes here.

### 3.9 TourFaq

Fields: tour_id, question varchar(500), answer text, sort_order, is_active.
Index tour/active/order. Tour owns deletion. Public FAQ structured data may
include only visible active answers; HTML sanitization is a service concern.

### 3.10 TourExtra

Fields: tour_id, code(80), name(160), description text nullable;
pricing_unit enum (booking/participant), amount_minor, currency char(3);
participant_types JSON allowlist; minimum_quantity and optional maximum_quantity;
is_active, is_public, sort_order. Unique tour/code, active/public index.
Relations: tour and booking extras; deactivate referenced extras instead of
rewriting history. An extra from another tour/currency cannot be purchased.
Quantities and participant association are revalidated at placement.

## 4. Scheduling Dictionary

### 4.1 TourDeparture

Fields: tour_id restricted; rate_plan_id nullable; unique code(80);
timezone(64), starts_at/ends_at UTC; optional booking_mode override,
booking_opens_at/closes_at; capacity >=1; minimum_participants >=1;
waitlist_enabled false; status enum; meeting_instructions, private
operational_notes; actors and soft-delete timestamp.
Indexes: tour/start, status/start, tour/window. Parallel groups on the same date
are supported; code is the unique operational identity. Availability derives
from locked active holds and consuming bookings. If later cached, reconcile and
never allow the cache alone to approve placement.
Relations: tour/rate, staff, holds, rules, bookings/inquiries, actors.
Rate plan must belong to tour; end after start; window closes by departure;
minimum <= capacity. Capacity reductions cannot invalidate existing commitments.

### 4.2 DepartureStaffAssignment

Fields: departure_id, user_id, role(80), is_lead, notes, assigned_by.
Unique departure/user/role; departure cascade, users restricted or assigner
nullable. No new authentication role is implied by guide/driver assignment.
Relations: departure, user, assigner. Assignment alone does not grant financial
or sensitive traveler permission.

### 4.3 AvailabilityHold

Fields: departure_id; optional customer_id; hashed session/owner correlation
(no raw session secret); optional email_hash; adult_count/child_count/infant_count,
seat_count; currency, quoted_total_minor, quote_fingerprint(64),
quote_snapshot JSON; status active/consumed/expired/released;
expires_at, consumed_at. Target adds released_at and release_reason.
Indexes: departure/status/expiry; owner/status; unique operation key for retries.
Relations: departure/customer and at most one consumed booking.
The quote snapshot is validated server output, not a browser payload. Hash
binding is authorization context, not a public credential. Expired rows do not
consume inventory even if cleanup has not yet run.

## 5. Pricing Dictionary

### 5.1 TourRatePlan

Fields: tour_id; code(60), name(160), description; currency;
tax_inclusive boolean, tax_rate_basis_points; deposit_type
none/fixed/percentage and deposit_value; balance_due_days;
is_refundable, validated booking_restrictions JSON, is_active, is_public, is_default;
minimum_participants/maximum_participants; display_order.
Unique tour/code; tour/active/public index. One default per tour/currency under
tour lock. Relations: tour, participant rates, pricing rules, departures/bookings.
No ambiguous public fallback across currencies. Deposit cannot exceed payable.

### 5.2 ParticipantRate

Fields: rate_plan_id; participant_type enum; minimum_age/maximum_age nullable;
amount_minor nonnegative; optional tax_inclusive override;
active_from/active_until dates, is_active. Index plan/type/active.
Rate service rejects overlapping active windows/age bands. Age is calculated
on local departure date, not booking date; sales-date validity uses the explicit
quote instant in the agreed timezone. Null boundaries are unbounded.
No silently selecting the first of multiple eligible rates.

### 5.3 PricingRule

Fields: rate_plan_id; departure_id optional and belonging to same tour;
name(160), rule_type seasonal/group/early_bird; travel_starts_on/ends_on dates;
sales_start_at/end_at UTC; minimum_participants/minimum_advance_days;
adjustment_type fixed/percentage/override; signed adjustment_value;
priority, is_stackable, is_active; conditions JSON allowlisted shape.
Indexes: plan/active/priority, departure/active. Relations: plan/departure and
historical price lines. Explicit rule stage and order resolve precedence.
Discount cannot make payable negative; override is never interpreted as discount.

### 5.4 Promotion

Fields: normalized unique code(80), name(160), description;
adjustment_type fixed/percentage, nonnegative adjustment_value;
currency required for fixed/value-threshold promotions; valid_from/until UTC;
minimum_booking_minor, minimum_participants; nullable maximum_uses and
maximum_uses_per_customer; applies_to_all_tours, is_active; conditions JSON.
Indexes: active/validity. Relations: assignments/tours/redemptions/bookings.
Exclusion wins over inclusion. Currency mismatch/invalid code fails explicitly.
Per-customer limits cannot rely on unverified arbitrary email ownership.

### 5.5 PromotionTourAssignment

Internal pivot: promotion_id, tour_id, is_exclusion; unique pair; documented
timestamps. No ULID or public endpoint. Cascades only this eligibility link.

### 5.6 PromotionRedemption

Fields: promotion_id, booking_id, customer_id, amount_applied_minor, redeemed_at.
Unique promotion/booking; index promotion/customer. Restricted history.
Target adds currency and nullable released_at/release_reason if cancellations
release usage; initial safe rule is that cancelled use remains consumed unless
explicitly reversed by the promotion service. No silent counter decrement.
Declare booking_id explicitly for maintainability; Eloquent also correctly
infers booking_id from a relationship method named booking().

## 6. Customer And Traveler Dictionary

### 6.1 TravelCustomer

Fields: optional user_id; title(30), first/middle/last_name(100);
normalized email(254), phone(32), whatsapp_phone(32);
email_hash/phone_hash(64), date_of_birth, nationality_code;
address_line_1/2(190), city/region(120), postal_code(30), country_code;
email_consent/sms_consent/whatsapp_consent false, consent_recorded_at;
status enum, private notes, creator/updater, deleted_at.
Indexes: keyed contact lookup, surname/given name, status/created. Nullable
user_id is unique; verified-contact timestamps and hash-key version are stored.
Contact hashes are not unique by default: shared family contacts are legitimate.
No anonymous email match may overwrite or attach a verified account.
Relations: user, travelers, bookings, inquiries, holds, redemptions, actors.
Operational notifications are distinct from optional marketing consent.

### 6.2 Traveler

Fields: optional customer_id; title/names; date_of_birth, participant_type,
nationality_code; email/phone; encrypted identity_number and private keyed
identity_number_hash; identity_type; passport_issuing_country/expiry_date;
encrypted dietary_requirements/accessibility_requirements/medical_notes;
emergency_contact_name/phone/relationship; is_active; deleted_at.
Indexes: customer/active, names and private identity hash.
Relations: customer and booking participant snapshots. Reusing a traveler
requires authenticated ownership or authorized staff scope. Do not infer age
solely from an old participant_type. Private media: profile and travel_documents.
All sensitive fields and hash values are hidden from model serialization.

## 7. Booking And Money Dictionary

### 7.1 TourBooking

Identity/ownership: ULID; unique booking_number and operation_key;
tour_id, departure_id, customer_id restricted; availability_hold_id unique
nullable; rate_plan_id/promotion_id/register_id/shift_id/agent_id nullable;
created_by/updated_by nullable. Each relation uses its explicit FK.
States: channel web/booking_desk/admin/agent; confirmation_mode instant/approval;
status; independent payment_status; preferred_payment_method nullable.
Counts: adult/child/infant, seat_count; participant total is validated by service.
Money: currency and snapshotted currency_exponent; subtotal_minor,
extras_total_minor, discount_total_minor, tax_total_minor, total_minor,
deposit_required_minor, paid_minor and refunded_minor with consistent
derived balance. Total/paid fields are service-maintained, not request inputs.
Immutable snapshots: customer name/email/phone; tour name/code;
departure timezone/start/end; cancellation terms, accepted terms/version;
pricing_snapshot with engine version, inputs, adjustments, tax/rounding and
quote fingerprint; rate/deposit and meeting-point snapshots.
Operations: encrypted special_requests, safe attribution JSON;
terms_accepted_at, placed_at, pending_expires_at, confirmed_at, cancelled_at,
completed_at, expired_at, cancellation reason and public access version.
Indexes: departure/status; customer/placed; status/payment/placed; shift/placed;
pending expiry for scheduler. No normal deletion. Parent edits do not change
snapshots. Customer contact changes require explicit audited workflow.

### 7.2 BookingParticipant

Fields: booking_id, optional traveler_id; sequence, is_lead, participant_type;
title/names, DOB, nationality, contact email/phone;
encrypted identity_number, identity_type, passport issuer/expiry;
encrypted dietary/accessibility/medical notes; emergency contact name/phone;
allocated_price_minor, age-at-departure, seat consumption and optional guardian
sequence. Index booking/type; unique booking/sequence; exactly one lead by service.
Counts must match booking and server quote. Prices are server allocated with
deterministic rounding, never accepted from a guest. Sensitive snapshots hidden.

### 7.3 BookingExtra

Fields: booking_id, optional tour_extra_id and booking_participant_id;
code_snapshot/name_snapshot/pricing_unit_snapshot; quantity positive;
currency, unit_amount_minor, discount_minor, tax_minor and total_minor.
Index booking/extra.
Participant, extra and booking must share the same booking/tour context.
Source deletion can null link, but cannot erase the commercial snapshot.

### 7.4 BookingPriceLine

Fields: booking_id; optional pricing_rule_id/promotion_id; line_type
fare/extra/discount/tax/fee/adjustment; description(220); quantity;
signed unit_amount_minor and total_minor; nonnegative discount_minor/tax_minor;
calculation_metadata allowlist; display_order.
Index booking/order. Immutable posted evidence; corrections use new approved
adjustments rather than rewriting. Sum and tax semantics must match quote.
No PII or arbitrary provider payloads in calculation_metadata.

### 7.5 BookingStatusHistory

Fields: booking_id; nullable previous_status; new_status; actor_id nullable;
source(40); reason; changed_at UTC; common timestamps.
Index booking/changed. Append-only, no ordinary update/delete. Initial placement
has null previous state. Reason required for rejection/cancellation/override.
Use safe rationale codes in activity logs rather than private free text.

### 7.6 PaymentSchedule

Fields: booking_id; instalment_number positive; due_date date;
expected_amount_minor, paid_amount_minor; status pending/partial/paid/overdue/
waived. Unique booking/number; status/due index.
All expected instalments sum to final payable under the accepted schedule.
Due date uses snapshotted departure timezone. Overdue is date/payment dependent,
not a reason to erase debt. Waivers require an authorized adjustment.

### 7.7 BookingPayment

Fields: booking_id; optional payment_schedule_id/shift_id; method enum;
provider(80), reference(120), transaction_identifier(160) nullable;
amount_minor positive, currency, status; paid_at, received_by;
safe_metadata shaped JSON. Unique operation_key and provider+transaction
uniqueness avoid globally assuming provider references cannot collide.
Indexes: booking/status, shift/method/status, reference.
Schedule/shift currency and ownership match booking. Pending is not revenue.
Manual confirmation is explicit; duplicate same operation is idempotent,
different payload under the same key is a conflict. Raw card data prohibited.

### 7.8 BookingRefund

Fields: booking_id; nullable payment_id; unique reference; amount_minor, currency;
reason, status pending/processed/failed/cancelled; processor;
transaction_identifier; operation_key; requested_by/requested_at; processed_by;
completed_at; safe_metadata; processor-scoped transaction uniqueness.
Index booking/status. Successful refunds cannot exceed confirmed unrefunded
payment amount. Keep failed attempts; retry must not double-refund cash.
Refund does not implicitly cancel or release booking capacity.

## 8. Booking Desk Dictionary

### 8.1 BookingRegister

Fields: unique code(60), name(160), optional location(190), is_active;
receipt_printer_driver browser default, receipt_paper_width_mm 80 default,
automatic_receipt_print false; receipt_printer_name; printer_options allowlisted
JSON; actors. No credentials in printer_options.
Relations: shifts/bookings and actors. Referenced register deactivates, not
deletes. Only 58/80 mm accepted initially; browser automation means print prompt.

### 8.2 BookingShift

Fields: register_id/operator_id restricted; status open/closed/reconciled;
currency/exponent; opening_float_minor, expected_cash_minor signed;
actual_cash_minor nullable nonnegative; variance_minor signed nullable;
opened_at, closed_at, reconciled_at, reconciled_by; closing/reconciliation notes.
Indexes: register/status, operator/opened. Nullable unique open-register and
open-operator guard keys are maintained/cleared transactionally with state.
Lock operator then register when opening; serialize uniqueness in DB as well.
Expected = opening float + cash receipts + cash-ins - cash refunds - cash-outs.
Non-cash payments do not affect drawer cash. Closed shifts reject new receipts.
Relations: register/operator/reconciler, bookings/payments/movements.

### 8.3 BookingShiftMovement

Fields: shift_id; optional booking_id/payment_id/refund_id; movement_type
opening_float/payment/cash_in/cash_out/refund; signed amount_minor;
currency; reason; actor_id; occurred_at.
Indexes: shift/occurred, type/occurred; unique operation_key and source
payment/refund movement uniqueness. Append-only corrections use compensating
movements. Cash-out/refund negative; cash-in/payment/float positive. Non-cash
payment references do not generate drawer movement.

## 9. Inquiry Dictionary

### 9.1 TourInquiry

Fields: unique reference; optional tour_id/departure_id/customer_id/
converted_booking_id; inquiry_type general/tour/private_tour/custom_tour;
contact_name(190), contact_email(254), contact_phone(32), whatsapp_preferred;
requested_destinations JSON; preferred_start_date/end_date; adult/child/infant
counts; budget_currency/budget_minor nullable; message; source;
assigned_to; status new/assigned/in_progress/awaiting_customer/converted/closed/
spam; follow_up_at/responded_at/closed_at; consent_ip/consent_recorded_at;
consent purpose/version and duplicate-submission operation key.
Indexes: status/follow-up, assignee/status, tour/created.
Private message/contact/consent IP hidden from broad serialization.
Departure must belong to selected published tour for public submission.
Conversion links one actual booking atomically; inquiry alone never reserves.

### 9.2 TourInquiryActivity

Fields: inquiry_id; activity_type note/call/email/whatsapp/assignment/
state_change/conversion; note; actor_id; occurred_at.
Index inquiry/occurred. Append-only operational timeline. Private note does not
belong in general activity logs or customer confirmation mail.
Historical inquiry deletion is prohibited in ordinary administration.

## 10. Migration Order And Constraints

Ordered groups: catalog -> pricing -> customers -> scheduling -> registers/shifts
-> bookings/payments/redemptions -> shift movements/inquiries. Rollback reverses
these groups and their intra-group FK order. The PricingRule class stays in
Pricing although its table is created after departures for FK dependency.

Database-enforced: primary/ULID/code/slug uniqueness, FK existence/deletion rules,
operation keys, consumed-hold uniqueness, source movement uniqueness and open
shift guard uniqueness. Service-enforced with locks: hierarchy cycles, one primary,
date/age ranges, currency consistency, same-parent relationships, rate overlaps,
capacity and ledger sums. SQLite tests cover structure but MySQL contention is
required before accepting capacity/shift/payment guarantees.

## 11. Implemented Schema Reconciliation

The K1 rewrite added operation keys to holds, bookings, payments, refunds,
movements, and inquiries; immutable booking policy, customer, tour, departure,
rate-plan, and pricing snapshots; contact verification/hash metadata; consumed-
hold uniqueness; open-shift register/operator guards; refund aggregates; and
promotion/cash retry evidence. Mutable reserved-seat counters were removed in
favor of locked calculations over capacity-consuming bookings and active holds.

The executable foundation test and metadata probe, rather than this dictionary,
are the migration evidence. Once any environment deploys these migrations,
future changes must use additive migrations and documented backfills. MySQL or
PostgreSQL verification is still required for row-lock contention, generated
index behavior, and production-engine constraint parity.
