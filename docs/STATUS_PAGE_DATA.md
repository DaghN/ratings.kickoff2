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

**Still needs Steve / prod agreement (later, not blocking dev):** live read on prod, joshua redirect, kickoff2 embed.

---

## Hub Status v1.2 (layout + polish — May 2026, shipped in repo)

**4-column grid** (`k2-status-room__layout` in `theme.css`):

| Col | Row 1 | Row 2 (west subgrid) |
|-----|--------|----------------------|
| 1 (narrower) | Online | Recent logins |
| 2 (wider) | Live games | Recent games |
| 3 (narrower) | Ticker (players/games counts, blue numbers) | New players |
| 4 (wider) | Heritage box | Leaderboard (active Elo top 20) |

Below west: **Monthly league** spans cols 1–3; **This month / Previous month** toggle (`js/status-league-toggle.js`, client-side; medals on previous month only).

**Copy / UI:** Leaderboard title `Leaderboard · N active players` (blue count); meta **Active in last 12 months**; ticker 14px muted prose; panel titles `.k2-panel-heading` (Plex 600, muted, 14px). League toggle uses segment-nav colours (not full accent). Column widths tuned for mobile (more space for recent games + leaderboard).

**Removed from `status.php`:** period-activity triple tables (→ Trends/status if revived). May 2026 prep: `player_period_games` aggregate + local-only preview page exist; not yet placed on Status.

---

## Hub Status v1.1 (superseded layout notes)

Earlier single-column / pulse-first ordering; replaced by v1.2 grid above.

---

## Hub Status v1 (agreed — implementation target)

**Story:** Lobby for *right now* (presence) + *current meta* (active Elo + this month’s league) + *community life* (logins, new players, recent games).

| Panel | Source | Rules |
|-------|--------|--------|
| **Pulse** | `playertable`, `resulttable`, optional `generalstatstable` | Online count, live game count, last login recency; no CPU/disk/mem |
| **Active top rated (20)** | `playertable` | `ORDER BY Rating DESC`; **`LastGame` ≥ now − 12 months**; rating shown **0 decimals**; public display rule (e.g. `Display = 1` if used elsewhere); names → profiles; link “Full leaderboard →” Leaderboards (all players) |
| **Monthly league (~20)** | `ratedresults` | **Calendar month**, **server timezone**; each rated row in month counts; **3 / 1 / 0** pts from `ActualScore` (or W/D/L flags); aggregate per player: Pld, W, D, L, GF, GA, GD, Pts; sort Pts ↓, GD ↓, GF ↓; **only players with ≥1 game in month** (natural from `GROUP BY` — no extra “min games” filter) |
| **Online now** | `playertable` · `IsOnline = 1` | |
| **Live games** | `resulttable` | Started, not finished, not shelved (match legacy filter when verified) |
| **Recent logins** | `playertable` · `LastLogin DESC` | ~10 |
| **Recent registrations** | `playertable` · `JoinDate DESC` | ~10; important community signal |
| **Recent rated games** | `ratedresults` · `Date DESC` | ~10 |
| **Heritage box** | static image | Keep from Phase A bridge layout |

**Not in v1:** games-played-by-period triple tables (`period_activity_leaderboards_section.php` — now backed by `player_period_games`, local preview only until deliberately placed); legacy Steve **`PlayerRank`** top 10; AWOL wall; ops metrics; polling (v1.5).

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
| Active top rated (Elo) | `playertable` · `Rating`, `LastGame` | **Yes (20, 12 mo)** |
| Monthly league | `ratedresults` (month aggregate) | **Yes (new)** |
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
| Period activity prep | **Local only** — `player_period_games` schema/backfill + `dev-period-activity.php`; staging/prod handoff pending Steve |
| v1.5+ | Polling, active filter on Leaderboards tab, kickoff2 embed, joshua redirect |

---

## Related docs

- `docs/hub-ia-agreement.md`
- `docs/LOCAL_DEV.md`
- `docs/playertable-schema.md`, `docs/ratedresults-schema.md`
- `PROJECT_MEMORY.md`
