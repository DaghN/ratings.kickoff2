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
- **CLI:** `php ops/dispatch.php` — [`../.htaccess`](../.htaccess) denies HTTP under `ops/`.
- **HTTP (game server):** `public_html/dispatch_request.php?key=…&CMD=…&game_id=…&target=…` — JSON `{ ok, exit, cmd, log }`; auth in `ops/config/dispatch-http.ini`. See [`steve-live-ops.md`](steve-live-ops.md).

---

## CMD semantics

### `ProcessPlayerRegistered`

After committed `playertable` row → `entered_arena` from `JoinDate`. Does not create the player.

Historical players: `entered_arena` from **`zero-derived`** lobby seed. This CMD = **new** registrations after cutover.

### `ProcessCompletedGame`

After committed `ratedresults` with ground columns only; **`NewRatingA` NULL**. One transaction (**only on InnoDB tables**; MyISAM cannot roll back): row Elo/flags, both `playertable`, GST, periods, game milestones, streaks.

**In order:** earlier waiting games are processed first, one writer at a time (`GET_LOCK`, also held by `zero-derived` and `run_ops_sim.php`). A day change closes the previous UTC day first. A web call waits at most 5 s for the lock and spends at most 10 s on a backlog. Over 1000 waiting games is refused (recalculation in progress).

Same code as `run_ops_sim.php` per-game step. **No** C++ post-game on the same row.

- Exit **0** + `skipped=true`: bad ids, missing goals, etc. — no commit; log and continue.
- Exit **2**: `skip_reason=already_processed` (`NewRatingA` already set) — safe duplicate dispatch; no commit.

- Exit **3**: refused — later games are already processed; full recalculation needed. Nothing is written.

### `ProcessPending`

All waiting games in order. `dry_run=1` reports `waiting` and `holes` and writes nothing. Lock busy → exit 0, nothing done. `max_backlog=N` overrides the 1000 limit (CLI, after a very long outage).

### `FinalizeUtcDay`

~00:00:01 UTC daily: league finalize → league event milestones → `perfect_day` / `nightmare_day`. Three internal commits. Does not replace per-game dispatch.

---

## Exit codes

| Code | Meaning | Action |
|------|---------|--------|
| **0** | OK / dry-run / skipped game | Continue |
| **1** | Failed, busy, or backlog not cleared; game stays queued (log says **HALF-PROCESSED** if MyISAM tables could not roll back) | Retry or leave to `ProcessPending`; HALF-PROCESSED → full recalculation |
| **2** | Already processed (game only) | Ignore duplicate |
| **3** | Refused: later games already processed | Full recalculation |
| **64** | Bad CLI | Fix args |

Logs: `[dispatch]` prefix.

**Retry (`ProcessCompletedGame`):** yes on **1** if unprocessed; no on **2** (duplicate); **0**+other skip = fix data if unexpected, else continue.

---

## Legacy

| CMD | Use |
|-----|-----|
| **`FinalizeUtcDay`** | **Steve cron** |
| `FinalizeLeagueDue` | **Do not** schedule — league finalize only |
| `Help` / `List` | Print usage to stderr — exit **64** (not a success smoke test) |

---

## Agents

| Topic | Detail |
|-------|--------|
| Registry | `includes/ops_dispatch.php` |
| Cutover / simul | [`cutover-readiness.md`](../../../../docs/coordination/cutover-readiness.md) — not batch `REP-xxx` on prod |
| Add CMD | module + registry + steve-live-ops + `periodic-register.md` if scheduled |
| Mid-simul repair | `run_ops_sim.php` / SQL — not dispatch |
| Local profiles | `local-work`, `local-dev` in ini — Dagh only |

**Code:** `dispatch.php` → `k2_ops_dispatch_run()`.
