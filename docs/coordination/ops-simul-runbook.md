# Ops simul runbook — prod-shaped replay on work

**Audience:** Dagh, Steve, Cursor agents.  
**Charter:** [`ops-completeness-charter.md`](ops-completeness-charter.md) · **ADR:** [`ops-orchestration-adr.md`](ops-orchestration-adr.md)

This is the **canonical** way to fill derived data on `ko2unity_work` / `kooldb1` so the result matches **daily ops** (per-game + UTC day tick), not batch rebuild shortcuts.

---

## What “simul complete” means

| Included | How |
|----------|-----|
| Per-game derived | `ProcessCompletedGame` for each rated game in order |
| UTC day tick | `FinalizeUtcDay` at each crossed UTC midnight (league, league event milestones, `perfect_day` / `nightmare_day`) |
| `entered_arena` | **Prepare §4.7 seed lobby** (`JoinDate`) — **not** timeline sim |
| New accounts after cutover | Live `ProcessPlayerRegistered` — validate at go-live |

**Not** the definition of done: Mode B batch (`rebuild_website_derived_data_local.ps1`, `player_milestones_rebuild.sql`, `rebuild-all`) except as **repair**.

---

## Pipeline

### 1. Prepare (day zero)

```bash
php ops/run_prepare.php prepare --target staging-work
```

Includes migrate, seed catalog, zero derived, **lobby seed** (`entered_arena` from `playertable.JoinDate`). See [`work-db-prepare.md`](../work-db-prepare.md) §4.7.

### 2. Prod-shaped simul (recommended)

```bash
php ops/run_ops_sim.php run --target staging-work
php ops/run_ops_sim.php run --target local-work --until-game-id 500
```

**Local smoke (~500):** proves scripts run end-to-end; expect **tens of minutes**, not hours. **Steve parity:** `--until-game-id 74879` (last game in frozen dev DB) — **~1.5h**, not a 9h local full-history run.

```bash
php ops/run_ops_sim.php run --target staging-work --until-game-id 74879
```

Wrapper runs timeline sim (post-game + `FinalizeUtcDay` each UTC day). Full history needs no extra flags; checkpoint uses `--until-game-id` (same idea as old `replay-to`).

**Lower level:** `run_timeline_sim.php run --stop-at …` when you need an explicit UTC end time.

### Debug vs smoke (do not confuse)

| Goal | Command | Notes |
|------|---------|--------|
| **Bisect / “does it run?”** | `run_timeline_sim.php run --stop-at 2017-06-12T00:10:00Z` (example) | ~25 games, seconds–minutes; first UTC day tick in history is **game 16** (2017-06-11), not “after four Steve–Lee games” |
| **Smoke throughput** | `run_ops_sim.php run --until-game-id 500` | ~491 **per-game** steps from first rated id — **slow** for debugging; use for “scripts scale”, not narrow repro |
| **Parity depth** | Steve: `--until-game-id 74879` | After local gate passes; not Dagh’s default local full replay |

**Do not** treat game-only replay as complete:

```bash
# Mode A only — ladder/post-game dev, NOT full ops simul
php ops/run_process_game.php replay-to --until-game-id N --target staging-work
```

After Mode A only: league honours, day-close milestones, and league event keys may be **missing** ([`parity-audit-backlog.md`](parity-audit-backlog.md) AUD-004).

### 3. Verify (optional, read-only)

```bash
php ops/run_verify_ops_sim.php --target local-work
```

**Run only after** you have intentionally run prepare + simul (or whenever you want a SQL snapshot of work). Verify **never** starts another simul, prepare, or batch job.

#### What verify is

| Property | Detail |
|----------|--------|
| **Mechanism** | CLI connects to work DB; runs **`SELECT` counts/sums** only |
| **Code** | `run_verify_ops_sim.php` → `modules/verify_ops_sim.php` |
| **Exit 0** | No check with severity **`fail`** (warnings allowed) |
| **Purpose** | **Internal consistency** on work after incremental ops — “did post-game + day ticks leave plausible rows?” |

Checks include: processed vs unprocessed `ratedresults`; contract **six-value** totals vs processed game count; `player_league_award` / finalized `league_period`; league-related milestone key count; informational `perfect_day` / `nightmare_day`; `entered_arena` vs `JoinDate` (prepare seed, not simul); game-sourced milestone row count.

#### What verify is not

| Misread | Truth |
|---------|--------|
| “Run verify to fix the DB” | **No writes** — fix by re-running **prepare + simul** or targeted ops, not verify |
| “Verify failed → run batch rebuild” | **No.** Mode B (`rebuild_website_derived_data_local.ps1`, `player_milestones_rebuild.sql`, `rebuild-all`, Python ladder batch) is **repair / legacy parity only**, not the happy-path definition of simul complete |
| “Verify = dev DB parity” | **No** diff against frozen `ko2unity_db`. Parity at depth uses spot SQL, `ab-post-game`, site compare **after** a meaningful simul slice (e.g. Steve **74879**) |
| “Verify will start a debug simul” | **No** — safe to run without oversight for **mutation**; it only reads |

#### After a **short** local run (bisect / `stop-at`)

Expect **hard FAIL** on **league awards** when no (or few) league periods have finalized — that is **normal**, not a signal to run batch scripts.

| Check | Short run |
|-------|-----------|
| Processed count, six-value (if DB only contains that window) | **Trust** — real regressions here matter |
| League awards FAIL | **Expected** until enough UTC day ticks / history |
| League milestones WARN | Often **expected** with few keys |
| Day-close counts | **Informational** only (always PASS) |
| Lobby `entered_arena` | Reflects **prepare**, not how far simul ran |

**Agreed process:** use verify after smoke to confirm “scripts + six-value hang together”; do **not** treat short-run league FAIL as a mandate to chase old batch parity.

#### After a **meaningful** local chunk or Steve full simul

Treat **FAIL** on six-value or league awards (when history should have closed periods) as **real** — investigate ops code / `FinalizeUtcDay`, not batch rebuild as first resort.

Then spot-check ranked9 / garden on work URL; optional `ab-post-game` per DDR.

Full checklist table: [`ops-derived-data-registry.md`](ops-derived-data-registry.md) § Verification.

---

## Testing order

See charter §6 — **prepare → simul → (optional) verify**; Steve full simul **last**: [`ops-completeness-charter.md`](ops-completeness-charter.md#6-testing-order-before-steve-full-simul).

---

## Live-shaped midnight (staging / prod)

One call per UTC day (~00:00:01):

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=staging-work as_of=2026-06-04T00:00:01Z
```

Per game after ground insert:

```bash
php ops/dispatch.php CMD=ProcessCompletedGame game_id=N target=staging-work
```

---

## Modes summary

| Mode | Command | Ops-complete? |
|------|---------|---------------|
| **A** | `replay-to` | No |
| **B** | A + batch rebuild scripts | Repair / parity only |
| **C** | `run_ops_sim.php run` after prepare | **Yes** (target) |

---

## Related

| Doc | Topic |
|-----|--------|
| [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md) | Staging WinSCP + commands |
| [`work-db-prepare.md`](../work-db-prepare.md) §5 | Simul modes |
| [`ops-dispatch.md`](ops-dispatch.md) | CMD registry |
