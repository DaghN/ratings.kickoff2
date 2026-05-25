# Hub IA Agreement

**Status:** current hub/navigation contract, May 2026. Phase A hub shell and Status Phase B v1.2 are shipped in repo. This file is no longer a phase diary; use it to answer "what is the hub supposed to be?"

**Related:** `docs/design-direction.md` for visual rules, `docs/STATUS_PAGE_DATA.md` for Status panel data, `docs/tint-vs-realm.md` for tint/realm separation.

---

## Current Hub Shape

Five top-level tabs, in this order:

1. **Status** — default landing.
2. **Leaderboards**
3. **Games**
4. **Trends**
5. **Records**

Routing:

| Tab | Page |
|-----|------|
| Status | `status.php` |
| Leaderboards | `ranked7.php` default, with wing tabs to `ranked1`-`ranked5`, `ranked7`, `ranked8` |
| Games | `server3.php` |
| Trends | `server1.php` |
| Records | `server2.php` |

Direct legacy URLs remain valid. The hub is page navigation, not a client-side SPA.

---

## Status Tab Contract

Purpose: answer "is the scene alive tonight?" while also showing current competition.

Current Status v1.2 includes:

- Online list.
- Live games from `resulttable`.
- Rated-games arc/count summary.
- Active Elo leaderboard, top 20 active players.
- Monthly league with current/previous toggle.
- Recent logins.
- Recent registrations.
- Recent rated games.
- Small heritage box.

Data source and exact query rules live in `docs/STATUS_PAGE_DATA.md`.

Not in current Status:

- CPU/disk/memory ops metrics.
- AWOL wall.
- Long-history charts; those belong on Trends.
- Period activity triple tables; those are prepared in DB/docs but not placed on Status.
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
| Header | Wordmark, player search, Online/Amiga realm switcher. No kickoff2.com header link. |
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

Leaderboards are comprehensive by default, not the same as Status' active-only top 20.

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
- Status may use an active-player window; Leaderboards stay broad unless filters are explicitly selected.

---

## Games, Trends, Records

| Tab | Contract |
|-----|----------|
| Games | `server3.php` rated-game ledger. Current UI is seven day buckets. |
| Trends | `server1.php` historical charts and long-horizon activity. |
| Records | `server2.php` Hall of Fame / record extremes. |

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
- Active-only filter on full Leaderboards, if desired.
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

*Last pruned: May 2026 — stale Phase B "in progress" notes collapsed into current Status v1.2 contract.*
