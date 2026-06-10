# Starter prompt — Amiga player universe implementation

**Status:** Track complete (Jun 2026). Slices 0–14 shipped. See final handoff [`2026-06-08-051-player-universe-slice-14.md`](2026-06-08-051-player-universe-slice-14.md).

Use this file only for **follow-up work** (profile overhaul, live incremental H2H, extra LB wings). For new agents, start from the deferred list in slice 14 handoff and [`amiga-player-universe-contract.md`](../../amiga-player-universe-contract.md) §9.

---

## Archived starter prompt (slices 0–14 — do not use for new execution)

```
You are implementing the Amiga player derived-data expansion for ko2amiga_db.

## Read first (mandatory, in order)

1. docs/amiga-player-universe-contract.md — data model, exclusions, locked decisions
2. docs/amiga-player-universe-implementation-plan.md — slice-by-slice tasks, verification, STOP gates
3. docs/amiga-data-contract.md — layer rules, finalize boundary, no streak product reads
4. scripts/amiga/README.md — existing CLI patterns

## Your operating mode

- Work **one slice at a time** from the implementation plan (Slice 0 → 14).
- When I say **"Do slice N"** or **"Continue with the next slice"**, execute **only that slice** unless I explicitly ask for more in one session.
- After each slice: run all **Verification** commands for that slice; fix failures before stopping.
- Write a handoff file: `docs/orchestration/agent-handoffs/2026-06-08-0XX-player-universe-slice-N.md` (pick next free number) with: goal, checklist done, files changed, verification results, known limitations, next slice hint.
- At **STOP gates** (A–G in the plan): stop and tell me exactly what to check in the browser; do not start the next slice until I confirm.
- **Do not git commit** unless I ask.
- **Do not** add milestone catalog, match streak UI, calendar play streaks, or UTC league features.
- **Do not** read/display `amiga_player_stats` streak columns in new PHP.

## Locked decisions (already decided — do not ask me)

- Denormalize tournament name/catalog on participation rows
- Participation = played (≥1 game), not tournament_entrants roster
- WC medals from knockout/placement scopes where possible; is_winner = overall position 1
- Defer slice_totals table and Tier C activity tables
- Access added_players medal parity = optional CLI report only

## Environment

- Repo: Online and Amiga 500 ELO
- DB: ko2amiga_db (local MySQL); config via scripts/amiga/config
- Full rebuild oracle: `python -m scripts.amiga replay` (~23s, ~27k games)
- Existing verifies: `verify-chronology`, `verify-rating-events`, `verify-player-participation`, `verify-player-matchups`

## Start command

Unless I specify otherwise, begin with **Slice 0** (DDL for amiga_player_tournament_participation + amiga_player_tournament_totals, wire import_access + clear_derived).

When slice 0 is complete, report verification output and wait for me to say **"Do slice 1"** or **"Continue"**.
```

---

## Parallel work (not this track)

Leaderboard wings Tier A (`/amiga/leaderboards/goals.php`, etc.) are **out of scope** for slices 0–14 — separate task reading existing `amiga_player_stats`.
