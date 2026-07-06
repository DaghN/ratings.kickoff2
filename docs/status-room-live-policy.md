# Status room live — policy

**Status:** Shipped (Jul 2026). Spec for making `status.php` a **living lobby** — heartbeat polling, cascade refresh on rated game finish, client-side live-game clocks, and glow feedback.

**Authority:** Product + [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md). Dagh's latest chat wins on scope. Visual glow defers to [`design-direction.md`](design-direction.md) accent tokens.

**For agents:** read this before adding Status live polling, pulse APIs, or lobby glow. Implementation slices: [`status-room-live-implementation-plan.md`](status-room-live-implementation-plan.md).

**Pointers:** [`status-period-competitions.md`](status-period-competitions.md) · [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md) (**live environment simulation** — login, register, games; work harness) · [`k2-jukebox-popup.md`](k2-jukebox-popup.md) (glow reference) · [`k2-page-boot.js`](../site/public_html/js/k2-page-boot.js) (`k2OnPageReady`)

---

## Product intent

Status is the **“right now”** hub — who is online, what is playing, what just happened. Today the room is **server-rendered once** per full page load; only the Leagues meta countdown ticks client-side (30 s).

**Goal:** the whole room **comes alive** without reload:

- **1 s heartbeat** — cheap signal check; heavy work only when something changed.
- **Rated game finish** is the **master cascade** — recent games, rating table, league standings, arc totals update together.
- **Lobby ticks** — live scores, online presence, logins, registrations patch in place.
- **Live-game half clock** runs down **client-side** between heartbeats (smooth, no extra DB reads).
- **Glow feedback** — reuse jukebox track-change bloom for new rows and cascade moments; shorter pulse for score digits.

**Not a separate live feed from Steve.** Same KOOL Unity MySQL family as today ([`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) § Finding). Staging and local **dev** remain **snapshot stale** unless you run the **live environment sim** on work (below).

**Testing without prod:** [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md) — do not wait for tonight's play to exercise pulse, cascade, and glow.

---

## Locked decisions

| ID | Decision |
|----|----------|
| SRL-1 | **Polling, not push** — no WebSockets/SSE for v1. Game server already writes MySQL; web reads it. |
| SRL-2 | **One heartbeat endpoint** — `api/status_room_pulse.php` (name locked in plan). Client polls **every 1 s** while the page is loaded. **Do not** voluntarily pause on `document.hidden` (multi-monitor / tiled layouts). **Browser note:** background tabs may throttle timers; **catch-up poll** on `visibilitychange` / `pageshow` (bfcache) / `focus` so returning to Status reflects current DB immediately. |
| SRL-3 | **Server-side 1 s shared cache** on the pulse bundle — all viewers share one compute tick per second. |
| SRL-4 | **Two change kinds:** **lobby tick** (presence, live state) vs **rated finish cascade** (`last_rated_id` change). |
| SRL-5 | **`last_rated_id`** = `MAX(id) FROM ratedresults` — primary cascade trigger. When it changes, refresh cascade sections (below). |
| SRL-6 | **Between finishes:** no rating-table or league-standings work. Heading count reloads **with** the full rating table on cascade, not on its own. |
| SRL-7 | **League refresh:** **active period tab only** (Day / Week / Month / Year). No adjacent-tab prewarm in v1 — keep simple. |
| SRL-8 | **Both** Activity + Points league tables in cascade (same period key as active tab). Reuse existing league query/API helpers where possible. |
| SRL-9 | **Live half clock:** client ticks from server `half_countdown` (50 ticks/s) + `sync_epoch`; resync every heartbeat. Display `M:SS`. |
| SRL-10 | **Glow — row / panel:** extract jukebox `k2-jukebox-track-glow` into shared **`k2-live-glow`** utility (class + JS trigger). |
| SRL-11 | **Glow — score change:** shorter digit pulse (`k2-live-score-pulse`) — distinct from full row glow. |
| SRL-12 | **Cascade glow choreography:** stagger affected panels **~150 ms** apart (recent games → rating table → league → arc count). Fun, not chaotic. |
| SRL-13 | **First paint stays SSR** — `status.php` + `k2_status_load_room()` unchanged for no-JS and fast paint; heartbeat **enhances**. |
| SRL-14 | **Revision / fingerprint** — when no signals changed, API returns `{ changed: false, revision }` (tiny body). |
| SRL-15 | **Period rollover:** heartbeat includes `period_keys` (day/week/month/year). Key change → league reload + meta glow (midnight/week boundaries). |
| SRL-16 | **Sortable rating table:** after cascade DOM replace, re-init or patch via `k2-table.js` conventions — preserve user sort if feasible; default re-sort by Elo acceptable for v1. |
| SRL-17 | **Live sim hook:** `api/status_room_pulse.php` may call `k2_status_room_sim_tick_if_due()` when [`k2_status_room_sim_is_allowed()`](../../site/public_html/includes/status_room_live_sim.php) — **work host + `ko2unity_work` only**; never prod/staging. Sim UI at `/status-room-live-sim.php`, not on `status.php`. |

---

## Change model

### Lobby tick (frequent, small patches)

| Signal | Source | Client action |
|--------|--------|---------------|
| `live_fp` | Hash of live `resulttable` rows (`game_id`, scores, `GamePeriod`) — **not** `half_countdown`; client ticks clock locally | Patch live list; score pulse; add/remove rows + row glow; resync clock anchor on fp change |
| `online_fp` | Hash of online player ids | Patch Online list; glow new names |
| `last_login_epoch` | Head of `playertable.LastLogin` | Refresh recent logins; **row glow for each player id newly in the list** (may be several in one second) |
| `last_join_epoch` | Head of `playertable.JoinDate` | Refresh new players; **row glow for each new registration id** |
| Local 1 s timer | Client math on last live payload | Tick half clocks (`half_countdown` at 50 ticks/s) |

### Rated finish cascade (rare, coordinated refresh)

When **`last_rated_id`** (or confirming **`games_played`**) changes:

| Section | Action |
|---------|--------|
| Live games | Patch (finished game should drop off) |
| Recent games | Full list refresh; **row glow for each new game id** in the list |
| Rating table + heading count | **Full table reload** (one payload) |
| League Activity + Points | Full reload for **active tab** period key |
| League meta | Refresh `total_games`, period label |
| Arc ticker | Refresh game count (`generalstatstable` / fallback); **glow blue number** |

**Staggered glow** across these panels (~150 ms steps) per SRL-12.

**Do not cascade-refresh:** Online (unless `online_fp` also changed), New players (unless join signal), heritage box, arc “since DATE” label, arc player count (changes rarely — optional daily refresh on period key change).

---

## Heartbeat signal bundle

Computed once per second (cached server-side). Minimal SQL — **not** a full `k2_status_load_room()`.

| Signal | SQL sketch | Notes |
|--------|------------|-------|
| `last_rated_id` | `SELECT MAX(id) FROM ratedresults` | Cascade trigger |
| `games_played` | `SELECT GamesPlayed FROM generalstatstable WHERE id = 1` | Arc confirm |
| `live_fp` | Live rows from `resulttable` (started, not finished, not shelved) | Same filter as `k2_status_live_games()` |
| `online_fp` | `playertable` where `IsOnline <> 0` | Lobby presence |
| `last_login_epoch` | `MAX(LastLogin)` or head row timestamp | |
| `last_join_epoch` | `MAX(JoinDate)` or head row timestamp | |
| `league_fp` | `{ period, key, total_games }` for active period | From `player_period_league` when present |
| `period_keys` | Current day/week/month/year keys (UTC) | Rollover detection |
| `server_now_epoch` | MySQL `NOW()` or PHP clock | Client countdown + meta |
| `revision` | Hash of all signals | Short-circuit unchanged responses |

When `changed: true`, include **`sections`** object with payloads only for signals that flipped (or full cascade bundle on `last_rated_id` change).

---

## Live games — display contract

Per game in JSON:

```json
{
  "game_id": 12345,
  "id_a": 1, "name_a": "…",
  "id_b": 2, "name_b": "…",
  "score_a": 2, "score_b": 1,
  "period": 1,
  "half_countdown": 14250,
  "start": "2026-07-05 22:14:00"
}
```

| Field | UI |
|-------|-----|
| `start` | Existing day clock markup |
| scores | Existing score HTML; pulse on change |
| `period` | `1st half` / `2nd half` |
| `half_countdown` | **Client-ticked** `M:SS` in `.k2-status-live-list__clock` |

**Half countdown math (locked):** 50 ticks per second ([`k2_status_format_half_countdown()`](../../site/public_html/includes/status_queries.php)). On sync:

`remaining_ticks = half_countdown - (client_now - sync_epoch) * 50`

Clamp at 0 → display `—`. Resync every heartbeat; on `period` change, take server countdown as truth.

**Diff rules:**

- Same `game_id`, score/period changed → update scores in place (no list HTML replace); pulse **only the goal cell(s) that increased**; resync clock anchor from payload
- New `game_id` → prepend row + **row glow**.
- Missing `game_id` → remove row (optional fade); if cascade also refreshed recent games, glow each **new** game row (id diff).

**Recency list glow (logins, registrations, recent games, online):** before replacing list HTML, snapshot existing `data-player-id` / `data-game-id` on rows; after replace, glow **every row whose id was not in the snapshot**. Existing rows that merely reorder do not glow. SSR and pulse HTML must both set ids on `<li>` — otherwise the first patch treats every row as new.

---

## Glow contract

| Event | Effect |
|-------|--------|
| New online player / new live game / new login / new registration / new recent game | **Row glow** on each **new** row (`k2-live-glow`) — id diff vs list before HTML replace; multiple new ids in one heartbeat all glow |
| Goal / score change | **Score pulse on the side that scored** — `.k2-status-score__goal[data-side=a|b]` only; equalizer (e.g. 1–0 → 1–1) pulses the trailing goal digit, not the whole scoreline |
| Arc or league meta number change | Glow the **`.blue` span** |
| Rated finish cascade | **Staggered panel glow** (~150 ms): recent games → ratings → league → arc |

Reference: [`k2-jukebox.css`](../site/public_html/stylesheets/k2-jukebox.css) `@keyframes k2-jukebox-track-glow`, [`k2-jukebox-launcher.js`](../site/public_html/js/k2-jukebox-launcher.js) `is-track-change` pattern.

Extract shared trigger: **`k2TriggerLiveGlow(el)`** — add class, remove on `animationend`.

---

## API response shape (sketch)

**Unchanged:**

```json
{ "changed": false, "revision": "a1b2c3", "server_now_epoch": 1751756400 }
```

**Changed (partial or cascade):**

```json
{
  "changed": true,
  "revision": "d4e5f6",
  "server_now_epoch": 1751756401,
  "signals": { "last_rated_id": 75684, "live_fp": "…", … },
  "cascade": true,
  "sections": {
    "live": [ … ],
    "online": [ … ],
    "recent_games": [ … ],
    "ratings": { "count": 68, "rows": [ … ] },
    "league": { "activity": …, "points": …, "meta": … },
    "arc": { "players": 264, "games": 75684, "since_label": "…" }
  }
}
```

Client sends optional `?revision=` to allow 304-style short responses.

---

## Integration with existing Leagues JS

[`status-period-competitions.js`](../site/public_html/js/status-period-competitions.js) today runs a **30 s** `refreshMeta` interval for countdown text only.

**When live room ships:** pulse heartbeat owns league meta **`total_games`** and period rollover; **retire or gate** the 30 s meta interval to avoid duplicate work. Countdown “time left” may remain client-side from embedded `data-league-end-epoch` (same as today) or move to heartbeat — plan slice decides; policy prefers **one clock owner**.

---

## Environment notes

| Environment | Behaviour |
|-------------|-----------|
| **Production** | Heartbeat reads live DB; room feels truly live. **No sim harness** (guard blocks). |
| **Staging (`kooldb1`)** | Heartbeat works; data is import snapshot. Synced sim **code** is inert — wrong host + DB. |
| **Local dev (`ratingskickoff.test` → `ko2unity_db`)** | Snapshot stale; sim **disabled**. |
| **Local work (`work.ratingskickoff.test` → `ko2unity_work`)** | **Live environment sim** — harness + pulse-driven ticks. Primary test bed for v1.5. |

No Steve agreement required to **build** or **test on work**; prod read authority unchanged from [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md).

---

## Shipped implementation (file map)

| Piece | Path |
|-------|------|
| Pulse API | `site/public_html/api/status_room_pulse.php` |
| Signal helpers | `site/public_html/includes/status_room_pulse.php` |
| 1 s cache | `site/public_html/includes/status_room_pulse_cache.php` |
| Client engine | `site/public_html/js/status-room-live.js` |
| Glow | `site/public_html/js/k2-live-glow.js` + `theme.css` |
| Markup hooks | `site/public_html/includes/status_room_section.php` |
| Page enqueue | `site/public_html/status.php` |
| Leagues integration | `site/public_html/js/status-period-competitions.js` |
| **Live sim (work only)** | `status-room-live-sim.php`, `api/status_room_live_sim.php`, `includes/status_room_live_sim.php`, `js/status-room-live-sim.js` — see [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md) |

---

## Out of scope (v1)

- WebSockets, SSE, long polling
- Push from game server
- Polling when tab hidden / idle pause (explicitly **not** wanted)
- Adjacent league tab prewarm
- AWOL wall, ops metrics, kickoff2 embed, joshua redirect (remain separate tracks)
- Card reflow / mobile layout changes

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-06 | **Visibility catch-up** — `status-room-live.js` polls immediately when tab/window visible again (background timer throttle) |
| 2026-07-06 | **Live clock on goal** — `live_fp` excludes `half_countdown`; score-only patch in place (no clock reset) |
| 2026-07-06 | **Shipped** — `api/status_room_pulse.php`, `status-room-live.js`, `k2-live-glow.js`, cascade on `last_rated_id` |