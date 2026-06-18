# Starter prompt — Amiga surface expansion (post player-universe)

**Status:** Ready for new agent session (Jun 2026).  
**Overview:** [`docs/amiga-surface-expansion-overview.md`](../../amiga-surface-expansion-overview.md)  
**Plan:** [`docs/amiga-surface-expansion-implementation-plan.md`](../../amiga-surface-expansion-implementation-plan.md)

Copy the block below into a **new agent chat** to execute slices 0–8.

---

## Prompt (copy from here)

```
You are implementing the Amiga **surface expansion** track — surfacing derived data that already exists after the player-universe expansion (participation, totals, H2H, generalstats, perf rating, full career stats). This is **read-path / PHP UI work**, not new rebuild writers or DDL.

## CRITICAL — first reply rule

Your **very first reply in this chat must NOT take any action** — no file reads, no edits, no terminal commands, no tool calls.

That first reply must **only** give feedback on your understanding of the task: what the track is for, what is in vs out of scope, how slices and STOP gates work, what you would read first, and what you would do when the user says "Do slice 0". End by asking the user to confirm before you begin slice 0.

Do not start slice 0 until the user explicitly confirms (e.g. "Do slice 0", "Looks good, proceed", "Continue").

## Read first (mandatory, in order — after user confirms)

1. docs/amiga-surface-expansion-overview.md — ready vs potential inventory
2. docs/amiga-surface-expansion-implementation-plan.md — slices 0–8, STOP gates, locked decisions
3. docs/amiga-player-universe-contract.md — §4 surfaces register, §5.0 stored-truth policy
4. docs/amiga-performance-rating.md — if working on perf rating slices
5. docs/amiga-realm-vision.md — Tier A wings, explicit skips (no streaks)
6. scripts/amiga/README.md — verify CLI patterns

## Your operating mode

- Work **one slice at a time** from the implementation plan (Slice 0 → 8).
- When I say **"Do slice N"** or **"Continue with the next slice"**, execute **only that slice** unless I explicitly ask for more in one session.
- After each slice: run all **Verification** commands listed for that slice; fix failures before stopping.
- Write a handoff file: `docs/archive/orchestration/agent-handoffs/2026-06-09-0XX-amiga-surface-expansion-slice-N.md` (pick next free XXX).
- At **STOP gates** (A–F in the plan): stop and tell me exactly what to check in the browser; do not start the next slice until I confirm.
- **Do not git commit** unless I ask.

## Locked decisions (already decided — do not ask me)

- No new derived tables or rebuild writers in this track
- No hot-path `amiga_games` aggregation on profile or leaderboard pages
- No match streak UI; do not read/display streak columns on `amiga_player_stats`
- Profile event_points suffix rules unchanged (contract §5.2.1)
- WC finish = medal podium only, not group overall_position
- H2H is realm-internal only (amiga_players.id)
- Port online LB wing patterns; Amiga reads `amiga_player_stats`

## Out of scope (defer to overview §4 — do not implement unless I explicitly expand scope)

- Milestones, UTC league honours, cross-realm H2H
- `amiga_player_tournament_slice_totals`
- Live incremental H2H/generalstats on single-game finalize
- Tournament games tab, activity/period tables
- `performance_rating − rating_before` column

## Environment

- Repo: Online and Amiga 500 ELO
- DB: ko2amiga_db (local MySQL); config via scripts/amiga/config
- Verify baseline after UI slices:
  python -m scripts.amiga verify-chronology
  python -m scripts.amiga verify-rating-events
  python -m scripts.amiga verify-player-participation
  python -m scripts.amiga verify-player-matchups

## Start command (after first-reply confirmation only)

Unless I specify otherwise, begin with **Slice 0** (profile honours strip from `amiga_player_tournament_totals`).

When slice 0 is complete, report verification output and wait for me to say **"Do slice 1"** or **"Continue"**.
```

---

## Related

- Prior track (complete): [`amiga-player-universe-STARTER-PROMPT.md`](amiga-player-universe-STARTER-PROMPT.md)
- Final player-universe handoff: [`2026-06-08-051-player-universe-slice-14.md`](2026-06-08-051-player-universe-slice-14.md)
