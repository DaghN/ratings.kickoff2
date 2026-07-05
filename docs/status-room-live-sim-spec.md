# Status room live — local sim spec

**Status:** Locked intent (Jul 2026). How to **simulate lobby activity** so the Status live pulse can be tested without prod or live game-server writes.

**Not ops simul.** This doc is **not** [`work-db-prepare.md`](work-db-prepare.md) / `run_ops_sim.php`. It fakes **game-server telemetry** (`IsOnline`, live `resulttable` rows) that prod writes continuously but work/staging imports lack.

**Product policy:** [`status-room-live-policy.md`](status-room-live-policy.md) · implementation: [`status-room-live-implementation-plan.md`](status-room-live-implementation-plan.md)

**For agents:** read this before building lobby sim scripts, manual test SQL, or browser harnesses for Status live pulse.

---

## Goal

Prove the **1 s heartbeat** (`api/status_room_pulse.php` + `status-room-live.js`) without waiting for tonight's play:

| Behaviour | How sim exercises it |
|-----------|----------------------|
| Online list updates | Toggle `playertable.IsOnline` |
| Live games + clock + score pulse | Insert/update live `resulttable` row |
| Recent logins | Bump `LastLogin` |
| Cascade on rated finish | New `ratedresults.id` (+ optional ops post-game) |
| League / rating / arc ripple | Cascade path (see §4) |
| Glow choreography | Any of the above while watching `work.ratingskickoff.test/status.php` |

---

## Where to run (locked)

| ID | Decision |
|----|----------|
| SIM-1 | **Database:** **`ko2unity_work`** only for lobby sim smoke. |
| SIM-2 | **URL:** **`http://work.ratingskickoff.test/status.php`** — same PHP tree as dev; hostname selects work DB ([`LOCAL_DEV.md`](LOCAL_DEV.md)). |
| SIM-3 | **Do not** use **`ko2unity_db`** for lobby sim — dev DB is **frozen for ladder narrative** (no simul, no new rated history); schema/UI work only ([`database-copies-2026-06.md`](coordination/database-copies-2026-06.md)). |
| SIM-4 | **Do not** use **`ko2unity_baseline`** — pristine clone source; never mutate. |
| SIM-5 | **Staging `kooldb1`** — optional later; same pattern as work if Dagh wants remote smoke. Not required for v1 sim spec. |
| SIM-6 | **Prod** — real truth only; not for iterative pulse tuning. |

**Pulse API on work:** `http://work.ratingskickoff.test/api/status_room_pulse.php`

---

## What we mutate (ground / telemetry only)

Lobby sim touches **game-server ground**, not derived ladder tables directly:

| Table | Fields | Pulse signal |
|-------|--------|--------------|
| `playertable` | `IsOnline`, `LastLogin` (optional `LastActive`) | `online_fp`, `last_login_*` |
| `resulttable` | live row: scores, `HalfCountdown`, `GamePeriod`, `HasStarted`, `HasFinished`, `Shelved`, names/ids | `live_fp` |
| `ratedresults` | **Cascade only** — new row → `last_rated_id` | cascade hub |
| `generalstatstable` | `GamesPlayed` | arc confirm (usually updated with cascade) |

**Leave alone during lobby-only sim (Tiers A–B):** `player_period_league`, `player_period_games`, rating columns on `playertable`, milestones — unless running **Tier C2** post-game.

**Sign-off hygiene:** Lobby hacks on work are **low risk** for derived simul sign-off (telemetry ≠ derived). If work feels dirty → **`refresh work`** from baseline ([`work-db-prepare.md`](work-db-prepare.md) §3.1) or delete sim rows (§6).

---

## Reserved sim IDs (locked)

| ID | Decision |
|----|----------|
| SIM-7 | **Live game IDs:** `GameID >= 990000` (e.g. `990001`, `990002`) — never collide with real history. |
| SIM-8 | **Rated cascade (UI-only):** `ratedresults.id >= 9900000` if inserting fake finish rows — clearly synthetic. |
| SIM-9 | **Player IDs:** use **real** `playertable.ID` pairs from work (e.g. top active players); do not invent players. |

---

## Live row filter (must match prod)

Same as [`k2_status_live_games()`](../../site/public_html/includes/status_queries.php):

```sql
HasStarted = 1 AND HasFinished = 0 AND Shelved = 0
```

`HalfCountdown`: **50 ticks per second**; 5:00 half ≈ `15000` ticks at kickoff. Client ticks down locally between pulses.

---

## Three tiers

### Tier A — Manual SQL (default first proof)

**Effort:** ~15 minutes · **No repo code**

1. Open **`work.ratingskickoff.test/status.php`** in a browser (hard refresh).
2. Pick two real player IDs (example from work: `260` / `537`).
3. Run SQL on **`ko2unity_work`** (see §5 templates).
4. Within ~1 s: Online + Live games panels should update; half clock ticks every second.
5. Every few seconds, `UPDATE` score or `HalfCountdown` — score pulse + clock resync.
6. Teardown (§6).

**Proves:** SIM-T1 lobby tick path — online, live, clock, score pulse, glow on new row.

### Tier B — Web harness (shipped)

**Effort:** one session · **URL:** `http://work.ratingskickoff.test/status-room-live-sim.php`

One button starts ~**20 games** with **1–3 live at a time** and **5–15 goals** per match. No SQL or CLI required.

| Piece | Path |
|-------|------|
| Control page | `status-room-live-sim.php` |
| API | `api/status_room_live_sim.php` (`action=start\|stop\|status`) |
| Engine | `includes/status_room_live_sim.php` |
| Tick driver | `api/status_room_pulse.php` (Status heartbeat) + control page 1 s poll |

**Flow:** Click **Start 20-game sequence** → open **`/status.php`** → watch Online / Live games / cascades unfold over a few minutes.

**Guard:** `work.ratingskickoff.test` or database `ko2unity_work` only — returns 403 elsewhere.

**Cleanup:** **Stop & clean up** on the harness page, or start a new sequence (auto-cleans prior sim rows).

**Proves:** SIM-T1–T2 lobby ticks + SIM-T3 cascade when games finish (ops post-game on work when available).

### Tier B (legacy) — Scripted tick loop (optional CLI)

**Status:** superseded by web harness for Dagh smoke; CLI oneoff still optional.

Loop every 1–2 s for N iterations:

- decrement `HalfCountdown` by 50–100
- occasionally `ScoreA` / `ScoreB` += 1
- optional: flip `IsOnline` on a third player

CLI sketch:

```text
php scripts/oneoff/status_room_live_sim.php --game-id 990001 --ticks 60
```

**Proves:** SIM-T2 repeated lobby ticks without hand SQL.

**Status:** Web harness **shipped** (Jul 2026); CLI oneoff still optional if needed.

### Tier C — Simulated rated finish (cascade)

**Effort:** higher · **Two modes:**

| Mode | Purpose | Derived truth |
|------|---------|---------------|
| **C1 UI-only** | Glow ripple, recent games head, pulse cascade sections | **Wrong** — skip for sign-off |
| **C2 Holy path** | Full cascade correctness | **`run_process_game.php`** on new game id |

**C1 (quick cascade smoke):**

1. Finish live sim row (`HasFinished = 1` or delete).
2. `INSERT` synthetic `ratedresults` with `id = MAX(id)+1` (or reserved id), `Date = NOW()`, copy shape from recent row.
3. Optionally bump `generalstatstable.GamesPlayed`.
4. Watch cascade stagger on Status.

**C2 (proper):**

1. Finish live sim row.
2. Insert **valid ground** `ratedresults` row (eight ground columns + NULL derived).
3. `php site/public_html/ops/run_process_game.php process-one --game-id NEW_ID --target local-work`
4. Verify pulse cascade + league/rating tables match ops output.

**Proves:** SIM-T3 `last_rated_id` cascade hub ([`status-room-live-policy.md`](status-room-live-policy.md) § Rated finish cascade).

**Prefer C2** before calling cascade “signed off”; **C1** is fine for animation tuning.

---

## SQL templates (Tier A)

**Connect:**

```text
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2unity_work
```

**1. Mark players online**

```sql
UPDATE playertable SET IsOnline = 1, LastLogin = NOW() WHERE ID IN (260, 537);
```

**2. Start live game** — clone required columns from a recent finished row; adjust IDs/names. Minimal pattern (extend with NOT NULL columns from a real row if INSERT fails):

```sql
INSERT INTO resulttable (
  GameID, HostID, SlaveID, NameA, NameB, GameVersion, GameMode,
  StartTime, ScoreA, ScoreB, RatedGameID, RNDSetup, VersionCV, Duration,
  HalfCountdown, GamePeriod, HasStarted, HasFinished, Shelved,
  HostGUID, SlaveGUID, ConnectionMethod, Referee
)
SELECT
  990001, 260, 537, NameA, NameB, GameVersion, GameMode,
  NOW(), 0, 0, -1, RNDSetup, VersionCV, 0,
  15000, 1, 1, 0, 0,
  HostGUID, SlaveGUID, ConnectionMethod, Referee
FROM resulttable WHERE GameID = 85766 LIMIT 1;
-- Fix NameA/NameB to match HostID/SlaveID if clone used wrong names:
UPDATE resulttable SET NameA = (SELECT Name FROM playertable WHERE ID = 260),
                       NameB = (SELECT Name FROM playertable WHERE ID = 537)
WHERE GameID = 990001;
```

**3. Goal + clock tick**

```sql
UPDATE resulttable
SET ScoreA = ScoreA + 1, HalfCountdown = GREATEST(0, HalfCountdown - 50)
WHERE GameID = 990001;
```

**4. Teardown**

```sql
DELETE FROM resulttable WHERE GameID >= 990000;
UPDATE playertable SET IsOnline = 0 WHERE ID IN (260, 537);
```

---

## Test checklist

Run on **`work.ratingskickoff.test/status.php`** with DevTools Network → `status_room_pulse.php` visible.

| # | Step | Pass |
|---|------|------|
| SIM-T1 | Tier A: online + live appear without reload | |
| SIM-T2 | Half clock ticks every second between pulses | |
| SIM-T3 | Score UPDATE → pulse within ~1 s + score pulse animation | |
| SIM-T4 | New live game row → row glow | |
| SIM-T5 | Unchanged second → `{ changed: false }` in network tab | |
| SIM-T6 | Tier C: `last_rated_id` bump → cascade stagger (recent → ratings → league → arc) | |
| SIM-T7 | Teardown → panels return to empty/stable; no orphan `990000` rows | |

---

## Cleanup

| Situation | Action |
|-----------|--------|
| After manual sim | §5 step 4 DELETE + IsOnline reset |
| Work feels polluted | `scripts\reset_local_work_db.ps1` or full prepare refresh ([`work-db-prepare.md`](work-db-prepare.md)) |
| Accidental cascade C1 | Note derived may be wrong — **re-simul** if you need trustworthy league/ratings on work |

---

## Future tooling (out of scope until asked)

| Item | Role |
|------|------|
| `scripts/oneoff/status_room_live_sim.php` | Optional CLI tick loop (web harness preferred) |
| `scripts/oneoff/status_room_pulse_probe.php` | Signal bundle + timing ms |

Do **not** add sim UI to prod `status.php`. Harness lives at **`/status-room-live-sim.php`** (work host only).

---

## Relationship to ops simul

| | **Lobby sim (this doc)** | **Ops simul** |
|--|--------------------------|---------------|
| Command | Manual SQL / future oneoff | `run_ops_sim.php run --target local-work` |
| Simulates | Game **server** presence + live match shell | **Post-game derived** writers over history |
| DB | `ko2unity_work` telemetry | `ko2unity_work` derived tables |
| Needed for pulse test? | **Yes** | **No** (except Tier C2 finish) |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-06 | **Tier B web harness shipped** — `status-room-live-sim.php` + API + engine; pulse-driven ticks |
| 2026-07-06 | Initial spec — work DB + work URL, Tier A–C, reserved IDs, SQL templates |