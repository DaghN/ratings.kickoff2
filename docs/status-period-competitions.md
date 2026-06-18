# Status — Leagues block

UI heading: **Leagues** (paired Activity + Points tables on `status.php`).

**Status:** **Shipped** (May–Jun 2026) — paired Activity + Points, period nav, Daily games list. No open polish track.

**Pointers:** [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · medals/rules [`leagues-rules-spec.md`](leagues-rules-spec.md)

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
| Navigation | **← / →** step by period type; **picker** for exact jump; lock-step keys across day/week/month/year |
| Meta | Period label, total rated games, end boundary, time left (reuse league meta behaviour) |
| SSR | **Default period** (week) tables in HTML; `current_keys` for all four; other periods load via API on navigate |
| Medals | Podium when period has **ended** (`end ≤ now`), not scope-based. **Persisted rules:** [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| Archive behaviour | Picker or step → API refresh for **both** tables |
| Client fetch | **One table area**. Cache by `period:key`. Optional prewarm (`data-competition-prewarm="1"`, set `0` to disable) |
| Lock-step floor | `data-first-rated-day` (MIN `ratedresults`); day/week/month clamp up to first rated day |
| Data | Points: `player_period_league`; Activity: `player_period_games` |
| Rows | All players with ≥1 game in period (no top-N cap) |
| **Daily tab** | **Games this day** list below league tables; `k2_status_rated_games_for_calendar_day` + `api/status_period_day_games.php`; narrow `ratedresults` day query |

---

## Layout

```text
Leagues

[ Day ] [ Week ] [ Month ] [ Year ]

                    ←  [ picker ]  →

Meta: Week 22, 2026 · N rated games · ends … UTC · … left

┌ Activity league ─────────┐  ┌ Points league ────────────────┐
│ #  Player  Games          │  │ # Player Pld W D L GF GA GD Pts │
└──────────────────────────┘  └───────────────────────────────┘

(Daily tab only) Games this day — compact recency list + game.php links
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
| Styles | `stylesheets/theme.css`, `stylesheets/flatpickr.min.css` |
| Day calendar | `js/flatpickr.min.js` |

---

## Changelog

| Date | Note |
|------|------|
| 2026-06-18 | **Closed** — Phase 1.5 / editorial polish track removed from backlog; WIP diary archived. |
| 2026-06-05 | **Doc closed** — polish track retired; live spec is this file; history in `docs/archive/`. |
| 2026-06 | **Daily games list** shipped under league tables when Daily tab active. |
| 2026-05-30 | Meta/month labels, KOOL listbox, Flatpickr + picker visibility fixes. |
| 2026-05-27 | **Phase 1 shipped** — paired block, single table slot, prewarm, lock-step, ←/→ + always-visible pickers. |

*Full implementation diary:* [`archive/status-period-competitions-wip.md`](archive/status-period-competitions-wip.md)
