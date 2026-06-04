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

**Local smoke (~500):** proves scripts run; minutes not hours. **Steve parity:** `--until-game-id 74879` (last game in frozen dev DB).

```bash
php ops/run_ops_sim.php run --target staging-work --until-game-id 74879
```

Wrapper runs timeline sim (post-game + `FinalizeUtcDay` each UTC day). Full history needs no extra flags; checkpoint uses `--until-game-id` (same idea as old `replay-to`).

**Lower level:** `run_timeline_sim.php run --stop-at …` when you need an explicit UTC end time.

**Do not** treat game-only replay as complete:

```bash
# Mode A only — ladder/post-game dev, NOT full ops simul
php ops/run_process_game.php replay-to --until-game-id N --target staging-work
```

After Mode A only: league honours, day-close milestones, and league event keys may be **missing** ([`parity-audit-backlog.md`](parity-audit-backlog.md) AUD-004).

### 3. Verify (local gate)

```bash
php ops/run_verify_ops_sim.php --target local-work
```

Exit **0** = no hard failures (warnings OK). Then spot-check ranked9 / garden on work URL.

Full checklist: [`ops-derived-data-registry.md`](ops-derived-data-registry.md) § Verification.

---

## Testing order

See charter §7 — **local checkpoint + verify first**, Steve full simul **last**: [`ops-completeness-charter.md`](ops-completeness-charter.md#7-testing-order-before-steve-full-simul).

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
