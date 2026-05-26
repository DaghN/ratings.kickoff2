# Project map — scaffold for new chats

**Read this when entering the repo cold** (with `PROJECT_MEMORY.md`). Five-minute orientation.

---

## What this is

**KOOL Kick Off 2 ratings site** — PHP + MariaDB ladder/stats for online play. Dagh iterates in **Cursor** locally (`http://ratingskickoff.test`), deploys PHP to **staging** via **WinSCP**, **production DB + C++ post-game** coordinated with **Steve** later.

Not a greenfield app: legacy tables (`ratedresults`, `playertable`, …), dense stats UI, Chart.js APIs.

---

## Repo layout (where what is)

| Path | What |
|------|------|
| `site/public_html/` | **The website** — PHP pages, `api/`, `stylesheets/`, `js/` |
| `site/config/` | DB config (gitignored) — `ko2unitydb_config.php` |
| `scripts/ladder/` | **Python replay** — recalc Elo/stats from all games |
| `scripts/run_local_replay.ps1` | One-command local replay |
| `scripts/rebuild_website_derived_data_local.ps1` | One-command local rebuild for website aggregate tables |
| `schema/migrations/` | SQL for local + Steve (indexes, DDL) |
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
| **Feature** | Working on X | e.g. `docs/STATUS_PAGE_DATA.md`, `docs/player-profile-feast.md`, `docs/hub-ia-agreement.md` |
| **Run** | Replay, SQL, commands | `docs/OPERATIONS_QUICK_START.md` |
| **Website data contract** | Stored/derived DB truth | `docs/website-data-contract.md` |
| **Session end** | Dagh says **“update docs”** | `docs/UPDATE_DOCS.md` |
| **Migration backlog** | Stored DB truth / Steve | `docs/prod-coordination.md`, `docs/coordination/` |

**Migration is a side track** — not required for CSS-only days. See decision tree in `UPDATE_DOCS.md` § Migration pass.

---

## Three databases (don’t confuse)

| | Local | Staging | Prod |
|---|--------|---------|------|
| Name | `ko2unity_db` | `kooldb` | `kooldb` |
| Live games | No | **No** | **Yes** |
| PHP deploy | Laragon | WinSCP | Steve |

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
| PHP site | WinSCP staging | Prod deploy agreed |
| Schema SQL | `schema/migrations/` | Runs on `kooldb` |
| History replay | `scripts/ladder` | Runs shell on server |
| After each game (prod) | [`website-data-contract.md`](website-data-contract.md) post-game § | Steve C++ at cutover |
| Hourly fade | Document stop (PER-001) | Stops job |

Post-game handoff: **code snippets to insert** (Steve option 2).

---

## Essential commands

```powershell
# Local replay
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1

# Local schema
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1

# Local website-derived aggregate rebuild
powershell -ExecutionPolicy Bypass -File scripts\rebuild_website_derived_data_local.ps1
```

---

*Agents: if MEMORY and this map disagree with the repo, trust the repo + Dagh, then offer a MEMORY fix.*
