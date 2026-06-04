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
| `site/public_html/ops/` | **Server operations** — dispatcher (planned), modules, SQL mirrors; [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) |
| `site/public_html/staging-scripts/` | **Legacy** staging PHP runners — migrate into `ops/` over time |
| `docs/self-hosted-assets.md` | **CDN audit** — what is self-hosted vs external (fonts, JS, YouTube embed) |
| `docs/DEAD_SURFACE.md` | **Removed / kept** runtime files and one-shot scripts (trim pass) |
| `site/config/` | DB config (gitignored) — `ko2unitydb_config.php` |
| `scripts/ladder/` | **Python replay** — recalc Elo/stats from all games |
| `scripts/run_local_replay.ps1` | One-command local replay |
| `scripts/rebuild_website_derived_data_local.ps1` | One-command local rebuild for website aggregate tables |
| `site/public_html/ops/sql/migrations/` | Canonical SCH DDL (indexes, tables); see `ops-schema-migrations.md` |
| `run_staging_ladder_replay.sh` | Steve runs on staging server |
| `docs/` | Specs, coordination, agent playbooks |
| `data/dumps/` | Local SQL dump (gitignored) |
| `README.md` | Repo entry — links to agents, ops, brief |
| `PROJECT_BRIEF.md` | Product taste / north star |
| `PROJECT_MEMORY.md` | **Current focus**, deploy facts, recent log |

---

## Doc layers (don’t read everything)

| Layer | When | Files |
|-------|------|--------|
| **Taste** | UI/copy/scope disputes | `PROJECT_BRIEF.md`, `docs/design-direction.md` |
| **Now** | Every session | `PROJECT_MEMORY.md` |
| **Feature** | Working on X | e.g. `docs/STATUS_PAGE_DATA.md`, **`docs/activity-charts.md`** (Activity `server1.php` charts), **`docs/milestones-README.md`** (milestones entry → `milestones-catalog.md`), `docs/player-profile-feast.md`, `docs/hub-ia-agreement.md` |
| **Run** | Replay, SQL, commands | `docs/OPERATIONS_QUICK_START.md` |
| **Ladder ops platform** | Steve boundary, `ops/`, sim | [`docs/ladder-ops-platform.md`](ladder-ops-platform.md) |
| **Website data contract** | Stored/derived DB truth | `docs/website-data-contract.md` |
| **Session end** | Dagh says **“update docs”** | `docs/UPDATE_DOCS.md` |
| **Migration backlog** | Stored DB truth / Steve | `docs/prod-coordination.md`, `docs/coordination/` — post-game day: [`post-game-cutover-checklist.md`](coordination/post-game-cutover-checklist.md) |

**Migration is a side track** — not required for CSS-only days. See decision tree in `UPDATE_DOCS.md` § Migration pass.

---

## Three databases (don’t confuse)

| | Local | Staging | Prod |
|---|--------|---------|------|
| Name | `ko2unity_db` (+ sandbox `ko2unity_work` / `ko2unity_baseline`) | `kooldb1` / `kooldb2` (legacy `kooldb` possible) | Steve-managed |
| Work prepare / simul | [`work-db-prepare.md`](work-db-prepare.md) | Same vocabulary (refresh → migrate → zero derived) | — |
| Live games | No | **No** | **Yes** |
| PHP deploy | Laragon | WinSCP sync **`site/public_html/`** | Steve |

---

## Two rituals (agents)

### 1) New chat bootstrap

See **`AGENTS.md`** § New chat. Minimal read: **MEMORY → this map → feature doc (if any)**.

### 2) “Update docs” (any slice)

Dagh uses this phrase often — **not only for DB work**. Always: session handoff in docs. **Sometimes:** migration registers. Full steps: **`docs/UPDATE_DOCS.md`**.

---

## Who does what on prod

| Piece | Us (repo) | Steve |
|-------|-----------|--------|
| PHP site + `ops/` | WinSCP sync `site/public_html/` | Prod deploy agreed |
| Schema SQL | `ops/sql/migrations/` (synced with ops) | `migrate-work` on work DB; Steve WinSCP `ops/` |
| History replay | `scripts/ladder` | Runs shell on server |
| After each game (prod) | [`ladder-ops-platform.md`](ladder-ops-platform.md) → planned `ops/dispatch.php` | Steve insert + call (agreed Jun 2026; PHP not in repo yet) |

Post-game **rules:** [`website-data-contract.md`](website-data-contract.md). **Runtime:** prod **today** = Steve C++; **target** = PHP `ops/dispatch.php` per [`ladder-ops-platform.md`](ladder-ops-platform.md) §2 (not in repo yet). Records: [`coordination/records-post-game-exception.md`](coordination/records-post-game-exception.md). Pointer: [`coordination/post-game-register.md`](coordination/post-game-register.md).

---

## Essential commands

```powershell
# Local replay (dev DB ko2unity_db)
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1

# Prod-shaped sandbox: prepare v2 then simul (work-db-prepare.md)
powershell -ExecutionPolicy Bypass -File scripts\prepare_local_work_db.ps1
# python -m scripts.ladder run --target sandbox --ini site/config/ladder-work.ini

# Local schema (dev DB)
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1

# Local website-derived aggregate rebuild (dev DB)
powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
```

---

*Agents: if MEMORY and this map disagree with the repo, trust the repo + Dagh, then offer a MEMORY fix.*
