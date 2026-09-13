# Brand Asset Contract

These filenames are the stable identity contract used by the generated template. Replace the PNG contents while preserving the filenames to rebrand the site without changing page markup.

| File | Intrinsic size | Purpose |
| --- | ---: | --- |
| `logo.png` | 520 x 128 | Default horizontal logo on light surfaces |
| `logo-light.png` | 520 x 128 | Horizontal logo on dark surfaces |
| `logo-small.png` | 180 x 140 | Compact centered desktop lockup on light surfaces |
| `logo-small-light.png` | 180 x 140 | Compact centered desktop lockup on dark surfaces |
| `logo-icon.png` | 256 x 256 | Page loader and touch-icon mark |
| `favicon.png` | 64 x 64 | Browser tab and bookmark icon |
| `twitter-card.png` | 1200 x 630 | Open Graph and large Twitter/X card image |

## Replacement rules

- Keep the filenames and intrinsic dimensions stable.
- Keep both wordmark files transparent and tightly bounded by their canvases.
- Supply light variants instead of relying on CSS filters; this preserves multicolor identities.
- Keep the icon readable at small sizes and avoid fine text in `favicon.png`.
- Export optimized sRGB PNGs and remove unnecessary metadata.
- Update the site name, deployment URL, social-image description, and loader settings in `src/site.mjs`.
- Run `node build.mjs` and `node scripts/check.mjs` after replacement.

The checker validates that every required file exists and has the expected dimensions. If an adopting brand needs different aspect ratios, update the dimensions in the CSS component rules and checker at the same time.
