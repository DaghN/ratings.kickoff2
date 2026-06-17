# Ops simul runbook — prod-shaped replay on work

**Audience:** Dagh, Steve, Cursor agents.  
**Charter:** [`ops-completeness-charter.md`](ops-completeness-charter.md) · **ADR:** [`ops-orchestration-adr.md`](ops-orchestration-adr.md)

This is the **canonical** way to fill derived data so the result matches **daily ops** (per-game + UTC day tick), not batch rebuild shortcuts.

**Steve (server):** day-zero on a prod copy = [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) (`migrate-work` → `seed-catalog` → `zero-derived`, then this runbook §2). **Not** full `prepare` / `refresh-work`.

---

## What “simul complete” means

| Included | How |
|----------|-----|
| Per-game derived | `ProcessCompletedGame` for each rated game in order |
| UTC day tick | `FinalizeUtcDay` at each crossed UTC midnight (league, league event milestones, `perfect_day` / `nightmare_day`) |
| `entered_arena` | **`zero-derived`** lobby seed (`JoinDate`) — **not** timeline sim |
| New accounts after cutover | Live `ProcessPlayerRegistered` — validate at go-live |

**Not** the definition of done: Mode B batch (`rebuild_website_derived_data_local.ps1`, `player_milestones_rebuild.sql`, `rebuild-all`) except as **repair**.

---

## Pipeline

### 1. Day zero (before simul)

**Steve — prod copy** ([`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md)):

```bash
php ops/run_prepare.php migrate-work --target YOUR_TARGET
php ops/run_prepare.php seed-catalog --target YOUR_TARGET
php ops/run_prepare.php zero-derived --target YOUR_TARGET
```

`zero-derived` ends with lobby seed (`entered_arena`). Do **not** run `prepare` / `refresh-work` unless you intentionally use the two-DB refresh path ([`work-db-prepare.md`](../work-db-prepare.md)).

**Dagh local — optional full refresh:**

```bash
php ops/run_prepare.php prepare --target local-work
```

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

After Mode A only: league honours, day-close milestones, and league event keys may be **missing** (why **AUD-004** required Mode C — see backlog).

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

Checks include: processed vs unprocessed `ratedresults`; contract **six-value** totals vs processed game count; `player_league_award` / finalized `league_period`; league-related milestone key count; informational `perfect_day` / `nightmare_day`; `entered_arena` vs `JoinDate` (prepare seed, not simul); game-sourced milestone row count; **`player_milestone_totals` parity**; **`milestone_definitions.holder_count` parity** (SCH-021 — stored count vs all `player_milestones` rows per key, including orphan earners).

**Holder-count FAIL:** bump drift only — investigate bump path during simul. Fix: **`zero-derived` → simul again** ([`work-db-prepare.md`](../work-db-prepare.md) §1.5). Orphan unlocks (deleted accounts) are **expected** and count toward `holder_count`.

**Orphan diagnostics (read-only):**

```bash
php ops/run_milestone_orphan_probe.php --target staging-work
```

Lists unlock rows whose `player_id` is missing from `playertable` (informational) and any `holder_count` drift vs unlock rows.

#### What verify is not

| Misread | Truth |
|---------|--------|
| “Run verify to fix the DB” | **No writes** — fix by **`zero-derived` → `run_ops_sim.php`** |
| “Verify failed → run batch rebuild” | **No** on work. Mode B (`rebuild_website_derived_data_local.ps1`, `player_milestones_rebuild.sql`, `rebuild-all`) is **dev repair only** — refused on `local-work` / `staging-work` |
| “Avoid re-simul — patch work in place” | **Wrong.** Work exists to prove the continuous pipeline; re-simul after a writer fix is the point |
| “Verify = dev DB parity” | **No** diff against frozen `ko2unity_db`. Parity at depth uses spot SQL, `ab-post-game`, site compare **after** a meaningful simul slice (e.g. Steve **74879**) |
| “Verify will start a debug simul” | **No** — safe to run without oversight for **mutation**; it only reads |

#### After a **short** local run (bisect / `stop-at`)

Expect **league awards FAIL** only when **no** `FinalizeUtcDay` ran or standings are empty. If day/week finalize ran and awards are still **0**, that is a **bug** (not “short run noise”).

| Check | Short run |
|-------|-----------|
| Processed count, six-value (if DB only contains that window) | **Trust** — real regressions here matter |
| League awards FAIL after finalize with day standings | **Bug** — investigate UTC bucketing / league finalize |
| League awards FAIL before any closed day | **Expected** (no medals yet) |
| League milestones WARN | Often **expected** with few keys |
| Day-close counts | **Informational** only (always PASS) |
| Lobby `entered_arena` | Reflects **`zero-derived`**, not how far simul ran |

**Agreed process:** use verify after smoke to confirm “scripts + six-value hang together”; do **not** treat short-run league FAIL as a mandate to chase old batch parity.

#### After a **meaningful** local chunk or Steve full simul

Treat **FAIL** on six-value or league awards (when history should have closed periods) as **real** — fix ops code, then **`zero-derived` → simul** — not batch rebuild as first resort ([`work-db-prepare.md`](../work-db-prepare.md) §1.5).

Then spot-check ranked9 / garden on work URL; optional `ab-post-game` per DDR.

Full checklist table: [`ops-derived-data-registry.md`](ops-derived-data-registry.md) § Verification.

### Staging sign-off (Jun 2026)

| Gate | Result |
|------|--------|
| Steve full simul on `kooldb1` | **Done** (re-confirmed after activity/milestone writer fixes) |
| `run_verify_ops_sim --target staging-work` | **PASS** — 0 fail, 0 warn (74,865 processed; league awards + 20 league keys) |
| Activity wing verify | Participation sums, per-player counts, SCH-025 reached_at oracle, play-streak oracle, HoF month/year play-streak rows — **all PASS** |
| Milestone librarian verify | `milestone_totals_parity` + `milestone_holder_count_parity` — **0 mismatch** (orphan unlock rows included) |
| Dagh visual parity vs frozen local dev | **Acceptable** — two milestone rule fixes (`clean_sheet_spread`, `giant_slayer`) then re-check |
| **AUD-004 / AUD-005** | **Closed** — [`parity-audit-backlog.md`](parity-audit-backlog.md) |

**Next:** Live-shaped test — [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md). After a **writer rule change**, sync code → **`zero-derived` → full simul** on work — expected, not optional.

---

## Testing order

See charter §6 — **prepare → simul → (optional) verify**; Steve full simul **last**: [`ops-completeness-charter.md`](ops-completeness-charter.md#6-testing-order-before-steve-full-simul).

---

## Live-shaped (after simul)

See [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) and [`steve-live-ops.md`](../../site/public_html/ops/docs/steve-live-ops.md).

---

## Modes summary

| Mode | Command | Ops-complete? |
|------|---------|---------------|
| **A** | `replay-to` | No |
| **B** | A + batch rebuild scripts | Repair / parity only |
| **C** | `run_ops_sim.php run` after day zero | **Yes** (target) |

---

## Related

| Doc | Topic |
|-----|--------|
| [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) | Steve bootstrap + live |
| [`work-db-prepare.md`](../work-db-prepare.md) §5 | Simul modes |
| [`ops-dispatch.md`](ops-dispatch.md) | CMD registry |
