# Starter prompt — Amiga standings scope unification

**Status:** **COMPLETE** — slices 0–7 shipped Jun 2026. Do not start a new session from this prompt unless re-running migration on a fresh DB clone.  
**Policy:** [`docs/amiga-standings-scope-policy.md`](../../amiga-standings-scope-policy.md)  
**Plan:** [`docs/amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

Copy the block below into a **new agent chat** to execute slices 0–7.

---

## Prompt (copy from here)

```
You are implementing the Amiga **standings scope unification** for ko2amiga_db — merge `overall` and `group` standings scopes into a single `league` points-table primitive; keep `knockout`; add `resolve_primary_league_standings()` for honours Tier B/C; Python/PHP writer parity; readers/URLs; migration `020`.

**One-line goal:** Stop pretending NULL-phase and labeled-phase points tables are different scope types.

This is **schema + standings writers + honours resolver + verify + UI read paths**. Follow the locked policy; do not invent new product rules.

## CRITICAL — first reply rule

Your **very first reply in this chat must NOT take any action** — no file reads, no edits, no terminal commands, no tool calls.

That first reply must **only** give feedback on your understanding of the task: what the migration fixes, what is in vs out of scope, how slices and STOP gates work, what you would read first, and what you would do when the user says "Do slice 0". End by asking the user to confirm before you begin slice 0.

Do not start slice 0 until the user explicitly confirms (e.g. "Do slice 0", "Looks good, proceed", "Continue").

## Read first (mandatory, in order — after user confirms)

1. docs/amiga-standings-scope-policy.md — locked S1–S10, resolver rules, rejected alternatives
2. docs/amiga-standings-scope-implementation-plan.md — slices 0–7, STOP gates, verification commands
3. docs/amiga-tournament-honours-rules.md — Tier B/C (wording still says overall scope — policy overrides)
4. docs/amiga-data-contract.md — § Tournament standings
5. scripts/amiga/tournament_phases.py — ScopeType, parse_phase
6. scripts/amiga/tournament_standings.py — aggregation, synthetic league+''
7. scripts/amiga/participation_placement.py — _overall_positions (to replace)
8. site/public_html/amiga/ops/includes/amiga_post_game_standings.php — PHP parity
9. site/public_html/amiga/tournament.php — tab/nav read path
10. scripts/amiga/README.md — CLI patterns

Also read PROJECT_MEMORY.md and AGENTS.md per workspace bootstrap rules.

## Your operating mode

- Work **one slice at a time** from the implementation plan (Slice 0 → 7).
- When I say **"Do slice N"** or **"Continue with the next slice"**, execute **only that slice** unless I explicitly ask for more in one session.
- After each slice: run all **Verification** commands listed for that slice; fix failures before stopping.
- Write a handoff file: `docs/orchestration/agent-handoffs/2026-06-11-0XX-amiga-standings-scope-slice-N.md` (pick next free XXX).
- At **STOP gates** (A–C in the plan): stop and tell me exactly what to check (SQL + browser URLs); do not start the next slice until I confirm.
- **Do not git commit** unless I ask.
- After slices that change stored truth: run docs/UPDATE_DOCS.md Part A in the same turn you finish a slice (Part B when migration 020 ships).

## Locked decisions (already decided — do not ask me)

- Merge overall + group → scope_type `league`; phase = scope_key only
- Empty scope_key = implicit single-phase league table (NULL-phase marathons)
- Standings enum final: `league` | `knockout` only
- Knockout scopes unchanged (placement finals stay knockout)
- scope_type is aggregation primitive — NOT a cap on future format modules/stages
- Keep synthetic league+'' aggregate for mixed NULL + labeled phases (Athens LXXXV exception documented)
- resolve_primary_league_standings() per policy §3 for honours Tier B/C
- Legacy URLs ?scope=overall and ?scope=group map to league
- catalog_stats.group_scopes → league_scopes
- Do not use "overall" for standings scope in new code/docs
- event_finish_position semantics unchanged — only which standings rows feed Tier B/C

## Out of scope (defer — do not implement unless I explicitly expand scope)

- Format-template / stage-graph builder
- Tournament tab IA polish (hide redundant tab on pure single-table leagues) — fast follow
- tournament_fixtures.stage_type enum changes
- Normalizing historical Access phase mislabels
- Staging WinSCP / server import (I deploy separately)
- Online kooldb ladder honours "overall" tab — different realm

## Environment

- Repo: Online and Amiga 500 ELO
- DB: ko2amiga_db (local MySQL); config via scripts/amiga/config
- MySQL: C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe
- PHP: C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
- Local site: http://ratingskickoff.test/amiga/tournament.php?id=24 and id=22
- Rebuild: python -m scripts.amiga participation-rebuild
- Full replay: python -m scripts.amiga replay
- Verify baseline (after slices 3+):
  python -m scripts.amiga verify-chronology
  python -m scripts.amiga verify-rating-events
  python -m scripts.amiga verify-player-participation
  python -m scripts.amiga verify-player-matchups

## Start command (after first-reply confirmation only)

Unless I specify otherwise, begin with **Slice 0** (migration 020: unify scope_type to league/knockout; catalog_stats column rename).

When slice 0 is complete, report verification output and wait for me to confirm **STOP GATE A** before slice 1.
```

---

## Related

- Policy: [`amiga-standings-scope-policy.md`](../../amiga-standings-scope-policy.md)
- Event finish (complete): [`amiga-event-finish-STARTER-PROMPT.md`](amiga-event-finish-STARTER-PROMPT.md)
- Format vision: [`amiga-tournament-format-vision.md`](../../amiga-tournament-format-vision.md)
