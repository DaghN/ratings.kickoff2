# Agent entry point — KOOL ratings site

**For Cursor agents and new chats.** Repo overview for humans: [`README.md`](README.md). Dagh’s habit: end many sessions with **“update docs”** — that means [Part A + maybe Part B in `docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md), not “database only.”

---

## New chat — read this first

Cold start (do **before** coding unless Dagh pasted full context):

| Order | File | Why |
|-------|------|-----|
| 1 | [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) | Current focus, deploy path, recent work |
| 2 | [`AGENTS.md`](AGENTS.md) | Agent rituals, authority, and finish rules |
| 3 | [`docs/PROJECT_MAP.md`](docs/PROJECT_MAP.md) | What repo is; where code and docs live |
| 4 | Dagh’s message | Today’s goal wins over stale docs |
| 5 | Feature spec **if** obvious | **Amiga player chronologies (inventory)** → [`amiga-player-chronologies-policy.md`](docs/amiga-player-chronologies-policy.md) · profile entry [`player-profile-stat-links-policy.md`](docs/player-profile-stat-links-policy.md). **Amiga profile mosaic stat links (Track B)** → [`player-profile-stat-links-policy.md`](docs/player-profile-stat-links-policy.md) + [`amiga-profile-mosaic-stat-links-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/amiga-profile-mosaic-stat-links-STARTER-PROMPT.md). **K2 LB server-side sort (Track A)** → [`k2-lb-ssr-sort-policy.md`](docs/k2-lb-ssr-sort-policy.md) + plan + [`k2-lb-ssr-sort-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/k2-lb-ssr-sort-STARTER-PROMPT.md). **Amiga pull / push staging** → run `pull_ko2amiga_from_staging.ps1 -Force` or `export_ko2amiga_work.ps1` per [`amiga-staging-handoff.md`](docs/amiga-staging-handoff.md) · policy [`amiga-staging-authority-policy.md`](docs/amiga-staging-authority-policy.md). Work DB / simul → **`docs/work-db-prepare.md`** + **`docs/coordination/ops-simul-runbook.md`**. Post-game PHP → **`docs/post-game-php-development.md`** + `ops/run_process_game.php`. Cutover / prod → **`docs/coordination/cutover-readiness.md`** (not batch REP scripts). **Amiga forward ground / simul** → **`docs/amiga-modern-ground-platform.md`** (living ground; day 0 bootstrap; Access retired). **Amiga modern video (V-1)** → **`docs/amiga-modern-video-policy.md`**. **Amiga live ops (staging authority, repair, media)** → **`docs/amiga-live-ops-platform.md`** + **`docs/amiga-live-ops-practice-track.md`** (drill-first implementation). **Running vs official boundary (score entry / Make official):** **`docs/amiga-running-tournament-boundary-policy.md`** + **`docs/amiga-running-tournament-boundary-inventory.md`** + **`docs/amiga-running-tournament-boundary-implementation-plan.md`**. **Amiga format scoring contract (L4b vs L5 standings):** **`docs/amiga-format-scoring-contract-policy.md`** + **`docs/amiga-format-scoring-contract-implementation-plan.md`**. **Amiga tournament structure display (legacy imprint + knockout UI end state):** **`docs/amiga-tournament-structure-display-policy.md`**. **Amiga time travel + with-player URL carry** → **`docs/amiga-time-travel-policy.md`** §3 + **`docs/with-player-stepper-policy.md`**. **Amiga Countries hub** → **`docs/amiga-countries-hub-policy.md`**. **Amiga country registry** (canonical tokens, L3 normalization, JSON registry) → **`docs/amiga-country-registry-policy.md`** + **`docs/amiga-country-registry-implementation-plan.md`**. **New page / tab / mode (URL + placement):** **`docs/k2-page-structure-checklist.md`** then **`docs/url-routes.md`** § Sub-hub navigation + **`docs/navigation-model.md`** NM3–NM4 — **do not** `?view=` / `?wing=` / `?tab=` for navigation. **Tables (sortable / wide / filter reload):** **`docs/k2-table-implementation-checklist.md`** then grep a reference from §1 — **do not** bare `k2_table_js_enqueue()` on new sortable pages. **Table entity links / Amiga inline flags:** **`docs/k2-table-entity-links-policy.md`**. **Tooltips (table headers, charts, controls):** **`docs/k2-tooltip-policy.md`** — **`data-k2-help`**, not native `title` on `<th>`. **Mobile / smartphone audits or responsive work:** **`docs/k2-mobile-smartphone-policy.md`** — dense tables intentional (read-first + pinch); not card-reflow debt. **Page chrome nav (hub / wing / sub-nav / hub shell):** **`docs/k2-nav-implementation-checklist.md`** then copy nearest include from §1 — **do not** ad-hoc nav markup or spacing. For *which pages get an active pill* and *where a detail page lives*, read **`docs/navigation-model.md`** (NM1–NM6: hub bar always present; entity pages at realm root, no active pill). **Page JS boot (widgets / charts / filters):** prefer `k2OnPageReady` (shim `js/k2-page-boot.js`); Turbo removed Jun 2026 ([`docs/k2-jukebox-popup.md`](docs/k2-jukebox-popup.md)) — **do not** bare `DOMContentLoaded` only. **Creative / product brainstorm** → [`docs/creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) (not default read). Else e.g. `docs/STATUS_PAGE_DATA.md`, `docs/activity-charts.md` |
| 6 | [`docs/design-direction.md`](docs/design-direction.md) | If UI/theme work |
| 7 | [`docs/url-routes.md`](docs/url-routes.md) | Route map; § Sub-hub navigation when registering paths |

**Do not** read all of `docs/coordination/` up front. Open [`docs/prod-coordination.md`](docs/prod-coordination.md) only when the task touches **stored ladder truth** or Steve/migration.

**Run commands:** [`docs/OPERATIONS_QUICK_START.md`](docs/OPERATIONS_QUICK_START.md)

---

## Rituals

| Ritual | When | Doc |
|--------|------|-----|
| **Bootstrap** | Start of chat / new task | This file + PROJECT_MAP + MEMORY |
| **Agent track** | Multi-session feature — locked policy, numbered slices | [`docs/orchestration/agent-track-playbook.md`](docs/orchestration/agent-track-playbook.md) — *doc · plan · prompt · slices* |
| **Update docs** | Dagh says it, or end of substantial slice | [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) — **Part A always**, **Part B if migration triggers** |

---

## Authority (conflicts)

1. Dagh’s latest message  
2. [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) — product taste  
3. [`docs/design-direction.md`](docs/design-direction.md) — visuals  
4. [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) — logistics (offer fixes if wrong)  
5. [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) — how to record work  

---

## Migration awareness (background)

Prod ladder data is written by **Steve** (ground insert per game + periodic jobs). **Reference implementation:** PHP **`ops/dispatch.php`** (`ProcessCompletedGame`, `FinalizeUtcDay`) — see [`ladder-ops-platform.md`](docs/ladder-ops-platform.md) §2. Behaviour rules: [`website-data-contract.md`](docs/website-data-contract.md).

**Prod today:** live games use **PHP ops** derived post-game (cutover **2026-07-18**) — Steve inserts ground truth and invokes dispatch; **do not extend legacy C++**. Contract and writers live in this repo ([`ladder-ops-platform.md`](docs/ladder-ops-platform.md)).

**Performance / stored truth habit:** For DB-backed website work, default to stored/precomputed truth on hot paths (~73× faster than wide `ratedresults` scans at ~75k rows — see May 2026 evidence in prior MEMORY).

**Default question:** *What stored table should this value live in, and what does [`website-data-contract.md`](docs/website-data-contract.md) say for rebuild + post-game?* **Amiga** player×event stats: [`amiga-player-universe-contract.md`](docs/amiga-player-universe-contract.md) **§5.0** (same stored-truth habit; `participation` vs `standings` vs `rating_events`). Work/staging proof: **ops simul** after `migrate-work` — not batch `REP-xxx` or `*_rebuild.sql` on prod. **Do not** treat missing C++ snippets as incomplete features.

**One-line cutover rule:** Prep + **live PHP ops** are done (`kooldb1` simul, then live **2026-07-18**); retired dev batch/replay CLIs — [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md).

**Steve / live ops:** hosting, ground insert, invoke dispatch — [`post-dagh-live-story.md`](site/public_html/ops/docs/post-dagh-live-story.md). Hall of Fame records: [`records-post-game-exception.md`](docs/coordination/records-post-game-exception.md).

**Triggers to think about migration:** new DB columns/tables/indexes, `scripts/k2_rating_core/` or ops PHP edits, “store this on profile” vs compute in PHP, medals persistent on `playertable`, etc.

**Do not resurrect:** obsolete product ideas (e.g. legacy rating fade / PER-001) — if an old id appears, see [`docs/archive/retired-product-decisions.md`](docs/archive/retired-product-decisions.md) only.

**Then:** Part B of UPDATE_DOCS + [`feature-log.md`](docs/coordination/feature-log.md).

---

## Agent traps (grep / register misreads)

- **K2 page structure (new page / tab / mode)** — read [`k2-page-structure-checklist.md`](docs/k2-page-structure-checklist.md) before choosing paths; folder file per mode, query params for filters only — **never** new `?view=` / `?wing=` / `?tab=` for navigation. Feature policy docs defer URL shape to [`url-routes.md`](docs/url-routes.md). Placement: [`navigation-model.md`](docs/navigation-model.md) NM3–NM4.
- **K2 sortable tables** — read [`k2-table-implementation-checklist.md`](docs/k2-table-implementation-checklist.md), open §1 reference, copy stack; run `python scripts/audit_k2_table_compliance.py` before ship. **Never** bare `k2_table_js_enqueue()` on full-page sortable tables.
- **K2 LB URL landing sort (Track A)** — **complete** (Jul 2026): [`k2-lb-ssr-sort-policy.md`](docs/k2-lb-ssr-sort-policy.md) **Implemented**; all hub wings SSR on `k2_sort` landing — **do not rewrite HoF hrefs**. New wings must copy `goals.php` pattern. Amiga rating LB: Δ always visible + `AMIGA_LB_RATING_COL_*` (SSR-13). Profile mosaic links = Track B ([`player-profile-stat-links-policy.md`](docs/player-profile-stat-links-policy.md)).
- **K2 quiet date columns** — avoid **first-load date blast**; prefer **ID default**; fallback **`k2-table-col-quiet-date`** + `data-k2-quiet-default-sort-cols` only when table must open Date-sorted without game ID ([`k2-table-quiet-date-column-policy.md`](docs/k2-table-quiet-date-column-policy.md)). User-chosen Date sort → normal emphasis. **Shipped** on five Amiga catalog/chronology tables — plan § Opt-in surfaces.
- **Player profile stat links (hero + mosaic)** — read [`player-profile-stat-links-policy.md`](docs/player-profile-stat-links-policy.md) before wiring clickable career numbers on player profiles. **Amiga chronology inventories (Opponents + planned victim kinds):** [`amiga-player-chronologies-policy.md`](docs/amiga-player-chronologies-policy.md). **Inverse-count TT (shipped):** [`amiga-player-inverse-count-timeline-policy.md`](docs/amiga-player-inverse-count-timeline-policy.md) — mosaic/LB read sparse changelog, not hero snapshot columns; chronology pointer scan unchanged. **Track B handoff:** [`amiga-profile-mosaic-stat-links-STARTER-PROMPT.md`](docs/orchestration/agent-handoffs/amiga-profile-mosaic-stat-links-STARTER-PROMPT.md). **Inventory-first** (games tab, tournament history, victim lists); **leaderboards** for comparison; rank/rating remain LB row anchors. Do not default profile stats to LB row links. Mosaic **Opponents count** → chronology Made it, **not** Opponents wing H2H.
- **K2 tooltips** — read [`k2-tooltip-policy.md`](docs/k2-tooltip-policy.md); column/control help = `data-k2-help` (+ optional `data-k2-tooltip-label`) + `k2-table.js` or `chart-theme.js` — **never** native `title` on `<th>` for user-visible help. Copy `games/recent.php` or `lb_column_help.php`.
- **K2 mobile / smartphone** — read [`k2-mobile-smartphone-policy.md`](docs/k2-mobile-smartphone-policy.md) before audits or "fix mobile" slices. **Dense tables on phone = deliberate** (read-first, pinch-second); do **not** treat as catastrophic deficiency or propose card reflow by default. Known gaps: chart tap-to-tooltip, hover+click tap/double-tap consistency, nav touch targets — fix only when asked.
- **K2 page chrome nav** — read [`k2-nav-implementation-checklist.md`](docs/k2-nav-implementation-checklist.md), copy nearest include from §1; spacing = `--k2-nav-gap` bottom-only ([`nav-spacing-policy.md`](docs/nav-spacing-policy.md)). **Filter composite stacks (Tier 1):** [`filter-stack-spacing-policy.md`](docs/filter-stack-spacing-policy.md) — same bottom-only rule; **no wrapper vertical `gap`**. **Never** `:has(+ …)` spacing lists or content `margin-top` for nav/filter stack gaps. **Active pill / page placement:** [`navigation-model.md`](docs/navigation-model.md) NM1–NM7 — hub bar always present; **entity pages** (game/player/tournament/country/milestone) live at the realm root in a **singular** namespace with **no active pill**; **domain mini-universes** (World Cups, Countries) are hub tabs, not Leaderboard wings (NM7).
- **Amiga time travel + with-player URL carry** — read [`amiga-time-travel-policy.md`](docs/amiga-time-travel-policy.md) §3 · [`with-player-stepper-policy.md`](docs/with-player-stepper-policy.md). **TT chrome sticky:** [`amiga-tt-chrome-dock-policy.md`](docs/amiga-tt-chrome-dock-policy.md) §2.4 + [`amiga-tt-chrome-sticky-invariants.md`](docs/amiga-tt-chrome-sticky-invariants.md) (failures/symptoms only — fixes go in handoffs). **Cutoff lens:** `as=` on wired reads; internal links via `amiga_url_with_context()` / `k2_amiga_route()`. **With-player (opt-in, per-surface params):** `as_with=` (TT Event ribbon), `id_with=` (tournament chevrons), `start_with=` (league) — propagate only within that surface’s link family; **never drop silently** when preserving `as=`. **PHP:** `amiga_url_with_context()` appends request `as_with`; `amiga_url_present()` strips `as=` and `as_with`. **JS client-built navigation** (header search, H2H chart → games): [`k2-amiga-time-travel-url.js`](site/public_html/js/k2-amiga-time-travel-url.js). Chart/API fetches need **`as=` only** (cutoff), not with-player params. **Never** auto-enable with-player from page context (T18 retired).
- **Page JS boot** — **Turbo removed Jun 2026; site uses normal full-page loads.** Gapless music is a **popup window** ([`k2-jukebox-popup.md`](docs/k2-jukebox-popup.md)), not Turbo. Still prefer `k2OnPageReady` / `k2PageReady` (shim `js/k2-page-boot.js`) + idempotent root guards over bare `DOMContentLoaded` (future-proof, idempotent). The old Turbo hazards no longer apply — [`k2-turbo-page-init-checklist.md`](docs/k2-turbo-page-init-checklist.md) is historical. **Hash anchor links** (`#player`, `#k2-lb-table`, `#k2-country-roster`, …): use `k2_carry_scroll_restore.php`; do not add page-local scroll JS.
- **Retired dev scripts** — track **complete** (Jun 2026). Stubs/archives only — inventory [`DEAD_SURFACE.md`](docs/DEAD_SURFACE.md). Holy online fill = PHP ops; Amiga forward = **simul** on **`ko2amiga_work`** (oracle **`prove`** frozen). Policy: [`obsolete-dev-scripts-retirement-policy.md`](docs/obsolete-dev-scripts-retirement-policy.md).
- **Work DB = simul only** — `ko2unity_work` / `kooldb1`: **`zero-derived` → `run_ops_sim.php` → `verify`**. No `rebuild-all`, no ad-hoc repair, no “avoid re-simul” patches. [`work-db-prepare.md`](docs/work-db-prepare.md) §1.5.
- **Cutover prep is done** on `kooldb1` / `ko2unity_work` via **ops simul** — do not assign batch **`REP-xxx`** or `*_rebuild.sql` on prod. Historical log: [`archive/replay-register-2026-05.md`](docs/archive/replay-register-2026-05.md).
- **`kooldb`** (May 2026) is **frozen** — forward staging work DB = **`kooldb1`**; pristine clone = **`kooldb2`**.
- **`feature-log.md`:** online ops cutover rows marked **Done (2026-07-18)** in Jul 2026 docs sweep; Amiga staging sync rows remain a **separate** track. See [`cutover-readiness.md`](docs/coordination/cutover-readiness.md).
- **`docs/archive/`** and May handoffs = **history** — do not run `staging-scripts/` PHP (folder **removed** from repo and remote Jun 2026); use **`site/public_html/ops/`**.
- **New SCH DDL** → `site/public_html/ops/sql/migrations/` — not `schema/migrations/` (redirect only).
- **`docs/STAGING_REPLAY.md`** is an **archive stub** — not the current staging runbook ([`cutover-readiness.md`](docs/coordination/cutover-readiness.md)).
- **Amiga staging** — **staged `ko2amiga_db` = prod**; **local `ko2amiga_work` = repair shop** (pull → simul/repair → push). Policy: [`amiga-staging-authority-policy.md`](docs/amiga-staging-authority-policy.md). **Pull from staged:** `powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force`. **Push to staged:** run `export_ko2amiga_work.ps1` after **simul** (script audits export table list vs `schema_bundles` before dump); remind **pull from staged first** if community events may have changed since last sync. Registry: `scripts/amiga/staging_export_tables.py` · `data/amiga/staging_export_tables.json`. Runbook: [`amiga-staging-handoff.md`](docs/amiga-staging-handoff.md). Oracle rebuild only: `setup_ko2amiga_db.ps1`. Do **not** ping Steve for routine Amiga SQL re-import.
- **Amiga work DB kill switches** — [`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) §0.1: **simul** ≠ **seed-work** / **prove** / **import-witness**; full **`apply-structure-work`** on living L4 needs destroy consent; video `start_sec` edits = **shared** CSV + align (never deploy-only JSON); **`promote-video-deploy`** always aligns; nuclear ops need `--i-mean-destroy-work` + `--confirm-destroy=destroy-ko2amiga-work`.
- **Amiga derived repair** — batch `*-rebuild` CLIs retired Jun 2026; **writer regression** → **simul** on living ground ([`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) §9) — not full `import-witness` nuclear prove. **Staging community mistakes / cancel / media** → [`amiga-live-ops-platform.md`](docs/amiga-live-ops-platform.md) + [`amiga-live-ops-practice-track.md`](docs/amiga-live-ops-practice-track.md) (**serial feedback** — one issue at a time; anchored repair, not prove-first). Policy: [`amiga-derived-write-policy.md`](docs/amiga-derived-write-policy.md).
- **Amiga catalog materialize (long tail)** — **not bulk-first** — read [`amiga-tournament-structure-manual-materialize-runbook.md`](docs/amiga-tournament-structure-manual-materialize-runbook.md): triage → unblock `tier_b_non_wc_register.py` if needed → `materialize --tournament-id` on **`ko2amiga_work`** → `backfill-standings-stage-id` → log in [`amiga-tournament-structure-review-queue.md`](docs/amiga-tournament-structure-review-queue.md). Disposition `handler` alone does **not** permit materialize. **Knockout display / bracket UI** — end state [`amiga-tournament-structure-display-policy.md`](docs/amiga-tournament-structure-display-policy.md); do not extend phase-regex layout (`amiga_tournament_knockout_phase_bucket`).
- **Amiga forward ground / simul / modern compartment** → **`docs/amiga-modern-ground-platform.md`** first; Access L0–L5 docs **archived** — [`docs/archive/amiga-access-pipeline-index.md`](docs/archive/amiga-access-pipeline-index.md).
- **Amiga modern / legacy boundary (MG11)** — forward code **`scripts/amiga/modern/`** only; **do not mutate** `prove.py`, `import_access.py`, or import from legacy prove path — **copy + rename** into `modern/` first ([`amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) §5.1). **Forbidden:** manual `mysql ALTER`, new numbered `sql/047_*.sql`, one-off `apply_schema_*` on live DB ([`amiga-ground-layers-policy.md`](docs/amiga-ground-layers-policy.md) G12; [`amiga-data-contract.md`](docs/amiga-data-contract.md)). **RTB (shipped Jul 2026):** running result cols in `sql/structure/006_tournament_fixtures.sql` — [`amiga-running-tournament-boundary-implementation-plan.md`](docs/amiga-running-tournament-boundary-implementation-plan.md) § DDL. Precedent: `047_player_source.sql` removed — PC-1 uses `ground/001_core.sql` only.
- **DB continuity in git** — sealed database backups **belong in git** (Amiga `data/amiga/day0/`, `data/amiga/checkpoints/`; online the same habit is wanted). Working `data/dumps/` / staging `_import/` SQL may stay gitignored until sealed. **Never** treat “dumps must stay out of git” as policy — that was an agent misunderstanding. **Secrets** (credentials, `*.local.php`, ops ini) still stay out. Authority: [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) · [`README.md`](README.md) Continuity · [`data/dumps/README.md`](data/dumps/README.md).
- **Amiga TT query performance** — slow TT page / "vanishes then draws slowly" = blocking queries past the 700 ms cloak budget, not a chrome bug. Read [`amiga-tt-query-optimization-playbook.md`](docs/amiga-tt-query-optimization-playbook.md): probe first, apply the fix patterns (shared `amiga_lb_snapshot_from_sql()` narrow join, dense-event equality, metric-first index, request cache), **full row-set parity oracle before ship**. Never write a new `SELECT snap.*` ROW_NUMBER window.

---

## Optional opener for Dagh

**Not required** — rules autoload bootstrap. If you want extra steer:

```
Today: [one line feature goal]
```

Example: `Status league — previous month medals column alignment.`

---

## Subagents

When launching subagents (Task tool, parallel agents, etc.):

- **Use `composer-2.5`** for subagents.
- **Never use `composer-2.5-fast`** for subagents.

---

## Autoload (you do **not** activate this)

**`.cursor/rules/kool-workspace.mdc`** has `alwaysApply: true`.

That means Cursor **automatically** attaches it to **every Agent chat** in this workspace. You do **not** need to say “read AGENTS.md”, “follow rules”, or “update docs” for the habits to apply — though **“update docs”** still works as an extra nudge.

| Habit | Automatic? | Your part |
|-------|------------|-----------|
| Read MEMORY + map at chat start | **Rule says yes** — agent should Read those files first | Just say the feature you want |
| Record session in docs when done | **Same turn as shipping code** — Part A of UPDATE_DOCS (see below) | Optional “update docs”; not required if agent already finished in that turn |
| Migration registers | **Only if** the slice changed stored DB truth | Nothing extra |

**Limits (honest):** Rules are instructions, not a hard program. Agents **cannot** run after you close the chat — so finish must happen **when they implement**, not when you archive.

**If you close right after they shipped code** and they already ran Part A in that reply, you are fine. **If you close mid-WIP**, nothing will run — expected.

**Safety habit before archive (5 sec):** If their last message does not mention MEMORY/docs updated, say **update docs** once, wait for one reply, then archive. Optional, not required when they already reported doc updates.

**Limits:** If an agent skips reads or finish, say “read MEMORY first” or “update docs” once. Tuning: **`kool-workspace.mdc`**.

**Check in Cursor:** Settings → Rules (or Project Rules) — you should see **kool-workspace** listed for this repo. No toggle required if the file is committed under `.cursor/rules/`.

---

## For Dagh: is the repo ready?

| Goal | Ready? |
|------|--------|
| Start chats with context | **Yes** — [`README.md`](README.md) → AGENTS + PROJECT_MAP + MEMORY |
| End sessions with “update docs” | **Yes** — UPDATE_DOCS Part A (always) + B (conditional) |
| Migration future-proofing without daily weight | **Yes** — registers + feature-log when L1+ |
| One-click prod cutover | **No** — still Steve + registers when you choose |

Hub: [`docs/prod-coordination.md`](docs/prod-coordination.md)
