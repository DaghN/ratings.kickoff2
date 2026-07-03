# KOOL Kick Off 2 ratings site

PHP + MariaDB ladder and statistics for online Kick Off 2 play. Dagh develops locally ([`docs/LOCAL_DEV.md`](docs/LOCAL_DEV.md)), deploys PHP to staging via WinSCP; production database and post-game logic are coordinated with Steve ([`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md) — ground/derived split and `ops/` target).

## Repo tour

If you know KOOL / Kick Off 2 and expected a small PHP refresh, this repo is larger than it looks on the surface.

**Two statistical realms.** The **online ladder** (`site/public_html/`, `ops/`) is the live PC ratings site — games, profiles, leaderboards, milestones, activity charts. Alongside it sits a full **Amiga 500 offline tournament realm** (`site/public_html/amiga/`, `scripts/amiga/`, separate `ko2amiga_db`) — World Cups, countries, Hall of Fame, opponent breakdowns, tournament videos, and [**time travel**](docs/amiga-time-travel-policy.md) (browse the historical ladder at any past cutoff via `as=`). Tournament disposition register: **605** catalogued; **44** still `pending_review` (Jun 2026).

**Docs are part of the workflow.** Under `docs/` you will find policy specs, implementation plans, data contracts, and agent handoff prompts — not leftover notes. [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) tracks current focus; [`AGENTS.md`](AGENTS.md) onboards Cursor agents; [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md) is the session handoff ritual. Multi-session features follow [`docs/orchestration/agent-track-playbook.md`](docs/orchestration/agent-track-playbook.md) (policy → plan → slices).

**Stored truth, not live scans.** Hot-path stats read precomputed tables maintained by post-game writers and replay jobs — not ad-hoc aggregation over wide historical game rows. Online proof runs through `ops/` simul on a work database; Amiga proof runs `python -m scripts.amiga prove` (import → replay → verify oracles). See [`docs/website-data-contract.md`](docs/website-data-contract.md) and [`docs/amiga-data-contract.md`](docs/amiga-data-contract.md).

**Amiga ground pipeline.** Offline data starts from legacy Access (`koatd.mdb`) and moves through a strict L0→L5 layer stack (mechanical SQL → pruned witness → ground MySQL → structure → derived product tables). Overview: [`docs/amiga-ground-stack.md`](docs/amiga-ground-stack.md).

**Product depth for a niche community.** Shipped or in active development: 112-key milestone catalog, performance rating per opponent pair, with-player stepper filters, gapless jukebox (popup window — Turbo removed Jun 2026), and dense sortable tables with shared K2 chrome. Creative origin stories: [`docs/creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md).

**Prod boundary.** This repository holds website code and schema/ops tooling. Live database contents, secrets, and scheduled post-game on production are coordinated with **Steve** — see [`docs/ladder-ops-platform.md`](docs/ladder-ops-platform.md) and [`docs/prod-coordination.md`](docs/prod-coordination.md).

Rough scale: ~500 PHP pages under `site/public_html/`, ~400 docs, ~700 scripts. Default change style is small, reversible slices — not big-bang rewrites.

## Start here

| You are… | Read |
|----------|------|
| **Cursor agent (new chat)** | [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) → [`AGENTS.md`](AGENTS.md) → [`docs/PROJECT_MAP.md`](docs/PROJECT_MAP.md) |
| **Human — product / creative ideas** | [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) · [`docs/creative-ideas-july-2026.md`](docs/creative-ideas-july-2026.md) (pre-track ledger) |
| **Run replay / SQL / deploy** | [`docs/OPERATIONS_QUICK_START.md`](docs/OPERATIONS_QUICK_START.md) |
| **Prod cutover / Steve** | [`docs/coordination/cutover-readiness.md`](docs/coordination/cutover-readiness.md) · [`docs/prod-coordination.md`](docs/prod-coordination.md) |

Website code lives in [`site/public_html/`](site/public_html/). Session handoff and “update docs” ritual: [`docs/UPDATE_DOCS.md`](docs/UPDATE_DOCS.md).
