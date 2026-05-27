# Hub IA Agreement

**Status:** current hub/navigation contract, May 2026. Phase A hub shell and Status Phase B v1.2 are shipped in repo. This file is no longer a phase diary; use it to answer "what is the hub supposed to be?"

**Related:** `docs/design-direction.md` for visual rules, `docs/STATUS_PAGE_DATA.md` for Status panel data, `docs/tint-vs-realm.md` for tint/realm separation.

---

## Current Hub Shape

Five top-level tabs, in this order:

1. **Status** — default landing.
2. **Activity**
3. **Games**
4. **Leaderboards**
5. **Hall of Fame**

Routing:

| Tab | Page |
|-----|------|
| Status | `status.php` |
| Activity | `server1.php` |
| Games | `server3.php` |
| Leaderboards | `ranked7.php` default, with wing tabs to `ranked1`-`ranked5`, `ranked7`, `ranked8` |
| Hall of Fame | `server2.php` |

Direct legacy URLs remain valid. The hub is page navigation, not a client-side SPA.

Ordering principle: show life and evidence before hierarchy. Status answers whether the scene is alive, Activity expands recent/historical play, Games exposes the match ledger, Leaderboards answers who is better, and Hall of Fame preserves records and legends.

---

## Status Tab Contract

Purpose: answer "is the scene alive tonight?" while also showing current competition.

Current Status v1.2 includes:

- Online list.
- Live games from `resulttable`.
- Rated-games arc/count summary with link to Activity.
- Active Elo leaderboard, top 20 active players.
- **Leagues** (points + activity, day/week/month/year) — **Phase 1 shipped** on Status; **Phase 1.5** polish — [`docs/status-period-competitions-wip.md`](status-period-competitions-wip.md).
- Recent logins.
- Recent registrations.
- Recent rated games.
- Small heritage box.

Data source and exact query rules live in `docs/STATUS_PAGE_DATA.md`.

Not in current Status:

- CPU/disk/memory ops metrics.
- AWOL wall.
- Long-history charts; those belong on Trends.
- Legacy dual top-10 `PlayerRank` snippets.

Still open with Steve / prod:

- Live production DB read for Status.
- `joshua.kickoff2.net/status.php` redirect or thin wrapper.
- Optional compact kickoff2.com embed.
- Optional shared JSON/data layer after PHP includes prove enough.

---

## Navigation Decisions

| Area | Current decision |
|------|------------------|
| Header | Wordmark + Online/Amiga realm switcher grouped as site identity; player search isolated on the right. No kickoff2.com header link. |
| Wordmark | Header text is **Kick Off 2**; broader product can still be "Kick Off 2 ratings". |
| Hub nav | Segment track + outline active cell. |
| Leaderboard wings | Segment track; wing tabs sit above table. |
| Player pages | Replace hub tabs with player context tabs: Profile, Games, W/D/L, Goals, DDs. |
| Back links | No "Back to Results"; browser back + search/nav are enough. |
| Tint picker | Hidden by default behind Show tint. |
| Realm switcher | UI for future Online/Amiga realm; tint and realm are separate. |

The old hub-nav A/B tuning path is removed; segment track + outline active cell is now the fixed product contract.

---

## Leaderboards Contract

Leaderboards are comprehensive by default, not the same as Status' active top-20 slice.

Wing tabs:

| Wing | Page |
|------|------|
| Results | `ranked7.php` |
| Rating | `ranked1.php` |
| Goals | `ranked2.php` |
| DDs & CSs | `ranked3.php` |
| Streaks | `ranked4.php` |
| Victims & Culprits | `ranked5.php` |
| Activity | `ranked8.php` |

Notes:

- `ranked6.php` / old "Rating records" split is gone.
- Activity is the leaderboard tab for day/month/year/all-time activity tables.
- Status uses an active-player window; Leaderboards stay broad by default and expose filters for narrower views.

---

## Activity, Games, Hall of Fame

| Tab | Contract |
|-----|----------|
| Activity | `server1.php` key activity sentence, four fact cards, small games/opponents line, past-month games-per-day chart, and historical charts. |
| Games | `server3.php` rated-game ledger. Current UI is 14 day buckets with all-column sorting. |
| Hall of Fame | `server2.php` record extremes. |

Do not merge these page bodies into Status unless Dagh explicitly changes the hub strategy.

---

## Player Context

Player pages use the same global header, then player-specific context:

- Hero block.
- Player nav: Profile, Games, W/D/L, Goals, DDs.
- `individual1.php` is the warm profile feast landing.
- `individual3.php` is the Games history tab with server-side filters/sort/100-row slices.
- `individual2a/b/c.php` are W/D/L, Goals, DDs matchup stat tabs.

Future Amiga/photo/media work belongs on the profile/content track, not in hub IA.

---

## Deferred / Open

- Production Status DB read and joshua redirect.
- kickoff2.com embed.
- Amiga realm routing once data exists.
- Pretty URLs / rebrand decisions.

---

## Changed Decisions Archive

Keep this short; it prevents old chat ideas from reappearing as current plans.

| Earlier idea | Current position |
|--------------|------------------|
| Leaderboards as default landing | Status is default. |
| Single Activity tab | Split into Status + Trends; Activity is a leaderboard wing. |
| Live as tab name | Status. |
| Records before Games | Games before Records. |
| Kickoff2.com header link | Removed from header. |
| Back to Results on player pages | Removed. |
| Full-accent links everywhere | Use `--k2-link-star` / `--k2-link` hierarchy. |
| Moving server1/server2/server3 bodies into hub panels | Not current plan. |

*Last pruned: May 2026 — stale Phase B and roadmap notes collapsed into current contracts.*
