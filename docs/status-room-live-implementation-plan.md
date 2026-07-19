# Status room live — implementation plan

**Status:** **Pulse shipped (Jul 2026)** · **Live sim harness shipped (work only)** · **SRL-16 rating re-sort shipped** · **Next:** SIM-R3 / SIM-R5 optional.

**Policy:** [`status-room-live-policy.md`](status-room-live-policy.md) · **Live sim:** [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md)

**Parent:** [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) · [`status-period-competitions.md`](status-period-competitions.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md)

**Migration:** **None** — read-only PHP + JS. No DDL, no ops writers, no Part B registers.

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. One slice per session unless Dagh asks for a batch.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**SRL-1…SRL-16**), signals, cascade, glow, API sketch |
| **This plan** | File paths, slice tasks, STOP gates, verification commands |
| **Starter prompt** | Optional — create under `docs/orchestration/agent-handoffs/` when starting SRL-1 |

---

## How to use this plan

1. Execute slices **SRL-1 → SRL-8** in order (**SRL-0** = policy + plan — done Jul 2026).
2. **STOP** if pulse API runs full `k2_status_load_room()` on every tick — use signal bundle only.
3. **STOP** if rating/league tables reload on every heartbeat without `last_rated_id` change.
4. **STOP** if glow reimplements jukebox keyframes from scratch — extract shared CSS/JS.
5. Reuse existing query functions in `status_queries.php`; do not duplicate SQL in API files.
6. After **SRL-8**: UPDATE_DOCS Part A — `STATUS_PAGE_DATA.md`, `PROJECT_MEMORY.md`, policy status → Shipped.

---

## Locked decisions (compressed)

See policy **SRL-1…SRL-16**. Implementer essentials:

| # | Rule |
|---|------|
| Endpoint | `api/status_room_pulse.php` |
| Interval | 1 s client poll; **fresh DB signal read** each pulse (no server signal cache on read path) |
| Cascade trigger | `last_rated_id` change |
| League | Active tab only; Activity + Points |
| Clock | Client SRL-9: **pending** → **running** (smooth uncapped) → **held** after 6 s idle; client `sync_epoch` |
| Glow | Minimal lobby set + **cascade:** active LB Elo (gainers); league Activity **Games** (both finishers); Points **Pts** (winner, or both on draw) — white bloom @ 2.6 s |
| Tab hidden | **Keep polling**; **catch-up poll** on visible (`visibilitychange` / `pageshow` / `focus`) |
| Prod | Pulse = DB reads only; sim tick hook **guarded no-op** off work — see policy § Production readiness |

---

## Reference implementation (copy patterns)

| Area | Reference |
|------|-----------|
| Policy | [`status-room-live-policy.md`](status-room-live-policy.md) |
| Status queries | `includes/status_queries.php` — `k2_status_live_games`, `k2_status_online_players`, `k2_status_active_top_rated`, `k2_status_arc_ticker`, … |
| Room markup | `includes/status_room_section.php` — panel hooks, live list structure |
| Leagues fetch | `js/status-period-competitions.js` — `fetch` + cache by period key; reuse league JSON shapes |
| League APIs | `api/status_period_points_league.php`, `api/server_period_activity_leaderboard.php`, `api/status_period_day_games.php` |
| Page boot | `js/k2-page-boot.js` — `k2OnPageReady` |
| Jukebox glow | `stylesheets/k2-jukebox.css` (`k2-jukebox-track-glow`), `js/k2-jukebox-launcher.js` |
| Server clock | `k2_status_server_clock()` + `data-server-now-epoch` pattern in Leagues block |
| No-cache headers | `status.php` top — pulse API should send `Cache-Control: no-store` |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **SRL-0** | Policy + this plan | Dagh OK — **done** |
| **SRL-1** | Pulse API + signal SQL + 1 s server cache | **Done** Jul 2026 |
| **SRL-2** | `js/status-room-live.js` — heartbeat loop + state machine | **Done** Jul 2026 |
| **SRL-3** | Live games panel — patch DOM + client half clock + score pulse | **Done** Jul 2026 |
| **SRL-4** | Online + recent logins + new players patches | **Done** Jul 2026 |
| **SRL-5** | Rated-finish cascade — recent games, ratings, arc, league (active tab) | **Done** Jul 2026 |
| **SRL-6** | Glow utility CSS/JS (lobby tick) | **Done** Jul 2026; cascade sequence **retired** (SRL-12) |
| **SRL-7** | Period rollover via `period_keys` + retire duplicate 30 s league meta poll | **Done** Jul 2026 |
| **SRL-8** | Wire into `status.php`, docs, polish | **Done** Jul 2026 |
| **SRL-16** | Rating table re-sort after cascade tbody swap (`k2TableRefreshSortableBody`) | **Done** Jul 2026 |

---

## Phase 2 — Live environment sim + testing (Jul 2026)

**Spec:** [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md). **Not** a new SRL slice — separate SIM-R* roadmap.

| Item | Status |
|------|--------|
| Web harness (`status-room-live-sim.php`) | **Shipped** |
| L2 registration + sim page options | **Shipped** |
| L1 login/logout + L3 games + ops on finish | **Shipped** |
| Realistic pacing (concurrent matches, staggered kickoff, timed goals) | **Shipped** |
| Online/match integrity + per-game crash | **Shipped** |
| Stop cleanup (logout all, cancel live, clear queue) | **Shipped** |
| Guard: `ko2unity_work` + `work.ratingskickoff.test` | **Shipped** |
| Pulse tick hook | **Shipped** |
| Sim wall-clock catch-up (status load + pulse; cap 600 s) | **Shipped** Jul 2026 |

**Before more pulse/sim code:** run § Verification (work harness) and SIM-T1…T13 in sim spec.

---

## File map (target)

| Piece | Location |
|-------|----------|
| Pulse API | `site/public_html/api/status_room_pulse.php` |
| Signal helpers | `includes/status_room_pulse.php` (new) or extend `status_queries.php` |
| Server 1 s cache | `includes/status_room_pulse_cache.php` (new) — APCu if available, else request-static + `microtime` bucket |
| Client engine | `site/public_html/js/status-room-live.js` |
| Glow CSS | `stylesheets/theme.css` — `k2-live-glow-bloom` (text-shadow; no box/pill) |
| Glow JS helper | `site/public_html/js/k2-live-glow.js` — `trigger`, `scorePulse` |
| Visibility catch-up | `js/status-room-live.js` — `catchUpOnVisible` / `bindVisibilityCatchUp` |
| Markup hooks | `includes/status_room_section.php` — `data-k2-status-live-*` roots |
| Page enqueue | `status.php` — script tag + defer |
| Leagues integration | `js/status-period-competitions.js` — gate/remove 30 s meta interval (SRL-7) |
| Rating cascade re-sort | `js/k2-table.js` — `k2TableRefreshSortableBody()`; `status-room-live.js` `patchRatings()` |
| **Live sim control page** | `status-room-live-sim.php` (work host only) |
| **Live sim API** | `api/status_room_live_sim.php` |
| **Live sim engine** | `includes/status_room_live_sim.php` |
| **Live sim client** | `js/status-room-live-sim.js` |
| Pulse → sim tick | `api/status_room_pulse.php` + `status.php` (before load) → `k2_status_room_sim_tick_if_due()` when allowed |

---

## SRL-1 detail — pulse API

### Signal queries (cheap)

Implement as named functions; unit-test via one-off probe if needed.

```sql
-- last_rated_id
SELECT MAX(id) AS v FROM ratedresults;

-- games_played
SELECT GamesPlayed AS v FROM generalstatstable WHERE id = 1 LIMIT 1;

-- live rows (for fp + payload)
SELECT GameID, HostID, SlaveID, NameA, NameB, ScoreA, ScoreB, GamePeriod, HalfCountdown, StartTime
FROM resulttable
WHERE HasStarted = 1 AND HasFinished = 0 AND Shelved = 0
ORDER BY StartTime ASC LIMIT 10;

-- online ids
SELECT ID FROM playertable WHERE COALESCE(IsOnline, 0) <> 0 ORDER BY ID;

-- last login head
SELECT ID, Name, LastLogin FROM playertable ORDER BY LastLogin DESC LIMIT 1;

-- last join head
SELECT ID, Name, JoinDate FROM playertable ORDER BY JoinDate DESC LIMIT 1;
```

League fingerprint + period keys: reuse `k2_status_league_period_bounds()` / period competition helpers from `status_queries.php`.

### Cache

- Key: `status_room_pulse:v1`
- TTL: **1 second** wall clock
- Store computed `{ revision, signals, sections? }` — sections omitted when building cache for unchanged next second is OK; rebuild sections on demand when client revision differs

### Request

```
GET /api/status_room_pulse.php?revision=<last>&period=week&key=2026-W27
```

Query params:

- `revision` — client last seen; if matches cached unchanged → minimal body
- `period`, `key` — active league tab (from Leagues JS or data attrs on room root)

---

## SRL-2 detail — client state machine

```
onPulse(response):
  if !response.changed → return

  if response.cascade → applyCascadeSections(response.sections)  /* data refresh only; no glow sequence */

  if live_fp changed → patchLive(response.sections.live)
  if online_fp changed → patchOnline(...)
  if last_login changed → patchLogins(...)
  if last_join changed → patchRegistrations(...)
  if period_keys changed → reloadLeagueForNewPeriod(...)

  syncLiveClocks(response.sections.live || cachedLive)
```

Boot: `k2OnPageReady`; root `[data-k2-status-room-live]`.

Local clock: `setInterval(tickLiveClocks, 1000)` independent of fetch schedule (both 1 s).

---

## SRL-5 detail — cascade payloads

On `last_rated_id` change, pulse returns `cascade: true` and sections:

| Section | Source function |
|---------|-----------------|
| `live` | `k2_status_live_games()` |
| `recent_games` | `k2_status_recent_rated_games()` |
| `ratings` | `k2_status_active_top_rated()` + count |
| `arc` | `k2_status_arc_ticker()` |
| `league.activity` | existing activity API logic for active period/key |
| `league.points` | existing points API logic |
| `league.meta` | timing + total_games |

Prefer **internal PHP calls** over HTTP loopback to league APIs.

Rating table DOM: replace `<tbody>`; call **`k2TableRefreshSortableBody(table)`** (preserves user sort when chosen, else default Elo desc + autorank).

---

## SRL-12 — cascade glow sequence (retired Jul 2026)

**Removed.** Cascade applies DOM patches only. Glow follows lobby rules (SRL-10/11): new list row ids, score deltas, arc count change. No `runCascadeGlow`, `highlight_player_ids`, or `glowRatingsPlayer`.

---

## Verification

### Primary — local work + live sim (recommended)

See **Quick start** in [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md).

```powershell
# Sim API allowed on work host only
curl.exe -s -H "Host: work.ratingskickoff.test" "http://127.0.0.1/api/status_room_live_sim.php?action=status"

# Sim blocked on dev host
curl.exe -s -H "Host: ratingskickoff.test" "http://127.0.0.1/api/status_room_live_sim.php?action=status"

# Pulse responds on work
curl.exe -s -H "Host: work.ratingskickoff.test" "http://127.0.0.1/api/status_room_pulse.php"
```

Browser on **`work.ratingskickoff.test`**:

1. Sim page → **Start** → Status tab open.
2. SIM-T1…T13 from sim spec checklist (SIM-T13: leave Status mid-game 2+ min → clock advanced on return).
3. Confirm `{ changed: false }` on quiet seconds; cascade on game finish (SIM-T6: data refresh + new recent row glow if id diff).
4. Tab away during sim → Stop → tab back: Status catches up without manual refresh (visibility catch-up).

### Secondary — dev / staging (pulse only, no sim)

```powershell
curl.exe -s "http://ratingskickoff.test/api/status_room_pulse.php"
```

Staging/prod: pulse works; **no moving lobby** without real game server or work sim.

### Optional — prod smoke

When prod is live: open `status.php` during real play — clock, scores, cascade. Not a substitute for work sim during development.

### Probe (optional)

Add `scripts/oneoff/status_room_pulse_probe.php` — prints signal bundle + timing ms (SIM-R5).

---

## Risks / notes

| Risk | Mitigation |
|------|------------|
| 1 s × N viewers DB load | Fresh signal SQL each poll; **`changed: false`** skips section HTML only; monitor on prod |
| Rating table sortable break after tbody swap | **`k2TableRefreshSortableBody()`** after cascade — preserves user sort or defaults to Elo desc |
| Staging looks “broken” | Document in UI? **No** — use **work live sim** for moving lobby; staging is snapshot |
| Half clock drift | SRL-9 pending / running / held (Jul 2026) — smooth mid-match; hold after 6 s with no decrease |
| `last_rated_id` alone misses unrated finish | Rare for Status story; live_fp drop still catches live panel |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-06 | **SRL-9 + sim catch-up** — `live_clocks` every pulse; sim wall-clock replay; cascade league/LB glow; prod-readiness nuance in policy |
| 2026-07-06 | **Doc sync** — glow contract (text ink, cascade player ids, visibility catch-up, SRL-16, full active LB) across policy + plan + STATUS_PAGE_DATA + sim checklist |
| 2026-07-06 | **SRL-16** — rating table re-sort after cascade (`k2TableRefreshSortableBody`) |
| 2026-07-06 | Phase 2 — live sim file map, work-first verification, SIM roadmap pointer |
| 2026-07-06 | Initial plan — slices SRL-0…SRL-8 |