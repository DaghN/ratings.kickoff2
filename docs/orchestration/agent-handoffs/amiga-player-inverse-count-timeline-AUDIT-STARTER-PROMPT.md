# Starter prompt — Audit: Amiga inverse-count timeline (proposed policy)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Policy under audit:** [`docs/amiga-player-inverse-count-timeline-policy.md`](../../amiga-player-inverse-count-timeline-policy.md)  
**Mission:** **Audit only** — validate analysis, challenge assumptions, confirm or refute proposed sparse changelog. **Do not implement** unless Dagh explicitly says go after audit.

---

## COPY INTO NEW CHAT

```
You are Dagh's **audit agent** for the proposed Amiga **inverse-count timeline** fix.

**Mission:** Carefully audit the analysis and proposed solution in `docs/amiga-player-inverse-count-timeline-policy.md`. Report: (1) is the root-cause analysis correct? (2) is the sparse changelog the right fix? (3) are there holes, cheaper alternatives, or TT edge cases we missed? (4) is retiring `LeastGoalsScoredVictims` / `LeastGoalsConcededCulprits` safe?

**You are NOT implementing** DDL, writers, or read-path changes in this chat unless Dagh says go after your audit report.

**Read first (in order):**
1. docs/amiga-player-inverse-count-timeline-policy.md — full proposed policy
2. docs/amiga-event-snapshot-policy.md — S5 non-participants (why sparse snapshots break)
3. docs/website-data-contract.md — § Personal record pointers and inverse counts
4. docs/amiga-player-chronologies-policy.md — §4.8–4.11 (pointer inventory reads; parity notes)
5. scripts/k2_rating_core/player_state.py — `_transfer_record_count`, `apply_match` (strict `>` transfers)
6. scripts/amiga/snapshot_persist.py — participant-only persist scope
7. scripts/amiga/elo_rank.py — `persist_elo_ranks_at_tournament` (dense precedent we rejected for size)
8. site/public_html/amiga/leaderboards/victims.php + includes/amiga_lb_snapshot_lib.php — LB TT read + SSR sort

**Reproduce the bug (mandatory probes):**

1. **DB parity** on `ko2amiga_work`:
   - For each of the 4 metrics, count players where stored inverse column ≠ COUNT(pointers at `amiga_player_current`).
   - Sample ids=2, 5, 328 — document stored vs pointer inverse.

2. **In-memory oracle:**
   - Replay all games with `apply_game_row` + `PlayerState` (no DB writes).
   - Assert memory inverse counts = pointer oracle for all 4 metrics (expect 0 mismatches).
   - Compare memory vs `amiga_player_current` for the 4 columns (expect many mismatches).

3. **TT edge case (reasoning or SQL):**
   - Construct or find: hero B loses credit at event E while absent; cutoff before E vs after E — confirm sparse snapshot wrong after E, pointer chronology correct, changelog would fix mosaic/LB.

**Evaluate alternatives (mandatory):**

| Option | Your verdict |
|--------|------------|
| A. Dirty flush to `amiga_player_current` only | TT-safe? |
| B. Dense `*_at_event` all players every finalize (~174k rows, ~37 MB) | Correct but worth cost? |
| C. Pointer oracle as primary LB read (GROUP BY on snapshot batch) | Acceptable at n≈469? Query complexity vs changelog? |
| D. **Sparse changelog** (~10–15k rows, ghost events) | Recommended? Risks? |

**Challenge these claims:**

- Transfer logic is correct; only persistence is wrong.
- Changelog size ~10–15k rows (re-count `_transfer_record_count` calls on full replay if needed).
- One row per `(player_id, tournament_id, metric)` at finalize is enough (multiple transfers same event → final value).
- Retiring least-metrics has no Amiga product surface (grep site + docs).

**Deliverable format:**

1. **Executive verdict** — agree / disagree / agree with caveats (1 paragraph)
2. **Root cause** — confirmed or corrected
3. **Proposed fix** — accept sparse changelog, or recommend alternative with reasoning
4. **Risks & edge cases** — bullet list
5. **Retire least-metrics** — safe or not
6. **Recommended next step** — implementation plan outline or further probes

**Constraints:**
- Work DB = `ko2amiga_work`; Laragon PHP/MySQL paths per AGENTS.md if needed.
- UTF-8 on Windows: StrReplace on existing files; PowerShell UTF-8 for new files.
- No git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>" unless Dagh asks.

**First message:**
1. Confirm audit mission (no implementation).
2. State your probe plan.
3. Run probes before delivering verdict — do not verdict from policy doc alone.
```

---

## Execution log

_(Audit agent appends verdict summary + date when complete.)_