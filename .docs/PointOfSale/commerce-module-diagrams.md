# Commerce Module Structural Diagrams

**Status:** Review draft. Diagrams describe the proposed version-one design.

## 1. System Context

```mermaid
flowchart LR
    Shopper[Guest or signed-in shopper]
    Cashier[Cashier]
    Manager[Commerce manager]

    Storefront[Public ecommerce storefront]
    POS[POS terminal]
    Admin[Commerce administration]
    Core[Shared commerce core]
    Existing[Aureon platform services]
    DB[(Laravel database)]

    Shopper --> Storefront
    Cashier --> POS
    Manager --> Admin

    Storefront --> Core
    POS --> Core
    Admin --> Core

    Core --> DB
    Core --> Existing

    Existing --- Identity[Users, roles, permissions]
    Existing --- Institution[Institutional details and brand media]
    Existing --- Activity[System activity recorder]
    Existing --- Comms[Mail notifications and PDF reports]
```

The three user surfaces share one transactional core. Neither storefront nor
POS maintains an independent product, price, stock, customer, or sales record.

## 1.1 Plugin-style Package Boundary

```mermaid
flowchart TB
    Host[Laravel Aureon host application]
    Provider[CommerceServiceProvider]

    subgraph Module[app/Modules/Commerce]
        Routes[Routes: admin, pos, storefront]
        Contexts[Bounded contexts and PHP classes]
        Database[Database: migrations, factories, seeders]
        Resources[Resources: views and frontend assets]
        Config[Config: commerce.php]
    end

    Host --> Provider
    Provider --> Routes
    Provider --> Database
    Provider --> Resources
    Provider --> Config
    Routes --> Contexts
```

## 2. Module Boundaries

```mermaid
flowchart TB
    subgraph Catalog
        Categories[ProductCategory]
        Products[Product]
        ProductService[ProductService]
    end

    subgraph Inventory
        Stocks[Stock]
        Movements[StockMovement]
        InventoryService[InventoryService]
    end

    subgraph Ordering
        Customers[Customer]
        Orders[Order and OrderItem]
        Payments[Payment]
        Calculator[CartCalculator]
        OrderService[OrderService]
        PaymentService[PaymentService]
    end

    subgraph PointOfSale
        Registers[Register]
        Tills[TillSession]
        TillService[TillService]
        PosCheckout[PosCheckoutService]
    end

    subgraph Surfaces
        WebUI[Storefront Livewire components]
        PosUI[POS Livewire terminal]
        AdminUI[Admin Livewire managers]
    end

    ProductService --> Categories
    ProductService --> Products
    InventoryService --> Stocks
    InventoryService --> Movements
    Calculator --> Products
    OrderService --> Calculator
    OrderService --> Customers
    OrderService --> Orders
    OrderService --> InventoryService
    PaymentService --> Payments
    PaymentService --> Orders
    TillService --> Registers
    TillService --> Tills
    PosCheckout --> OrderService
    PosCheckout --> PaymentService
    PosCheckout --> TillService

    WebUI --> ProductService
    WebUI --> OrderService
    AdminUI --> ProductService
    AdminUI --> InventoryService
    AdminUI --> OrderService
    AdminUI --> PaymentService
    PosUI --> PosCheckout
```

## 3. Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o| CUSTOMERS : links
    USERS ||--o{ PRODUCTS : manages
    USERS ||--o{ TILL_SESSIONS : operates
    USERS ||--o{ ORDERS : cashes
    USERS ||--o{ PAYMENTS : records
    USERS ||--o{ STOCK_MOVEMENTS : creates

    PRODUCT_CATEGORIES o|--o{ PRODUCT_CATEGORIES : contains
    PRODUCT_CATEGORIES o|--o{ PRODUCTS : classifies
    PRODUCTS ||--|| STOCKS : has
    PRODUCTS ||--o{ ORDER_ITEMS : snapshots
    PRODUCTS ||--o{ STOCK_MOVEMENTS : changes

    CUSTOMERS o|--o{ ORDERS : places
    REGISTERS ||--o{ TILL_SESSIONS : opens
    REGISTERS o|--o{ ORDERS : processes
    TILL_SESSIONS o|--o{ ORDERS : contains
    TILL_SESSIONS o|--o{ PAYMENTS : receives

    ORDERS ||--|{ ORDER_ITEMS : contains
    ORDERS ||--o{ PAYMENTS : settles
    ORDERS o|--o{ STOCK_MOVEMENTS : references

    PRODUCT_CATEGORIES {
        bigint id PK
        string ulid UK
        bigint parent_id FK
        string name
        string slug UK
        boolean is_active
    }

    PRODUCTS {
        bigint id PK
        string ulid UK
        bigint category_id FK
        string name
        string slug UK
        string sku UK
        string barcode UK
        string status
        bigint price_minor
        bigint sale_price_minor
        boolean track_stock
    }

    STOCKS {
        bigint id PK
        bigint product_id FK,UK
        bigint on_hand
        bigint low_stock_threshold
    }

    CUSTOMERS {
        bigint id PK
        string ulid UK
        bigint user_id FK,UK
        string first_name
        string last_name
        string email
        string phone
    }

    REGISTERS {
        bigint id PK
        string ulid UK
        string name
        string code UK
        boolean is_active
    }

    TILL_SESSIONS {
        bigint id PK
        string ulid UK
        bigint register_id FK
        bigint opened_by FK
        string status
        bigint opening_float_minor
        bigint expected_cash_minor
        bigint counted_cash_minor
        bigint variance_minor
    }

    ORDERS {
        bigint id PK
        string ulid UK
        string order_number UK
        string channel
        string status
        string payment_status
        bigint customer_id FK
        bigint till_session_id FK
        bigint subtotal_minor
        bigint tax_minor
        bigint total_minor
    }

    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        string product_name
        string sku
        bigint quantity
        bigint unit_price_minor
        bigint line_total_minor
    }

    PAYMENTS {
        bigint id PK
        string ulid UK
        bigint order_id FK
        bigint till_session_id FK
        string method
        string status
        bigint amount_minor
        string reference
    }

    STOCK_MOVEMENTS {
        bigint id PK
        string ulid UK
        bigint product_id FK
        string type
        bigint quantity_delta
        bigint balance_before
        bigint balance_after
        string reference_type
        bigint reference_id
    }
```

Media Library owns product gallery rows outside this domain diagram.

## 4. Migration Dependency Order

```mermaid
flowchart LR
    Users[(existing users)]
    Categories[1. product_categories]
    Products[2. products]
    Stocks[3. stocks]
    Customers[4. customers]
    Registers[5. registers]
    Tills[6. till_sessions]
    Orders[7. orders]
    Items[8. order_items]
    Payments[9. payments]
    Movements[10. stock_movements]

    Categories --> Products
    Users --> Products
    Products --> Stocks
    Users --> Stocks
    Users --> Customers
    Registers --> Tills
    Users --> Tills
    Customers --> Orders
    Registers --> Orders
    Tills --> Orders
    Users --> Orders
    Orders --> Items
    Products --> Items
    Orders --> Payments
    Tills --> Payments
    Users --> Payments
    Products --> Movements
    Users --> Movements
```

## 5. Storefront Request and Component Structure

```mermaid
flowchart TB
    Routes[Public storefront routes]
    Layout[storefront.blade.php]
    Header[Header, search, categories, cart badge]
    Footer[Corporate footer]

    CatalogPage[Catalog page]
    ProductPage[Product detail page]
    CartPage[Cart page or drawer]
    CheckoutPage[Checkout page]
    ConfirmationPage[Confirmation page]
    TrackingPage[Tokenized tracking page]

    ProductIndex[Livewire ProductIndex]
    ProductShow[Livewire ProductShow]
    Cart[Livewire CartDrawer]
    Checkout[Livewire Checkout]
    Tracker[Livewire OrderTracker]

    Routes --> Layout
    Layout --> Header
    Layout --> Footer
    Layout --> CatalogPage
    Layout --> ProductPage
    Layout --> CartPage
    Layout --> CheckoutPage
    Layout --> ConfirmationPage
    Layout --> TrackingPage

    CatalogPage --> ProductIndex
    ProductPage --> ProductShow
    Header --> Cart
    CartPage --> Cart
    CheckoutPage --> Checkout
    TrackingPage --> Tracker
```

## 6. Web Order Placement Sequence

```mermaid
sequenceDiagram
    actor Shopper
    participant UI as Livewire Checkout
    participant Orders as OrderService
    participant Calc as CartCalculator
    participant DB as Database
    participant Stock as InventoryService
    participant Notify as Notification queue

    Shopper->>UI: Submit customer, fulfillment, payment preference
    UI->>UI: Validate typed form data
    UI->>Orders: placeWebOrder(cart IDs and quantities, form data)
    Orders->>DB: Begin transaction
    Orders->>DB: Re-fetch published products
    Orders->>Stock: Lock product stock rows
    Stock-->>Orders: Current available balances
    Orders->>Calc: Recalculate trusted prices and totals
    Calc-->>Orders: Line and order totals
    Orders->>DB: Create customer link and order snapshot
    Orders->>DB: Create immutable order items
    Orders->>Stock: Commit negative order movements
    Stock->>DB: Append movements and update balances
    Orders->>DB: Commit transaction
    Orders->>Notify: Dispatch confirmation after commit
    Orders-->>UI: Order number, ULID, and signed confirmation URL
    UI-->>Shopper: Show confirmation and tracking action
```

Any stock or validation failure rolls back the entire transaction and leaves the
cart available for correction.

## 7. POS Checkout Sequence

```mermaid
sequenceDiagram
    actor Cashier
    participant POS as Livewire POS Terminal
    participant Till as TillService
    participant Checkout as PosCheckoutService
    participant Orders as OrderService
    participant Payments as PaymentService
    participant Stock as InventoryService
    participant DB as Database

    Cashier->>POS: Scan SKU/barcode and adjust cart
    POS->>POS: Re-fetch products and preview totals
    Cashier->>POS: Submit one or more tenders
    POS->>Checkout: complete(till, cart, customer, tenders)
    Checkout->>Till: Assert open session owned or permitted
    Checkout->>DB: Begin transaction
    Checkout->>Orders: Create completed POS order and item snapshots
    Orders->>Stock: Lock and decrement stock
    Stock->>DB: Append order movements
    Checkout->>Payments: Record completed tenders
    Payments->>DB: Update aggregate payment and till cash totals
    Checkout->>DB: Commit transaction
    Checkout-->>POS: Receipt snapshot and cash change
    POS-->>Cashier: Render printable receipt
```

## 8. Order State Model

```mermaid
stateDiagram-v2
    [*] --> Held: POS hold
    [*] --> Pending: Web order placed
    [*] --> Completed: POS checkout

    Held --> Completed: Resume, reprice, pay
    Held --> Cancelled: Discard hold

    Pending --> Confirmed: Staff confirms order/payment
    Pending --> Cancelled: Eligible cancellation
    Confirmed --> Processing: Fulfillment starts
    Confirmed --> Cancelled: Eligible cancellation
    Processing --> Ready: Prepared for pickup/delivery
    Ready --> Completed: Handed over or delivered

    Cancelled --> [*]
    Completed --> [*]
```

Rules:

- stock is not committed for `held` POS orders;
- stock is committed when a web order enters `pending`;
- stock is committed in the same transaction that creates a completed POS sale;
- eligible web cancellation restores stock once;
- completed POS sales require a future return/refund module.

## 9. Till State and Cash Reconciliation

```mermaid
stateDiagram-v2
    [*] --> Open: Cashier opens register with float
    Open --> Open: Completed POS payments update expected cash
    Open --> Closed: Count cash and record variance
    Closed --> [*]
```

```mermaid
flowchart LR
    Float[Opening float]
    CashSales[Completed cash payments]
    Expected[Expected cash]
    Counted[Counted cash]
    Variance[Variance]

    Float --> Expected
    CashSales --> Expected
    Expected --> Variance
    Counted --> Variance
```

Version one does not include pay-in/pay-out cash movements. If that workflow is
approved later, add a dedicated append-only `till_cash_movements` table and
include it in expected cash rather than overloading payments.

## 10. Authorization Boundary

```mermaid
flowchart LR
    Public[Public visitor]
    Staff[Authenticated staff]
    Gate[Spatie permission gates]

    Public --> Catalog[Catalog and product detail]
    Public --> Cart[Session cart]
    Public --> Checkout[Validated checkout]
    Public --> Token[Tokenized confirmation/tracking]

    Staff --> Gate
    Gate --> ProductAdmin[Product/category administration]
    Gate --> InventoryAdmin[Inventory and movements]
    Gate --> OrderAdmin[Order and payment administration]
    Gate --> PosTerminal[POS terminal]
    Gate --> TillAdmin[Till management]
```

Sequential model IDs must never form the public authorization boundary for
confirmation or tracking.

## 11. Implementation Dependency Diagram

```mermaid
flowchart LR
    Base[Current Aureon base engine]
    Phase1[Phase 1: catalog and stock]
    Phase2[Phase 2: storefront and web orders]
    Phase3[Phase 3: POS and tills]
    Phase4[Phase 4: documents, QA, adoption]

    Base --> Phase1 --> Phase2 --> Phase3 --> Phase4
```

Each phase must end with focused tests, full regression, Pint, migration/seed
verification, route inspection, production asset build, responsive browser QA,
and module documentation before the next phase begins.
