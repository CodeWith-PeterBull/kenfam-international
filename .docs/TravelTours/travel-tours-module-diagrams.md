# TravelTours Architecture And Workflow Diagrams

Status: target design, not deployed evidence. Diagrams use Mermaid; accompanying
text remains readable without a renderer. The data dictionary is authoritative
for exact fields and FK behavior.

## 1. Host And Module Dependency Direction

```mermaid
flowchart TD
    Client[Kenfam application and editorial homepage] --> Host[Aureon host contracts]
    Travel[TravelTours provider] --> Host
    Travel --> Catalog[Catalog and destination management]
    Travel --> Pricing[Pricing and promotions]
    Travel --> Scheduling[Departures and holds]
    Travel --> Booking[Bookings and customers]
    Travel --> Desk[Point of booking]
    Travel --> Reporting[Reports and notifications]
    Host --> Auth[Auth, 2FA, roles and profile]
    Host --> Institution[Institution details and brand]
    Host --> Infra[Activity, queue, media and PDF adapters]
    References[Commerce and PropertyBooking source references]
    References -. disabled, no runtime dependency .-> Client
```

There is no arrow from TravelTours to a reference module. Client-specific content
flows into institution/branding adapters, not into generic module configuration.

## 2. Catalog, Scheduling And Price Relations

```mermaid
erDiagram
    TOUR_CATEGORY ||--o{ TOUR_CATEGORY : parent
    DESTINATION ||--o{ DESTINATION : parent
    TOUR ||--o{ TOUR_CATEGORY_ASSIGNMENT : classifies
    TOUR_CATEGORY ||--o{ TOUR_CATEGORY_ASSIGNMENT : assigned
    TOUR ||--o{ TOUR_DESTINATION_ASSIGNMENT : visits
    DESTINATION ||--o{ TOUR_DESTINATION_ASSIGNMENT : assigned
    TOUR ||--o{ ITINERARY_DAY : describes
    ITINERARY_DAY ||--o{ ITINERARY_ACTIVITY : orders
    TOUR ||--o{ TOUR_CONTENT_ITEM : explains
    TOUR ||--o{ TOUR_FAQ : answers
    TOUR ||--o{ TOUR_EXTRA : offers
    TOUR ||--o{ TOUR_RATE_PLAN : prices
    TOUR_RATE_PLAN ||--o{ PARTICIPANT_RATE : classifies
    TOUR_RATE_PLAN ||--o{ PRICING_RULE : adjusts
    TOUR ||--o{ TOUR_DEPARTURE : schedules
    TOUR_DEPARTURE ||--o{ DEPARTURE_STAFF_ASSIGNMENT : staffs
    TOUR_DEPARTURE ||--o{ AVAILABILITY_HOLD : reserves
    TOUR_DEPARTURE o|--o{ PRICING_RULE : scopes
    PROMOTION ||--o{ PROMOTION_TOUR_ASSIGNMENT : scopes
    TOUR ||--o{ PROMOTION_TOUR_ASSIGNMENT : eligible
```

Optional same-tour relationships are validated by services; individual foreign
keys alone cannot prove that a departure's selected rate belongs to its tour.

## 3. Booking, Customer And Financial Relations

```mermaid
erDiagram
    USER o|--o| TRAVEL_CUSTOMER : authenticated_owner
    TRAVEL_CUSTOMER o|--o{ TRAVELER : saves
    TRAVEL_CUSTOMER ||--o{ TOUR_BOOKING : requests
    TOUR_DEPARTURE ||--o{ TOUR_BOOKING : commits
    AVAILABILITY_HOLD o|--o| TOUR_BOOKING : consumed_once
    TOUR_BOOKING ||--|{ BOOKING_PARTICIPANT : snapshots
    TRAVELER o|--o{ BOOKING_PARTICIPANT : source
    TOUR_BOOKING ||--o{ BOOKING_EXTRA : purchases
    BOOKING_PARTICIPANT o|--o{ BOOKING_EXTRA : receives
    TOUR_BOOKING ||--|{ BOOKING_PRICE_LINE : calculates
    TOUR_BOOKING ||--|{ BOOKING_STATUS_HISTORY : records
    TOUR_BOOKING ||--o{ PAYMENT_SCHEDULE : schedules
    TOUR_BOOKING ||--o{ BOOKING_PAYMENT : receives
    PAYMENT_SCHEDULE o|--o{ BOOKING_PAYMENT : allocates
    BOOKING_PAYMENT o|--o{ BOOKING_REFUND : refunds
    TOUR_BOOKING ||--o{ BOOKING_REFUND : retains
    PROMOTION ||--o{ PROMOTION_REDEMPTION : consumes
    TOUR_BOOKING ||--o| PROMOTION_REDEMPTION : applies
```

Commercial snapshots are independent of later catalog/profile edits. Optional
source links never imply snapshots may be erased.

## 4. Desk And Inquiry Relations

```mermaid
erDiagram
    BOOKING_REGISTER ||--o{ BOOKING_SHIFT : hosts
    USER ||--o{ BOOKING_SHIFT : operates
    BOOKING_SHIFT o|--o{ TOUR_BOOKING : places
    BOOKING_SHIFT o|--o{ BOOKING_PAYMENT : receives
    BOOKING_SHIFT ||--o{ BOOKING_SHIFT_MOVEMENT : reconciles
    BOOKING_PAYMENT o|--o| BOOKING_SHIFT_MOVEMENT : cash_receipt
    BOOKING_REFUND o|--o| BOOKING_SHIFT_MOVEMENT : cash_refund
    TOUR o|--o{ TOUR_INQUIRY : concerns
    TOUR_DEPARTURE o|--o{ TOUR_INQUIRY : requests
    TRAVEL_CUSTOMER o|--o{ TOUR_INQUIRY : contacts
    TOUR_INQUIRY ||--o{ TOUR_INQUIRY_ACTIVITY : records
    TOUR_BOOKING o|--o{ TOUR_INQUIRY : converted_to
```

One open shift per register/operator uses database guards and service locking.
An inquiry is capacity-neutral until the booking transaction succeeds.

## 5. Placement Sequence And Failure Boundary

```mermaid
sequenceDiagram
    actor Guest
    participant UI as Livewire Form
    participant Service as Booking service
    participant DB as Database transaction
    participant Quote as Quote engine
    participant Queue as After-commit delivery
    Guest->>UI: Submit participant/contact data and owned hold
    UI->>Service: Validated DTO, actor context, operation key
    Service->>DB: Lock departure then hold; check retry/owner/expiry
    Service->>Quote: Recalculate from current server data
    Quote-->>Service: Lines, totals, currency and version
    Service->>DB: Recheck capacity and promotion under locks
    alt Valid commitment
        Service->>DB: Booking, participants, prices, history, schedule, redemption
        Service->>DB: Consume hold and commit
        Service->>Queue: Dedicated booking event
        Service-->>UI: Signed confirmation result
    else Invalid or persistence failure
        Service->>DB: Roll back all changes
        Service-->>UI: Typed recoverable failure
    end
```

No notification is sent before commit. A browser total or valid fingerprint
does not bypass recalculation, authorization or capacity checking.

## 6. Independent Lifecycle States

```mermaid
stateDiagram-v2
    [*] --> pending: approval placement
    [*] --> confirmed: instant placement
    pending --> confirmed: authorized approval
    pending --> expired: deadline and locked recheck
    pending --> cancelled: reject/cancel with reason
    confirmed --> cancelled: accepted cancellation
    confirmed --> completed: operational completion
```

Payment state is separate: unpaid -> partial -> paid; processed refunds produce
partially_refunded/refunded based on net received. Neither payment confirmation
nor refund silently changes the booking lifecycle.

## 7. Dependency-Ordered Delivery

```mermaid
flowchart LR
    K0[Client baseline] --> K1[Schema and model foundation]
    K1 --> K2[Catalog administration]
    K2 --> K3[Scheduling and prices]
    K3 --> K4[Public discovery]
    K3 --> K5[Holds and checkout]
    K4 --> K5
    K5 --> K6[Operations and POB]
    K6 --> K7[Documents and dedicated notifications]
    K7 --> K8[Client content and release verification]
```

Services are developed before their UI actions. Financial/concurrency tests are
gates, not a later cosmetic QA phase.
