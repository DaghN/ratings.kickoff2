# Starter prompt — Amiga event finish migration

**Status:** Ready for new agent session (Jun 2026).  
**Policy:** [`docs/amiga-tournament-honours-rules.md`](../../amiga-tournament-honours-rules.md)  
**Plan:** [`docs/amiga-event-finish-implementation-plan.md`](../../amiga-event-finish-implementation-plan.md)

Copy the block below into a **new agent chat** to execute slices 0–10.

---

## Prompt (copy from here)

```
**Status: COMPLETE Jun 2026** (slices 0–10). Use for context only — do not start a new migration unless extending policy (e.g. WC holistic finish import).

You implemented the Amiga **event finish migration** for ko2amiga_db — replace legacy `overall_position` with `event_finish_position`, fix honours counters (podiums, cup medals, wins), populate `best_knockout_phase`, and align PHP/Python writers and UI read paths.

This is **schema + derivation writers + verify + UI** work. Follow the locked policy; do not invent new product rules.

## CRITICAL — first reply rule

Your **very first reply in this chat must NOT take any action** — no file reads, no edits, no terminal commands, no tool calls.

That first reply must **only** give feedback on your understanding of the task: what the migration fixes, what is in vs out of scope, how slices and STOP gates work, what you would read first, and what you would do when the user says "Do slice 0". End by asking the user to confirm before you begin slice 0.

Do not start slice 0 until the user explicitly confirms (e.g. "Do slice 0", "Looks good, proceed", "Continue").

## Read first (mandatory, in order — after user confirms)

1. docs/amiga-tournament-honours-rules.md — locked policy (NULL finish, tiers A–E, shared semi bronze, no league/group on participation)
2. docs/amiga-event-finish-implementation-plan.md — slices 0–10, STOP gates, verification commands
3. docs/amiga-player-universe-contract.md — §5.2–§6 (target column model)
4. docs/amiga-data-contract.md — participation table register, finalize boundary
5. scripts/amiga/participation_placement.py — current legacy derivation
6. scripts/amiga/player_tournament_participation.py — writer + totals SQL
7. scripts/amiga/README.md — CLI patterns

Also read PROJECT_MEMORY.md and AGENTS.md per workspace bootstrap rules.

## Your operating mode

- Work **one slice at a time** from the implementation plan (Slice 0 → 10).
- When I say **"Do slice N"** or **"Continue with the next slice"**, execute **only that slice** unless I explicitly ask for more in one session.
- After each slice: run all **Verification** commands listed for that slice; fix failures before stopping.
- Write a handoff file: `docs/orchestration/agent-handoffs/2026-06-11-0XX-amiga-event-finish-slice-N.md` (pick next free XXX).
- At **STOP gates** (A–C in the plan): stop and tell me exactly what to check (SQL queries + browser URLs); do not start the next slice until I confirm.
- **Do not git commit** unless I ask.
- After slices that change stored truth: run docs/UPDATE_DOCS.md Part A in the same turn you finish a slice (Part B when schema registers apply).

## Locked decisions (already decided — do not ask me)

- event_finish_position NULL = unknown; never use 0
- Retire overall_position (drop in slice 8, not before)
- No league_position / group_position on participation
- Phase ranks only in amiga_tournament_standings
- Shared semi bronze: both semi losers get event_finish_position = 3 when no 3rd-place match (Olympic-style)
- WC: event_finish_position always NULL; wc_medal for podium; shared bronze on medals when no 3rd-place match
- Podiums: event_finish_position <= 3 OR wc_medal in (gold, silver, bronze)
- is_winner: event_finish_position = 1 (non-WC) or wc_medal = gold (WC)
- Main Final label only for gold/silver (not subsidiary cup finals) unless Tier E override
- Profile event_points suffix rules unchanged (contract §5.2.1)

## Out of scope (defer — do not implement unless I explicitly expand scope)

- WC holistic event_finish_position from external/import verified data
- Finish bands (5–8) without exact integer rank
- Bulk Tier E override data (slice 9 = empty table + hook only)
- Milestones, match streaks, UTC leagues, cross-realm H2H
- Staging WinSCP / server import (I deploy separately)

## Environment

- Repo: Online and Amiga 500 ELO
- DB: ko2amiga_db (local MySQL); config via scripts/amiga/config
- MySQL: C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe
- PHP: C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
- Rebuild: python -m scripts.amiga participation-rebuild
- Verify baseline (run after writer/UI slices):
  python -m scripts.amiga verify-chronology
  python -m scripts.amiga verify-rating-events
  python -m scripts.amiga verify-player-participation
  python -m scripts.amiga verify-player-matchups

## Start command (after first-reply confirmation only)

Unless I specify otherwise, begin with **Slice 0** (DDL: add event_finish_position + best_knockout_phase; migration 017).

When slice 0 is complete, report verification output and wait for me to say **"Do slice 1"** or **"Continue"**.
```

---

## Related

- Policy lock (Jun 2026): [`amiga-tournament-honours-rules.md`](../../amiga-tournament-honours-rules.md)
- Prior surface track: [`amiga-surface-expansion-STARTER-PROMPT.md`](amiga-surface-expansion-STARTER-PROMPT.md)
- Player universe (complete): [`amiga-player-universe-STARTER-PROMPT.md`](amiga-player-universe-STARTER-PROMPT.md)
