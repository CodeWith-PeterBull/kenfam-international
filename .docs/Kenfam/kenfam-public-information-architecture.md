# Kenfam Public Information Architecture

Status: client experience plan. Tours and departure data are independently
authored, not imported from the old website.

## 1. Public Navigation And Page Ownership

| Page | Owner | Content and primary action |
| --- | --- | --- |
| / | Client host | Kenfam identity, relevant travel image, destination/tour discovery, inquiry |
| About | Client host | Approved history, purpose, values and institutional facts |
| Contact | Client host | Verified channels/address, accessible inquiry and WhatsApp |
| /tours | TravelTours | Published tour catalog with bounded search and filters |
| Destination discovery/detail | TravelTours | Published geography, related eligible tours |
| Tour detail | TravelTours | Gallery, overview, itinerary, inclusions, FAQs, map, dates and price |
| Selection/checkout | TravelTours | Party/date/rate/extras, quote, contact, terms and confirmation |
| Booking confirmation/tracking/document | TravelTours | Signed private projection, noindex/no-store |
| Auth/profile | Aureon host | Existing secure account flow, no premature customer portal |
| Legal/policy pages | Client host | Approved versioned commercial/privacy text |

The root page must have an intentional fallback when TravelTours is disabled
or no tours are published. Never query disabled reference modules to populate
a client homepage. Never advertise unimplemented self-service functionality.

## 2. Homepage And Discovery

Make Kenfam International the first-viewport identity. Use a relevant full-bleed
image with minimal copy and a clear discovery/inquiry action, then visible next
content. Avoid a generic software feature showcase or card-wrapped hero.
Desktop/tablet/mobile headers reuse Aureon conventions and accessible navigation.

Catalog filters: destination, price range with explicit currency, duration,
category, date and keywords. Persist safe query filters, whitelist sorting,
bound pagination, clear filters ergonomically, and show true empty/unavailable
states. No exchange-rate comparison across currencies.

## 3. Tour Detail Content Order

Identity/breadcrumb; accessible gallery; short overview/highlights; dated
availability and transparent starting price; itinerary by day and activities;
inclusions/exclusions, requirements, meeting/end location; FAQs; cancellation
summary and approved booking terms; custom/private inquiry.

Prices derive from an eligible current rate and explain participant basis.
Unknown or request-only prices invite inquiry, not a fabricated zero price.
Maps have textual location alternatives; external embeds respect consent/performance.
Gallery supports keyboard arrows, Escape, captions and focus return.

## 4. Booking UX

Choose date/party and extras, display server quote, gather necessary contact and
participants, explicitly accept current terms, then submit idempotently.
Show hold expiry and recover from changed price/availability without losing
safe form data. Never pre-check hidden consent. Distinguish approval requested,
booking confirmed, payment pending and payment received.

Signed pages expose minimal booking details. Private identity documents are
not collected unnecessarily at first inquiry or shown on confirmation.
Future account history/wishlist/reviews have no visible dead controls.

## 5. SEO, Accessibility And Performance

Unique titles/descriptions, canonical URLs, OG/Twitter images, favicon, published
sitemap, breadcrumb structured data and truthful visible offer information.
No fabricated ratings/reviews. Private signed/auth/admin pages noindex and
non-cacheable. Contact structured data uses verified details only.

Accessible labels, headings, focus, contrast, status/error live regions and
reduced-motion support. Stable image ratios, responsive sources and lazy loading
below the fold. Load the hero without unnecessary lazy delay. Reuse the Aureon
light/dark initialization and avoid an artificial blocking wait.

## 6. Content Approval Gate

Institution facts, logo variants, hero/gallery provenance, rates, participant
age policy, included services, cancellation/refund/legal copy and response
contacts require explicit review. Record approval in the brand/content register
before staging is represented as a sales-ready public platform.
