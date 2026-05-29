# Career league honours — leaderboard (v1 spec)

**Status:** **v1 implemented** in repo (`ranked9.php`). This doc is the v1 reference; later versions may change columns or filters without treating v1 as permanent.

**Data:** `playertable` + `LEFT JOIN player_league_totals` ([`leagues-rules-spec.md`](leagues-rules-spec.md)). Event history remains `player_league_award` (profile / later).

---

## v1 product (May 2026)

| Item | Choice |
|------|--------|
| Placement | Leaderboards wing — after Victims & Culprits, before Milestones / Activity peaks / Peak rating ([`hub-ia-agreement.md`](hub-ia-agreement.md)) |
| Page | `ranked9.php` |
| Label | **League honours** |
| Player pool | Same as other ranked wings (`lb_player_filters.php`) — **all eligible players**, including zero medals |
| Extra UI | None (no intro line, strip, or period filter) |

### v1.1 views (segment pills + URL)

| Row | Pills | Data |
|-----|-------|------|
| **Overall** | First row only | `player_league_totals` (all eight leagues) |
| **Activity leagues** | + Day · Week · Month · Year | Aggregate `player_league_award` read-time (`league_kind=activity`, `period_type`) |
| **Points leagues** | + Day · Week · Month · Year | Same for `points` |

**URL:** `ranked9.php?cup=overall` · `?cup=activity&grain=day` · `?cup=points&grain=week` (plus leaderboard filter params). Tab link defaults to **Overall**; first visit to Activity/Points defaults **Day**. Switching Activity ↔ Points keeps the current `grain` (daily/weekly/monthly/year). Canonical redirect adds `grain=day` when missing.

**Slice data:** `player_league_slice_totals` (SCH-010, REP-013) — read-time aggregation removed May 2026.

### Table columns

| # | Column | Source |
|---|--------|--------|
| 1 | # | autorank |
| 2 | Player | `playertable` → profile link |
| 3 | Rating | `Rating` |
| 4 | Games | `NumberGames` |
| 5 | Gold | `COALESCE(player_league_totals.gold, 0)` — first place in any finished league |
| 6 | Silver | `COALESCE(silver, 0)` |
| 7 | Bronze | `COALESCE(bronze, 0)` |
| 8 | Podium | `COALESCE(podiums, 0)` — top-three finishes combined |

Default sort: **Gold** descending (k2-table column index 4, zero-based).

Gold and career “wins” are the same count in data; v1 shows **Gold** only (not a separate Wins column).

---

## Read pattern (performance)

```text
FROM playertable p
LEFT JOIN player_league_totals t ON t.player_id = p.ID
WHERE <leaderboard player filters>
```

Do **not** aggregate from `player_league_award` on page load. Totals are maintained by REP-012 / PER-003.

---

## Code map

| Piece | Path |
|-------|------|
| Query + URL helpers | `includes/league_honours_leaderboard.php` |
| Pills + table | `includes/league_honours_panel.php` |
| Page | `ranked9.php` |
| Wing tab | `includes/lb_nav.php` → `league-honours` |

---

## Later (not v1)

- Profile league section from `player_league_award`
- Link from Status Leagues meta
- Points vs activity breakdown columns
- Cross-link to Milestones hub (`milestones.php`) when full hub ships

---

*Updated May 2026 — v1 shipped in repo.*
