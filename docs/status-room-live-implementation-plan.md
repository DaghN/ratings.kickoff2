# Status room live — implementation plan

**Status:** **Shipped in repo (Jul 2026)** — heartbeat + cascade + glow. Prod smoke on live DB recommended.

**Policy:** [`status-room-live-policy.md`](status-room-live-policy.md) · **Local sim:** [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md)

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
| Interval | 1 s client poll; 1 s server shared cache |
| Cascade trigger | `last_rated_id` change |
| League | Active tab only; Activity + Points |
| Clock | Client ticks `half_countdown` at 50/s; resync on pulse |
| Glow | Shared `k2-live-glow` + `k2-live-score-pulse`; cascade stagger ~150 ms |
| Tab hidden | **Keep polling** |

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
| **SRL-6** | Glow utility CSS/JS + staggered cascade choreography | **Done** Jul 2026 |
| **SRL-7** | Period rollover via `period_keys` + retire duplicate 30 s league meta poll | **Done** Jul 2026 |
| **SRL-8** | Wire into `status.php`, docs, polish | **Done** Jul 2026 — prod smoke on live DB |

---

## File map (target)

| Piece | Location |
|-------|----------|
| Pulse API | `site/public_html/api/status_room_pulse.php` |
| Signal helpers | `includes/status_room_pulse.php` (new) or extend `status_queries.php` |
| Server 1 s cache | `includes/status_room_pulse_cache.php` (new) — APCu if available, else request-static + `microtime` bucket |
| Client engine | `site/public_html/js/status-room-live.js` |
| Glow CSS | `stylesheets/theme.css` or `stylesheets/k2-live-glow.css` |
| Glow JS helper | `site/public_html/js/k2-live-glow.js` (small) |
| Markup hooks | `includes/status_room_section.php` — `data-k2-status-live-*` roots |
| Page enqueue | `status.php` — script tag + defer |
| Leagues integration | `js/status-period-competitions.js` — gate/remove 30 s meta interval (SRL-7) |

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
ORDER BY StartTime DESC LIMIT 10;

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

  if response.cascade → runCascade(response.sections) with staggered glow

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

Rating table DOM: replace `<tbody>`; call `k2TableInit` / sortable rebind if project helper exists (grep `k2Table` after patch).

---

## SRL-6 detail — glow stagger

Cascade order (150 ms steps):

1. Recent games panel
2. Rating table panel
3. Leagues block (both columns)
4. Arc game count span

Use `k2TriggerLiveGlow(panelEl)` per step. Score-only changes skip stagger — instant pulse on score node.

---

## Verification

### Local / staging

```powershell
# Pulse responds
curl.exe -s "http://ratingskickoff.test/api/status_room_pulse.php"

# Cache hit (same revision within 1s)
curl.exe -s "http://ratingskickoff.test/api/status_room_pulse.php"; curl.exe -s "http://ratingskickoff.test/api/status_room_pulse.php"
```

Browser (prod for true live):

1. Open `status.php` — SSR paint unchanged.
2. Live game running — clock ticks every second; score updates within ~1 s of goal.
3. Game finishes — cascade ripple; recent games head glows; rating row updates.
4. Player logs in — online list updates without reload.
5. Leagues meta countdown still correct; no double-firing from old 30 s interval.

### Probe (optional)

Add `scripts/oneoff/status_room_pulse_probe.php` — prints signal bundle + timing ms (mirror `status_load_breakdown_probe.php`).

---

## Risks / notes

| Risk | Mitigation |
|------|------------|
| 1 s × N viewers DB load | Server 1 s shared cache (SRL-1) |
| Rating table sortable break after tbody swap | Re-init k2-table or document “resets to default sort on cascade” for v1 |
| Staging looks “broken” | Document in UI? **No** — same as today; staging is snapshot |
| Half clock drift | Resync every pulse; server wins on conflict |
| `last_rated_id` alone misses unrated finish | Rare for Status story; live_fp drop still catches live panel |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-06 | Initial plan — slices SRL-0…SRL-8 |