# Property Booking Module Diagrams

**Status:** Foundation and Phase 2 catalog/availability paths are implemented; later-phase paths remain the visual contract.

## 1. System Context

```mermaid
flowchart LR
    Guest[Guest browser]
    Receptionist[Receptionist]
    Manager[Property manager]
    Admin[System administrator]

    Storefront[Public stay storefront]
    POB[Point of Booking]
    Backoffice[Accommodation administration]

    Module[PropertyBooking module]
    Identity[Aureon users, roles and 2FA]
    Institution[Institution profile and brand]
    Activity[System activity]
    Communications[Mail, queues and PDF reports]
    Media[Spatie media storage]
    Database[(Application database)]

    Guest --> Storefront
    Receptionist --> POB
    Manager --> Backoffice
    Admin --> Backoffice

    Storefront --> Module
    POB --> Module
    Backoffice --> Module

    Module --> Identity
    Module --> Institution
    Module --> Activity
    Module --> Communications
    Module --> Media
    Module --> Database
```

## 2. Bounded Contexts

```mermaid
flowchart TB
    Provider[PropertyBookingServiceProvider]

    Catalog[Catalog<br/>properties, amenities, unit types, units]
    Pricing[Pricing<br/>rate plans, overrides, quotes]
    Availability[Availability<br/>search, blocks, unit allocation]
    Guests[Guests<br/>identity and contact]
    Bookings[Bookings<br/>stays, charges, payments, lifecycle]
    POB[PointOfBooking<br/>registers, shifts, terminal, receipts]
    Storefront[Storefront<br/>search, selection, checkout, signed access]
    Reporting[Reporting<br/>dashboard and exports]
    Notifications[Notifications<br/>events, listeners, mail]

    Host[Host contracts<br/>institution, activity, PDF, users]

    Provider --> Catalog
    Provider --> Pricing
    Provider --> Availability
    Provider --> Guests
    Provider --> Bookings
    Provider --> POB
    Provider --> Storefront
    Provider --> Reporting
    Provider --> Notifications

    Pricing --> Catalog
    Availability --> Catalog
    Availability --> Pricing
    Bookings --> Availability
    Bookings --> Pricing
    Bookings --> Guests
    POB --> Bookings
    Storefront --> Bookings
    Reporting --> Bookings
    Notifications --> Bookings

    Catalog --> Host
    Bookings --> Host
    POB --> Host
    Notifications --> Host
```

Arrows are dependency direction. Catalog does not depend on Bookings, and no
context depends on Commerce.

## 3. Core Entity Relationships

```mermaid
erDiagram
    PROPERTY_CATEGORY ||--o{ PROPERTY : classifies
    PROPERTY ||--o{ PROPERTY_USER : scopes
    USER ||--o{ PROPERTY_USER : assigned

    PROPERTY ||--o{ PROPERTY_AMENITY : has
    AMENITY ||--o{ PROPERTY_AMENITY : describes

    PROPERTY ||--o{ UNIT_TYPE : offers
    UNIT_TYPE ||--o{ UNIT_TYPE_AMENITY : has
    AMENITY ||--o{ UNIT_TYPE_AMENITY : describes
    UNIT_TYPE ||--o{ ACCOMMODATION_UNIT : instantiates
    UNIT_TYPE ||--o{ RATE_PLAN : prices
    RATE_PLAN ||--o{ RATE_OVERRIDE : overrides
    ACCOMMODATION_UNIT ||--o{ AVAILABILITY_BLOCK : blocks

    PROPERTY ||--o{ RECEPTION_REGISTER : owns
    RECEPTION_REGISTER ||--o{ RECEPTION_SHIFT : opens
    USER ||--o{ RECEPTION_SHIFT : operates

    PROPERTY ||--o{ BOOKING : receives
    GUEST ||--o{ BOOKING : primary_guest
    RECEPTION_SHIFT o|--o{ BOOKING : creates
    BOOKING ||--|{ BOOKING_STAY : contains
    UNIT_TYPE o|--o{ BOOKING_STAY : snapshots
    RATE_PLAN o|--o{ BOOKING_STAY : snapshots
    BOOKING_STAY ||--o{ UNIT_ASSIGNMENT : allocated
    ACCOMMODATION_UNIT ||--o{ UNIT_ASSIGNMENT : consumed

    BOOKING ||--o{ BOOKING_GUEST : includes
    GUEST ||--o{ BOOKING_GUEST : occupies
    BOOKING_STAY o|--o{ BOOKING_GUEST : optionally_scopes

    BOOKING ||--o{ BOOKING_CHARGE : adds
    BOOKING_STAY o|--o{ BOOKING_CHARGE : optionally_scopes
    BOOKING ||--o{ BOOKING_PAYMENT : settles
    RECEPTION_SHIFT o|--o{ BOOKING_PAYMENT : receives
```

## 4. Availability Transaction

```mermaid
sequenceDiagram
    actor Surface as Storefront or POB
    participant Checkout as Checkout service
    participant DB as Database transaction
    participant Rate as Rate calculator
    participant Allocator as Unit allocation service
    participant Activity as System activity
    participant Events as After-commit events

    Surface->>Checkout: placement DTO
    Checkout->>DB: begin transaction
    DB->>DB: lock property and unit types in ID order
    Checkout->>Rate: calculate from locked rate data
    Rate-->>Checkout: immutable calculation
    Checkout->>Allocator: allocate interval and occupancy
    Allocator->>DB: load and lock candidate units
    Allocator->>DB: reject overlapping assignments and blocks

    alt enough eligible units
        Allocator-->>Checkout: deterministic assignments
        Checkout->>DB: write booking, stays, assignments, totals
        Checkout->>DB: write payments when POB
        Checkout->>Activity: record privacy-safe activity
        Checkout->>DB: commit
        DB-->>Events: dispatch after commit
        Checkout-->>Surface: refreshed booking and signed/receipt actions
    else no availability or stale rate
        Allocator-->>Checkout: domain exception
        Checkout->>DB: rollback
        Checkout-->>Surface: safe conflict feedback
    end
```

## 5. Booking And Stay States

```mermaid
stateDiagram-v2
    [*] --> Held
    Held --> Pending: place awaiting confirmation
    Held --> Confirmed: confirm directly
    Held --> Expired: hold timeout
    Held --> Cancelled: discard

    Pending --> Confirmed: confirm
    Pending --> Expired: pending timeout
    Pending --> Cancelled: cancel

    Confirmed --> Cancelled: cancel before check-in
    Confirmed --> NoShow: authorized no-show
    Confirmed --> Completed: checkout completes booking

    Expired --> [*]
    Cancelled --> [*]
    NoShow --> [*]
    Completed --> [*]
```

```mermaid
stateDiagram-v2
    [*] --> Expected
    Expected --> CheckedIn: validated arrival
    Expected --> NoShow: no-show action
    CheckedIn --> CheckedOut: validated departure
    CheckedOut --> [*]
    NoShow --> [*]
```

Payment state is calculated separately and therefore is not drawn as a booking
transition.

## 6. Unit Readiness And Availability

```mermaid
flowchart LR
    Future[Future interval search]
    Assignment[Active overlapping assignment?]
    Block[Active overlapping block?]
    Active[Unit active?]
    Capacity[Unit type capacity fits?]
    Available[Interval available]
    Unavailable[Unavailable]

    Future --> Assignment
    Assignment -- yes --> Unavailable
    Assignment -- no --> Block
    Block -- yes --> Unavailable
    Block -- no --> Active
    Active -- no --> Unavailable
    Active -- yes --> Capacity
    Capacity -- no --> Unavailable
    Capacity -- yes --> Available
```

For immediate check-in, `ready` is an additional requirement. Dirty or cleaning
does not by itself erase a non-overlapping future reservation.

## 7. POB Shift And Payment Flow

```mermaid
flowchart TB
    Login[Authenticated receptionist]
    Access{POB permission and property assignment?}
    Shift{Own open reception shift?}
    Open[Manager opens shift<br/>register + float]
    Search[Search interval and occupancy]
    Select[Select guest, unit type, rate]
    Hold[Optional hold]
    Pay[Split tenders and server validation]
    Commit[Atomic booking + payment commit]
    Receipt[Receipt print instruction]
    Close[Count cash and close shift]
    Variance{Variance threshold crossed?}
    Alert[Dedicated variance notification]

    Login --> Access
    Access -- no --> Denied[403]
    Access -- yes --> Shift
    Shift -- no --> Open
    Open --> Search
    Shift -- yes --> Search
    Search --> Select
    Select --> Hold
    Hold --> Pay
    Select --> Pay
    Pay --> Commit
    Commit --> Receipt
    Receipt --> Search
    Search --> Close
    Close --> Variance
    Variance -- yes --> Alert
    Variance -- no --> Done[Closed]
```

## 8. Runtime Module Toggle

```mermaid
flowchart TD
    Boot[Application boot]
    Merge[Merge property-booking config]
    Enabled{property-booking.enabled?}
    Bind[Bind module contracts]
    Register[Register policies, Livewire, listeners,<br/>migrations, views and routes]
    Hidden[Do not register runtime resources]
    Menu{Config enabled and route exists?}
    Show[Render authorized navigation]
    Omit[Omit navigation]

    Boot --> Merge
    Merge --> Bind
    Bind --> Enabled
    Enabled -- yes --> Register
    Enabled -- no --> Hidden
    Register --> Menu
    Hidden --> Omit
    Menu -- yes --> Show
    Menu -- no --> Omit
```

Disabling is runtime unregistration, not destructive schema or media removal.

## 9. Future Tenancy Boundary

```mermaid
flowchart LR
    Shared[Property Booking catalog<br/>Property, Unit Type, Unit, Guest]
    ShortStay[Bookings<br/>interval stay, POB, receipts]
    Tenancy[Future Tenancies context<br/>lease, rent schedule, deposit, notices]

    Shared --> ShortStay
    Shared --> Tenancy
    ShortStay -. no status or ledger reuse .-> Tenancy
```
