# Status page — data sources & v1 scope (May 2026)

Reference for replacing [joshua.kickoff2.net/status.php](https://joshua.kickoff2.net/status.php) on hub **`status.php`**.  
**IA:** `docs/hub-ia-agreement.md` (Status tab role, exclusions).

---

## Finding (corrects earlier assumption)

We previously treated Status **Phase B** as blocked on a **separate live feed or API from Steve**. Investigation of Steve’s page and our DB copies shows that is **not** required to **build** the status UI.

Steve’s status page is **very likely** the same **KOOL Unity MySQL** the game server writes to — the same database family we already mirror:

| Environment | Database name | Notes |
|-------------|---------------|--------|
| **Production / joshua** | Steve live DB | **Live writes** — legacy C++ **today**; **PHP ops** (`ops/dispatch.php`) at cutover |
| **Staging work** | **`kooldb1`** (pristine clone **`kooldb2`**) | **Same schema family, no live game writes** — forward proof via ops prepare/simul ([`coordination/cutover-readiness.md`](coordination/cutover-readiness.md)). Legacy May DB **`kooldb`** = frozen historical log only. WinSCP syncs PHP only. |
| **Local Laragon** | `ko2unity_db` | HeidiSQL export from **`ts-joshua`**, May 2026; no live writes |
| **Local work (sim)** | **`ko2unity_work`** | Ops simul + **live environment sim** — [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md); URL **`work.ratingskickoff.test`** only |

**No separate “status.php API”** — PHP + SQL against these tables (same pattern as `activity.php`, ranked pages).

**Activity tab (`activity.php`) charts** are client-side Chart.js + `api/server_*.php` — plan and panel registry: [`activity-charts.md`](activity-charts.md) (out of Status hub scope).

**Still needs Steve / prod agreement (later, not blocking dev):** live read on prod, joshua redirect, kickoff2 embed.

---

## Hub Status v1.2 (layout + polish — May 2026, shipped in repo)

**4-column grid** (`k2-status-room__layout` in `theme.css`):

| Col | Row 1 | Row 2 (west subgrid) |
|-----|--------|----------------------|
| 1 (narrow) | Ticker (players/games counts, blue numbers) | New players |
| 2 (narrow) | Online | Recent logins |
| 3 (widest) | Live games | Recent games |
| 4 (moderate) | Heritage box (dark inset well, clipped warm tint halo/rays behind box art + caption); **whole inset is a link to `boxart.php#k2-boxart-story`** (box art story) — hover: image lift + moment-card border/glow (2px accent + `--k2-accent-glow`); no visible link text | Leaderboard (full active rated list — 12‑month window) |

Below west: **Leagues** (shipped) spans cols 1–3 — paired **Activity** + **Points**, day/week/month/year tabs. **Daily tab only:** compact **Games this day** list below the league tables (recent-games style + `game.php` link column); updates with day picker / step nav via `api/status_period_day_games.php`. Spec: [`docs/status-period-competitions.md`](status-period-competitions.md).

**Legacy (pre–period competitions):** League stack was **Daily**, **Weekly**, **Monthly**, **Yearly** panels, each with current/previous toggle (`js/status-league-toggle.js`; medals on previous period only). Monday-start weeks.

**Copy / UI:** Leaderboard title `Leaderboard · N active online players in the past year` (blue count); meta link **Leaderboards →**; ticker 14px muted prose; **On this day last year →** below the arc sentence with one line of spacing (`k2-status-room__arc-link`); Points day league one UTC year back (C07); panel titles `.k2-panel-heading` (Plex 600, muted, 14px). **Leagues toolbar:** Day/Week/Month/Year segment left using the shared `k2-chrome-tabs` track with compact milestone-style density; period step nav + picker grouped in `.k2-status-period-competitions__period-nav` for layout only (transparent, no border — chevrons + picker read as loose controls beside the period tabs, not a second segment pill); **60px** horizontal gap (`column-gap` on controls row); spacious arrows + centered picker; **week/month/year archive** + **Flatpickr month/year** = themed listbox (`k2-archive-listbox.js`; day calendar = Flatpickr); listbox trigger width is locked after init to the longest label in that control (hidden measure probe + chevron gutter); picker row width is the max across day/week/month/year pickers so tab changes do not shift step nav; picker row does not break apart (`flex-wrap: nowrap`); if nav wraps, it drops to a full-width centered row (`is-period-nav-stacked` in `status-period-competitions.js`). Meta/ticker line (`data-competition-meta`) left-aligned; `margin-top` above it is 1.5× `--k2-leagues-meta-gap` (30px). League toggle uses segment-nav colours (not full accent). League meta (`data-competition-meta`) leads with plain **League** then highlighted period label (e.g. `League Week 1, 2024 · … · ended January 8, 00:00 UTC`); day picker trigger `F j, Y` (no weekday); league meta day label keeps weekday; full month names elsewhere; countdown from MySQL `NOW()`. Column widths favor live/recent games; the heritage/leaderboard lane is secondary. Rated-games arc links to Activity (`activity.php`) with a discreet left-aligned action below the sentence; Activity’s first screen expands the story with a key sentence, fact cards, a small games/opponents line, and a past-month games-per-day chart before longer charts. Recent games stay compact/chatty (names + score only) and expose a small `Games →` link to the full Games list (`games.php`) for sortable detail, rating diff, Fav ES, and adjustment columns. **West recency lists** (New players · Recent logins · Recent games): CSS subgrid on `.k2-status-recency-list` — date column = widest row in that panel, 8px gap to names/match; panel widths unchanged. `status.php` sends no-cache headers so lobby/status panels do not linger stale in browsers.

**Removed from `status.php`:** period-activity triple tables (→ Trends/status if revived). May 2026 prep: `player_period_games` aggregate + dev/staging preview page exist; not yet placed on Status.

---

## Hub Status v1.1 (superseded layout notes)

Earlier single-column / pulse-first ordering; replaced by v1.2 grid above.

---

## Hub Status v1 (agreed — implementation target)

**Story:** Lobby for *right now* (presence) + *current meta* (active Elo + this month’s league) + *community life* (logins, new players, recent games).

| Panel | Source | Rules |
|-------|--------|--------|
| **Pulse** | `playertable`, `resulttable`, optional `generalstatstable` | Online count, live game count, last login recency; no CPU/disk/mem |
| **Rated-games arc** | `generalstatstable` + `ratedresults` + `playertable` | Compact all-time sentence (`players`, `online Kick Off 2 games`, first rated date); no hub link (Activity tab is separate) |
| **Active rated leaderboard** | `playertable` | Full active list, not capped: `ORDER BY Rating DESC`; **`LastGame` ≥ now − 12 months**; **`NumberGames` ≥ 1**; rating shown **0 decimals**; names → profiles; **Elo** → rating LB row (`k2_lb_rating_cell_link()`); heading count is exact active row count; sortable `#`/Player/Elo/Games headers with compact help — **Elo** help notes 12‑month active window + complete leaderboards live in the Leaderboards hub section; **Games** = career `NumberGames` (“Games played (career).”); link `Leaderboards →` opens broad Leaderboards section |
| **League stack** | **`player_period_league`** when present; else `ratedresults` scan | **Calendar day**, **Monday-start calendar week**, **calendar month**, **calendar year**; UI current/previous boundaries use server `NOW()`; **stored `period_start` keys are UTC** per [`website-data-contract.md`](website-data-contract.md); **3 / 1 / 0** pts from `ActualScore`; aggregate per player: Pld, W, D, L, GF, GA, GD, Pts; sort Pts ↓, GD ↓, GF ↓; **all players with ≥1 game in period**; reader: `status_queries.php` |
| **Online now** | `playertable` · nonzero `IsOnline` | Do not gate by `Display`; this is lobby presence, not ladder eligibility. **Heading:** `<count> online` (`.blue` count, lowercase label — same stat treatment as active LB count). **Order:** `LastLogin ASC` (logged-in-first at top; newest login at bottom). |
| **Live games** | `resulttable` | Started, not finished, not shelved (match legacy filter when verified) |
| **Recent logins** | `playertable` · `LastLogin DESC` | ~10; **do not gate by `Display`** (lobby signal, like Online now) |
| **Recent registrations** | `playertable` · `JoinDate DESC` | ~10; important community signal; **do not gate by `Display`** (includes registrants before first rated game) |
| **Recent rated games** | `ratedresults` · `Date DESC` | ~10; show player names and score only, no rating deltas; header link `Games →` opens full Games list |
| **Heritage box** | static image | Box art only; **Play & Setup** is a hub tab. Inset links to **`boxart.php#k2-boxart-story`** (illustrated box-art history) |

**Not in v1:** games-played-by-period triple tables (preview includes removed Jun 2026 — data now in `player_period_games` + LB UI when placed); legacy Steve **`PlayerRank`** top 10; AWOL wall; ops metrics. **Live polling (v1.5)** shipped — [`status-room-live-policy.md`](status-room-live-policy.md).

**Display:** Full active list may use slightly smaller type if needed for density.

---

## Table → panel map (legacy + hub)

| Panel | Table / source | Hub v1 |
|-------|----------------|--------|
| Online players | `playertable` · `IsOnline` | Yes |
| Recent logins | `playertable` · `LastLogin` | Yes |
| Recent registrations | `playertable` · `JoinDate` | **Yes** |
| Live games | `resulttable` | Yes |
| Recent finished games | `ratedresults` | Yes |
| Active rated leaderboard (Elo) | `playertable` · `Rating`, `LastGame` | **Yes (full active list, 12 mo)** |
| Daily / Weekly / Monthly / Year league | `player_period_league` (preferred); fallbacks as above | **Yes** |
| Top 10 Steve `PlayerRank` | `playertable` | **No** |
| Legacy ratings Top 10 only | `playertable` | **No** (replaced by active Elo strip) |
| AWOL | `playertable` · `LastLogin` | No (v1) |
| Headline totals | `generalstatstable` | Optional in pulse |
| Games-by-period (played count) | `ratedresults` | **No** (v1) |
| Uptime, CPU, disk, mem | OS | No |

**`resulttable` vs `ratedresults`:** finished rated games → `ratedresults`; live/shelved → `resulttable`.

---

## Snapshot vs live

**Staging has no live game feed** (confirmed May 2026) — same staleness pattern as a local dump: `IsOnline`, live `resulttable` rows, and “online now” counts reflect **last import or last manual update**, not tonight’s play.

**Local dev (`ratingskickoff.test` / `ko2unity_db`):** same snapshot behaviour.

**Local work (`work.ratingskickoff.test` / `ko2unity_work`):** **exception** — the **live environment sim** ([`status-room-live-sim-spec.md`](status-room-live-sim-spec.md)) writes prod-shaped ground telemetry (login/logout, games; registration planned) so Status v1.5 pulse can be tested without prod. Guard: **`ko2unity_work` + work hostname only** — synced code on staging/prod cannot activate sim.

**Production** = truth for “tonight.” Do not label staging or local dev as live prod.

---

## Implementation status

| When | What |
|------|------|
| Phase A | Hub shell, bridge, heritage box |
| **Phase B v1** | **Shipped** — `status_queries.php`, `status_room_section.php`, `status.php` |
| **v1.2 polish** | **Shipped** — 4-col grid, league month toggle, typography/column balance (`theme.css`) |
| **League stack (legacy)** | Replaced on Status by **Leagues** block (`status_period_competitions_section.php`); old four-panel + `status-league-toggle.js` removed from `status.php` |
| **League stored truth (SCH-008)** | **Repo + `kooldb1` proof done** (Jun 2026) — `player_period_league` via ops simul on work DB; May batch era on frozen **`kooldb`** only. **Live prod:** schema + PHP post-game at Steve cutover (not repo backlog) |
| Performance pass | **Local + work/staging proof done** — `idx_ratedresults_date`, `idx_resulttable_live_status`, `player_period_league`; Status loader ~6.6s → ~51ms locally (Jun 2026). **Jul 2026:** current **year** bundle deferred to client prewarm + `k2_league_load_first_games` request memo — first paint ~0.15 s curl (`2026-07-04-017`). Legacy `player_monthly_league` dropped SCH-017 (Jun 2026) |
| Period activity prep | **Repo + `kooldb1` proof done** — `player_period_games` / peaks via ops simul; historical May **`kooldb`** batch in [`archive/replay-register-2026-05.md`](archive/replay-register-2026-05.md) |
| **Leagues (period competitions)** | **Shipped** — paired Activity + Points, tab nav, prewarm, Daily games list — [`docs/status-period-competitions.md`](status-period-competitions.md) |
| **v1.5 live room** | **Shipped (Jul 2026)** — 1 s pulse, cascade on rated finish, client half clocks, **text-ink glow @ 2.6 s** (names / goal digits / counts; cascade highlights finished-game players in active LB), visibility catch-up, SRL-16 rating re-sort — [`status-room-live-policy.md`](status-room-live-policy.md) |
| **v1.5 live testing** | **Work sim harness shipped** — L1–L3 + L2 registration on work — [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md) |
| v1.5+ (other) | kickoff2 embed, joshua redirect |

---

## Related docs

| Doc | Role |
|-----|------|
| [`status-room-live-policy.md`](status-room-live-policy.md) | Live polling contract (SRL-1…SRL-17), environments, file map |
| [`status-room-live-implementation-plan.md`](status-room-live-implementation-plan.md) | SRL slices (shipped) + work verification workflow |
| [`status-room-live-sim-spec.md`](status-room-live-sim-spec.md) | **Live environment sim** — L1–L3, guard, harness, test checklist |
| [`status-period-competitions.md`](status-period-competitions.md) | Leagues block (integrates with pulse) |
| [`hub-ia-agreement.md`](hub-ia-agreement.md) | Status hub IA |
| [`LOCAL_DEV.md`](LOCAL_DEV.md) | Hostnames, work DB setup |
- `docs/playertable-schema.md`, `docs/ratedresults-schema.md`
- `docs/website-data-contract.md` (league aggregates, UTC)
- `PROJECT_MEMORY.md`
