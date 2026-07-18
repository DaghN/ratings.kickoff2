# Project map — scaffold for new chats

**Read this when entering the repo cold** (with `PROJECT_MEMORY.md`). Five-minute orientation.

---

## What this is

**KOOL Kick Off 2 ratings site** — PHP + MariaDB ladder/stats for online play. Dagh iterates locally: **`http://ratingskickoff.test`** (dev DB) and **`http://work.ratingskickoff.test`** (work DB); deploys PHP to **staging** via **WinSCP**; **production** coordinated with **Steve** later.

Not a greenfield app: legacy tables (`ratedresults`, `playertable`, …), dense stats UI, Chart.js APIs.

---

## Repo layout (where what is)

| Path | What |
|------|------|
| `site/public_html/` | **The website** — PHP pages, `api/`, `stylesheets/`, `js/`, `fonts/` |
| `site/public_html/ops/` | **Server operations** — `dispatch.php`, modules, SQL mirrors; [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) |
| `docs/self-hosted-assets.md` | **CDN audit** — what is self-hosted vs external (fonts, JS, YouTube embed) |
| `docs/DEAD_SURFACE.md` | **Removed / kept** runtime files; **§ Retired dev scripts** inventory (Jun 2026 track complete) |
| `site/config/` | DB config — `ko2unitydb_config.php` router; `*.local.php` gitignored |
| `site/public_html/amiga/` | **Amiga realm** — leaderboard, profile, staging SQL dump path |
| `scripts/amiga/modern/` | **Amiga forward ground** — simul, parity, video on **`ko2amiga_work`** |
| `scripts/amiga/` | **Amiga tooling** — verify oracles, DDL bundles, **frozen** legacy prove/import (oracle) |
| `scripts/k2_rating_core/` | **Shared Elo library** — Amiga replay/simul + PHP mirror reference |
| `scripts/ladder/` | **Deprecated shim** + GST DDL — see [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) |
| `docs/coordination/cutover-readiness.md` | **Prep done vs live cutover** — read before schema/replay registers |
| `site/public_html/ops/sql/migrations/` | Canonical SCH DDL (indexes, tables); see `ops-schema-migrations.md` |
| `docs/` | Specs, coordination, agent playbooks |
| **K2 page structure** (new page / tab / mode — folder paths, not `?view=` / `?wing=`) | **`docs/k2-page-structure-checklist.md`** — read before choosing URLs; then `url-routes.md` § Sub-hub navigation |
| `docs/k2-table-implementation-checklist.md` | **K2 tables** — mandatory before new/refactored sortable tables; run `scripts/audit_k2_table_compliance.py` for backlog |
| `docs/k2-tooltip-policy.md` | **K2 tooltips** — mandatory before table header/column help, chart hovers, or control tooltips; audit flags `<th title=` |
| `docs/k2-table-entity-links-policy.md` | **K2 table entity links** — player/tournament/country name helpers, Amiga inline flags; no flag-only Country columns |
| `docs/k2-table-quiet-date-column-policy.md` | **K2 quiet date** — avoid first-load date blast; ID default first; shipped on five Amiga tables (Jul 2026) |
| `docs/k2-lb-ssr-sort-policy.md` | **K2 LB SSR sort (Track A)** — hub wing `?k2_sort=` on first paint; HoF verify-only; plan + starter in `orchestration/agent-handoffs/` |
| `docs/player-profile-stat-links-policy.md` | **Profile stat links (Track B)** — inventory-first mosaic/hero; Amiga mosaic starter prompt in `orchestration/agent-handoffs/` |
| `docs/amiga-player-chronologies-policy.md` | **Amiga player chronologies** — unlock inventory (Opponents shipped; Victims kinds planned); Made it + Graphs |
| `docs/k2-nav-implementation-checklist.md` | **K2 page chrome nav** — mandatory before new wing/sub-nav/hub shell; spacing in `nav-spacing-policy.md`; **filter stacks** in `filter-stack-spacing-policy.md` |
| `docs/navigation-model.md` | **Hub-vs-entity invariants (NM1–NM6)** — which pages get an active pill; where entity pages live (realm root, singular namespace, no pill). Read before active-pill / page-placement decisions |
| `docs/present-layer-ia.md` | **Present layer & site completion** — News, pulse, Misc shelf, leaf pages, footer/about, path to shippable v1 (intent/policy; PL1–PL16) |
| `docs/k2-jukebox-popup.md` | **Gapless audio = popup window** (Turbo removed Jun 2026); FAB launcher + `BroadcastChannel`; centred window + raise/behind toggle |
| `docs/k2-turbo-page-init-checklist.md` | **Historical (Turbo removed Jun 2026).** Page JS boot now plain full loads — prefer `k2OnPageReady` (shim `js/k2-page-boot.js`) + idempotent guards. Carry-scroll / `#fragment` hash landing live in `includes/k2_carry_scroll_restore.php` (pre-paint cloak) |
| `data/dumps/` | Local SQL dump (gitignored) |
| `data/amiga/day0/` | L3 witness archive (git-tracked) |
| `data/amiga/checkpoints/` | Milestone **`ko2amiga_work`** git seals — [`data/amiga/checkpoints/README.md`](../data/amiga/checkpoints/README.md) |
| `README.md` | Human repo overview — website + DB/ops backbone, continuity, dive-deeper links (agents → `AGENTS.md`) |
| `PROJECT_BRIEF.md` | Product taste / north star |
| `PROJECT_MEMORY.md` | **Current focus**, deploy facts, recent log |

---

## Doc layers (don’t read everything)

| Layer | When | Files |
|-------|------|--------|
| **Taste** | UI/copy/scope disputes | `PROJECT_BRIEF.md`, `docs/design-direction.md` |
| **Now** | Every session | `PROJECT_MEMORY.md` |
| **Feature** | Working on X | e.g. `docs/STATUS_PAGE_DATA.md`, … **`docs/amiga-modern-ground-platform.md`** (**Amiga forward ground — read first for simul/staging/DDL**), [`archive/amiga-access-pipeline-index.md`](archive/amiga-access-pipeline-index.md) (Access L0–L5 **archived**), **`docs/amiga-derived-write-policy.md`** (simul sign-off on work; prove = oracle), **`docs/amiga-staging-handoff.md`**, … |
| **Run** | Replay, SQL, commands | `docs/OPERATIONS_QUICK_START.md` · **retired scripts:** [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md) |
| **Ladder ops platform** | Steve boundary, `ops/`, sim | [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) |
| **Website data contract** | Stored/derived DB truth | `docs/website-data-contract.md` (online) · **`docs/amiga-data-contract.md`** (Amiga) |
| **Session end** | Dagh says **“update docs”** | `docs/UPDATE_DOCS.md` |
| **Multi-slice tracks** | Large features across chats | [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md) |
| **Creative / pre-track ideas** | Brainstorm, product passes, "what else?" | [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) — recipe, origin stories, idea ledger (not authority) |
| **Cutover / live prod** | Steve go-live | [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md), `ops/docs/post-dagh-live-story.md` |

**Migration is a side track** — not required for CSS-only days. See decision tree in `UPDATE_DOCS.md` § Migration pass.

---

## Databases (don’t confuse)

| | Local | Staging | Prod |
|---|--------|---------|------|
| **Online** | `ko2unity_db` (+ sandbox `ko2unity_work` / `ko2unity_baseline`) | `kooldb1` / `kooldb2` (legacy `kooldb` possible) | Steve-managed |
| **Amiga (offline)** | **`ko2amiga_work`** local living ground; **`ko2amiga_db`** frozen oracle + staging import name | **Staging push:** `export_ko2amiga_work.ps1` (preflight `staging_export_tables.json`) + WinSCP + browser import — [`amiga-staging-handoff.md`](amiga-staging-handoff.md). **Pull:** `pull_ko2amiga_from_staging.ps1`. **Work checkpoint:** `seal_amiga_work_checkpoint.ps1` → `data/amiga/checkpoints/` (milestone git). **Live ops:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · drill track [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) | A2 live staging |
| Work prepare / simul | [`work-db-prepare.md`](work-db-prepare.md) | Same vocabulary (refresh → migrate → zero derived) | — |
| Live games | No | **No** | **Yes** |
| PHP deploy | Laragon | WinSCP sync **`site/public_html/`** | Steve |

---

## Rituals (agents)

### 1) New chat bootstrap

See **`AGENTS.md`** § New chat. Minimal read: **MEMORY → this map → feature doc (if any)**.

### 2) Agent track (doc · plan · prompt · slices)

For multi-session work (online or Amiga): explore → policy doc → implementation plan → starter prompt → execute slices in fresh chats. **`docs/orchestration/agent-track-playbook.md`**.

### 3) “Update docs” (any slice)

Dagh uses this phrase often — **not only for DB work**. Always: session handoff in docs. **Sometimes:** migration registers. Full steps: **`docs/UPDATE_DOCS.md`**.

---

## Who does what on prod

| Piece | Us (repo) | Steve |
|-------|-----------|--------|
| PHP site + `ops/` | WinSCP sync `site/public_html/` | Prod deploy agreed |
| Schema SQL | `ops/sql/migrations/` (synced with ops) | `migrate-work` on work DB; Steve WinSCP `ops/` |
| Website derived history | `ops/run_ops_sim.php` | Steve on prod copy / live (cutover) |
| Shared rating formulas (library) | `scripts/k2_rating_core/` | Amiga **simul** + PHP mirror reference |
| After each game (prod) | [`ladder-ops-platform.md`](ladder-ops-platform.md) → `dispatch_request.php` or `ops/dispatch.php` | Steve insert + HTTP/CLI call (agreed Jun 2026) |

Post-game **rules:** [`website-data-contract.md`](website-data-contract.md). **Live derived runtime:** PHP `ops/dispatch.php` since **2026-07-18** ([`ladder-ops-platform.md`](ladder-ops-platform.md) §2). **Prod today:** PHP ops (Steve ground insert + invoke); **C++ derived retired** — do not extend. Records: [`coordination/records-post-game-exception.md`](coordination/records-post-game-exception.md).

---

## Essential commands

```powershell
# Cutover / work sign-off (ko2unity_work / kooldb1)
php site/public_html/ops/run_prepare.php migrate-work --target local-work
php site/public_html/ops/run_prepare.php seed-catalog --target local-work
php site/public_html/ops/run_prepare.php zero-derived --target local-work
php site/public_html/ops/run_ops_sim.php run --target local-work
php site/public_html/ops/run_verify_ops_sim.php --target local-work

# Or: full prepare + parity
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1

# Local schema (dev DB)
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

**Holy ops only:** see [`obsolete-dev-scripts-retirement-policy.md`](obsolete-dev-scripts-retirement-policy.md). Frozen **`ko2unity_db`** → re-import dump, not retired dev scripts.

---

*Agents: if MEMORY and this map disagree with the repo, trust the repo + Dagh, then offer a MEMORY fix.*
