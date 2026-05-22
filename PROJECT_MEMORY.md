# PROJECT_MEMORY — running context for agents



**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.



**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief.



---



## Current focus



- **Design / cosmetics track:** **Phase A hub shell shipped** — `includes/k2_head.php` (shared CSS + `theme_boot_head` in `<head>`), **`main2.css` removed** (all `--k2-*` in `theme.css`), production **neon C**, chart helpers use `--k2-text-muted` (`#8b949e`). Staging **accent preview pills** kept for now; **TEST realm accent swap** in CSS (revert or lock before launch). **Next:** WinSCP sync refactor to staging; Status **Phase B** live feed; realm switcher behavior when Amiga data exists.

- **Charts (first wave):** largely **shipped** on staging — see **Shipped charts** below. **Busiest day / month / year hall of fame** on `server1.php` — three SSR tables (`peak_period_leaderboard_query.php`). Further chart ideas only **after** profile tone / layout pass unless Dagh prioritises otherwise.

- **DB performance (May 2026):** Profile blank wait was almost entirely **many `ratedresults` scans per player** (`idA OR idB`) with no player indexes. **Phase A shipped:** `idx_ratedresults_idA`, `idx_ratedresults_idB` — applied **local** (PowerShell) and **staging** (browser throwaway from `scripts/`); **production still pending** — may ask **Steve** when ready. **Result on profile:** heavy players ~**8s → ~1s** locally; light players ~**100ms**. **Trends (`server1.php`):** still slow locally (~**7s** blocking PHP = 3× hall-of-fame full-table rollups; ~**9s** more for seven chart APIs). A **`Date` index is not the right fix for server1** — cost is `GROUP BY DATE_FORMAT(…)` and window SQL over **all** games. Future server1 wins: fewer/heavier queries cached or precomputed.

- **Profile feast (shipped):** production **`individual1.php`** only — layout contract in **`docs/player-profile-feast.md`**. Pass-2 audit/framing archived; dev preview URLs removed (no redirects). Load trimmed (dropped unused rival/recent SQL). Further work = gradual copy/UX, not mock lab.

- **Product tone (Dagh direction):** keep the ladder **truthful and data-rich** for regulars, but make the site feel **inclusive, playful, and inviting** — not discouraging. **Player profile (`individual1.php`) “above the fold”** should feel **active, fun, and welcoming** (stories and participation first); deeper / comparative analytics (win rate vs rating, H2H compare, etc.) **lower or grouped** (“matchup lab”), not the first impression.

- **Fun stats block (planned, not built):** curated **trophy-cabinet** highlights — re-use existing `playertable` extremes (biggest win/draw, streaks, goal festivals) plus **new monthly aggregates** (busiest month, most goals in a month). Brainstorm logged under **Next**; **no longest-game** stat. Discussed only — **no implementation yet**.

- **Operational loop:** mirror → edit locally/Git → deploy to **staging** with **WinSCP** (**Synchronize** `site/public_html/` → remote `public_html/`). Hard refresh after CSS/JS/PHP. **SSH shell for Dagh:** still **permission denied** (May 2026); Steve will **run one-off scripts** when sent — plan batch recalc for that path unless SSH is fixed.

- **Ladder replay (Python):** **P0–P2 done (May 2026)** — local **`ko2unity_db`** + staging **`kooldb`** (Steve ran **`run_staging_ladder_replay.sh`**; success). Record: **`docs/STAGING_REPLAY.md`**. CLI: **`scripts/ladder/README.md`**. **Deferred:** Amiga/offline (P3–P4), prod C++ alignment (P5) — see **`docs/ladder-engine-plan.md`**. **Prod live ratings stay C++.**

- **`resulttable` vs `ratedresults` (May 2026):** Local DB has both — **`resulttable`** is the wide match log (rated + unrated, aborted 0–0, never-linked rows); **`ratedresults`** is the rated ladder only (~74.9k rows). A one-off Steve-era JSON export matched **`resulttable` by `GameID`**, not **`ratedresults`**, and included extra non-ladder games — so Elo from an external replay on that list will differ slightly from ours; that is expected, not a bug. **Canonical source for this project: `ratedresults` only** (replay v1 already does this). Do not commit ad-hoc JSON dumps.

- **Dev database:** Writable staging/dev copy (**`kooldb`** per PHP config). **Write probe done** (May 2026). Schema/SQL: **dev first**, scripts in repo, Steve for production.

- **Local dev (Dagh PC):** **`docs/LOCAL_DEV.md`** — Laragon at **`C:\laragon`**, site **`http://ratingskickoff.test`** (Apache **port 80**), DB **`ko2unity_db`**. **Workflow verified (May 2026):** desktop shortcut → **Start All** → site loads; **Stop All** → site stops (Apache watchdog + Avast **`SSLKEYLOGFILE`** shim — one-time **`scripts/setup_laragon_apache_fix.ps1`**, sources in **`laragon/`**). Dump in **`data/dumps/`** — import then **`python -m scripts.ladder run`** for Elo + stats; **`generalstatstable`** created by replay if absent. Optional diagnostic: **`scripts/check_local_dev.ps1`**.

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

| Rating by game number (same data, linear X; `NewRating*`) | `player_rating_history.php` | `player-rating-game-chart.js` |

| Win rate vs opponent pre-game rating (50-pt buckets) | `player_winrate_vs_opponent_rating.php` | `player-winrate-opponent-chart.js` |

| Top 20 opponents (click → H2H below) | `player_top_opponents.php` | `player-top-opponents-chart.js` |

| H2H cumulative wins vs selected opponent | `player_head_to_head.php` | `player-head-to-head-chart.js` |

| Full-career rating comparison vs opponent | `player_compare_rating_history.php` | `player-compare-rating-chart.js` |

| Opponent search (syncs via `kool-opponent-selected`) | — | `player-h2h-opponent-search.js` |



**Other profile fixes:** `individual3.php` game links → `individual1.php`; date format aligned with activity table.



---



## Reference: live post-game logic (`ratings_cpp.txt`)



Steve supplied an excerpt of the **Unity/C++** job that runs after each rated online game (Dagh’s original code). **Repo path:** `docs/ratings_cpp.txt` (reference only — **not** deployed with PHP; not executed by the website).



**Entry point:** `RatingProcedureUnity(con, idA, idB, goalsA, goalsB, gameID)` when both player ids are valid and distinct.



**Per-game flow (summary):**



1. **Load** both rows from `playertable` (`SELECT *`).

2. **Derive** from goals: `ActualScore` (1 / 0.5 / 0), `HomeWin`/`Draw`/`AwayWin`, `WinnerID` (winner’s `idA`/`idB`; **-1** on draw), `SumOfGoals`, `GoalDifference`, double-digit (≥10 goals), clean sheets (0 conceded).

3. **Elo** via `BothRated`: logistic expected score for A, `RatingAdjustment = Kfactor * (ActualScore - ExpectedScore)`, `NewRatingA/B` (zero-sum). Uses global **`Kfactor`** (value not in this excerpt).

4. **`INSERT` `ratedresults`** — pre-ratings, expected/actual, adjustments, new ratings, flags (matches `docs/ratedresults-schema.md`).

5. **Recompute both players’ `playertable` fields in memory** — W/D/L, ratios, goal extremes + `*GameID`, opponent/victim/culprit counts (some via extra `COUNT` queries on `ratedresults`), streaks, peak/lowest/recent avg rating (last **300** games by id), `Rating` = new rating, `Display=1` when `NumberGames >= 1`.

6. **`UPDATE` `playertable`** twice (player A, then B) — one wide `UPDATE` per player.

7. **Load/update `generalstatstable` id=1** — server totals and “records” (most games, longest streak, biggest peak on server, etc.); final **`UPDATE generalstatstable`**.



**Not in this excerpt:** **rating decay** (may live elsewhere; Dagh wants it removed). **`PlayerRank`**, new-player creation, unrated paths. **`Kfactor`** definition. Full production binary may include more files.



**Batch recalc implication:** replay **`ratedresults` + both `playertable` rows** in **date/id order** (ratings depend on prior state). **`generalstatstable`** is a **leaf** table — rebuild **once at end** from final `ratedresults` + `playertable` (see **`scripts/ladder/generalstats.py`**); live prod keeps C++ per-game updates after any one-shot recalc.



---



## Next (prioritised intent)



1. **Deploy cosmetics slice** — WinSCP sync `site/public_html/` → staging; hard refresh hub + ranked + server pages.

2. **Launch polish:** revert or confirm **TEST realm accent swap**; decide fate of **seven accent preview pills** on hub row (staging lab vs prod).

3. **Profile gradual improvements:** see `docs/player-profile-feast.md` (shipped layout); archived planning in `docs/archive/`.

4. **Fun stats block (v1 brainstorm):** biggest win, biggest draw, busiest month, goal-rich month, longest win streak, goal-festival game; pull from existing `playertable` + monthly SQL on `ratedresults`; playful section title (e.g. trophy cabinet). **Exclude** longest game; keep harsh rows (biggest loss) in full table only.

5. **Tone pass:** chart titles/helper copy — context, sample size, “rematch story” not report-card (see chat on inclusive analytics).

6. **Dev DB workflow:** document migration habit (`schema/` SQL files, dev → staging test → Steve prod); ladder-engine sandbox work on dev.

7. **Optional:** local `ko2unitydb_config.php` template from Steve; align laptop + staging config shapes (gitignored only).

8. **Status Phase B** — real live feed on hub default (Steve / API); bridge page until then.



---



## Recent log



| When (approx.) | What |

|----------------|------|

| 2026-05 | **Ops cleanup:** removed localhost diags (`individual1_profile_diag.php`, `server1_trends_diag.php`), theme lab (`theme-lab.html` + CSS/JS); `throwaway_ratedresults_player_indexes.php` only under **`scripts/`** (copy to `public_html` when needed, not WinSCP-synced). |
| 2026-05 | **Profile hygiene:** removed unused rival/recent/H2H SQL from `player_feast_load.php`; dropped winrate chart script from `individual1.php`; deleted orphan `peak_month_leaderboard_table.php` + `api/server_peak_month_leaderboard.php`. |
| 2026-05 | **CSS hygiene:** `k2_head.php`; deleted `main2.css`; `--k2-*` tokens for chart subtitles; neon C documented; removed unused rank-#1 table glow; `theme_boot` only in `<head>`. |
| 2026-05 | **Profile feast shipped:** `individual1.php` only; dev preview URLs (`profile_feast.php`, mock lab) deleted — not deployed for players. Maintainer doc `docs/player-profile-feast.md`; audit/framing → `docs/archive/`. Mock lab history: `b8c5a98`. |
| 2026-05 | **Cosmetics wrap-up:** segment-track + outline wing nav on ranked pages; hub accent preview pills + `realm-switch.js`; `theme_boot_head.php` sync realm/accent before paint; Games hub 7-day window (min 50 fallback), no table pager; `individual3` 100 games/page; peak-month leaderboard SSR on Trends. |
| 2026-05 | **Phase A hub nav** on production PHP — `hub_nav.php`, `status.php` bridge, wing tabs via `lb_nav.php`; theme-lab wing/segment experiments promoted to `theme.css`. |
| 2026-05 | **Theme lab:** `theme-lab.html`, `stylesheets/theme-lab.css`, `js/theme-lab.js` — interactive neon/realm/amiga-accent/display-font preview. |
| 2026-05 | **`docs/design-direction.md`** — cosmetics track: neon noir theme, realm colors, Tailwind mock-only, theme lab plan, player media notes (photos, YouTube). |
| 2026-05 | **Profile:** rating-by-game-number chart (`player-rating-game-chart.js`); `player_rating_history.php` uses `NewRatingA`/`NewRatingB` + `gameNumber`. |
| 2026-05 | **Dev DB:** dropped unused `KungFu*` columns on `playertable` (staging); `docs/playertable-schema.md` trimmed; `scripts/throwaway_drop_playertable_kungfu_columns.php`. |
| 2026-05 | **`docs/ladder-engine-plan.md`** — agreed plan: Python replay engine, dev sandbox + offline tracks, Steve runs scripts, schema vocabulary, defer website realm wiring. |
| 2026-05 | **`docs/ratings_cpp.txt`** — Steve supplied C++ post-game excerpt (`RatingProcedureUnity`); reference for live / formula. |
| 2026-05 | **`docs/ratedresults-schema.md`** — curated snapshot of per-game table `ratedresults` (from `throwaway_ratedresults_schema.php` on dev `kooldb`). |
| 2026-05 | **Writable dev DB** + updated server config (Steve). **Write probe passed** (`throwaway_db_write_probe.php`; removed from server after). |
| 2026-05 | **Ladder replay v2 + `generalstatstable`:** local migration + batch server-stats rebuild; profile/leaderboard parity verified; usage documented in **`scripts/ladder/README.md`** + MEMORY. |
| 2026-05 | **Staging one-shot replay on `kooldb`:** Steve ran `run_staging_ladder_replay.sh` — success; **`docs/STAGING_REPLAY.md`**. P3–P5 deferred. |

| 2026-05 | **Inclusive / fun profile direction** — brainstorm: welcoming copy, progressive disclosure, **fun stats** block; profile “front page” should feel active/fun, not judgment-first. |

| 2026-05 | **Chart wave 2 committed:** H2H + compare rating, top opponents, win rate vs opponent rating, server goals/cumulative established/rating distribution (empty buckets filled), peak/current on one chart, `individual3` link/date fixes. |

| 2026-05 | **Established rating distribution** — 100-pt buckets; API fills **empty buckets with 0** between min and max. |

| 2026-05 | **Staging DB:** MariaDB **10.11.7** confirmed (`throwaway_mysql_version.php`; removed after). |

| 2026-05 | **Profile/server load diagnostics:** `individual1_profile_diag.php`, `server1_trends_diag.php`; Phase A indexes on `ratedresults` (`idA`/`idB`) — big **individual1** win; **server1** slowness separate (hall of fame + chart APIs; Date index not the answer). Indexes on **staging**; prod left for Steve. |
| 2026-05 | **Profile feast polish:** career rank fix for NULL `DoubleDigits`; presence/moment dates `M j, Y`; “Last rated game”; played-days inactive cells brighter; `k2_player_feast_query` profiler (opt-in). |
| 2026-05 | **Profile mock lab cleanup:** archive redesign docs; drop preview duplicate + dead `player_feast_render_core`; delete dev preview URLs (`profile_feast.php`, mock lab). |
| 2026-05 | **Trends hall of fame:** busiest **day / month / year** trio on `server1.php`; full-width layout. |
| 2026-05 | **`bd9730a`** — first Chart.js batch (server games/active/established year, player rating + games/month). |

| 2026-05 | **`index.php`** → **302** to **`status.php`** (hub landing). WinSCP deploy loop; Git **`main`** → [ratings.kickoff2](https://github.com/DaghN/ratings.kickoff2). |

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

| **Database** | **MariaDB 10.11** on staging; dev DB **`kooldb`** (writable; probe done May 2026) |

| Local preview | **`docs/LOCAL_DEV.md`** — **`http://ratingskickoff.test`** (needs **Apache on :80**); junction **`C:\laragon\www\ratingskickoff`** |
| Local DB dump | **`data/dumps/ko2unity_db-2026-05-20.sql`** → **`ko2unity_db`**; dump omits **`generalstatstable`** — replay creates it |
| Ladder replay CLI | **`python -m scripts.ladder run`** — local/staging recalc done; record **`docs/STAGING_REPLAY.md`** |

| Throwaway probes | Under **`scripts/`** only — manual WinSCP copy to **`public_html`**, gated `?once=…`, **delete from server after** |
| `ratedresults` indexes (Phase A) | **`idx_ratedresults_idA`**, **`idx_ratedresults_idB`** — local + staging; prod via Steve when agreed |



---



## Rules of engagement (agents)



- **Authority:** **`PROJECT_BRIEF.md`** carries product intent and taste. **`docs/design-direction.md`** carries visual identity and cosmetics-track decisions. **Dagh’s latest message** wins on scope and direction. **This file** is logistics hand-off only — if it clashes with Brief, design doc, or Dagh, follow Brief + design doc + Dagh and **offer to update MEMORY** afterward.

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


