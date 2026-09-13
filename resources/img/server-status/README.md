# Error artwork

These files are the stable artwork contract for Laravel exception pages. Replace an image in place and preserve its filename so no Blade or configuration change is required.

| Status | File |
| --- | --- |
| 401 | `401-unauthorized.png` |
| 403 | `403-forbidden.png` |
| 404 | `404-not-found.png` |
| 419 | `419-page-expired.png` |
| 500 | `500-server-error.png` |
| 503 animated | `503-maintenance.gif` |
| 503 static fallback | `503-service-unavailable.png` |

Use transparent PNG or optimized GIF artwork with a similar visual footprint. Run `npm run build` to copy replacements into `public/build/img/server-status/`.

