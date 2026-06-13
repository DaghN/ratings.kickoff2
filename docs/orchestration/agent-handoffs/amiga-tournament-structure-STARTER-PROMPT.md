# Starter prompt — Amiga tournament structure (modules vs legacy backfill)

**Status:** **READY** — copy into a **new agent chat** to execute slices 1–9.  
**Policy:** [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md)  
**Plan:** [`docs/amiga-tournament-structure-implementation-plan.md`](../../amiga-tournament-structure-implementation-plan.md)

Planning trio shipped Jun 2026 from exploration chat. **Do not re-litigate** modules vs structure or game-authoritative import.

---

## Prompt (copy from here)

```
You are implementing the Amiga **tournament structure** track for ko2amiga_db — collapse stage types to `round_robin` | `knockout`, materialize legacy fixtures from games (not draw-order), verify side parity, bulk backfill NULL-phase and phase-labeled events, then optional catalog flags and Steve WC structure reference.

**One-line goal:** Fix legacy tournament format/import properly — explicit stages and fixtures grounded in games, separate from inter-stage structure graph.

This is **DDL migration 023 + builders + legacy materialize + verify CLI + bulk backfill**. Standings scope unification (`league` | `knockout` tally) is **already shipped** — do not merge overall/group again.

## CRITICAL — first reply rule

Your **very first reply in this chat must NOT take any action** — no file reads, no edits, no terminal commands, no tool calls.

That first reply must **only** give feedback on your understanding of the task: two module types vs structure artifact, legacy materialize rules, slice map and STOP gates, what you would read first, and what you would do when the user says "Do slice 1". End by asking the user to confirm before you begin slice 1.

Do not start slice 1 until the user explicitly confirms (e.g. "Do slice 1", "Looks good, proceed", "Continue").

## Read first (mandatory, in order — after user confirms)

1. docs/amiga-tournament-structure-policy.md — locked T1–T15
2. docs/amiga-tournament-structure-implementation-plan.md — slices 1–9, STOP gates, verification commands
3. docs/amiga-standings-scope-policy.md — standings `league`/`knockout` tally (separate from stage types)
4. docs/amiga-data-contract.md — tournament_stages, fixtures, format flags
5. scripts/amiga/sql/006_tournament_fixtures.sql — current stage_type enum
6. scripts/amiga/tournament_standings.py — _fixture_scope
7. scripts/amiga/tournament_format.py — infer_legacy_tournament_format (mis-tag root cause)
8. scripts/amiga/tournament_phases.py — phase → scope buckets
9. scripts/amiga/tournament_structure/ — link.py, homburg.py, verify.py (paused pilot)
10. scripts/amiga/import_access.py — Team A/B → player_a_id/player_b_id
11. site/public_html/includes/amiga_tournament_lib.php — format_kind read paths
12. scripts/amiga/README.md — CLI patterns

Also read PROJECT_MEMORY.md and AGENTS.md per workspace bootstrap rules.

## Your operating mode

- Work **one slice at a time** from the implementation plan (Slice 1 → 9).
- When I say **"Do slice N"** or **"Continue with the next slice"**, execute **only that slice** unless I explicitly ask for more in one session.
- After each slice: run all **Verification** commands listed for that slice; fix failures before stopping.
- Write a handoff file: `docs/orchestration/agent-handoffs/2026-06-13-0XX-amiga-tournament-structure-slice-N.md` (pick next free XXX).
- At **STOP gates** (A–D in the plan): stop and tell me exactly what to check (SQL + browser URLs); do not start the next slice until I confirm.
- **Do not git commit** unless I ask.
- After slices that change stored truth: run docs/UPDATE_DOCS.md Part A in the same turn you finish a slice (Part B when migration 023 ships).
- **Do not extend** format-backbone Homburg backfill until slice 1 enum + slice 3 materialize smoke pass.

## Locked decisions (already decided — do not ask me)

- Stage types: `round_robin` | `knockout` only (retire league/group/placement/other as stage types)
- `round_robin` module → standings scope `league`; `knockout` module → standings scope `knockout`
- Structure (singleton marathon vs eight groups) is NOT encoded in stage type — use stage_key, template, StructureSpec
- Legacy import: games authoritative; one fixture per game; copy player_a/b from game; no draw-order RR generation
- Side parity: fixture A/B must match game A/B
- NULL phase → single implicit round_robin stage; labeled phases → stage buckets
- fixture_id path wins over phase parser for standings scope
- has_league/has_cup recompute from stages (slice 7)
- Steve WC source = structure reference (slice 8+), not blocking bulk backfill
- No koatd patches — import layer only

## Out of scope (defer — do not implement unless I explicitly expand scope)

- Live WC generator UI for new events
- Full promotion engine for all 603 events
- Swiss stage type
- Tournament index UI polish (optional late)
- Staging WinSCP / server import (I deploy separately)
- Online kooldb ladder

## Environment

- Repo: Online and Amiga 500 ELO
- DB: ko2amiga_db (local MySQL); config via scripts/amiga/config
- MySQL: C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe
- PHP: C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe
- Local site: http://ratingskickoff.test/amiga/

## Pilot tournaments

- Athens IV Cup tournament_id=74 — NULL-phase KO cup mis-tagged league+cup
- Athens L tournament_id=281 — incomplete RR; not primary misclassification test
- Homburg — curated structure pilot (align types in slice 2)
- Pure cups: has_league=0, has_cup=1 (10 events)

## When you finish a slice

1. Run verification commands from the plan
2. Write handoff markdown
3. STOP at gates A–D and wait for my OK
4. UPDATE_DOCS Part A (+ Part B if migration 023 changed schema register)

Start by confirming your understanding only — no tools until I say proceed.
```

---

## After slices 1–7

Mark plan status **Complete** in slice 9. Slice 8 (Steve WC reference) may complete when source path is available.
