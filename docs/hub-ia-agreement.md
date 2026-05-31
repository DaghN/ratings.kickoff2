# Hub IA Agreement

**Status:** current hub/navigation contract, May 2026. Phase A hub shell and Status Phase B v1.2 are shipped in repo. This file is no longer a phase diary; use it to answer "what is the hub supposed to be?"

**Related:** `docs/design-direction.md` for visual rules, `docs/STATUS_PAGE_DATA.md` for Status panel data, `docs/tint-vs-realm.md` for tint/realm separation, `docs/milestones-hub-ia.md` (WIP) for Milestones hub build plan.

---

## Current Hub Shape

Six top-level tabs, in this order:

1. **Status** — default landing.
2. **Activity**
3. **Leaderboards**
4. **Milestones**
5. **Hall of Fame**
6. **Play & Setup** — join / onboarding (`join.php`), last tab.

Routing:

| Tab | Page |
|-----|------|
| Status | `status.php` |
| Activity | `server1.php` |
| Leaderboards | `ranked7.php` default; wings `ranked1`–`ranked5`, `ranked7`–`ranked10` (see Leaderboards contract) |
| Milestones | `milestones.php` (stub → full hub per WIP spec) |
| Hall of Fame | `server2.php` |
| Play & Setup | `join.php` |

**Not a hub tab:** **Games** / full match log — `server3.php`. Primary entry: Status recent games panel → **Games →**. Direct URL and player **Games** pill unchanged.

Direct legacy URLs remain valid. The hub is page navigation, not a client-side SPA.

Ordering principle: **alive → pulse → rank → shared career → extremes → join.** Status answers whether the scene is alive; Activity shows server pulse and (for now) legacy milestone charts; Leaderboards answers who is better on the ladder; Milestones is the public milestone universe; Hall of Fame preserves single-holder records; **Play & Setup** (last tab) is how to get online.

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
- Recent rated games (with link to full match log on `server3.php`).
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
| Header | Wordmark + player search on the right. Online/Amiga realm switcher **hidden** (markup in `site_header.php`, `display: none` in `theme.css`) until Amiga ships. **Play & Setup** is a hub tab (`join.php`), not a header link. No kickoff2.com header link. |
| Wordmark | Header text is **Kick Off 2**; broader product can still be "Kick Off 2 ratings". |
| Hub nav | Segment track + outline active cell. |
| Leaderboard wings | Segment track; wing tabs sit above table. |
| Player pages | Replace hub tabs with player context tabs: Profile, Games, W/D/L, Goals, DDs, Milestones. |
| Back links | No "Back to Results"; browser back + search/nav are enough. |
| Tint picker | Hidden by default behind Show tint. |
| Realm switcher | UI for future Online/Amiga realm; tint and realm are separate. |
| Peer pill scroll | Hub, leaderboard wing (`lb_nav`), and player pills use `data-k2-carry-scroll`: pill click keeps `window.scrollY` on the next page (one-shot `sessionStorage`). Short destinations extend `documentElement` min-height so carry is not clamped to top. Filter toggles in `lb_nav`, content links, and player names load at top as usual. |

The old hub-nav A/B tuning path is removed; segment track + outline active cell is now the fixed product contract.

---

## Leaderboards Contract

Leaderboards are comprehensive by default, not the same as Status' active top-20 slice.

Wing tabs (left → right):

| Wing | Page |
|------|------|
| Rating (current ladder) | `ranked7.php` |
| Goals | `ranked2.php` |
| DDs & CSs | `ranked3.php` |
| Streaks | `ranked4.php` — **Days** / **Weeks** from `player_play_streaks.best_streak` (SCH-014 + REP-015; staging verified May 2026) |
| Victims & Culprits | `ranked5.php` |
| League honours | `ranked9.php` |
| Milestones | `ranked10.php` |
| Activity peaks | `ranked8.php` |
| Peak rating | `ranked1.php` |

Ordering principle: **classic ladder lenses first** (rating + match stats), then **career / community celebration** (league medals, milestone breadth, busiest-period peaks), then **peak-rating archive** last. Hub default and Status “Leaderboards →” still open `ranked7.php`.

Notes:

- `ranked6.php` / old split is gone. Nav label **Peak rating** = `ranked1.php` (peak/low/average columns); first tab **Rating** = current order on `ranked7.php`.
- Activity peaks = personal busiest day/week/month/year tables (`ranked8.php`); not the hub Activity tab (`server1.php`) or Status activity league.
- Meta milestone **count** leaderboard (`ranked10.php`) complements the **Milestones hub** (`milestones.php`) — encyclopedia + achievers vs sort table.
- Status uses an active-player window; Leaderboards stay broad by default and expose filters for narrower views (not on Activity peaks wing).

---

## Milestones hub (stub → full)

| Item | Contract |
|------|----------|
| Hub tab | `milestones.php` between Leaderboards and Hall of Fame |
| Build plan | [`docs/milestones-hub-ia.md`](milestones-hub-ia.md) — Recent · Catalog · `milestone.php` detail |
| Player garden | `individual_milestones.php` (per player) |
| HoF achievers | Trial block on `server2.php` until hub hosts lists; then remove from HoF |
| Activity | Milestone digest/charts stay on `server1.php` until hub Charts sub-nav; **no Activity slim scheduled in stub slice** |

---

## Activity & Hall of Fame

| Surface | Contract |
|---------|----------|
| Activity | `server1.php` — server pulse: key sentence, fact cards, games/opponents line, charts (includes milestone digest/charts until migrated). |
| Match log | `server3.php` — rated-game ledger; 14 day buckets; **not** a hub tab. |
| Hall of Fame | `server2.php` — single-holder record extremes only (milestone achievers trial migrates later). |

Do not merge these page bodies into Status unless Dagh explicitly changes the hub strategy.

---

## Player Context

Player pages use the same global header, then player-specific context:

- Hero block.
- Player nav: Profile, Games, W/D/L, Goals, DDs, Milestones.
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
- Full Milestones hub (Home / Story / Charts) — see WIP doc.

---

## Changed Decisions Archive

Keep this short; it prevents old chat ideas from reappearing as current plans.

| Earlier idea | Current position |
|--------------|------------------|
| Leaderboards as default landing | Status is default. |
| Hub tab **Games** | Removed May 2026; match log via Status **Games →** and `server3.php`. |
| Five-tab arc with Games before Leaderboards | Replaced: Status · Activity · Leaderboards · Milestones · HoF. |
| Milestones hub tab deferred | Tab + stub shipped; full hub WIP. |
| Single Activity tab | Split into Status + Activity; Activity peaks is a Leaderboards wing. |
| Live as tab name | Status. |
| Records before Games | Superseded by Games demotion. |
| Kickoff2.com header link | Removed from header. |
| Back to Results on player pages | Removed. |
| Full-accent links everywhere | Use `--k2-link-star` / `--k2-link` hierarchy. |
| Moving server1/server2/server3 bodies into hub panels | Not current plan. |

*Last pruned: May 2026 — hub IA update: Milestones tab, Games off hub.*
