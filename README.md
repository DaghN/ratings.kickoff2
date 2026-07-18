# Kick Off 2 ratings

**Live site:** [ratings.kickoff2.com](https://ratings.kickoff2.com)

PHP + MariaDB site for **Kick Off 2** — the live **online** (KOOL) ratings ladder, plus a full **Amiga 500** offline tournament realm. At the core: game results, Elo, and derived career truth. Around that: a richer content surface that has grown with the project (Status and join online; News, tournament video, time travel, and Live / organizer tooling on Amiga; lore and chrome shared across realms).

This repository is where that system is built and maintained: the **website**, and the **database / ops backbone** behind it (schema, post-game writers, rebuild and proof tooling, Amiga scripts, and the contracts that say what stored data means). **Steve** runs live hosting and production databases; site and ladder development happen in this repo and are synced to production when ready.

**This page is a short overview** — what the site is, how the work is partitioned, what lives in the repository, and where to look next if you want to explore, run things locally, or continue the work. Product intent and taste live in [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md). Detail lives in `docs/`; you do not need to absorb the whole tree at once.

## How the work is split

**Online ladder.** Steve runs the live KOOL side: the game app, hosting, and writing each rated game as **ground truth** (who played, score, when), then invoking post-game processing. This repository is responsible for the **derived** side that turns those games into ratings, leaderboards, milestones, activity stats, and the rest of the website surface — schema, writers, contracts, and proof tooling. Short version: ground truth on Steve's side; derived data and the site maintained here ([`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md)).

**Amiga realm.** Offline tournament history began in the community's long-running **Access** database. This repo carries that record forward in **MySQL**, and adds structure and derived data so the website can show ratings, time travel, World Cups and countries, opponent breakdowns, Hall of Fame, News, tournament video, and related views — including Live / organizer tooling as that matures. Access remains the historical source that was brought across; forward maintenance and website-facing derived work live here ([`docs/amiga-modern-ground-platform.md`](docs/amiga-modern-ground-platform.md)).

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
- **Amiga data:** milestone work snapshots are **in git** — e.g. [`data/amiga/checkpoints/work-2026-07-18-forum/`](data/amiga/checkpoints/work-2026-07-18-forum/) (restore notes in that folder and [`data/amiga/checkpoints/README.md`](data/amiga/checkpoints/README.md)). An earlier L3 bootstrap witness lives under [`data/amiga/day0/`](data/amiga/day0/).
- **Online ladder data:** live ground truth stays with Steve’s production side; derived writers and schema live in this repo. **Continuity backups of online DB state belong in git** the same way Amiga checkpoints do (wanted / evolving). Working extracts under [`data/dumps/`](data/dumps/) may still be local or gitignored until a sealed milestone — that folder’s README is about working hygiene, not a ban on online archives in the repo.
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