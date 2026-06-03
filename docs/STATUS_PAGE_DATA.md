# Status page — data sources & v1 scope (May 2026)

Reference for replacing [joshua.kickoff2.net/status.php](https://joshua.kickoff2.net/status.php) on hub **`status.php`**.  
**IA:** `docs/hub-ia-agreement.md` (Status tab role, exclusions).

---

## Finding (corrects earlier assumption)

We previously treated Status **Phase B** as blocked on a **separate live feed or API from Steve**. Investigation of Steve’s page and our DB copies shows that is **not** required to **build** the status UI.

Steve’s status page is **very likely** the same **KOOL Unity MySQL** the game server writes to — the same database family we already mirror:

| Environment | Database name | Notes |
|-------------|---------------|--------|
| **Production / joshua** | `kooldb` (typical PHP config name) | **Live writes** from C++ post-game (`docs/ratings_cpp.txt`) + periodic server jobs |
| **Staging** | `kooldb` | **Same schema, no live game writes** — not fed by the game server; DB changes via dump, Steve-run replay/SQL, or one-offs. WinSCP only updates PHP. |
| **Local Laragon** | `ko2unity_db` | HeidiSQL export from **`ts-joshua`**, May 2026; no live writes |

**No separate “status.php API”** — PHP + SQL against these tables (same pattern as `server1.php`, ranked pages).

**Activity tab (`server1.php`) charts** are client-side Chart.js + `api/server_*.php` — plan and panel registry: [`activity-charts.md`](activity-charts.md) (out of Status hub scope).

**Still needs Steve / prod agreement (later, not blocking dev):** live read on prod, joshua redirect, kickoff2 embed.

---

## Hub Status v1.2 (layout + polish — May 2026, shipped in repo)

**4-column grid** (`k2-status-room__layout` in `theme.css`):

| Col | Row 1 | Row 2 (west subgrid) |
|-----|--------|----------------------|
| 1 (narrow) | Ticker (players/games counts, blue numbers) | New players |
| 2 (narrow) | Online | Recent logins |
| 3 (widest) | Live games | Recent games |
| 4 (moderate) | Heritage box (dark inset well, clipped warm tint halo/rays behind box art + caption) | Leaderboard (active Elo top 20) |

Below west: **Leagues** (Phase **1** shipped in repo) spans cols 1–3 — paired **Activity** + **Points**, day/week/month/year tabs. **Daily tab only:** compact **Games this day** list below the league tables (recent-games style + `game.php` link column); updates with day picker / step nav via `api/status_period_day_games.php`. **Phase 1.5 next** (one-liner, Monday editorial, etc.): [`docs/status-period-competitions-wip.md`](status-period-competitions-wip.md) · handoff [`docs/coordination/status-period-competitions-phase-1.5-handoff.md`](coordination/status-period-competitions-phase-1.5-handoff.md).

**Legacy (pre–period competitions):** League stack was **Daily**, **Weekly**, **Monthly**, **Yearly** panels, each with current/previous toggle (`js/status-league-toggle.js`; medals on previous period only). Monday-start weeks.

**Copy / UI:** Leaderboard title `Leaderboard · N active players in the past year` (blue count); meta link **Leaderboards →**; ticker 14px muted prose; panel titles `.k2-panel-heading` (Plex 600, muted, 14px). **Leagues toolbar:** Day/Week/Month/Year segment left using the shared `k2-chrome-tabs` track with compact milestone-style density; period step nav + picker stay separate on a quiet grouped surface (spacious arrows + centered picker), rather than merging into the period tabs; **week/month/year archive** + **Flatpickr month/year** = themed listbox (`k2-archive-listbox.js`; day calendar = Flatpickr); listbox trigger width is locked after init to the longest label in that control (hidden measure probe + chevron gutter); picker row width is the max across day/week/month/year pickers so tab changes do not shift step nav; picker row does not break apart (`flex-wrap: nowrap`); if nav wraps, it drops to a full-width centered row (`is-period-nav-stacked` in `status-period-competitions.js`). Meta/ticker line (`data-competition-meta`) left-aligned; `margin-top` above it is 1.5× `--k2-leagues-meta-gap` (30px). League toggle uses segment-nav colours (not full accent). League meta (`data-competition-meta`) leads with plain **League** then highlighted period label (e.g. `League Week 1, 2024 · … · ended January 8, 00:00 UTC`); day picker trigger `F j, Y` (no weekday); league meta day label keeps weekday; full month names elsewhere; countdown from MySQL `NOW()`. Column widths favor live/recent games; the heritage/leaderboard lane is secondary. Rated-games arc links to Activity (`server1.php`) with a discreet left-aligned action below the sentence; Activity’s first screen expands the story with a key sentence, fact cards, a small games/opponents line, and a past-month games-per-day chart before longer charts. Recent games stay compact/chatty (names + score only) and expose a small `Games →` link to the full Games list (`server3.php`) for sortable detail, rating diff, Fav ES, and adjustment columns. `status.php` sends no-cache headers so lobby/status panels do not linger stale in browsers.

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
| **Rated-games arc** | `generalstatstable` + `ratedresults` + `playertable` | Compact all-time sentence (`players`, `rated games`, first rated date); no hub link (Activity tab is separate) |
| **Active rated leaderboard** | `playertable` | Full active list, not capped: `ORDER BY Rating DESC`; **`LastGame` ≥ now − 12 months**; rating shown **0 decimals**; public display rule (`Display = 1`); names → profiles; heading count is exact active row count; sortable `#`/Player/Elo/Games headers with compact help — **Elo** help notes 12‑month active window + complete leaderboards live in the Leaderboards hub section; **Games** = career `NumberGames` (“Games played (career).”); link `Leaderboards →` opens broad Leaderboards section |
| **League stack** | **`player_period_league`** when present; else `ratedresults` scan | **Calendar day**, **Monday-start calendar week**, **calendar month**, **calendar year**; UI current/previous boundaries use server `NOW()`; **stored `period_start` keys are UTC** per [`website-data-contract.md`](website-data-contract.md); **3 / 1 / 0** pts from `ActualScore`; aggregate per player: Pld, W, D, L, GF, GA, GD, Pts; sort Pts ↓, GD ↓, GF ↓; **all players with ≥1 game in period**; reader: `status_queries.php` |
| **Online now** | `playertable` · nonzero `IsOnline` | Do not gate by `Display`; this is lobby presence, not ladder eligibility |
| **Live games** | `resulttable` | Started, not finished, not shelved (match legacy filter when verified) |
| **Recent logins** | `playertable` · `LastLogin DESC` | ~10 |
| **Recent registrations** | `playertable` · `JoinDate DESC` | ~10; important community signal |
| **Recent rated games** | `ratedresults` · `Date DESC` | ~10; show player names and score only, no rating deltas; header link `Games →` opens full Games list |
| **Heritage box** | static image | Box art only; **Play & Setup** is a hub tab |

**Not in v1:** games-played-by-period triple tables (`period_activity_leaderboards_section.php` — now backed by `player_period_games`, preview only until deliberately placed); legacy Steve **`PlayerRank`** top 10; AWOL wall; ops metrics; polling (v1.5).

**Display:** Active top 20 may use slightly smaller type if needed for density.

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

Local dump: same. Do not label staging or local as live prod. Production read = truth for “tonight.”

---

## Implementation status

| When | What |
|------|------|
| Phase A | Hub shell, bridge, heritage box |
| **Phase B v1** | **Shipped** — `status_queries.php`, `status_room_section.php`, `status.php` |
| **v1.2 polish** | **Shipped** — 4-col grid, league month toggle, typography/column balance (`theme.css`) |
| **League stack (legacy)** | Replaced on Status by **Leagues** block (`status_period_competitions_section.php`); old four-panel + `status-league-toggle.js` removed from `status.php` |
| **League stored truth (SCH-008)** | **Local + staging done** (May 2026) — `player_period_league` + REP-007–011 on `kooldb`; Steve verify all parity checks pass (74,870 rated games). Prod schema + post-game from contract pending |
| Performance pass | **Local + staging DB done** — `idx_ratedresults_date`, `idx_resulttable_live_status`, and `player_period_league`; Status loader ~6.6s → ~51ms locally; legacy `player_monthly_league` dropped SCH-017 (Jun 2026) |
| Period activity prep | **Local + staging done (May 2026)** — SCH-006 + REP-003 week refresh + REP-005 on `kooldb`; prod handoff/method pending Steve |
| **Leagues (period competitions)** | **Phase 1 shipped** — paired Activity + Points, tab nav, prewarm; **Phase 1.5** next — [`docs/status-period-competitions-wip.md`](status-period-competitions-wip.md) |
| v1.5+ | Polling, kickoff2 embed, joshua redirect |

---

## Related docs

- `docs/hub-ia-agreement.md`
- `docs/LOCAL_DEV.md`
- `docs/playertable-schema.md`, `docs/ratedresults-schema.md`
- `docs/website-data-contract.md` (league aggregates, UTC)
- `PROJECT_MEMORY.md`
