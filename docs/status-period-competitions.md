# Status — Leagues block

UI heading: **Leagues** (paired Activity + Points tables on `status.php`).

**Status:** **Shipped** (May–Jun 2026) — paired Activity + Points, period nav, Daily games list. No open polish track.

**Pointers:** [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · medals/rules [`leagues-rules-spec.md`](leagues-rules-spec.md) · **Live room (future):** [`status-room-live-policy.md`](status-room-live-policy.md)

---

## Product intent

- **Two cups** for the same calendar window: **Points league** (results) and **Activity league** (games played).
- Both belong on **Status** — the “is the scene alive / is another game worth it?” hub — not buried on Activity charts or Leaderboards wings.
- **Activity** gets equal dignity as participation; **Points** keeps the full mini-league table.
- **Period tabs**, **archive pickers**, and **Daily games list** carry the “scene alive” story.

---

## Shipped contract

| Element | Decision |
|--------|----------|
| Placement | One block on `status.php`, replaces four stacked points-only league panels |
| Period tabs | Day · Week · Month · Year — **default Week** |
| Tables | **Activity** (rank, player, games) + **Points** (existing columns); same period + scope |
| Layout | Side-by-side when width allows; **stack Activity above Points** when not — **no toggle** |
| Navigation | **← / →** step by period type; **picker** for exact jump; lock-step keys across day/week/month/year. **URL lens:** `?period=` + `?start=` (same names as `league.php`); **pushState** on in-panel nav; **popstate** restores without reload; boot/Back-from-league read the params. AJAX cache + prewarm unchanged. |
| Meta | Period label, total rated games, end boundary, time left (reuse league meta behaviour). Label order: **Week 49, 2025 League** (period in `.blue`, then “League”); rated-games count uses `.blue` |
| SSR | **Default period** (week, or URL lens) tables in HTML when key matches live SSR; other periods / archive keys load via API on navigate |
| Medals | Podium when period has **ended** (`end ≤ now`), not scope-based. **Persisted rules:** [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| Archive behaviour | Picker or step → API refresh for **both** tables |
| Client fetch | **One table area**. Cache by `period:key`. Prewarm (`data-competition-prewarm="1"`) — **current year** not built on PHP first paint (Jul 2026); prewarm fetches year first in queue after load. Set `data-competition-prewarm="0"` to disable. |
| Lock-step floor | `data-first-rated-day` (MIN `ratedresults`); day/week/month clamp up to first rated day |
| Data | Points: `player_period_league`; Activity: `player_period_games` |
| Rows | All players with ≥1 game in period (no top-N cap) |
| **Daily tab** | **Games this day** list below league tables; columns **ID · time · match** (games-hub style); `k2_status_rated_games_for_calendar_day` + `api/status_period_day_games.php`; **live pulse** refreshes with league cascade when Daily is active |
| **Weekly tab** | **Games this week** below league tables; day sections newest-first (weekday labels); **omit future UTC days** in the current week; Recent-style table minus GD/Sum/TS/Elo Diff/Fav ES/Adjustment; Rating A/B show `rating (±adj)` with adjustment ink; `k2_status_rated_games_for_calendar_week` + `api/status_period_week_games.php`; **live pulse** refreshes with league cascade when Weekly is active |

---

## Layout

```text
Leagues

[ Day ] [ Week ] [ Month ] [ Year ]

                    ←  [ picker ]  →

Meta: Week 22, 2026 League · N rated games · ends … UTC · … left

┌ Activity league ─────────┐  ┌ Points league ────────────────┐
│ #  Player  Games          │  │ # Player Pld W D L GF GA GD Pts │
└──────────────────────────┘  └───────────────────────────────┘

(Daily tab only) Games this day — ID · time · match (+ game.php on ID)

(Weekly tab only) Games this week — Sun…Mon sections (newest first); ID · Date · teams · goals · Rating A/B (+adj)
```

Mobile: single column, **Activity first**, then Points.

---

## Implementation map

| Piece | Location |
|-------|----------|
| Room payload + SSR default | `status_queries.php` → `period_competitions` |
| Markup | `includes/status_period_competitions_section.php` |
| Room include | `includes/status_room_section.php` |
| Tabs, meta, archive, API | `js/status-period-competitions.js` |
| Week/month/year listbox | `js/k2-archive-listbox.js` |
| Activity JSON | `api/server_period_activity_leaderboard.php` |
| Points JSON | `api/status_period_points_league.php` |
| Day games JSON | `api/status_period_day_games.php` |
| Week games HTML JSON | `api/status_period_week_games.php` (+ `includes/status_week_games_render.php`) |
| Styles | `stylesheets/theme.css`, `stylesheets/flatpickr.min.css` |
| Day calendar | `js/flatpickr.min.js` |

---

## Changelog

| Date | Note |
|------|------|
| 2026-07-18 | **Leagues URL history** — `?period=` + `?start=` + pushState/popstate; Back restores lens (incl. from `league.php`); AJAX/prewarm kept. |
| 2026-07-18 | **Games this week / day live** — league pulse cascade refreshes Daily list + Weekly tables when that tab is active. |
| 2026-07-18 | **Games this week** — Weekly tab Recent-style tables (newest-first weekdays; skip future UTC days); Rating A/B inline `(±adj)`; thinner column set. |
| 2026-07-18 | **Games this day column order** — ID first, then time, then match (parity with Games hub lists). |
| 2026-07-06 | **Live room spec** — heartbeat will own league meta refresh + cascade; 30 s meta interval to retire when shipped — [`status-room-live-policy.md`](status-room-live-policy.md). |
| 2026-06-18 | **Closed** — Phase 1.5 / editorial polish track removed from backlog; WIP diary archived. |
| 2026-06-05 | **Doc closed** — polish track retired; live spec is this file; history in `docs/archive/`. |
| 2026-06 | **Daily games list** shipped under league tables when Daily tab active. |
| 2026-05-30 | Meta/month labels, KOOL listbox, Flatpickr + picker visibility fixes. |
| 2026-05-27 | **Phase 1 shipped** — paired block, single table slot, prewarm, lock-step, ←/→ + always-visible pickers. |

*Full implementation diary:* [`archive/status-period-competitions-wip.md`](archive/status-period-competitions-wip.md)
