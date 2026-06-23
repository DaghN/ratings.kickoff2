# Ops orchestration ADR — UTC day tick & simul

**Status:** Accepted Jun 2026; **`FinalizeUtcDay` shipped** (Jun 2026) — `finalize_utc_day.php`, `league_milestones_sync.php`, `day_close_milestones.php`, timeline sim wired. **Pending:** one-click full-history orchestrator CLI; Steve cron cutover from `FinalizeLeagueDue`.  
**Charter:** [`ops-completeness-charter.md`](ops-completeness-charter.md)  
**Inventory:** [`ops-derived-data-registry.md`](ops-derived-data-registry.md)

This ADR records **structural** decisions for calendar-bound derived data. Table rules remain in [`website-data-contract.md`](../website-data-contract.md).

---

## Context

Derived work splits into **event types**:

```text
[Ground]     Steve inserts ratedresults (goals, date, players)
    ↓
[Per game]   ProcessCompletedGame(game_id)     — shipped
    ↓
[Calendar]   UTC day boundary (as_of ≈ 00:00:01 UTC):
               league period finalize
               league-dependent milestones
               day-close milestones (perfect_day, nightmare_day)
    ↓
[Register]   ProcessPlayerRegistered           — rare
```

**Dependencies at midnight** (order is mandatory):

```text
1. Finalize league periods with period_end <= as_of
      → player_league_award, league_period.finalized_at
      → player_league_totals (+ slice totals)
      → k2_league_sync_win_milestones (league_wins_10/50/100/500 only today)

2. League event milestones (~20 keys)
      → READ player_league_award
      → INSERT player_milestones (source_kind = league)
      MUST run after step 1 commits

3. Day-close milestones (perfect_day, nightmare_day)
      → Qualifying UTC day ended; achieved_at = next UTC midnight
      Independent of league; same clock tick is fine after step 2
```

Today: step 1 exists as **`FinalizeLeagueDue`**; step 2 is mostly **batch SQL**; step 3 has **no PHP CMD** (surgical/rebuild SQL only).

---

## Decision 1 — One Steve CMD at midnight

**Decision:** Steve (and cron) call **one** dispatcher CMD per UTC day tick:

```text
php ops/dispatch.php CMD=FinalizeUtcDay target=<profile> as_of=2026-06-04T00:00:01Z
```

**Rationale:**

- One cron entry, one success/fail for monitoring
- Step order enforced in PHP, not in Steve’s scheduler
- Shared `as_of` for live retry and simul replay
- Dev can still invoke individual step functions from `run_finalize_league.php` / tests

**Alternatives rejected:**

- Many separate exe calls per night — operational fragility
- League finalize without chained milestone sync — leaves ~20 keys batch-only

**Implementation note:** `FinalizeUtcDay` may be implemented initially as a thin wrapper that calls existing `k2_ops_finalize_league_due_periods()` then new helpers for steps 2–3. **`FinalizeLeagueDue` remains** as an alias or internal step until callers migrate.

---

## Decision 2 — Internal steps, separate functions

**Decision:** Three **PHP functions** (names illustrative), one process, ordered logging:

| Step | Function (planned) | Today |
|------|-------------------|--------|
| 1 | `k2_ops_finalize_league_due_periods($con, $asOf)` | `finalize_league_period.php` / `CMD=FinalizeLeagueDue` |
| 2 | `k2_league_sync_event_milestones()` | **Shipped** — `site/public_html/ops/includes/league_milestones_sync.php` via `FinalizeUtcDay` |
| 3 | `k2_day_close_finalize_utc_day()` | **Shipped** — `site/public_html/ops/includes/day_close_milestones.php` via `FinalizeUtcDay` |

Stdout: `[dispatch] step=league_finalize ok` (or `fail`) per sub-step for grep.

**Do not** fold step 2 into `k2_league_finalize_instance()` per period — keep **awards** separate from **milestone inserts** for idempotency (re-run milestone sync without rewriting awards).

---

## Decision 3 — Transaction boundaries

**Decision:** Prefer **one MySQL transaction per `FinalizeUtcDay`** if performance allows; otherwise **one transaction per step** with documented partial-failure behaviour (Steve retries whole CMD).

**Per game** stays **one transaction** (unchanged) — see [`ops-dispatch.md`](ops-dispatch.md).

---

## Decision 4 — Idempotency & `as_of`

| Rule | Detail |
|------|--------|
| `as_of` | ISO-8601 UTC instant; default live = `now()` rounded to day tick (~00:00:01 UTC) |
| Re-run safe | Finalize skips already-finalized `league_period`; milestone inserts use contract idempotency |
| Simul | When replay crosses UTC midnight between games, call **`FinalizeUtcDay(as_of=that_midnight)`** before processing games on the new day (or immediately after last game of prior day — pick one convention and document in orchestrator) |

---

## Decision 5 — Simul = daily ops, not batch

**Decision:** Target simul is **not** “`replay-to` then `rebuild-all`”. It is:

```text
foreach game in chronological order:
  ProcessCompletedGame(game_id)
  if game.Date crossed UTC day boundary since last tick:
    FinalizeUtcDay(as_of = next_midnight_utc)
```

**Vehicle:** Extend [`run_timeline_sim.php`](../../site/public_html/ops/run_timeline_sim.php) / `timeline_sim.php` **or** new `run_ops_sim.php` — same internal functions as `dispatch.php`.

**Batch** (archived `*_rebuild.sql`, retired dev batch PS1, `rebuild-all`) — **repair and parity only**; labelled in runbooks.

**Simul:** `entered_arena` comes from **prepare seed lobby** ([`work-db-prepare.md`](../work-db-prepare.md) §4.7) — not interleaved in timeline sim. **Live:** `ProcessPlayerRegistered` for new accounts.

---

## Decision 6 — Register path

**Decision:** `entered_arena` stays on **`ProcessPlayerRegistered`** for **new** accounts, not per-game. Historical simul uses **prepare lobby seed** (`achieved_at = JoinDate`); timeline sim does **not** replay registration.

---

## Steve surface (summary)

| When | CMD | Count |
|------|-----|-------|
| Each rated game | `ProcessCompletedGame` | 1 per game |
| Each UTC day (~00:00:01) | `FinalizeUtcDay` | **1** per day |
| New account | `ProcessPlayerRegistered` | 1 per registration |
| Hourly | — | 0 |

**Not** ~20 midnight calls.

---

## Dev runners (unchanged role)

| Runner | Use |
|--------|-----|
| `run_process_game.php` | Long `replay-to`, `process-one` |
| `run_finalize_league.php` | `finalize-due`, `rebuild-all` (batch/repair until orchestrator ships) |
| `run_timeline_sim.php` | Mode C prototype — becomes canonical sim path |

---

## Consequences

| Area | Action |
|------|--------|
| `ops_dispatch.php` | Register `FinalizeUtcDay`; implement wrapper module |
| `ops-dispatch.md` | Document CMD, parameters, exit codes |
| `periodic-register.md` | PER-003 → `FinalizeUtcDay` step 1; add steps 2–3 |
| `staging-work-steve-handoff.md` | One midnight line; simul = orchestrator |
| `timeline_sim.php` | Call full day tick, not only league subset |
| `parity-audit-backlog.md` | **AUD-004 closed** Jun 2026 (staging verify + visual sign-off) |

---

## Open implementation questions (non-blocking for ADR)

| Question | Default |
|----------|---------|
| Exact midnight between games vs after last game of day | Document in orchestrator; both valid if `as_of` consistent |
| `play_streak` day rollover | Mostly per-game today; revisit only if DDR shows gap |
| Python ladder tail batch fields | Oracle only until PHP owns them |
