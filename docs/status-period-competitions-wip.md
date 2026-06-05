# Status — Leagues block (WIP)

UI heading: **Leagues** (paired Activity + Points tables).

**Status:** **Phase 1 shipped** on `status.php` (May 2026). Items below are **optional polish only** — Phase 1.5 agent handoff is **retired** ([`coordination/status-period-competitions-phase-1.5-handoff.md`](coordination/status-period-competitions-phase-1.5-handoff.md)). Do not treat this file as mandatory next work.

**Operating chat:** Use the Cursor thread where this doc was created for day-to-day decisions; this file is the durable decision log.

**Pointers:** [`STATUS_PAGE_DATA.md`](../STATUS_PAGE_DATA.md) · [`hub-ia-agreement.md`](../hub-ia-agreement.md)

---

## Product intent

- **Two cups** for the same calendar window: **Points league** (results) and **Activity league** (games played).
- Both belong on **Status** — the “is the scene alive / is another game worth it?” hub — not buried on Activity charts or Leaderboards wings.
- **Activity** gets equal dignity as participation; **Points** keeps the full mini-league table.
- Nostalgia and “one more game” come from **period tabs**, **archive pickers**, and (later) **editorial / one-liners**.

---

## Phase 1 contract (implementation target)

| Element | Decision |
|--------|----------|
| Placement | One block on `status.php`, replaces four stacked points-only league panels |
| Period tabs | Day · Week · Month · Year — **default Week** |
| Tables | **Activity** (rank, player, games) + **Points** (existing columns); same period + scope |
| Layout | Side-by-side when width allows; **stack Activity above Points** when not — **no toggle** |
| Navigation | **← / →** step by period type; **picker** for exact jump; lock-step keys across day/week/month/year |
| Meta | Period label, total rated games, end boundary, time left (reuse league meta behaviour) |
| SSR | **Default period** (week) tables in HTML; `current_keys` for all four; other periods load via API on navigate |
| Medals | Podium when period has **ended** (`end ≤ now`), not scope-based. **Persisted rules:** [`leagues-rules-spec.md`](../leagues-rules-spec.md) (unique gold/silver/bronze; tie-breaks; `period_end` = achievement time). |
| Archive behaviour | Picker or step → API refresh for **both** tables (activity + points JSON endpoints) |
| Client fetch | **One table area** (no four hidden copies). Cache by `period:key`. Visible fetch on ←/→ / picker / tab; arrows keep prior table until new data arrives. |
| Lock-step floor | `data-first-rated-day` (MIN `ratedresults`); after derive, day/week/month clamp up to first rated day (avoids phantom Jan when year starts mid-year). |
| Prewarm | `data-competition-prewarm="1"` on Leagues `<section>` — after each load, quietly fetch up to **five** next clicks (←, →, three other period types). Set `0` to disable. |
| Data | Points: `player_period_league` via `status_queries.php`; Activity: `player_period_games` |
| Activity rows | **All players** with ≥1 game in period (no top-N cap; same as points league) |
| Points rows | All players with ≥1 game in period (no cap), same as prior league stack |

### Phase 1 — not required

- Top-of-block **day activity one-liner** (try in 1.5)
- Empty state → “see last week” copy
- **Monday editorial** strip (last week podium × 2 + “new week open”)
- Auto-tease previous week on Monday
- Historical pickers on points without archive (archive covers via API)
---

## Layout & chrome

```text
Leagues
(Activity + Points — no tagline in UI)

[ Day ] [ Week ] [ Month ] [ Year ]

                    ←  [ picker ]  →

Meta: Week 22, 2026 · N rated games · ends … UTC · … left

┌ Activity league ─────────┐  ┌ Points league ────────────────┐
│ #  Player  Games          │  │ # Player Pld W D L GF GA GD Pts │
└──────────────────────────┘  └───────────────────────────────┘
     (narrow)                         (wider)
```

Mobile: single column, **Activity first**, then Points.

---

## Phase 1.5 — **not started** (next slice after Phase 1)

**Phase 1 (navigation + paired tables) is shipped in repo** — see changelog below. **Do not re-implement Phase 1** unless fixing a bug.

**Goal:** Polish and “scene alive” extras on the Leagues block without changing stored-truth contracts unless a row registers in [`website-data-contract.md`](../website-data-contract.md).

### Phase 1.5 backlog (checklist)

| # | Item | Notes | Status |
|---|------|--------|--------|
| 1 | **Day activity one-liner** | e.g. `Today · 4 games · activity leader: Name` when **Week** (or hub) context; hide when quiet | Not started |
| 2 | **Empty table copy** | Point to stepping **←** / last week, not “yesterday” for weekly default | Not started |
| 3 | **Monday editorial strip** | 48h window (try); previous week top 3 × points + activity; hook `data-k2-editorial`; tables stay on **current** week | Not started |
| 4 | **Archive always visible** | A/B vs collapsed `<details>` for picker row | Not started |
| 5 | **Points pickers without archive** | Same Tier B chrome when ready (if still desired) | Not started |
| 6 | **Day tab — games list under tables** | When **Day** period is selected, show a compact list of **rated games that day** below Activity + Points (who played whom, score, link to game/profile). Week/month/year unchanged. Indexed `ratedresults` day range + `api/status_period_day_games.php`. | **Shipped** (Jun 2026) |

### Day games list — product sketch (item 6)

- **When:** `Day` tab active only; updates with ←/→ / calendar / lock-step day key.
- **Where:** Below the two league tables (full width or under Points column — match Status density).
- **Content:** Chronological or reverse-chronological rows: time optional, **Player A vs Player B**, result/score, links (`player/profile.php`, game detail if exists).
- **Empty:** Align with league empty states; no list when 0 rated games that day.
- **Performance:** Default habit = narrow query on `ratedresults` by `DATE(Date)` + indexes, or small aggregate/API endpoint; see contract for rebuild/post-game if storing a day game list is justified.

Items 1–5 were deferred from Phase 1 (“not required” above). Item 6 is new scope for 1.5.

---

## Monday editorial (spec for 1.5)

- **When:** Week tab, server UTC, e.g. Mon 00:00 – Tue 23:59 (tune later).
- **Strip:** Read-only **previous week** finals (top 3 × points + activity); headline “Week N is in the books”; sub “Week N+1 just started”.
- **Tables below:** Stay on **This period** (new week), not forced to Previous.
- **Medals:** Reuse previous-period podium on points; activity = rank + games only.

---

## Implementation map

| Piece | Location |
|-------|----------|
| Room payload + SSR default | `status_queries.php` → `period_competitions` (all four keys; HTML renders default week only) |
| Markup | `includes/status_period_competitions_section.php` |
| Room include | `includes/status_room_section.php` |
| Tabs, scope, meta, archive, API | `js/status-period-competitions.js` |
| Week/month/year listbox | `js/k2-archive-listbox.js` (hidden value + themed panel; replaces native `<select>`) |
| Activity JSON | `api/server_period_activity_leaderboard.php` (existing) |
| Points JSON (archive) | `api/status_period_points_league.php` |
| Styles | `stylesheets/theme.css`, `stylesheets/flatpickr.min.css` (vendored) |
| Day calendar | `js/flatpickr.min.js` (vendored, MIT — `js/flatpickr.LICENSE.txt`) |
---

## Changelog

| Date | Note |
|------|------|
| 2026-05-30 | **Meta + months** — `League of` (ticker default) + blue label; full month names (`F` not `M`); listbox weight 500. |
| 2026-05-30 | **Listbox type** — secondary + weight 500; subtle hover mix; background-only selection (`theme.css` + `design-direction.md`). |
| 2026-05-30 | **Day picker chrome** — Daily trigger uses same listbox box + chevron as week/month/year (no calendar icon); click opens Flatpickr. |
| 2026-05-30 | **Picker visibility** — toolbar shows one picker per tab (`data-active-period` CSS); month/year in Flatpickr header when calendar open. |
| 2026-05-30 | **Flatpickr listbox click fix** — capture-phase `click` shield blocked `open()`; use mousedown capture only; `.flatpickr-innerContainer` pointer-events so header clicks reach month/year. |
| 2026-05-30 | **KOOL listbox** — week/month/year archive + Flatpickr month/year headers use `k2-archive-listbox` (shared hover/scroll). |
| 2026-05-27 | Doc created from design chat; Phase 1 implementation started (paired block on Status). |
| 2026-05-27 | **Phase 1 shipped in repo** — `status_period_competitions_section.php`, `status-period-competitions.js`, `api/status_period_points_league.php`; replaces four stacked league panels on `status.php`. |
| 2026-05-27 | Polish — removed tagline; archive picker right under scope toggle; activity podium medals; meta uses **UTC** not “server time”. |
| 2026-05-27 | Heading **Leagues**; archive row (link right, pickers left on open); flex table columns; meta→tables spacing. |
| 2026-05-27 | Scope segment **Today \| Last week \| Earlier** (prev labels: Yesterday / Last week / month / year); pickers only when **Earlier** selected; removed separate archive link. |
| 2026-05-27 | Layout: activity natural width; points **centered** in remaining space; stacks only when points min width does not fit beside activity. |
| 2026-05-27 | **Nav redesign** — removed This/Last/Earlier; ←/→ + always-visible picker; SSR current per tab; lock-step keys. |
| 2026-05-27 | Day picker — **Flatpickr** vendored under `site/public_html/js/` + `stylesheets/`; icon opens calendar (`clickOpens: false`); `Y-m-d` keys unchanged. |
| 2026-05-27 | Day picker **calendar button** — inline SVG; neutral chrome (`k2-status-period-competitions__calendar-btn`). |
| 2026-05-27 | **Single table + optional prewarm** — one DOM slot; memory cache; prewarm gated by `data-competition-prewarm` (`0` off, `1` on — default on). |
| 2026-05-27 | Day picker **month dropdown** — all 12 months listed; out-of-range months disabled (Flatpickr hid months after `maxDate` in the current year). **2026-05-30:** same UX via `k2-archive-listbox` inline (not native `<select>`). |
| 2026-05-27 | Earlier pickers lock-step (one anchor date); This/Last always restore server views (picker cannot stick). |
| 2026-05-27 | **Phase 1 nav complete** — single table, prewarm, first-rated lock-step floor, rapid-click fixes. **Phase 1.5 backlog** documented (incl. day games list under tables). |
| 2026-05-27 | **Cleanup** — removed dead `k2_status_render_league_period_panel`; one `MIN(ratedresults)` in PHP; dead CSS `[hidden]` view rule; docs aligned Phase 1 shipped / 1.5 next. |
| 2026-05-27 | Removed **`dev-period-activity.php`** and orphan **`js/status-period-activity.js`** (preview superseded by Status Leagues). |
