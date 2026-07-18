# Dead surface audit

**Jun 2026** — grep-backed pass: remove unused runtime assets and one-shot migration scripts; keep schema throwaways and bookmark redirects.

**Related:** [`self-hosted-assets.md`](self-hosted-assets.md) (runtime inventory).

---

## Removed (Jul 2026 — theme.css dead-token pass)

Full-corpus audit (`scripts/audit_theme_css_dead_tokens.py`: every class/custom-prop in `theme.css` vs all PHP/JS/CSS under `site/public_html/`), then manual verification of dynamic construction and git history. `theme.css` 7,052 → 6,691 lines (192.5 → 185.1 KB). Smoke: status, rating LB, games recent/highlights, profile, Amiga rating LB, join, game — all OK.

| Tokens removed | Why safe |
|------|----------|
| `.filtercell` th rules, `.nohovercell:hover`, `.goalcounter`, `.tbody_header`, `.pagelink`, `.currentpage`, `select.table-autofilter` | Legacy elolist-era table compat; no PHP/JS emits them since elolist retirement (`:not(.filtercell)/:not(.nohovercell)` left inert inside surviving th:hover rule) |
| `.k2-hub-chapter__texture`, `.k2-hub-chapter__nav` | Hub chapter include emits title/lede/list only |
| `.k2-wordmark__sub`, `.k2-portal-link`, `.k2-site-header__utility-link` | Header markup pruned with theme-lab removal (`site_header.php` uses main/link/brand/links/search) |
| `.k2-page-nav__up` | Wing up-link folded into site_header nav Jun 2026 (see Jun pass) |
| `.k2-lb-wc-tabs` (2 selector-list lines) | WC LB wing retired Jun 2026; legacy URLs 302 |
| `.k2-player-nav__tune` (selector-list line) | Tint picker moved off player nav (`k2-hub-tabs__tune`/`k2-nav-tune` survive) |
| `.k2-table-cell--pad-left-{sm,xl,xxl,xxxl}` | Only xs/md/lg emitted; no dynamic `pad-left-' .` construction found |
| `.k2-join-page__{lede,actions,footer,cta,cta--primary,grid,prose--muted,link-note,details,summary,details-body,faq,faq-item,eval-banner}` | Current `join_page_section.php` (Play & Setup) uses hero/title/prose/card/steps/link-list/signoff/video only |
| `.k2-chart-frame--short` | Only bare frame + `--tall` in markup; no JS size-class construction |
| `.k2-player-opponents__h2h-placeholder` | H2H shipped; placeholder markup gone |
| `.k2-player-hero__{stat--country,stat-value--country,country-link,country-flag}` | Hero Country stat column removed in `d265237` (inline flag beside name instead); glance JS builds rank/accent/milestones/medal variants only |
| `.k2-heritage-box__note`, `.k2-status-tagline`, `.k2-card__title`, `.k2-card__hint`, `.k2-hub-panel__hint` | Status v1.1/v1.2 leftovers; markup uses art/caption, `k2-card` + `k2-panel-heading` |
| `.server-activity-summary__texture` | Texture sentence merged into `__lede` paragraphs (`ecf74db`) |
| `.k2-status-panel__{head--league,subtitle}`, `.k2-status-league-head__title-row` | Legacy league panels retired by Status Leagues Phase 1 (`9904d3b`) |
| `--k2-h2h2-red-ring` | Custom prop with zero `var()` consumers anywhere |

**Kept despite zero grep hits (do not delete blind):** `k2-games-highlights-col--*`, `k2-amiga-wc-podium-th--{gold,silver,bronze}`, `k2-country-hero__medal-value--{gold,silver,bronze}` — **built dynamically in PHP/JS** (`k2_games_highlights_col_classes()`, `amiga_wc_podium_th_markup()`, medal `variant` interpolation). `.turbo-progress-bar` — deliberately restored Jul 1 2026 for stale phone caches. `.k2-status-room__now-col`, `.k2-status-room__panel-league` — zero hits but protected `.k2-status-room*` family; grep-only proof, left in place.

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

---

## Retired dev scripts (Jun 2026)

**Track:** [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) — **complete**. Stubs exit 1 and print the holy-path pointer. **Do not** restore as runbook steps.

| Path | Was | Use instead |
|------|-----|-------------|
| `scripts/rebuild_website_derived_data_local.ps1` | Batch SQL chain on `ko2unity_db` | Work: `zero-derived` → `run_ops_sim.php` → `run_verify_ops_sim.php` |
| `scripts/rebuild_activity_wing_local.ps1` | Activity-wing batch slice | Same (ops simul) |
| `scripts/rebuild_player_period_games_local.ps1` | Period-games batch slice | Same |
| `scripts/run_local_replay.ps1` | Python ladder `run` on dev DB | Work simul; dev DB → re-import dump |
| `run_staging_ladder_replay.sh` (root stub — **removed Jul 2026**) | Staging one-shot replay | Archived only: `docs/archive/run_staging_ladder_replay.sh` |
| `python -m scripts.ladder run` / `reset` / `replay` | Full-memory replay CLI | `run_ops_sim.php` (online) · `scripts.amiga prove` (Amiga) |
| `python -m scripts.work_prepare` | Legacy prepare / A/B oracle | `php ops/run_prepare.php` verbs |
| `scripts/ladder/sql/archive/batch-2026-05/` (stub README) | Batch `*_rebuild.sql` | Archived: `docs/archive/batch-rebuild-sql-2026-05/` |
| `scripts/ladder/` replay modules | `engine.py`, `milestones.py`, … | Archived: `docs/archive/ladder-retired-2026-06/` · library: `scripts/k2_rating_core/` |
| `scripts/work_prepare/` (except `paths.py`) | Prepare / zero / ab oracle | Archived: `docs/archive/work-prepare-retired-2026-06/` |

**Kept (not dead):** `scripts/k2_rating_core/` · `scripts/amiga/` · `scripts/prepare_local_work_db.ps1` · `scripts/refresh_local_work_db.ps1` (→ PHP) · `scripts/ladder/sql/generalstatstable.sql` · `scripts/ladder/__init__.py` (shim).

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
| ~~Legacy CSS tokens with zero grep hits~~ | **Done Jul 2026** — full `theme.css` pass (see § Removed Jul 2026 above); audit script `scripts/audit_theme_css_dead_tokens.py` reusable for v2 (page-scoped sheets) |
| ~~`body.k2-activity-charts-lab` rules in `theme.css`~~ | **Done** Jun 2026 — grep found zero hits (Activity v2); row removed from candidates |

---

*Re-run: grep `site/public_html/js/` for each file vs `src=` in PHP/includes; grep `scripts/` root for doc links before delete.*
