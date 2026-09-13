# Contextual Commerce Demonstration Seeders Plan

**Status:** S0-S5 implemented; featured imagery complete, secondary views deferred

**Baseline branch:** `feature/laravel-aureon-base-engine`

**Baseline commit:** `1c24011`

**Planning branch:** `feature/commerce-context-seeder-plan`

**Created:** 2026-09-05

## 1. Purpose

This plan defines a controlled library of merchant-context demonstration
catalogs for Aureon Commerce. It supports sales demonstrations and adopter
evaluation without turning one electronics-oriented fixture into an implied
production catalog for every business.

The first release will provide ten contexts grounded in common Kenyan retail
operations. Each context will contain complete categories, products, prices,
stock, specifications, search identifiers, metadata, and generated imagery.
The implementation will remain a small extension of the existing seeder chain:
select one context, keep existing products by default, or explicitly archive
existing products before applying the selected context.

The approved implementation now extends the existing seeder chain and command
boundary without adding permissions or runtime administration architecture.
Named local media completes the deterministic source contract. Product-specific
featured images are complete; second product-specific gallery views remain a
deferred optional asset pass.

## 2. Factual Baseline

The plan extends the current module rather than creating a second fixture
system:

- `CommerceDemoSeeder` remains optional and is not called by the host
  `DatabaseSeeder`.
- `CatalogDemoSeeder` currently upserts six categories by slug and ten products
  by SKU.
- Every product is published, uses integer minor-unit pricing, receives a stock
  projection and one reconciled opening movement, and can own multiple Spatie
  Media Library images in `product_gallery`.
- Every category can own one `category_image` media item.
- Rerunning the current fixture repairs missing media and does not reset an
  existing stock balance.
- The shared product model supports one simple saleable SKU with descriptions,
  structured specifications, unit policy, regular and sale prices, tax policy,
  stock policy, physical dimensions, SEO metadata, and publication state.
- Product variants are not implemented (`COM-006`), stock is not location-aware
  (`COM-007` and `COM-008`), and batch/lot/expiry or variable-measure inventory
  is not implemented (`COM-028`). Context fixtures must not pretend otherwise.
- Existing demonstration orders, payments, stock movements, registers, and till
  sessions depend on the current catalog graph. A context switch therefore
  cannot blindly truncate Commerce tables.

## 3. Objectives And Boundaries

### 3.1 Objectives

1. Offer recognizable, coherent demonstration data for multiple merchant
   sectors while retaining one shared storefront/POS engine.
2. Keep every context deterministic, repeatable, idempotent, and testable using
   the current category, product, stock, movement, and media behavior.
3. Produce polished product and category imagery with deterministic local file
   names and alt-text-ready descriptions.
4. Make price, tax, stock, dimensions, and descriptions plausible as demo data,
   while clearly identifying them as illustrative rather than live market data.
5. Provide one clear CLI entry point that defaults to the current catalog but
   accepts an exact context and an explicit archive-existing flag.

### 3.2 Non-goals

- No tenant or multi-store isolation is introduced by a catalog context.
- No live supplier, market-price, medicine, tax, or regulatory feed is implied.
- No product variant, serial, lot, expiry, weighted-item, prescription, or
  fiscal-compliance feature is simulated in JSON as though it were operational.
- No seeder will delete completed orders, payment records, stock movements,
  till history, media, or products. The optional replacement behavior changes
  existing product publication state to archived.
- No run-history tables, ownership tables, queue jobs, orchestration services,
  dataset contracts, policies, permissions, or Livewire manager are required
  for the first implementation.
- The proposed dashboard selector is deferred. It must later call the same
  allowlisted seeder entry point rather than introduce a second catalog engine.

## 4. Context Registry

Context keys are immutable once released. Names may be refined, but an existing
key must continue resolving to the same merchant domain and dataset lineage.

| Context | Stable key | Products | Initial categories | Delivery wave | Notes |
| --- | --- | ---: | ---: | --- | --- |
| Current Aureon mixed catalog | `default-mixed` | 10 | 6 | A | Existing fixture retained as the default and compatibility reference. |
| Computers and IT | `computers-it` | 12 | 6 | A | Business computing, networking, power, storage, and peripherals. |
| Hardware and construction | `hardware-construction` | 12 | 7 | A | Packaged paint, cement, steel, roofing, timber, plumbing, electrical, and tools. |
| Boutique and fashion | `boutique-fashion` | 12 | 6 | A | Apparel, footwear, bags, and accessories without variant-level stock claims. |
| Pharmacy and health retail | `pharmacy-health` | 12 | 6 | A | OTC and first-aid demonstration only; see the mandatory safety boundary. |
| Supermarket and FMCG | `supermarket-fmcg` | 12 | 7 | B | Common packaged staples, household goods, and beverages. |
| Beauty and personal care | `beauty-personal-care` | 12 | 6 | B | Hair, skin, hygiene, fragrance, and cosmetic retail. |
| Automotive parts and accessories | `automotive-parts` | 12 | 6 | B | Common service parts, fluids, electrical items, tyres, and accessories. |
| Agrovet and farm supplies | `agrovet-farm` | 12 | 6 | B | Feed, seed, fertilizer, equipment, PPE, and non-medicinal farm care. |
| Office, bookshop, and stationery | `office-bookshop` | 12 | 6 | B | Paper, writing, filing, school, desk, and printing supplies. |

**Initial total:** 118 deterministic sample products across 10 contexts.

Additional contexts such as furniture/homeware, restaurant supplies, bakery,
mobile-only retail, wine and spirits, and industrial safety may be proposed
later. They should receive new stable keys and the same approval checklist; they
must not be folded into an existing context merely to increase product count.

## 5. Dataset Contract

Every context definition must provide the following data before implementation
is accepted:

| Area | Required definition |
| --- | --- |
| Identity | Stable context key, dataset version, product SKU, slug, internal barcode, and optional manufacturer barcode. |
| Classification | Stable category slug, display order, description, active state, and optional parent slug after the taxonomy strategy is approved. |
| Presentation | Name, short and long descriptions, grouped specifications, featured state, SEO title/description, image manifest, and image alt text. |
| Commerce | Unit label, minimum/maximum order quantities, regular price, optional sale price and window, cost price, and explicit tax treatment. |
| Inventory | Track-stock flag, opening quantity, low-stock threshold, and one ledger-backed opening movement. |
| Physical | Weight and packaged dimensions where meaningful; null values must be deliberate. |
| Provenance | Context key and source data file recorded in documentation; no new persistence table is required. |
| Validation | Unique identifiers, non-negative money, valid sale-price relationship, reconciled stock, available local media, and publication invariants. |

All money will use configured currency minor units. Proposed KES prices below
are fixture values for demonstrations, not quotations or assertions of current
Kenyan market prices. Tax treatment must be reviewed per product and adopter;
the current global default must not be assumed to be legally correct for every
context.

## 6. Product Manifests

The tables below are the approved first-pass product manifests. `Opening/low`
means opening stock units followed by the low-stock threshold. Final long copy,
cost price, dimensions, SEO metadata, barcodes, grouped specifications, and alt
text will be completed in each dataset definition during implementation.

### 6.1 Current Aureon Mixed Catalog (`default-mixed`)

This context preserves the existing names, identifiers, fixture prices, stock,
and local images. Its current categories are Computing, Mobile, Audio,
Wearables, Workspace, and Lifestyle.

| # | Product | Category | Unit | Demo KES | Opening/low | Primary image |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Aureon Air 14 | Computing | item | 129,900 | 24/5 | `aureon-air-14.png` |
| 2 | StudioBook Pro 16 | Computing | item | 189,500 | 16/4 | `studio-laptop.png` |
| 3 | Pulse X1 Smartphone | Mobile | item | 89,900 | 38/6 | `pulse-phone-front.png` |
| 4 | Halo S4 Smartwatch | Wearables | item | 24,900 | 44/8 | `halo-watch.png` |
| 5 | Arc ANC Headphones | Audio | item | 18,500 | 29/5 | `arc-headphones.png` |
| 6 | Echo Mini Speaker | Audio | item | 12,900 | 52/7 | `echo-speaker.png` |
| 7 | Canvas 27 Display | Workspace | item | 42,900 | 21/5 | `canvas-monitor.png` |
| 8 | Form Executive Chair | Workspace | item | 58,000 | 12/3 | `form-chair.png` |
| 9 | Stride Performance Trainer | Lifestyle | item | 14,800 | 34/6 | `stride-trainer.png` |
| 10 | Nomad Leather Slip-On | Lifestyle | item | 16,900 | 18/4 | `nomad-slip-on.png` |

### 6.2 Computers And IT (`computers-it`)

Categories: Laptops, Desktops, Displays, Input Devices, Storage and Power, and
Networking and Conferencing.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Horizon 14 Business Laptop | Laptops | item | 84,900 | 18/4 | 14-inch, 16 GB RAM, 512 GB SSD |
| 2 | Forge 15 Performance Laptop | Laptops | item | 149,900 | 10/3 | 15.6-inch, 32 GB RAM, 1 TB SSD |
| 3 | Compact Mini Desktop | Desktops | item | 58,500 | 14/3 | 16 GB RAM, 512 GB SSD, Wi-Fi 6 |
| 4 | Studio 24 All-in-One | Desktops | item | 79,900 | 9/2 | 23.8-inch FHD, 16 GB RAM |
| 5 | Canvas 24 IPS Monitor | Displays | item | 24,900 | 24/5 | 24-inch FHD IPS, HDMI/DP |
| 6 | TypePro Wireless Keyboard | Input Devices | item | 3,200 | 45/8 | Full-size, 2.4 GHz receiver |
| 7 | Point Wireless Mouse | Input Devices | item | 1,200 | 60/10 | Adjustable DPI, silent switches |
| 8 | DockLink USB-C Hub | Networking and Conferencing | item | 4,800 | 32/6 | HDMI, Ethernet, USB-A, PD pass-through |
| 9 | Vault 1 TB Portable SSD | Storage and Power | item | 12,900 | 20/4 | USB-C, 1 TB solid-state storage |
| 10 | Shield 650 VA UPS | Storage and Power | item | 8,500 | 26/5 | 650 VA line-interactive backup |
| 11 | Mesh AC1200 Wi-Fi Router | Networking and Conferencing | item | 6,900 | 28/5 | Dual-band, gigabit WAN |
| 12 | Vision 1080p Webcam | Networking and Conferencing | item | 5,400 | 30/6 | FHD video, privacy shutter |

### 6.3 Hardware And Construction (`hardware-construction`)

Categories: Paint and Finishes, Cement and Masonry, Steel and Roofing, Timber
and Boards, Plumbing, Electrical, and Tools and Ironmongery.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | WeatherGuard Exterior Paint 20 L | Paint and Finishes | tin | 8,950 | 28/5 | Exterior acrylic, white, 20 L |
| 2 | SilkFinish Interior Paint 20 L | Paint and Finishes | tin | 7,800 | 32/6 | Interior emulsion, brilliant white |
| 3 | General Purpose Cement 50 kg | Cement and Masonry | bag | 850 | 180/30 | 50 kg construction cement |
| 4 | Deformed Steel Bar Y12 x 12 m | Steel and Roofing | length | 1,450 | 120/20 | 12 mm diameter, 12 m length |
| 5 | Box Profile Roofing Sheet 3 m | Steel and Roofing | sheet | 2,250 | 75/12 | 3 m pre-painted sheet |
| 6 | Square Hollow Section 40 x 40 x 2 mm | Steel and Roofing | length | 2,900 | 64/10 | 6 m structural tube |
| 7 | Marine Plywood 18 mm 8 x 4 ft | Timber and Boards | sheet | 4,800 | 40/8 | 18 mm moisture-resistant board |
| 8 | PVC Pressure Pipe 25 mm x 6 m | Plumbing | length | 620 | 90/15 | 25 mm pressure pipe |
| 9 | Twin-and-Earth Cable 2.5 mm 100 m | Electrical | roll | 13,500 | 22/4 | 100 m copper cable roll |
| 10 | Masonry Drill Bit Set 8 Piece | Tools and Ironmongery | set | 2,800 | 38/7 | Common masonry diameters |
| 11 | Heavy-Duty Claw Hammer 20 oz | Tools and Ironmongery | item | 1,150 | 48/8 | Fibreglass handle, 20 oz head |
| 12 | Lever Mortise Lock Set | Tools and Ironmongery | set | 3,400 | 35/6 | Lever handles, lock body, keys |

All lengths remain integer sale units in the first fixture. Cut-to-length metal,
cable, timber, or paint tinting requires the variable-measure and variant work
tracked under `COM-028` and `COM-006`.

### 6.4 Boutique And Fashion (`boutique-fashion`)

Categories: Women's Clothing, Men's Clothing, Children's Clothing, Footwear,
Bags, and Accessories.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Ankara Panel Midi Dress | Women's Clothing | item | 4,800 | 20/4 | Cotton blend, midi silhouette |
| 2 | Classic Oxford Shirt | Men's Clothing | item | 3,200 | 28/5 | Long sleeve, regular fit |
| 3 | Everyday Cotton Hoodie | Men's Clothing | item | 3,900 | 24/5 | Midweight fleece, unisex styling |
| 4 | High-Waist Straight Jeans | Women's Clothing | item | 4,200 | 22/4 | Stretch denim, straight leg |
| 5 | Tailored Chino Trousers | Men's Clothing | item | 3,600 | 26/5 | Cotton twill, tapered fit |
| 6 | Pleated Maxi Skirt | Women's Clothing | item | 3,400 | 18/4 | Ankle length, lined |
| 7 | Kids Printed T-Shirt Twin Pack | Children's Clothing | pack | 1,850 | 30/6 | Two cotton crew-neck shirts |
| 8 | Structured Crossbody Bag | Bags | item | 3,750 | 17/4 | Adjustable strap, zipped sections |
| 9 | Court Canvas Sneakers | Footwear | pair | 3,950 | 24/5 | Lace-up canvas upper |
| 10 | Beaded Bracelet Gift Set | Accessories | set | 1,200 | 40/8 | Three coordinated bead bracelets |
| 11 | Lightweight Woven Shawl | Accessories | item | 2,100 | 25/5 | Soft woven wrap |
| 12 | Single-Breasted Tailored Blazer | Women's Clothing | item | 6,500 | 14/3 | Lined, single-button closure |

The first dataset represents one display configuration per product. Size,
colour, material, and fit choices are descriptive only; stock by option must
wait for saleable variants under `COM-006`.

### 6.5 Pharmacy And Health Retail (`pharmacy-health`)

Categories: Pain and Fever, Digestive and Rehydration, Allergy Care, Vitamins
and Supplements, First Aid, and Hygiene and Monitoring.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Paracetamol 500 mg 20 Tablets | Pain and Fever | pack | 120 | 90/15 | Generic OTC demonstration pack |
| 2 | Oral Rehydration Salts 10 Sachets | Digestive and Rehydration | pack | 350 | 65/12 | Ten individually sealed sachets |
| 3 | Antacid Suspension 200 ml | Digestive and Rehydration | bottle | 280 | 48/8 | 200 ml sealed bottle |
| 4 | Cetirizine 10 mg 10 Tablets | Allergy Care | pack | 180 | 70/12 | Generic OTC demonstration pack |
| 5 | Vitamin C 500 mg 30 Tablets | Vitamins and Supplements | bottle | 650 | 44/8 | Thirty-tablet supplement bottle |
| 6 | Zinc 20 mg 30 Tablets | Vitamins and Supplements | bottle | 720 | 38/7 | Thirty-tablet supplement bottle |
| 7 | Digital Clinical Thermometer | Hygiene and Monitoring | item | 650 | 30/6 | Digital oral/axillary thermometer |
| 8 | Home First-Aid Kit 42 Piece | First Aid | kit | 1,850 | 22/4 | Dressings, tape, gloves, scissors |
| 9 | Three-Ply Face Masks 50 Pack | Hygiene and Monitoring | box | 550 | 55/10 | Disposable masks, 50-count box |
| 10 | Hand Sanitizer 500 ml | Hygiene and Monitoring | bottle | 420 | 60/10 | Sealed pump bottle |
| 11 | Adhesive Plasters 100 Pack | First Aid | box | 480 | 46/8 | Assorted sterile strips |
| 12 | Absorbent Cotton Wool 100 g | First Aid | pack | 190 | 52/9 | Sealed 100 g pack |

**Mandatory pharmacy boundary:** this fixture is not a prescribing, dispensing,
dosage, contraindication, interaction, batch, expiry, or regulatory-compliance
system. It excludes antibiotics, controlled medicines, prescription-only
products, and therapeutic claims. Product wording and tax treatment require
qualified adopter review. A production pharmacy adoption is blocked until the
appropriate parts of `COM-028` and applicable Kenyan pharmacy controls are
designed and verified.

### 6.6 Supermarket And FMCG (`supermarket-fmcg`)

Categories: Flour and Grains, Pulses and Staples, Cooking Essentials, Dairy,
Beverages, Bakery, and Household Care.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Fortified Maize Meal 2 kg | Flour and Grains | packet | 220 | 100/18 | Sealed 2 kg packet |
| 2 | Home Baking Wheat Flour 2 kg | Flour and Grains | packet | 240 | 90/16 | Sealed 2 kg packet |
| 3 | Long-Grain Rice 2 kg | Flour and Grains | packet | 430 | 72/12 | Cleaned long-grain rice |
| 4 | Dry Red Beans 1 kg | Pulses and Staples | packet | 260 | 68/12 | Sorted dry beans |
| 5 | Pure Cooking Oil 2 L | Cooking Essentials | bottle | 690 | 64/10 | Sealed 2 L bottle |
| 6 | White Sugar 2 kg | Cooking Essentials | packet | 380 | 80/14 | Sealed 2 kg packet |
| 7 | Iodised Table Salt 1 kg | Cooking Essentials | packet | 95 | 100/18 | Sealed 1 kg packet |
| 8 | UHT Whole Milk 500 ml | Dairy | packet | 75 | 120/20 | Shelf-stable 500 ml pack |
| 9 | Kenyan Black Tea 500 g | Beverages | packet | 420 | 58/10 | Loose-leaf black tea |
| 10 | Drinking Water 1.5 L | Beverages | bottle | 90 | 144/24 | Sealed 1.5 L bottle |
| 11 | Sliced White Bread 400 g | Bakery | loaf | 75 | 36/8 | Packaged 400 g loaf |
| 12 | Multipurpose Laundry Bar 1 kg | Household Care | bar | 260 | 76/14 | Wrapped 1 kg cleaning bar |

Perishability and expiry are not enforced in the current engine. Dates may not
be hidden in specifications as a substitute for lot-aware inventory.

### 6.7 Beauty And Personal Care (`beauty-personal-care`)

Categories: Skin Care, Hair Care, Bath and Body, Deodorants, Cosmetics, and
Fragrance.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Daily Moisture Body Lotion 400 ml | Bath and Body | bottle | 680 | 44/8 | Pump bottle, 400 ml |
| 2 | Pure Petroleum Jelly 250 ml | Skin Care | jar | 420 | 50/9 | Sealed 250 ml jar |
| 3 | Moisture Balance Shampoo 500 ml | Hair Care | bottle | 850 | 36/7 | 500 ml flip-cap bottle |
| 4 | Nourishing Conditioner 500 ml | Hair Care | bottle | 880 | 34/7 | 500 ml flip-cap bottle |
| 5 | Gentle Bathing Soap Four Pack | Bath and Body | pack | 360 | 62/11 | Four wrapped soap bars |
| 6 | Conditioning Hair Food 150 g | Hair Care | jar | 450 | 42/8 | Sealed 150 g jar |
| 7 | Braiding Fibre Twin Pack | Hair Care | pack | 520 | 70/12 | Two synthetic braid bundles |
| 8 | Fresh Roll-On Deodorant 50 ml | Deodorants | item | 320 | 56/10 | 50 ml roll-on |
| 9 | Daily Face Sunscreen SPF 50 50 ml | Skin Care | tube | 1,450 | 28/5 | 50 ml tube, SPF label demo |
| 10 | Moisture Lip Balm 4.5 g | Cosmetics | item | 280 | 60/10 | Twist-up balm tube |
| 11 | Gloss Nail Colour 12 ml | Cosmetics | bottle | 350 | 45/8 | 12 ml applicator bottle |
| 12 | Everyday Eau de Parfum 50 ml | Fragrance | bottle | 2,400 | 20/4 | 50 ml spray bottle |

Cosmetic efficacy, SPF, ingredient, and allergen wording must be treated as
fixture presentation and reviewed before adopter reuse.

### 6.8 Automotive Parts And Accessories (`automotive-parts`)

Categories: Lubricants and Fluids, Filters and Ignition, Braking, Electrical,
Tyres and Batteries, and Interior Accessories.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Multigrade Engine Oil 5 L | Lubricants and Fluids | bottle | 4,800 | 30/6 | 5 L sealed container |
| 2 | Long-Life Coolant 1 L | Lubricants and Fluids | bottle | 850 | 42/8 | Premixed 1 L bottle |
| 3 | Standard Spin-On Oil Filter | Filters and Ignition | item | 750 | 50/9 | Generic fitment demonstration |
| 4 | Panel Air Filter | Filters and Ignition | item | 1,200 | 36/7 | Generic panel filter |
| 5 | Copper Spark Plug Four Pack | Filters and Ignition | pack | 1,600 | 40/8 | Four matched plugs |
| 6 | Front Brake Pad Set | Braking | set | 4,500 | 24/5 | Axle set, generic fitment demo |
| 7 | Universal Wiper Blade Pair | Interior Accessories | pair | 1,850 | 32/6 | 22/18-inch blade pair |
| 8 | Maintenance-Free Battery 70 Ah | Tyres and Batteries | item | 15,500 | 12/3 | 12 V, 70 Ah demonstration unit |
| 9 | All-Season Tyre 195/65 R15 | Tyres and Batteries | item | 9,800 | 20/4 | 195/65 R15 tyre |
| 10 | H4 LED Headlamp Bulb Pair | Electrical | pair | 2,600 | 28/5 | 12 V H4 replacement pair |
| 11 | Dashboard Phone Holder | Interior Accessories | item | 1,250 | 45/8 | Adjustable clamp mount |
| 12 | All-Weather Floor Mat Set | Interior Accessories | set | 3,900 | 18/4 | Four-piece universal set |

Fitment remains descriptive. A production parts catalog needs vehicle
compatibility data and must not rely on free-text specifications alone.

### 6.9 Agrovet And Farm Supplies (`agrovet-farm`)

Categories: Animal Feed, Seeds, Soil Nutrition, Farm Equipment, Protective Wear,
and Farm Hygiene and Fencing.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | Dairy Meal 70 kg | Animal Feed | bag | 3,850 | 45/8 | 70 kg sealed feed bag |
| 2 | Chick Mash 50 kg | Animal Feed | bag | 3,600 | 40/7 | 50 kg starter feed bag |
| 3 | Growers Mash 50 kg | Animal Feed | bag | 3,350 | 42/8 | 50 kg grower feed bag |
| 4 | Mineral Lick 5 kg | Animal Feed | pack | 780 | 38/7 | 5 kg sealed mineral pack |
| 5 | Certified Maize Seed 2 kg | Seeds | packet | 650 | 54/10 | Sealed 2 kg demo seed pack |
| 6 | Certified Bean Seed 2 kg | Seeds | packet | 720 | 48/9 | Sealed 2 kg demo seed pack |
| 7 | Compound Fertilizer 50 kg | Soil Nutrition | bag | 4,200 | 50/9 | 50 kg labelled demo bag |
| 8 | Knapsack Sprayer 20 L | Farm Equipment | item | 4,800 | 18/4 | Manual 20 L sprayer |
| 9 | PVC Farm Gumboots | Protective Wear | pair | 1,650 | 28/5 | Knee-height waterproof boots |
| 10 | Poultry Drinker 10 L | Farm Equipment | item | 950 | 35/6 | 10 L gravity drinker |
| 11 | Galvanised Fencing Wire 25 kg | Farm Hygiene and Fencing | roll | 5,900 | 16/4 | 25 kg wire roll |
| 12 | Farm Surface Disinfectant 5 L | Farm Hygiene and Fencing | container | 2,400 | 24/5 | Non-medicinal surface-care demo |

This context excludes veterinary medicines, pesticides, and dosage or crop-
treatment claims. Seed certification, fertilizer composition, controlled
inputs, and product registration require adopter validation. Batch/expiry
control remains dependent on `COM-028`.

### 6.10 Office, Bookshop, And Stationery (`office-bookshop`)

Categories: Paper, Writing and Marking, Filing, Desk Equipment, School Supplies,
and Printing Supplies.

| # | Product | Category | Unit | Demo KES | Opening/low | Key specification |
| ---: | --- | --- | --- | ---: | --- | --- |
| 1 | A4 Copier Paper 80 gsm 500 Sheets | Paper | ream | 780 | 70/12 | 500-sheet white ream |
| 2 | A4 Counter Book Four Quire | Paper | item | 420 | 58/10 | Hard cover, ruled pages |
| 3 | A5 Exercise Books 12 Pack | School Supplies | pack | 720 | 48/9 | Twelve ruled exercise books |
| 4 | Blue Ballpoint Pens 50 Box | Writing and Marking | box | 850 | 40/8 | Fifty blue medium-point pens |
| 5 | Permanent Markers 12 Pack | Writing and Marking | pack | 1,050 | 34/7 | Assorted permanent markers |
| 6 | Lever Arch File | Filing | item | 380 | 64/11 | A4 spine file |
| 7 | Two-Hole Desktop Punch | Desk Equipment | item | 750 | 28/5 | Metal body, paper guide |
| 8 | Full-Strip Stapler | Desk Equipment | item | 620 | 32/6 | Standard 24/6 staples |
| 9 | Scientific Calculator | School Supplies | item | 1,850 | 26/5 | Multi-line academic calculator |
| 10 | Geometry Instrument Set | School Supplies | set | 480 | 46/8 | Compass, divider, rulers, protractor |
| 11 | Black Laser Toner Cartridge | Printing Supplies | item | 4,500 | 18/4 | Generic demonstration cartridge |
| 12 | Magnetic Whiteboard 900 x 600 mm | Desk Equipment | item | 4,200 | 14/3 | Aluminium frame and wall kit |

## 7. Image Generation And Asset Contract

All new product and category images will be generated specifically for the
template. Implementation must not hotlink remote files or copy protected brand
photography.

### 7.1 Visual standard

- one primary product image for every product;
- a second detail or alternate-angle image for at least featured and visually
  ambiguous products;
- one category image per category, derived from context-owned products rather
  than unrelated stock imagery;
- square master canvas, recommended 1200 x 1200 pixels, with safe whitespace for
  the existing card crop;
- PNG or WebP output accepted by the current media collections;
- clear, well-lit object presentation on neutral or context-appropriate simple
  surfaces, with no gradients, watermarks, embedded prices, or real trademarks;
- coherent art direction within a context while keeping product shape, colour,
  scale, and packaging easy to inspect;
- image manifest containing context key, product SKU/category slug, source
  prompt, file name, dimensions, MIME type, checksum, and alt text;
- deterministic file names such as
  `computers-it/horizon-14-business-laptop-01.webp`.

### 7.2 Asset location

The current default image paths remain unchanged. New context files may use
subdirectories because `CatalogDemoSeeder::syncMedia()` already accepts a
relative path:

```text
app/Modules/Commerce/Resources/demo/products/
|-- aureon-air-14.png
|-- ... current default assets
`-- contexts/
    |-- computers-it/
    |-- hardware-construction/
    |-- boutique-fashion/
    `-- ...
```

Existing files are not moved. Spatie continues copying selected source files to
the public media disk, and the module assets remain the repair source.

## 8. Minimal Seeder Design

### 8.1 Keep the existing chain

The immediate implementation retains these classes and their responsibilities:

- `CommerceDemoSeeder` orchestrates access, catalog, customer, and transaction
  fixtures in dependency order.
- `CatalogDemoSeeder` creates or updates categories and products, reconciles
  opening stock, and attaches media.
- `TransactionDemoSeeder` creates coherent web and POS examples through domain
  services.

No new domain module, model, migration, service layer, repository, policy,
permission, job, or Livewire component is introduced in this phase.

### 8.2 Add simple context data

The large contextual arrays should not make `CatalogDemoSeeder` unreadable.
Store them as plain, trusted PHP data files beside the current module seeders:

```text
app/Modules/Commerce/Database/Seeders/
|-- CommerceDemoSeeder.php
|-- CatalogDemoSeeder.php
|-- TransactionDemoSeeder.php
`-- Data/
    |-- default-mixed.php
    |-- computers-it.php
    |-- hardware-construction.php
    |-- boutique-fashion.php
    `-- ...
```

Each allowlisted file returns the same simple shape:

```php
return [
    'key' => 'computers-it',
    'categories' => [],
    'products' => [],
    'transactions' => [
        'web_order_sku' => 'AUR-CIT-LT01',
        'pos_order_skus' => ['AUR-CIT-KB06', 'AUR-CIT-MS07'],
    ],
];
```

The existing product helper, `seedCategories()`, `seedProduct()`, stock ledger
logic, and `syncMedia()` remain in `CatalogDemoSeeder`. Only the source of the
category/product arrays changes. `CatalogDemoSeeder` owns a small constant map
from stable context key to trusted data file; arbitrary file paths or class
names are rejected.

Non-default category slugs, product slugs, SKUs, and barcodes must be unique
across all built-in contexts. Context-prefixed category slugs prevent a merge
from rewriting the meaning of an existing category.

### 8.3 Seeder parameters

Use Laravel's installed `Seeder::callWith()` support instead of global mutable
state. Both orchestration and catalog seeders accept typed defaults:

```php
public function run(
    string $context = 'default-mixed',
    bool $archiveExisting = false,
): void
```

`CommerceDemoSeeder` passes both parameters to `CatalogDemoSeeder`. It also
passes the selected data file's transaction SKU choices to
`TransactionDemoSeeder`; this removes the current hard-coded default SKUs while
preserving its service-driven order/till behavior. The existing transaction
markers remain exactly-once guards: a context only selects transaction products
when those demonstration orders do not already exist. Changing context never
rewrites historical demo orders.

Calling either seeder without parameters remains backward compatible and
produces the existing `default-mixed` catalog.

### 8.4 One small command for explicit selection

Add one module command as the human-friendly parameter boundary:

```text
php artisan commerce:demo-seed
php artisan commerce:demo-seed computers-it
php artisan commerce:demo-seed hardware-construction --archive-existing
```

The positional `context` defaults to `default-mixed`. The command validates the
key against the fixed map and invokes `CommerceDemoSeeder` with named
parameters. It does not contain catalog write logic.

A fresh contextual setup remains two explicit steps, preserving the current
decision not to couple Commerce demonstration data to `DatabaseSeeder`:

```text
php artisan migrate:fresh --seed
php artisan commerce:demo-seed computers-it
```

Running the existing command below continues to seed the default context:

```text
php artisan db:seed --class="App\Modules\Commerce\Database\Seeders\CommerceDemoSeeder"
```

## 9. Existing Product Handling

Only two behaviors are required now.

| Option | Parameter | Behavior | Default |
| --- | --- | --- | --- |
| Keep existing products | `archiveExisting: false` | Leave all existing products unchanged except matching selected-context SKUs, which retain the current deterministic upsert behavior. Seed and publish the selected context alongside them. | Yes |
| Archive existing products | `archiveExisting: true` | Set every non-deleted existing product to `ProductStatus::Archived`, then seed/re-publish the selected context. Preserve product rows, media, stock, movements, and historical order links. | No |

The archive option is intentionally broad and explicit. It replaces the active
catalog presentation without deleting data. Selected products are archived
first and then restored to published state by the normal upsert, leaving the
selected context active at completion.

Additional rules:

- preflight the selected data file, identifier uniqueness, category references,
  and every declared media path before archiving anything;
- do not reset stock for an existing selected-context product;
- do not add a second opening movement when the stable opening note exists;
- leave soft-deleted non-selected products untouched;
- allow the current selected-context restore behavior for a matching
  soft-deleted SKU;
- retain categories rather than attempting to infer whether an existing
  category is adopter-owned;
- never truncate or delete catalog, stock, movement, order, payment, register,
  till, customer, or media tables;
- fail clearly on an unsupported context or identifier collision.

Because this phase adds no ownership table, it cannot distinguish a manually
created product from a demo product during broad archival. That is why archival
is opt-in, named plainly, and unsuitable as an automatic production action.

## 10. Deferred Architecture

The following ideas from the original draft are valid only when the future
administration selector is approved or operational requirements prove them
necessary:

- persisted demo run and entity-ownership tables;
- preview/result DTOs and a catalog application service;
- queue jobs, run progress, locking, retries, and run history;
- dedicated demo-data permissions and policies;
- a Livewire Sample Catalogs manager and Commerce menu entry;
- merge/replace/purge modes beyond keep versus archive-existing;
- environment-gated browser execution and typed destructive confirmations.

That future interface must reuse the same context keys and data files. It may
wrap or extract the seeder behavior after concrete UI requirements exist; the
first implementation must not pre-build it.

## 11. Implementation Phases

| Phase | Status | Deliverable | Exit evidence |
| --- | --- | --- | --- |
| S0 | Complete | Approve context names, product manifests, simple parameter contract, and regulated-domain boundaries. | Reduced plan reviewed and committed. |
| S1 | Complete | Move the current arrays to `Data/default-mixed.php`, parameterize the existing seeder chain, and add the command without changing default output. | Original compatibility test passes with ten products, six categories, unchanged transaction graph, repaired media, and idempotent reruns. |
| S2 | Complete | Add preflight validation and archive-existing behavior. | Keep/archive/collision/invalid-context tests prove preserved history, stable stock, exact-once movements, and validation before mutation. |
| S3 | Complete | Implement the four new Wave A data files and deterministic media paths. | All 58 Wave A products have complete data and valid local media; each new product has a generated featured image and branded secondary fallback. |
| S4 | Complete | Implement the five new Wave B data files and deterministic media paths. | All 118 products are selectable across ten contexts; all contexts coexist with 62 categories and 290 media records. |
| S5 | Complete | Complete command documentation, full Commerce regression, storefront/POS/browser, responsive, dark-mode, and media verification. | 123 Commerce tests/3,399 assertions, production build, and 22 browser diagnostic cases passed; 18 screenshots and both diagnostics files are retained. |

Wave A totals the 10 current products plus 48 products from Computers and IT,
Hardware and Construction, Boutique and Fashion, and Pharmacy and Health.
Wave B adds 60 products across the remaining five contexts.

## 12. Verification Matrix

### 12.1 Data and default compatibility

- every context contains 10 to 12 products and all category references resolve;
- SKU, slug, barcode, category slug, and asset paths are unique as required;
- all money values are non-negative integer minor units;
- sale prices and windows satisfy Product model invariants;
- no-argument seeding still creates the current six categories, ten products,
  transaction examples, and media counts;
- `CommerceDemoSeederTest` remains green for the default context and reruns;
- every selected context provides valid SKUs for one web order and two POS sale
  lines.

### 12.2 Keep and archive behavior

- default keep mode leaves unrelated products and their status unchanged;
- selected-context products are deterministically upserted and published;
- archive mode archives pre-existing products and republishes only the selected
  context without deleting rows;
- existing stock quantities are not reset on rerun;
- stock projection equals the sum of movements after first and repeated runs;
- historical order items, payments, movements, media, registers, and till
  sessions remain present;
- invalid context, malformed data, missing media, duplicate identifiers, or
  unresolved categories fail during preflight before archival.

### 12.3 Command and presentation QA

- omitted context resolves to `default-mixed`;
- every allowlisted key resolves to the expected dataset;
- unknown context exits non-zero with the supported choices;
- `--archive-existing` maps only to the explicit boolean parameter;
- generated images load in catalog administration, storefront, POS, documents,
  and reports;
- product names, units, and specifications remain readable on desktop, tablet,
  and mobile in light and dark modes.

## 13. Documentation Deliverables

Implementation must add or update:

- command examples for default, selected-context, and archive-existing runs;
- a warning that fixture prices, tax, regulated products, and stock are demo
  values requiring adopter review;
- a short data-file authoring checklist for adding another allowlisted context;
- the image generation and optimization manifest for every context;
- the Commerce adoption and operations guides with the non-destructive archive
  semantics;
- this file's phase and context implementation status.

## 14. Settled Decisions

| Decision | Immediate choice |
| --- | --- |
| Seeder architecture | Retain `CommerceDemoSeeder`, `CatalogDemoSeeder`, and `TransactionDemoSeeder`. |
| Context data | Trusted PHP array files under `Database/Seeders/Data`. |
| Context selection | Typed seeder parameter plus one `commerce:demo-seed` command. |
| Default context | `default-mixed`. |
| Existing product default | Keep existing products. |
| Optional replacement | Archive existing products, then publish the selected context. |
| Persistence additions | None: no migration, run model, or ownership model. |
| Seeder runtime additions | The S0-S5 seeder release adds one small command only; no queue, service subsystem, or persisted orchestration. |
| Optional administration surface | A later, separate Livewire interface invokes only the canonical command behind a dedicated permission and runtime lock. |
| Pricing | Illustrative KES fixture values; adopter must replace or review them. |
| Images | Generated local assets using the existing Spatie media flow. |
| Pharmacy scope | OTC/first-aid demonstration only; no production-readiness claim. |
| Apparel configuration | One display SKU per product until `COM-006` variants are implemented. |

## 15. Acceptance Criteria For This Planning Task

- [x] Current seeder classes, Laravel `callWith()` behavior, product/category
  models, media path, transaction SKU assumptions, and fixture test were
  inspected before revision.
- [x] Ten contextual merchant ecosystems remain defined with 118 products.
- [x] Default execution remains backward compatible with `default-mixed`.
- [x] Context selection and archive-existing behavior have concrete parameters
  and command examples.
- [x] The S0-S5 seeder core adds no model, migration, service subsystem, job,
  policy, permission, or Livewire component; the later launcher remains a thin,
  independently authorized adapter over that core.
- [x] Image expectations and regulated-domain limitations remain explicit.
- [x] The richer dashboard and ownership architecture is deferred.
- [x] Seeder code, ten data files, deterministic media, command,
  tests, adopter documentation, and browser evidence are implemented.
- [x] Replace all 108 primary placeholders with optimized product-specific
  generated imagery while retaining the established paths.
- [ ] Replace the 108 secondary branded fallbacks with optional product-specific
  gallery views while retaining the two-image gallery contract.

## 16. Planning Ledger

| Date | Change | Branch | Database effect |
| --- | --- | --- | --- |
| 2026-09-05 | Created the contextual merchant registry and 118-product manifest. | `feature/commerce-context-seeder-plan` | None; documentation only. |
| 2026-09-05 | Reduced the immediate architecture to context data consumed by the existing seeder chain, a default context parameter, one archive-existing flag, and one small command; deferred persisted orchestration and dashboard management. | `feature/commerce-context-seeder-plan` | None; documentation only. |
| 2026-09-05 | Implemented S1-S5 on the existing seeder chain with ten contexts, 118 products, transactional preflight/archive behavior, deterministic placeholder media, tests, documentation, and isolated browser verification. | `feature/commerce-context-seeders` | No developer database mutation; tests and browser QA used disposable databases/storage. |
| 2026-09-05 | Replaced all 108 contextual primary placeholders with optimized generated product images and added checksum-based stored-media reconciliation. | `feature/commerce-context-product-images` | No developer database mutation; source assets and isolated QA fixtures only. |
| 2026-09-05 | Added the approved simple Livewire launcher over the canonical contextual seeding command, with visual context selection, a dedicated permission, serialized execution, and explicit archive confirmation. | `feature/commerce-context-product-images` | No migration; runtime effects occur only when an authorized operator submits the form. |
