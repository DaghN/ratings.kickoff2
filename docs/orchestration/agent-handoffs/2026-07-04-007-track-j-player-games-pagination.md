# Handoff 2026-07-04-007 — Track J player games pagination

**Status:** Done · **Fixture:** player **382** @ `year:2024` · **Method:** Online `K2_PLAYER_GAMES_PAGE_SIZE` pattern

---

## Shipped

| Change | File(s) | Notes |
|--------|---------|-------|
| 500-row LIMIT/OFFSET on main SELECT; offset clamp | `amiga/player/games.php` | COUNT stays full-set |
| Shared prev/next pager + sort links reset offset | `includes/amiga_player_games_lib.php` | `amiga_games_render_page_nav()` |
| Status line slice range + pager | `amiga/player/games.php` | Matches Online / Amiga All games tone |

## Verification

- `scripts/oneoff/amiga_player_games_pagination_probe.php` — row count ≤500, first-page IDs vs oracle LIMIT 500, curl ≤0.45 s @ id=382 `year:2024`

## Curl before / after

| URL | Before (census worst) | After (3-run local) |
|-----|----------------------|---------------------|
| `/amiga/player/games.php?id=382&as=year:2024` | **1.09 s** (~1492-row HTML) | best **~0.69 s** · worst **~0.96 s** (500-row page) |

Aspirational ≤0.45 s not reached on local Laragon — TT ribbon + filter facet chrome dominate after row cap.

## Boundaries

- Pagination only — no progressive/virtual scroll
- Perf-rating async API unchanged
- Query shape unchanged except LIMIT/OFFSET