# Steve — live ops via `dispatch.php`

**On server:** `public_html/ops/docs/steve-live-ops.md` (Dagh syncs `site/public_html/`; **you** run CLI on the host).

**Read first:** [`post-dagh-live-story.md`](post-dagh-live-story.md) (bootstrap on prod copy → simul → this live phase).  
**Detail:** [`ops-dispatch.md`](ops-dispatch.md) (exit codes, retries, legacy CMDs).

---

## Live ops (after bootstrap + simul)

| # | You (ground) | Then PHP |
|---|----------------|----------|
| **1** | Insert **`playertable`** row | `CMD=ProcessPlayerRegistered` |
| **2** | Insert **`ratedresults`** — `Date`, `idA`, `NameA`, `idB`, `NameB`, `GoalsA`, `GoalsB` only | `CMD=ProcessCompletedGame` |
| **3** | Cron ~**00:00:01 UTC** | `CMD=FinalizeUtcDay` |

Game server and website must use the **same** database. **No** C++ post-game on those rows. **No** rating decay.

All commands: **`cd public_html`**, space-separated `key=value` args, your **`target=…`** from `work-targets.ini`.

---

## Setup (once)

1. Dagh uploads **`public_html/`** (incl. `ops/` and `dispatch_request.php`).
2. You: `ops/config/work-targets.ini.example` → `work-targets.ini` — set **real** `host`, `user`, `password`, and `work_database` for this server (do not leave example `127.0.0.1` on production). Extra profiles (e.g. **`[live-game]`**) need **only** an ini section — no PHP stub in `ops_prepare_constants.php`. **Never** put live passwords in PHP source.
3. **Game server (HTTP):** `ops/config/dispatch-http.ini.example` → `dispatch-http.ini` — set a strong **`shared_key`** (not the example placeholder).
4. Smoke (on web host): `php ops/dispatch.php CMD=Help` — prints command list to stderr; exit **64** is normal (not a failure).
5. Smoke (HTTP): `GET /dispatch_request.php?key=YOUR_KEY&CMD=ProcessCompletedGame&game_id=TEST_ID&target=YOUR_TARGET` — JSON `exit` field matches CLI exit codes.

**HTTP streams:** `includes/ops_std.php` provides `stderr()` / `stdout()` so dispatch logging works when PHP is not pure CLI (`STDERR`/`STDOUT` may be undefined). Dagh keeps this file in git; do not delete it on the server.

**Targets:** `k2_ops_load_work_target` accepts built-in profiles **or** any `[section]` in `work-targets.ini` (ini-only must set `work_database`, `baseline_database`, `host`, `user`). `database=` resolution also scans ini sections.

---

## Commands

**Registration** (after `playertable` commit; not for accounts that already got `entered_arena` from **`zero-derived`**):

```bash
php ops/dispatch.php CMD=ProcessPlayerRegistered player_id=PLAYER_ID target=YOUR_TARGET
```

**Rated game** (after ground `INSERT`; use row `id` as `game_id`):

```bash
php ops/dispatch.php CMD=ProcessCompletedGame game_id=GAME_ID target=YOUR_TARGET
```

| Exit | Meaning |
|------|---------|
| **0** | OK — derived committed, or skipped (bad ids / goals — see log) |
| **1** | Failed — retry after fix if `NewRatingA` still NULL |
| **2** | Already processed (`NewRatingA` set) — safe duplicate; ignore |
| **64** | Bad CLI |

**Midnight UTC:**

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=YOUR_TARGET
```

Use **`FinalizeUtcDay`** only — not **`FinalizeLeagueDue`** (legacy, league-only).

---

## From the game server (.exe)

The game server often runs on a **different machine** than the website — **no PHP CLI** there. Use **HTTP** via `dispatch_request.php` on the site host.

**Rated game** (after ground `INSERT`; use row `id` as `game_id`):

```text
https://ratings.kickoff2.com/dispatch_request.php?key=YOUR_KEY&CMD=ProcessCompletedGame&game_id=57216&target=YOUR_TARGET
```

**Registration:**

```text
https://ratings.kickoff2.com/dispatch_request.php?key=YOUR_KEY&CMD=ProcessPlayerRegistered&player_id=42&target=YOUR_TARGET
```

Read the JSON **`exit`** field (same semantics as CLI). **`log`** carries `[dispatch]` lines. HTTP status: **200** for exit **0** or **2**; **400** for **64**; **500** for **1**; **401** if `key` is wrong; **503** if `dispatch-http.ini` is missing.

`key=` is required on every request (value from `ops/config/dispatch-http.ini`).

**Optional — same host as PHP:** `exec` CLI instead:

```text
/usr/bin/php ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=YOUR_TARGET
```

`ops/dispatch.php` remains **CLI only** (`.htaccess` blocks HTTP under `ops/`). **`dispatch_request.php`** is the public HTTP bridge.

**Midnight UTC** — run on the **web server** (cron + PHP CLI), not from the game machine:

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=YOUR_TARGET
```

---

## Bootstrap / simul (before live)

Not covered here — see [`post-dagh-live-story.md`](post-dagh-live-story.md): `migrate-work` → `seed-catalog` → `zero-derived` → `run_ops_sim.php` → `run_verify_ops_sim.php`.

**Dev-only (ignore for server):** `run_process_game.php replay-to`, full `prepare` with `refresh-work`.
