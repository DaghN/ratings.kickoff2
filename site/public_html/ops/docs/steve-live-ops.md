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

1. Dagh uploads **`public_html/`** (incl. `ops/`).
2. You: `ops/config/work-targets.ini.example` → `work-targets.ini` (credentials + profile).
3. Smoke: `php ops/dispatch.php CMD=Help`

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
| **0** | OK (or skipped with log — still continue) |
| **1** | Failed — retry if `NewRatingA` still NULL |
| **2** | Already processed |
| **64** | Bad CLI |

**Midnight UTC:**

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=YOUR_TARGET
```

Use **`FinalizeUtcDay`** only — not **`FinalizeLeagueDue`** (legacy, league-only).

---

## From the game server (.exe)

`exec` PHP from `public_html/` (or absolute paths); read **exit code**, not only stdout.

```text
/usr/bin/php ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=YOUR_TARGET
```

`ops/` is **CLI only** — not HTTP.

---

## Bootstrap / simul (before live)

Not covered here — see [`post-dagh-live-story.md`](post-dagh-live-story.md): `migrate-work` → `seed-catalog` → `zero-derived` → `run_ops_sim.php` → `run_verify_ops_sim.php`.

**Dev-only (ignore for server):** `run_process_game.php replay-to`, full `prepare` with `refresh-work`.
