# Dead surface audit

**Jun 2026** — grep-backed pass: remove unused runtime assets and one-shot migration scripts; keep schema throwaways and bookmark redirects.

**Related:** [`self-hosted-assets.md`](self-hosted-assets.md) (runtime inventory).

---

## Removed (this pass)

| Item | Why safe |
|------|----------|
| `site/public_html/js/elolist.js` | No `src=` references; table cloak in `theme.css` + `k2-table.js` |
| `site/public_html/js/status-league-toggle.js` | Status Leagues Phase 1 replaced legacy four-panel stack; no PHP loads it |
| `site/public_html/status-realm-lab.php` (body) | **302 → `status.php`**; realm decision in production `site_header.php` (switcher hidden until Amiga) |
| `theme.css` — `.k2-realm-lab-*`, `.k2-status-league-toggle*` | Only used by removed lab / legacy league UI |
| `scripts/wire_k2_theme_pages.py` | One-shot theme wiring (May 2026); pages already migrated |
| `scripts/patch_k2_head.py` | One-shot head patch |
| `scripts/wire_phase_a_nav.py` | One-shot hub nav wiring |
| `scripts/simplify_individual1_layout.py` | One-shot profile layout |
| `scripts/patch-activity-charts.js` | Patched deleted `server-*-chart.js` boot files (Activity v2) |
| `scripts/test_daily_active_perf.php` | Local perf probe; findings recorded in MEMORY / contract habit |

**Deploy:** WinSCP sync `site/public_html/` (JS deletes, `status-realm-lab.php`, `theme.css`). Hard refresh.

---

## Kept on purpose

| Item | Role |
|------|------|
| `server1-charts-lab.php` | **302 → `server1.php`** (old Activity lab bookmarks) |
| `status-realm-lab.php` | **302 → `status.php`** (old realm lab bookmarks) |
| `scripts/throwaway_*.php` | Schema snapshot / index apply via browser; documented in `ratedresults-schema.md`, `LOCAL_DEV.md`, `one-off-register.md` — **not** in default `site/public_html/` sync |
| `site/public_html/staging-scripts/` | Server cutover backlog — retire per [`coordination/staging-scripts-inventory.md`](coordination/staging-scripts-inventory.md) (separate slice) |
| `scripts/finalize_league_periods.php` | Thin delegate → `ops/run_finalize_league.php` (documented deprecated) |

---

## Next dead-surface candidates (not done here)

| Candidate | Blocker / note |
|-----------|----------------|
| Empty `staging-scripts/` on server | Execute inventory + Steve confirm |
| `scripts/oneoff/` rows marked Archived in register | Delete script files after grep |
| Legacy CSS tokens with zero grep hits | Careful pass on `theme.css` only |
| `body.k2-activity-charts-lab` rules in `theme.css` | Grep — likely orphan after Activity v2 (verify before delete) |

---

*Re-run: grep `site/public_html/js/` for each file vs `src=` in PHP/includes; grep `scripts/` root for doc links before delete.*
