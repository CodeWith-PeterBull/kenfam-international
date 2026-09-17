# TravelTours K2E: Catalog Completion And Base Pricing

Status: implemented and locally verified on
`feature/travel-tours-k2-catalog-completion`; branch base `a17e937`.
Awaiting review; do not commit before approval. Delivery and evidence are in
`travel-tours-k2e-catalog-completion-implementation.md`.

## Purpose and boundary

Make a tour draft complete enough to preview and publish from the staff catalog:
cover, ordered gallery, public attachments, SEO, visible base adult/child/infant
pricing, explicit readiness, and governed publication actions. The public
catalog must reflect the resulting record consistently. No new migrations are
needed; K1 already owns the media collections, rate plans, and participant
rates. K3 retains departures, seasonal/group rules, promotions, tax-policy
expansion, and advanced rate-plan administration.

## Planned edits

1. Extend the module media service with tour-owned cover/gallery/document
   operations, validation, accessible text, order, and scoped removals. Add a
   contextual Media tab with upload controls and existing asset previews.
2. Add a small typed base-pricing write service and Livewire tab. Support one
   public default plan per tour, ISO currency, and adult/child/infant base
   prices in exact minor units; never accept float-based money input. Leave
   pricing-rule, promotion, and departure mutation outside this increment.
3. Add a readiness evaluator and publication service. Separate authoring from
   publish permission; require public descriptions, route, itinerary, cover,
   SEO, and valid base pricing before publication. Provide authenticated
   preview without widening the public `published()` scope. Scheduled publish,
   unpublish, and archive must preserve stable URLs and historical bookings.
4. Complete the catalog index with readiness/price cues and action links.
   Public tour detail should expose ordered gallery and public attachments,
   without leaking nonpublic media or rate plans.
5. Test services, policies, Livewire and routes; run the module/full suites,
   Pint, PHPDoc, schema probe, Blade, Vite, and desktop/tablet/mobile browser QA
   in light/dark/reduced-motion states. Record screenshots and diagnostics.

During implementation, the image upload requirement expanded to include
filename-derived editable alt suggestions and temporary previews for both
tour and destination covers/galleries. The public tour gallery also gained
a full-window lightbox following the Property Booking interaction pattern.

## Exit boundary

K2E is complete only after the user reviews the behavior and evidence. The
next phase, K3, owns date-specific departures, availability, dynamic pricing
rules, group/early-bird adjustments, and promotions.
