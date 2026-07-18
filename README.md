# KOOL Kick Off 2 ratings site

PHP + MariaDB statistics and Elo ladder for **Kick Off 2** — the live **online** ratings site, plus a full **Amiga 500 offline** tournament realm (World Cups, countries, Hall of Fame, time travel, and more).

This repository is where that system is built and maintained: the **website**, and the **database / ops backbone** behind it (schema, post-game writers, rebuild and proof tooling, Amiga scripts, and the contracts that say what stored data means). **Steve** runs live hosting and production databases; site and ladder development happen in this repo and are synced to production when ready.

**This page is a short overview** — what the site is, how the work is partitioned, what lives in the repository, and where to look next if you want to explore, run things locally, or continue the work. Detail lives in `docs/` and the linked READMEs below; you do not need to absorb the whole tree at once.

## How the work is split

**Online ladder.** Steve runs the live KOOL side of the world: the game app, hosting, and writing each rated game as **ground truth** (who played, score, when), then invoking post-game processing. This repository is responsible for the **derived** side that turns those games into ratings, leaderboards, milestones, activity stats, and the rest of the website surface — schema, writers, contracts, and proof tooling. Short version: ground truth on Steve's side; derived data and the stats site maintained here ([`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md)).

**Amiga realm.** Offline tournament history began in the community's long-running **Access** database. This repo carries that record forward in **MySQL**, and adds structure and derived data so the website can show ratings, time travel, opponent breakdowns, Hall of Fame, and related views. Access remains the historical source that was brought across; forward maintenance and website-facing derived work live here ([`docs/amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md)).

## What's here

| Area | Location |
|------|----------|
| Website (PHP, JS, CSS, APIs) | [`site/public_html/`](site/public_html/) |
| Online ladder ops / post-game + schema migrations | [`site/public_html/ops/`](site/public_html/ops/) |
| Amiga realm pages | [`site/public_html/amiga/`](site/public_html/amiga/) |
| Amiga tooling (simul, export, ground, structure) | [`scripts/amiga/`](scripts/amiga/) |
| Shared rating library | [`scripts/k2_rating_core/`](scripts/k2_rating_core/) |
| Data contracts & how stored truth is maintained | [`docs/website-data-contract.md`](docs/website-data-contract.md) · [`docs/amiga-data-contract.md`](docs/amiga-data-contract.md) |
| Docs (policies, runbooks, how-to) | [`docs/`](docs/) |

The project is **doc-oriented**: important behaviour and data rules live under `docs/` as durable specs, not only as tribal knowledge. Use the links below rather than trying to read everything.

## Continuity (fork / picking up the work)

If you are continuing from a clone:

- **Website, ops/schema, scripts, and docs** in this repo are the main handoff — including how derived data is supposed to be written and verified.
- **Amiga data:** a full milestone snapshot of local work is sealed at [`data/amiga/checkpoints/work-2026-07-18-forum/`](data/amiga/checkpoints/work-2026-07-18-forum/) (restore notes in that folder and [`data/amiga/checkpoints/README.md`](data/amiga/checkpoints/README.md)). An earlier L3 bootstrap witness lives under [`data/amiga/day0/`](data/amiga/day0/).
- **Online ladder data:** large SQL dumps are **not** stored in git (see [`data/dumps/README.md`](data/dumps/README.md)). Live online ground truth stays with Steve's production side; the derived writers and schema are in this repo.
- **Local setup:** [`docs/LOCAL_DEV.md`](docs/LOCAL_DEV.md).

## Where to look next

| If you want… | Read |
|--------------|------|
| Product intent / taste | [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) |
| Repo layout (folders, DBs, rituals) | [`docs/PROJECT_MAP.md`](docs/PROJECT_MAP.md) |
| Run locally | [`docs/LOCAL_DEV.md`](docs/LOCAL_DEV.md) |
| Commands / ops quick path | [`docs/OPERATIONS_QUICK_START.md`](docs/OPERATIONS_QUICK_START.md) |
| Online ladder ops & Steve boundary | [`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md) · [`docs/prod-coordination.md`](docs/prod-coordination.md) |
| Amiga forward ground / staging | [`docs/amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md) · [`docs/amiga-staging-handoff.md`](docs/amiga-staging-handoff.md) |
| URL / navigation map | [`docs/url-routes.md`](docs/url-routes.md) |

## Using an AI agent

This tree is large enough that an AI coding agent (e.g. in Cursor) is a practical way to explore or dig into one area. Point the agent at [`AGENTS.md`](AGENTS.md); a cold start is [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) → [`AGENTS.md`](AGENTS.md) → [`docs/PROJECT_MAP.md`](docs/PROJECT_MAP.md). Ask a concrete question ("how does Amiga time travel work?", "where is local setup?", "where do post-game writers live?") rather than "read the whole repo."