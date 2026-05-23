# Status page — data sources (May 2026)

Reference for replacing [joshua.kickoff2.net/status.php](https://joshua.kickoff2.net/status.php) on hub **`status.php`**.  
**IA / scope:** `docs/hub-ia-agreement.md` (player-facing panels, no duplicate Top 10, no CPU/disk ops strip).

---

## Finding (corrects earlier assumption)

We previously treated Status **Phase B** as blocked on a **separate live feed or API from Steve**. Investigation of Steve’s page and our DB copies shows that is **not** required to **build** the status UI.

Steve’s status page is **very likely** the same **KOOL Unity MySQL** the game server writes to — the same database family we already mirror:

| Environment | Database name | Notes |
|-------------|---------------|--------|
| **Production / joshua** | `kooldb` (typical PHP config name) | Live writes from C++ (`docs/ratings_cpp.txt`) |
| **Staging** | `kooldb` | Same schema; fresher than a laptop dump |
| **Local Laragon** | `ko2unity_db` | HeidiSQL export from **`ts-joshua`**, `data/dumps/ko2unity_db-2026-05-20.sql` |

**No separate “status.php API”** is implied by the legacy page — it is almost certainly **PHP + SQL** against these tables (same pattern as `server1.php`, ranked pages).

**What still needs Steve / prod agreement (later, not blocking dev):**

- Deploying hub Status on **ratings.kickoff2.com** against **live** `kooldb` (read credentials / host).
- **`joshua.kickoff2.net/status.php`** redirect or thin wrapper.
- Optional **kickoff2.com** embed.
- **Write** access or migrations on prod (out of scope for Status read UI).

---

## Table → status panel map

| Status panel (legacy page) | Table / source | Notes |
|----------------------------|----------------|--------|
| Online players | `playertable` · `IsOnline = 1` | Stale in SQL dumps |
| Recent logins | `playertable` · `ORDER BY LastLogin DESC` | |
| Live / in-progress games | `resulttable` · `HasStarted = 1` AND `HasFinished = 0` (and filters Steve uses for shelved vs active) | Scores: `ScoreA`/`ScoreB`; period: `GamePeriod`; shelved: `Shelved`; start: `StartTime`; clock likely `HalfCountdown` or `Duration` |
| Shelved count | `COUNT(*)` on `resulttable` WHERE `Shelved = 1` | Local snapshot ~3989 vs prod ~4011 (May 2026) — confirms same metric |
| Recent finished games | `ratedresults` · `ORDER BY Date DESC` | Ladder archive only |
| Top 10 (legacy “Steve rank”) | `playertable` · `PlayerRank <> 9999` · `ORDER BY PlayerRank` | **Exclude** on hub per IA — use Leaderboards |
| Top 10 (ratings) | `playertable` · `ORDER BY Rating DESC` | **Exclude** on hub per IA |
| New arrivals | `playertable` · `ORDER BY JoinDate DESC` | Optional / Trends later |
| AWOL (days offline) | `playertable` · `DATEDIFF` on `LastLogin` (displayed players) | Verified: DelCa 43, KONEY 47, etc. match legacy page |
| Games played / players / goals (headline) | `generalstatstable` · `id = 1` | `GamesPlayed`, `NumberOfPlayers`, `GoalsScored` |
| Games-by-period leaderboards | `ratedresults` | **Already on hub** — `includes/period_activity_leaderboards_section.php` |
| Uptime, disk, CPU, RAM, swap | **OS / PHP** | **Not in MySQL** — omit on player hub Status |
| DB threads | MySQL server status | Ops-only — omit on player hub |

**`resulttable` vs `ratedresults`:** Use **`ratedresults`** for finished rated games (canonical ladder). Use **`resulttable`** for live/shelved/aborted rows. Ladder Python replay does **not** touch `resulttable` — Status queries are independent.

---

## Snapshot vs live

| Concern | Detail |
|---------|--------|
| **Local dump** | Point-in-time (~May 2026). `IsOnline`, in-progress scores, and counts lag prod by days and thousands of games. |
| **Staging `kooldb`** | Same schema; use for integration tests closer to live. |
| **Production** | Truth for “who’s on tonight”; requires read access to live DB, not a new API shape. |

**Rule for hub copy:** Do not present **stale dump data** as live prod. Label staging/snapshot when needed, or only ship panels that are honest about refresh time once wired to live DB.

---

## Implementation status

| When | What |
|------|------|
| Phase A (shipped) | Hub shell, bridge copy, link to legacy status.php, **period-activity** tables from DB |
| **Phase B (in progress, May 2026)** | Replace bridge with SQL-driven panels above on **`status.php`**; start on **local `ko2unity_db`**, then staging; prod read + joshua redirect when agreed |

**Work started:** May 2026 — build against existing `ko2unitydb_config.php` connection (same as period-activity block).

---

## Related docs

- `docs/LOCAL_DEV.md` — import `ko2unity_db`, table list in dump
- `docs/playertable-schema.md`, `docs/ratedresults-schema.md`, `docs/generalstatstable-schema.md`
- `PROJECT_MEMORY.md` — current focus / recent log
