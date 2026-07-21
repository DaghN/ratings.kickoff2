# Open Graph share cards

| File | Size | Use |
|------|------|-----|
| `ratings-default.jpg` | 1200×630 (~130 KB) | Site-wide default (WhatsApp-friendly JPEG) |
| `ratings-default.png` | 1200×630 | Lossless archive |
| `ratings-grok-source.png` | full Imagine export | Source archive |

Pages may override title/description before `k2_head.php` via `$k2OgTitle` / `$k2MetaDescription`.

**Root share card:** `/` redirects to `status.php`, so Status uses the **site-wide default** OG copy (`Kick Off 2 ratings` / `Live Kick Off 2 ladder and Amiga 500 statistics.`) — not Status-specific wording. After changing live meta, refresh WhatsApp/Facebook via [Sharing Debugger](https://developers.facebook.com/tools/debug/) → Scrape Again.