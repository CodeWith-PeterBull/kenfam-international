# Contextual Commerce Product Images

## 1. Scope

This delivery replaces the primary placeholder for every product in the nine
non-default Commerce fixture contexts. It deliberately retains the existing
two-source gallery contract: the first source is now product-specific and the
second remains the centralized Aureon fallback.

The original `default-mixed` catalog keeps its established photography and is
outside this replacement pass.

## 2. Delivered Inventory

| Context | Featured images | Secondary fallbacks |
| --- | ---: | ---: |
| `computers-it` | 12 | 12 |
| `hardware-construction` | 12 | 12 |
| `boutique-fashion` | 12 | 12 |
| `pharmacy-health` | 12 | 12 |
| `supermarket-fmcg` | 12 | 12 |
| `beauty-personal-care` | 12 | 12 |
| `automotive-parts` | 12 | 12 |
| `agrovet-farm` | 12 | 12 |
| `office-bookshop` | 12 | 12 |
| **Total** | **108** | **108** |

## 3. Visual And Regulatory Boundaries

Featured assets use a consistent warm-white studio surface, restrained shadows,
clear product silhouettes, and no real merchant branding or watermarks. Apparel
uses product-only presentation rather than synthetic people. Pharmacy packaging
is generic and claim-conscious. Automotive assets avoid model-specific fitment
claims, while agrovet assets exclude pesticides and veterinary medicines.

These images are demonstration fixtures. Production adopters remain responsible
for licensed product photography, accurate packaging, applicable warnings, and
regulated-domain approval.

## 4. Asset Profile

- Format: indexed PNG (`PNG8`), sRGB, metadata stripped.
- Dimensions: 640 x 640 pixels for every generated featured image.
- Compression: 192-color Floyd-Steinberg quantization and PNG level 9.
- Generated featured total: 10,622,942 bytes.
- Generated featured mean: 98,361 bytes.
- Generated featured range: 28,002 to 200,648 bytes.
- Secondary fallback: existing 256 x 256 centralized Aureon icon PNG.

Assets live below:

```text
app/Modules/Commerce/Resources/demo/products/contexts/{context}/
```

The established `*-01.png` featured and `*-02.png` secondary naming convention
must be retained because the trusted context data files reference these paths.

## 5. Stored Media Reconciliation

`CatalogDemoSeeder::syncMedia()` now verifies more than collection count. It
checks source order, file existence, and SHA-256 equality between each module
source and its copied Spatie media file. Any mismatch clears and rebuilds that
product gallery from the trusted sources.

This makes image adoption deterministic for both fresh and previously seeded
databases while preserving the seeder's idempotent product and inventory graph.

## 6. Verification Evidence

Permanent evidence is stored at:

```text
.docs/dev/commerce-context-image-qa/
```

`asset-manifest.json` records all 216 sources with product, context, category,
role, path, dimensions, MIME type, byte size, SHA-256, and alt text. Nine contact
sheets provide a compact visual review surface, one per merchant context.

Automated regression coverage proves that corrupting an already copied primary
media file and rerunning the same context restores the exact source bytes while
retaining the two-item gallery.

## 7. Deferred Work

The 108 secondary files remain branded fallbacks by explicit scope. They can be
replaced later with alternate product angles without schema, seeder, data-file,
or runtime architecture changes.
