# Hub IA Agreement

**Status:** current hub/navigation contract, May 2026. Phase A hub shell and Status Phase B v1.2 are shipped in repo. This file is no longer a phase diary; use it to answer "what is the hub supposed to be?"

**Related:** `docs/design-direction.md` for visual rules, `docs/STATUS_PAGE_DATA.md` for Status panel data, `docs/tint-vs-realm.md` for tint/realm separation, `docs/url-routes.md` for page paths and `k2_routes.php`, `docs/milestones-hub-ia.md` for **future** hub phases (Story / Charts migration ? not required for current v0).

---

## Current Hub Shape

Six top-level tabs, in this order:

1. **Status** ? default landing.
2. **Activity**
3. **Leaderboards**
4. **Milestones**
5. **Hall of Fame**
6. **Play & Setup** ? join / onboarding (`join.php`), last tab.

Routing:

| Tab | Page |
|-----|------|
| Status | `status.php` |
| Activity | `activity.php` |
| Leaderboards | `leaderboards/rating.php` default; wings under `leaderboards/` (see Leaderboards contract) |
| Milestones | `milestones.php` ? **v0 hub** (Recent + Catalog sub-nav); detail `milestone.php?key=` |
| Hall of Fame | `hall-of-fame.php` |
| Play & Setup | `join.php` |

**Not a hub tab:** **Games** ? `games.php`. Sub-nav (Milestones-style): **Recent** (14-day rated ledger, default) ? **Highlights** (`?view=highlights`, all-time top-100 spectacle boards among matches ? biggest wins, most goals, biggest draws, one-side peak; not Hall of Fame single-holder records). Primary entry: Status recent games panel ? **Games ?**. Player **Games** pill: `player/games.php`.

Canonical paths live in `includes/k2_routes.php` ([`url-routes.md`](url-routes.md)). All nav and cross-links use root-absolute URLs (`/milestones.php`, not bare `milestones.php`). The hub is page navigation, not a client-side SPA.

Ordering principle: **alive ? pulse ? rank ? shared career ? extremes ? join.** Status answers whether the scene is alive; Activity shows server pulse charts; Leaderboards answers who is better on the ladder; **Milestones** is the public milestone universe (Recent feed + catalog + per-key achievers); Hall of Fame preserves single-holder records; **Play & Setup** (last tab) is how to get online.

---

## Status Tab Contract

Purpose: answer "is the scene alive tonight?" while also showing current competition.

Current Status v1.2 includes:

- Online list.
- Live games from `resulttable`.
- Rated-games arc/count summary with link to Activity.
- Active Elo leaderboard, top 20 active players.
- **Leagues** (points + activity, day/week/month/year) ? **Phase 1 shipped**; optional backlog ? [`docs/status-period-competitions-wip.md`](status-period-competitions-wip.md) only.
- Recent logins.
- Recent registrations.
- Recent rated games (with link to full match log on `games.php`).
- Small heritage box.

Data source and exact query rules live in `docs/STATUS_PAGE_DATA.md`.

Not in current Status:

- CPU/disk/memory ops metrics.
- AWOL wall.
- Long-history charts; those belong on Activity (until milestone charts migrate to Milestones hub).
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
| Header | Wordmark + **realm switcher** (Online · Amiga 500) beside wordmark + player search on the right. **Play & Setup** is a hub tab (`join.php`), not a header link. No kickoff2.com header link. |
| Header player search | Cross-realm: `api/player_search.php?realm=all` — Online + Amiga 500 in one dropdown; each hit labelled **Online** or **Amiga** and links to the correct profile (`/player/profile.php` vs `/amiga/player/profile.php`). No realm picker on the search field. In-page pickers (e.g. H2H opponent) stay `realm=online`. |
| Wordmark | Header text is **Kick Off 2**; broader product can still be "Kick Off 2 ratings". |
| Hub nav | Segment track + outline active cell. |
| Leaderboard wings | Segment track; wing tabs sit above table. |
| Player pages | Replace hub tabs with player context tabs: Profile, Games, Opponents, Milestones. |
| Back links | No "Back to Results"; browser back + search/nav are enough. |
| Tint picker | Closed by default behind **Tint** disclosure (hub + player nav). |
| Peer pill scroll | Hub, leaderboard wing (`lb_nav`), and player pills use `data-k2-carry-scroll`: pill click keeps scroll on the next page (one-shot `sessionStorage`). Pill clicks store **nav anchor** (`aria-label` + viewport offset) so table/filter height changes do not nudge scroll; restore falls back to raw `y` for listbox/sort/pager. **Listbox filter forms** opt in with `data-k2-carry-scroll` on the `<form>` (`js/k2-carry-scroll.js` stores on listbox `change` before `form.submit()` and on in-form **Reset** links). **Player games** server-sort column links (`.k2-table--player-games`) carry scroll the same way. Online **Previous / Next 100** pager links opt in via `data-k2-carry-scroll` on `.k2-player-games-status` (same `a.k2-player-games-action` selector). Restore (`k2_carry_scroll_restore.php` in `<head>`) runs after DOM ready; after first apply only scrolls down if the page grows. Stops on success, user scroll input, or 2s. Short destinations extend `documentElement` min-height so carry is not clamped to top. Filter toggles in `lb_nav`, content links, and player names load at top as usual. |
| Wide games tables | Hub `games.php` (Recent per-day buckets + Highlights) and player games (`player/games.php`, `/amiga/player/games.php`): `data-k2-scroll-mirror` on `.k2-table-wrap` + `k2-table-scroll-mirror.js` — top horizontal bar when the table overflows; syncs `scrollLeft` with the wrap below. |

The old hub-nav A/B tuning path is removed; segment track + outline active cell is now the fixed product contract.

---

## Leaderboards Contract

Leaderboards are comprehensive by default, not the same as Status' active top-20 slice.

**Section chapter (Jun 2026):** `includes/k2_hub_chapter.inc.php` — title + lede above sub-nav. Online: **Online activity** chapter title on `activity.php` (hub tab label still **Activity**), Leaderboards (`lb_nav.php`), Milestones (hub chapter lede; catalog count on Catalog view intro), HoF. Amiga: Leaderboards (`amiga_lb_nav.php`), Tournaments, Live tournaments, HoF. Wing carry-scroll unchanged.

Wing tabs (left ? right):

| Wing | Page |
|------|------|
| Rating (current ladder) | `leaderboards/rating.php` |
| Goals | `leaderboards/goals.php` |
| DDs & CSs | `leaderboards/double-digits.php` |
| Streaks | `leaderboards/streaks.php` ? **Days** / **Weeks** from `player_play_streaks.best_streak` (SCH-014 + REP-015; staging verified May 2026) |
| Victims & Culprits | `leaderboards/victims.php` |
| League honours | `leaderboards/league-honours.php` |
| Milestones | `leaderboards/milestones.php` |
| Activity peaks | `leaderboards/activity-peaks.php` |
| Peak rating | `leaderboards/peak-rating.php` |

Ordering principle: **classic ladder lenses first** (rating + match stats), then **career / community celebration** (league medals, milestone breadth, busiest-period peaks), then **peak-rating archive** last. Hub default and Status ?Leaderboards ?? still open `leaderboards/rating.php`.

Notes:

- `ranked6.php` / old split is gone. Nav label **Peak rating** = `leaderboards/peak-rating.php` (peak/low/average columns); first tab **Rating** = current order on `leaderboards/rating.php`.
- Activity peaks = personal busiest day/week/month/year tables (`leaderboards/activity-peaks.php`); not the hub Activity tab (`activity.php`) or Status activity league.
- Meta milestone **count** leaderboard (`leaderboards/milestones.php`) complements the **Milestones hub** (`milestones.php`) ? encyclopedia + achievers vs sort table.
- Status uses an active-player window; Leaderboards stay broad by default and expose filters for narrower views (not on Activity peaks wing).

---

## Milestones hub (v0 shipped; future phases)

| Item | Contract |
|------|----------|
| Hub tab | `milestones.php` between Leaderboards and Hall of Fame |
| **Shipped v0** | **Recent** (tier filter + unlock feed) ? **Catalog** (four tier sections) ? **`milestone.php?key=`** (Made it + Graphs) |
| Build plan (future) | [`docs/milestones-hub-ia.md`](milestones-hub-ia.md) ? Story sub-nav, Charts migration from Activity, etc. |
| Player garden | `player/milestones.php` (per player) |
| Meta leaderboard | `leaderboards/milestones.php` (Leaderboards wing) |
| HoF achievers | **Removed** May 2026 ? per-key lists on `milestone.php`; HoF footer links to Milestones hub |
| Activity | Established-player charts on `activity.php`; milestone digest removed Jun 2026. Further milestone charts may migrate to hub later. |

---

## Activity & Hall of Fame

| Surface | Contract |
|---------|----------|
| Activity | `activity.php` ? server pulse: key sentence, fact cards, games/opponents line, charts (includes milestone digest/charts until migrated). |
| Match log | `games.php` ? **Recent** (14 day buckets) + **Highlights** (top matches by board); **not** a hub tab. |
| Hall of Fame | `hall-of-fame.php` ? single-holder record extremes only (milestone achievers trial migrates later). |

Do not merge these page bodies into Status unless Dagh explicitly changes the hub strategy.

---

## Player Context

Player pages use the same global header, then player-specific context:

- Hero block.
- Player nav: Profile, Games, Opponents, Milestones.
- `player/profile.php` is the warm profile feast landing.
- `player/games.php` is the Games history tab with server-side filters/sort/100-row slices.
- `player/opponents/{h2h,wdl,goals,dds}.php` — Opponents wing inner tabs (path per tab).

Future Amiga/photo/media work belongs on the profile/content track, not in hub IA.

---

## Deferred / Open

- Production Status DB read and joshua redirect.
- kickoff2.com embed.
- Extensionless URLs (`.htaccess` rewrites) ? filenames renamed Jun 2026; see `includes/k2_routes.php`.
- Full Milestones hub **Story / Charts** sub-navs ? see [`milestones-hub-ia.md`](milestones-hub-ia.md) (v0 Recent + Catalog + detail already shipped).

---

## Changed Decisions Archive

Keep this short; it prevents old chat ideas from reappearing as current plans.

| Earlier idea | Current position |
|--------------|------------------|
| Leaderboards as default landing | Status is default. |
| Hub tab **Games** | Removed May 2026; match log via Status **Games ?** and `games.php`. |
| Five-tab arc with Games before Leaderboards | Replaced: Status ? Activity ? Leaderboards ? Milestones ? HoF. |
| Milestones hub tab deferred | Tab shipped May 2026; **v0** Recent + Catalog + `milestone.php` live; Story/Charts later. |
| Single Activity tab | Split into Status + Activity; Activity peaks is a Leaderboards wing. |
| Live as tab name | Status. |
| Records before Games | Superseded by Games demotion. |
| Kickoff2.com header link | Removed from header. |
| Back to Results on player pages | Removed. |
| Full-accent links everywhere | Use `--k2-link-star` / `--k2-link` hierarchy. |
| Moving server1/server2/server3 bodies into hub panels | Not current plan. |

*Last pruned: Jun 2026 ? Milestones v0 hub (not stub); Status Leagues spec closed (no Phase 1.5 track).*
