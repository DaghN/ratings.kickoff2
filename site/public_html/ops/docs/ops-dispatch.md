# Ops dispatcher (`dispatch.php`)

**On server:** `public_html/ops/docs/ops-dispatch.md`  
**Steve (plain):** [`steve-live-ops.md`](steve-live-ops.md) · **Full path:** [`post-dagh-live-story.md`](post-dagh-live-story.md)

**Role:** Router only — `CMD=` → one `k2_ops_*` module → exit code. Rules in `modules/` + contract in git `docs/website-data-contract.md`.

**Not here:** migrations, `run_prepare.php`, `run_ops_sim.php` (see post-dagh-live-story).

---

## Three live flows

| | Ground (Steve) | Dispatch |
|---|----------------|----------|
| Register | `playertable` row | `ProcessPlayerRegistered` `player_id=N` |
| Rated game | `ratedresults` insert — **seven columns only** (post-dagh-live-story § Rated game insert) | `ProcessCompletedGame` `game_id=N` |
| Midnight UTC | — | `FinalizeUtcDay` |

```bash
php ops/dispatch.php CMD=ProcessPlayerRegistered player_id=42 target=YOUR_TARGET
php ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=YOUR_TARGET
php ops/dispatch.php CMD=FinalizeUtcDay target=YOUR_TARGET
```

- Args: separate tokens (`CMD=…` `game_id=…` `target=…`) — not one comma-separated string.
- **`target=YOUR_TARGET`** or `database=…` from **`work-targets.ini`** — required; no silent default.
- Optional: `dry_run=1` (player/game); `as_of=2026-06-04T00:00:01Z` (midnight only).
- CLI only — [`../.htaccess`](../.htaccess) denies HTTP. **`exec`** and read exit code.

---

## CMD semantics

### `ProcessPlayerRegistered`

After committed `playertable` row → `entered_arena` from `JoinDate`. Does not create the player.

Historical players: `entered_arena` from **`zero-derived`** lobby seed. This CMD = **new** registrations after cutover.

### `ProcessCompletedGame`

After committed `ratedresults` with ground columns only; **`NewRatingA` NULL**. One transaction: row Elo/flags, both `playertable`, GST, periods, game milestones, streaks.

Same code as `run_ops_sim.php` per-game step. **No** C++ post-game on the same row.

- Exit **0** + `skipped=true`: bad ids, missing goals, etc. — no commit.
- Exit **2**: already processed.

### `FinalizeUtcDay`

~00:00:01 UTC daily: league finalize → league event milestones → `perfect_day` / `nightmare_day`. Three internal commits. Does not replace per-game dispatch.

---

## Exit codes

| Code | Meaning | Action |
|------|---------|--------|
| **0** | OK / dry-run / skipped game | Continue |
| **1** | Rolled back | Fix; retry if `NewRatingA` NULL |
| **2** | Already processed (game only) | Ignore duplicate |
| **64** | Bad CLI | Fix args |

Logs: `[dispatch]` prefix.

**Retry (`ProcessCompletedGame`):** yes on **1** if unprocessed; no on **2** or **0**+skip.

---

## Legacy

| CMD | Use |
|-----|-----|
| **`FinalizeUtcDay`** | **Steve cron** |
| `FinalizeLeagueDue` | **Do not** schedule — league finalize only |
| `Help` | List CMDs |

---

## Agents

| Topic | Detail |
|-------|--------|
| Registry | `includes/ops_dispatch.php` |
| Add CMD | module + registry + steve-live-ops + `periodic-register.md` if scheduled |
| Mid-simul repair | `run_ops_sim.php` / SQL — not dispatch |
| Local profiles | `local-work`, `local-dev` in ini — Dagh only |

**Code:** `dispatch.php` → `k2_ops_dispatch_run()`.
