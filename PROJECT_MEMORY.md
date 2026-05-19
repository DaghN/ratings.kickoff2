# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief.

---

## Current focus

- **Charts (first wave):** largely **shipped** on staging — see **Shipped charts** below. Further chart ideas only **after** profile tone / layout pass unless Dagh prioritises otherwise.
- **Product tone (Dagh direction):** keep the ladder **truthful and data-rich** for regulars, but make the site feel **inclusive, playful, and inviting** — not discouraging. **Player profile (`individual1.php`) “above the fold”** should feel **active, fun, and welcoming** (stories and participation first); deeper / comparative analytics (win rate vs rating, H2H compare, etc.) **lower or grouped** (“matchup lab”), not the first impression.
- **Fun stats block (planned, not built):** curated **trophy-cabinet** highlights — re-use existing `playertable` extremes (biggest win/draw, streaks, goal festivals) plus **new monthly aggregates** (busiest month, most goals in a month). Brainstorm logged under **Next**; **no longest-game** stat. Discussed only — **no implementation yet**.
- **Operational loop:** mirror → edit locally/Git → deploy to **staging** with **WinSCP** (**Synchronize** `site/public_html/` → remote `public_html/`). Hard refresh after CSS/JS/PHP.
- **Dev database:** Steve provided a **writable dev DB**; staging **`ko2unitydb_config.php`** updated (May 2026). Confirm `DATABASE()` on staging before any write test. Schema/SQL changes: **dev first**, scripts in repo, Steve for production. **`scripts/throwaway_db_write_probe.php`** — one-shot CREATE/INSERT/UPDATE/DELETE/DROP on `_kool_dev_write_probe`; copy to `public_html`, `?once=db-write-probe-one-shot`, **delete after**.
- **Local PHP+DB:** still needs gitignored **`site/config/ko2unitydb_config.php`** (or equivalent) + reachable MySQL if Dagh wants Laragon against dev.
- **Change style:** small, reversible slices (brief).

---

## Shipped charts (May 2026)

**Pattern:** self-hosted Chart.js under **`js/`**, JSON **`api/`**, `realm=online`, MariaDB 10.11 window SQL where needed.

**Established** = career **20th rated game** (same as `api/server_established_players_by_year.php`). No `playertable.Display` filter unless Dagh says otherwise.

### `server1.php` (server stats)

| Chart | API | JS |
|--------|-----|-----|
| Games per month | `server_games_by_month.php` | `server-games-month-chart.js` |
| Goals per month | `server_goals_by_month.php` | `server-goals-month-chart.js` |
| Active players per month | `server_active_players_by_month.php` | `server-active-players-month-chart.js` |
| New established players per year | `server_established_players_by_year.php` | `server-established-players-year-chart.js` |
| Cumulative established (step at each player’s 20th-game **date**) | `server_cumulative_established_by_month.php` | `server-cumulative-established-month-chart.js` |
| Established rating distribution (100-pt buckets, **includes empty buckets as 0**) | `server_established_rating_distribution.php` | `server-established-rating-distribution-chart.js` |

### `individual1.php` (player profile)

| Chart | API | JS |
|--------|-----|-----|
| Rating history + peak/current on **one** chart | `player_rating_history.php` | `player-rating-chart.js` |
| Games per month | `player_games_by_month.php` | `player-games-month-chart.js` |
| Win rate vs opponent pre-game rating (50-pt buckets) | `player_winrate_vs_opponent_rating.php` | `player-winrate-opponent-chart.js` |
| Top 20 opponents (click → H2H below) | `player_top_opponents.php` | `player-top-opponents-chart.js` |
| H2H cumulative wins vs selected opponent | `player_head_to_head.php` | `player-head-to-head-chart.js` |
| Full-career rating comparison vs opponent | `player_compare_rating_history.php` | `player-compare-rating-chart.js` |
| Opponent search (syncs via `kool-opponent-selected`) | — | `player-h2h-opponent-search.js` |

**Other profile fixes:** `individual3.php` game links → `individual1.php`; date format aligned with activity table.

---

## Next (prioritised intent)

1. **Profile first screen:** welcoming layout — fun stats / activity / rating story up front; tuck comparative charts.
2. **Fun stats block (v1 brainstorm):** biggest win, biggest draw, busiest month, goal-rich month, longest win streak, goal-festival game; pull from existing `playertable` + monthly SQL on `ratedresults`; playful section title (e.g. trophy cabinet). **Exclude** longest game; keep harsh rows (biggest loss) in full table only.
3. **Tone pass:** chart titles/helper copy — context, sample size, “rematch story” not report-card (see chat on inclusive analytics).
4. **Dev DB workflow:** run write probe if not done; document migration habit (`schema/` SQL files, dev → staging test → Steve prod).
5. **Optional:** local `ko2unitydb_config.php` template from Steve; align laptop + staging config shapes (gitignored only).

---

## Recent log

| When (approx.) | What |
|----------------|------|
| 2026-05 | **Writable dev DB** + updated server config (Steve). **`scripts/throwaway_db_write_probe.php`** for staging write check. |
| 2026-05 | **Inclusive / fun profile direction** — brainstorm: welcoming copy, progressive disclosure, **fun stats** block; profile “front page” should feel active/fun, not judgment-first. |
| 2026-05 | **Chart wave 2 committed:** H2H + compare rating, top opponents, win rate vs opponent rating, server goals/cumulative established/rating distribution (empty buckets filled), peak/current on one chart, `individual3` link/date fixes. |
| 2026-05 | **Established rating distribution** — 100-pt buckets; API fills **empty buckets with 0** between min and max. |
| 2026-05 | **Staging DB:** MariaDB **10.11.7** confirmed (`throwaway_mysql_version.php`; removed after). |
| 2026-05 | **`bd9730a`** — first Chart.js batch (server games/active/established year, player rating + games/month). |
| 2026-05 | **`index.php`** → **302** to **`ranked1.php`**. WinSCP deploy loop; Git **`main`** → [ratings.kickoff2](https://github.com/DaghN/ratings.kickoff2). |
| 2026-05 | **`scripts/throwaway_playertable_schema.php`** — staging one-shot schema dump; delete after. **`/javascript/`** → **`/js/`** for `elolist.js` (URL segment issue). |

*(Append concise rows; prune old noise.)*

---

## Deferred / blocked

- GitHub branch protection / enforced PRs — when collaborators land.
- **Amiga/offline datasets** and large bulk imports — after dev migration workflow is routine.
- **Pretty URLs** (`/online/{id}`): needs Steve (**`.htaccess`** / vhost); cosmetic until then.

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub repo | https://github.com/DaghN/ratings.kickoff2 |
| Default branch | `main` |
| Staging SFTP host | **`ratings.kickoff2.com`**, port **`5322`**, user **`dagh@ratings.kickoff2.com`** |
| Deploy | **WinSCP** — **Synchronize** local **`…\site\public_html`** → remote **`public_html`** |
| Legacy public reference | https://joshua.kickoff2.net/ratings/ |
| Local mirrored web root | **`site/public_html/`** |
| Server DB config | **`public_html/../config/ko2unitydb_config.php`** — **not mirrored**; never commit |
| **Database** | **MariaDB 10.11.7** on staging; **`mysqli`**; window functions OK. **Dev copy writable** (May 2026) — confirm which DB name staging uses before DDL/DML |
| Local preview | **Laragon** → **`ratingskickoff.test`** (junction to **`site/public_html`**) |
| Throwaway probes | Under **`scripts/`** — manual copy to **`public_html`**, gated `?once=…`, **delete after** |

---

## Rules of engagement (agents)

- **Authority:** **`PROJECT_BRIEF.md`** carries product intent and taste. **Dagh’s latest message** wins on scope and direction. **This file** is logistics hand-off only — if it clashes with Brief or Dagh, follow Brief + Dagh and **offer to update MEMORY** afterward.
- **Memory vs reality:** Treat **staging, SFTP-visible files, and repo/`git`** as ground truth when debugging. **If MEMORY lags** (deploy path, URLs, hosting), trust **facts + what Dagh says**, then propose a concise **MEMORY patch** instead of inventing lore.
- **Secrets:** Never commit **`ko2unitydb_config.php`**, `.env`, or live credentials. **Do not** paste SFTP/MySQL passwords into chat or this doc; redact logs and screenshots.
- **Deploy vs GitHub:** **`git push`** does **not** update the public site unless a future automation says so. **Today:** staging updates only via **WinSCP sync** of **`site/public_html/`** → server **`public_html/`**.
- **Change style:** **Small, reversible** diffs aligned with Brief — no drive-by mega-refactors unless Dagh expands scope.

---

## Agent hygiene

- After completing a slice: **one line** under Recent log; tweak **Current focus** / **Next** accordingly.
- **Never** paste secrets/SFTP/MySQL passwords in this doc or commits.
- Prefer relative asset paths that match Linux **case-sensitive** filesystems (`images/`, **`js/`** not **`javascript/`** for this stack).
- Do **not** commit **`site/public_html/javascript/`** (legacy duplicate of **`js/elolist.js`**).
