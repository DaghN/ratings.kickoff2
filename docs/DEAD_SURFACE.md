# Dead surface audit

**Jun 2026** — grep-backed pass: remove unused runtime assets and one-shot migration scripts; keep schema throwaways and bookmark redirects.

**Related:** [`self-hosted-assets.md`](self-hosted-assets.md) (runtime inventory).

---

## Removed (this pass)

| Item | Why safe |
|------|----------|
| `site/public_html/js/elolist.js` | No `src=` references; table cloak in `theme.css` + `k2-table.js` |
| `site/public_html/js/status-league-toggle.js` | Status Leagues Phase 1 replaced legacy four-panel stack; no PHP loads it |
| `site/public_html/status-realm-lab.php` (body) | **302 → `status.php`**; realm header lab retired |
| `theme.css` — `.k2-realm-lab-*`, `.k2-status-league-toggle*` | Only used by removed lab / legacy league UI |
| `scripts/wire_k2_theme_pages.py` | One-shot theme wiring (May 2026); pages already migrated |
| `scripts/patch_k2_head.py` | One-shot head patch |
| `scripts/wire_phase_a_nav.py` | One-shot hub nav wiring |
| `scripts/simplify_individual1_layout.py` | One-shot profile layout |
| `scripts/patch-activity-charts.js` | Patched deleted `server-*-chart.js` boot files (Activity v2) |
| `scripts/test_daily_active_perf.php` | Local perf probe; findings recorded in MEMORY / contract habit |
| `site_header.php` — Online/Amiga realm switcher markup | No Amiga realm UI; tint picker unchanged via `realm-switch.js` |
| `theme.css` — `.k2-site-header__realm*`, `.k2-realm-switch*` | Orphan after header markup removed |
| `realm-switch.js` — realm UI + `k2-realm` localStorage | File kept for tint pills only; `theme_boot_head.php` no longer reads realm |
| `includes/peak_period_leaderboards_section.php` | Activity v1-era preview; zero runtime includes |
| `includes/period_activity_leaderboards_section.php` | Activity v1-era preview; superseded by stored `player_period_games` + LB UI |
| `includes/player_wing_up_link.php` | Wing « Leaderboards link folded into `site_header` nav (Jun 2026) |
| `js/activity-mode-toggle.js` | Unloaded after Activity charts v2; file had zero `src=` refs |
| `player-feast-sections.css` — `.pm3-rivalry-teaser*` | Rivalry placeholder removed from profile (Jun 2026) |
| `theme.css` — `.k2-status-bridge*` | Status room grid iteration leftover; markup uses `k2-status-room` panels |

**Deploy:** WinSCP sync `site/public_html/` (JS deletes, `status-realm-lab.php`, `theme.css`). Hard refresh.

**Re-shipped Jun 2026:** header realm switcher (`includes/realm_switcher.php`, `.k2-realm-switch*` in `theme.css`) and cross-realm header search (`realm=all` API). Tint picker still via `realm-switch.js` only.

---

## Kept on purpose

| Item | Role |
|------|------|
| `server1-charts-lab.php` | **302 → `activity.php`** (old Activity lab bookmarks) |
| `status-realm-lab.php` | **302 → `status.php`** (old realm lab bookmarks) |
| `scripts/throwaway_*.php` | Schema snapshot / index apply via browser; documented in `ratedresults-schema.md`, `LOCAL_DEV.md`, `one-off-register.md` — **not** in default `site/public_html/` sync |
| ~~`site/public_html/staging-scripts/`~~ | **Removed Jun 2026** — May 2026 cutover runners; ops replaces all paths ([`archive/staging-scripts-inventory.md`](archive/staging-scripts-inventory.md)) |
| `scripts/finalize_league_periods.php` | Thin delegate → `ops/run_finalize_league.php` (documented deprecated) |

---

## Remote deploy (confirmed Jun 2026)

| Item | Status |
|------|--------|
| `public_html/staging-scripts/` on staging | **Removed** — Dagh confirmed remote matches repo after WinSCP sync |

---

## Next dead-surface candidates (not done here)

| Candidate | Blocker / note |
|-----------|----------------|
| ~~Delete `staging-scripts/` on staging server~~ | **Done** Jun 2026 (local + remote) |
| `scripts/oneoff/` rows marked Archived in register | Delete script files after grep |
| Legacy CSS tokens with zero grep hits | Careful pass on `theme.css` only |
| ~~`body.k2-activity-charts-lab` rules in `theme.css`~~ | **Done** Jun 2026 — grep found zero hits (Activity v2); row removed from candidates |

---

*Re-run: grep `site/public_html/js/` for each file vs `src=` in PHP/includes; grep `scripts/` root for doc links before delete.*
