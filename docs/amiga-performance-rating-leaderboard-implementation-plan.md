# Amiga performance rating leaderboard — implementation plan

**Status:** Complete (Jun 2026)  
**Policy:** [`amiga-performance-rating-leaderboard-policy.md`](amiga-performance-rating-leaderboard-policy.md)

---

## Goal

Folder **Perf. rating** into **Best · Top 100 · Perfect** sub-wings with shared table + W-D-L columns; time-travel reads; legacy URL redirect. **No DDL.**

---

## Slice map

| Slice | Goal |
|-------|------|
| **0** | Policy locked | — (done) |
| **1** | Routes + folder shell + segment nav + redirects | Browser: three tabs, `as=` preserved |
| **2** | Read lib: three row functions (present + cutoff) | SQL spot checks |
| **3** | Best + Top 100 pages (refactor current table) | Match today + W-D-L |
| **4** | Perfect page (∞ column, default sort) | Browser + TT |
| **5** | Ledes, tooltips, table audit, docs closure | `k2_table` compliance script |

---

## Files (expected)

| Area | Path |
|------|------|
| Nav | `includes/amiga_lb_performance_rating_nav.php` |
| Read lib | `includes/amiga_player_tournament_lib.php`, `includes/amiga_lb_snapshot_lib.php` |
| Pages | `amiga/leaderboards/performance-rating/{index,best,top,perfect}.php` |
| Redirect | `amiga/leaderboards/performance-rating.php` → `best.php` |
| Routes | `includes/k2_amiga_routes.php`, `docs/url-routes.md` |
| LB nav href | `includes/amiga_lb_nav.php` → folder default |
| Profile link | `amiga_profile_blocks.php` → `best.php` |
| Docs | `amiga-performance-rating.md` § Read paths |

---

## Verification

- Browser: three tabs; W-D-L matches player tournaments for sample rows
- Top 100: exactly 100 rows; 101st perf in corpus excluded
- Perfect: ∞ displays; sort newest-first; TT cutoff reduces rows
- `as=` on all segment links
- No footnotes rendered

---

## Agent prompt

Today: Amiga perf-rating LB — slice N per `amiga-performance-rating-leaderboard-implementation-plan.md`.