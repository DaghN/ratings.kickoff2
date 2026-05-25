# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Design / cosmetics track:** **Phase A hub shell + Status Phase B v1.2 shipped in repo** (May 2026) — `status.php` 4-col room grid, league month toggle, shared `.k2-panel-heading`, softer `--k2-text-primary` (`#d0d7de`). Spec: `docs/STATUS_PAGE_DATA.md`. **Next:** WinSCP to staging; Steve for prod `kooldb` read + joshua redirect; realm switcher when Amiga exists.

- **Charts:** **six-colour palette signed** (May 2026) — canonical tokens `pitch` / `chrome` / `holo` / `amber` / `teal` / `magenta` in `theme.css` + `chart-theme.js`; Activity on `server1.php`; profile uses pitch/chrome or `profileCompare*` helpers. No legacy green/blue/coral/purple names.

- **DB performance (May 2026):** Profile load fixed mainly via **`idx_ratedresults_idA` / `idx_ratedresults_idB`** — local + staging; **production still pending** (Steve when agreed). Heavy profiles ~**8s → ~1s** locally. **`server1.php` trends** still slow (~7s SSR hall of fame + chart APIs) — not fixed by a `Date` index; needs fewer/heavier queries or precompute later.

- **Profile feast (shipped):** production **`individual1.php`** only — **`docs/player-profile-feast.md`**. Further work = gradual copy/UX, not mock lab (`docs/archive/`).

- **Product tone:** ladder stays **truthful and data-rich**; surface **inclusive, playful, welcoming**. Profile above-the-fold = participation first; deep analytics lower (“matchup lab”).

- **Fun stats block (planned, not built):** trophy-cabinet highlights from `playertable` + monthly `ratedresults` SQL — **no longest-game** stat.

- **Operational loop:** edit locally/Git → **WinSCP** sync `site/public_html/` → staging `public_html/`; hard refresh after assets. **SSH:** permission denied for Dagh (May 2026) — Steve runs one-offs when sent.

- **Ladder replay (Python):** P0–P2 done — local `ko2unity_db` + staging `kooldb` replay; **`docs/STAGING_REPLAY.md`**, **`scripts/ladder/README.md`**. Deferred P3–P5: **`docs/ladder-engine-plan.md`**. **Prod live ratings stay C++.**

- **`ratedresults` only** for ladder/replay (~74.9k rated rows). **`resulttable`** is wider match log — external JSON on `GameID` can differ slightly; expected.

- **Local dev:** **`docs/LOCAL_DEV.md`** — `http://ratingskickoff.test`, DB `ko2unity_db`, dump `data/dumps/`, replay `scripts/run_local_replay.ps1`.

- **Change style:** small, reversible slices.

---

## Deep reference (read on demand)

| Topic | Where |
|--------|--------|
| Live post-game C++ | `docs/ratings_cpp.txt` |
| Per-game table | `docs/ratedresults-schema.md` |
| Replay / Elo sandbox | `scripts/ladder/`, `docs/replay-v1-scope-and-reset.md` |
| Profile layout / charts | `docs/player-profile-feast.md` |
| Status hub spec | `docs/STATUS_PAGE_DATA.md` |
| Prod cutover | `docs/prod-coordination.md`, `docs/coordination/` |

---

## Next (prioritised intent)

1. **Deploy cosmetics slice** — WinSCP sync `site/public_html/` → staging; hard refresh hub, ranked, server, **status** pages.
2. **Status on prod data** — Steve: prod `kooldb` read for live panels; joshua redirect when agreed (`docs/STATUS_PAGE_DATA.md`).
3. **Launch polish** — tint picker expose vs hidden (`docs/tint-vs-realm.md`); realm switcher when Amiga exists.
4. **Profile gradual improvements** — `docs/player-profile-feast.md`; archived planning in `docs/archive/`.
5. **Fun stats block (v1)** — trophy cabinet from `playertable` + monthly SQL; exclude longest game.
6. **Tone pass** — chart/helper copy: context, sample size, rematch story not report-card.
7. **Prod coordination** — when stored truth changes: `docs/prod-coordination.md`, registers, `schema/migrations/`.
8. **Optional** — local `ko2unitydb_config.php` template from Steve (gitignored).

---

## Recent log

*(Newest first. Prune older rows when adding.)*

| When | What |
|------|------|
| 2026-05 | **Agent bootstrap order cleanup** — `README.md` and `AGENTS.md` now mirror the auto-attached Cursor rule order: MEMORY → AGENTS → PROJECT_MAP, reducing small cold-start wording drift. |
| 2026-05 | **Sortable header help tooltips** — `k2-table.js` now uses a styled shared tooltip for sortable headers, combining abbreviation/activity/player-table explanations with the “Click to sort.” hint, including server-side Games history sort links. |
| 2026-05 | **Realm switch flash fix** — header toggle initial paint now follows early `html[data-realm]` boot state, so Amiga no longer flashes Online during main-nav page loads. |
| 2026-05 | **Leaderboard nav/table polish** — active `lb_nav.php` filter dots use the selected UI accent while labels stay neutral; `ranked-pages-table` first column now has a stable width across non-Activity leaderboard tabs. |
| 2026-05 | **Activity Longevity table** — `ranked8.php` Period headings now read “Most games in one day/month/year”; All time shows Longevity beside “Most games of all time” with natural-width table spacing. |
| 2026-05 | **Records two-panel split** — `server2.php` splits records into natural-width Peak activity and Peak performance panels; activity includes year/month/day games rows, opponent/victim totals, and New/Legendary age markers (`Legendary` in holo). |
| 2026-05 | **`k2-table.js` reverse-sort tie fix** — same-column sort toggles now reverse current row order, so tied groups in `ranked8.php` flip correctly when Games changes from desc to asc. |
| 2026-05 | **Activity Period/All time toggle** — `ranked8.php` now shows a realm-style in-place switch: Period defaults to the three day/month/year tables, All time reveals the preloaded all-time table. |
| 2026-05 | **Nav wording swap** — leaderboard `ranked8.php` tab label renamed from Hall of Fame to Activity; main hub `server2.php` nav label renamed from Records to Hall of Fame. |
| 2026-05 | **Hall of Fame all-time table** — `ranked8.php` now shows a fourth “Busiest of all time” table using `playertable.NumberGames` plus each player’s first rated-game date as “Since”. |
| 2026-05 | **Player games adjustment sign** — positive `individual3.php` adjustment values now show an explicit `+` in the blue adjustment cell via `k2_player_game_row.php`. |
| 2026-05 | **`individual3.php` Phase 7B shipped** — player-games row rendering extracted to `includes/k2_player_game_row.php`, preserving watched-player perspective and setting up optional AJAX reuse. |
| 2026-05 | **`individual3.php` Phase 7A shipped** — Games history now uses auto-submit server-side Result/Opponent filters, URL sort links, 100-row slices, and lightweight Previous/Next links; `elolist.js` removed from the page. |
| 2026-05 | **Hall of Fame aggregate read path** — `ranked8.php` busiest day/month/year now prefers `player_period_games` via `peak_month_leaderboard_query.php`, falling back to `ratedresults` until staging/prod aggregate rollout catches up. |
| 2026-05 | **`individual3.php` Phase 7 plan** — agreed path: 7A server-side URL filters/sort/limit, 7B shared renderer, 7C optional AJAX enhancement; no hidden sort persistence. |
| 2026-05 | **Player nav wording** — player pill label `Wins` renamed to `W/D/L`; route/key remains `wins` / `individual2a.php`. |
| 2026-05 | **Period activity aggregate prep** — `player_period_games` schema/backfill/query switch done locally; `dev-period-activity.php` previews tables; staging/prod handoff + PG-005 docs ready for Steve review. |
| 2026-05 | **Player stat table `k2-table.js` migration** — `individual2a/b/c.php` now use opt-in table sorting with Games highlighted by default. |
| 2026-05 | **Simple leaderboard `k2-table.js` migration** — `ranked1`–`ranked5`, `ranked7`, and `ranked8` now use opt-in table sorting with tab-specific defaults (Peak/ELO/GF/DD/LWS/Victims/Games). |
| 2026-05 | **Agent local CLI preload** — `.cursor/rules/kool-workspace.mdc` now tells agents Laragon PHP/MySQL may not be on PATH and gives explicit `php.exe` / `mysql.exe` paths. |
| 2026-05 | **Profile Games tab perspective fix** — `individual3.php` now keeps a stable `$playerId`, uses prepared/named `ratedresults` columns, and renders W/L, F/A, ratings, ES, and adjustment from the watched player’s perspective; tint picker no longer clobbers caller `$id`. |
| 2026-05 | **`k2-table.js` Phase 4 pilot** — `ranked7.php` now uses opt-in `data-k2-table` sorting + autorank instead of `elolist.js`; default SQL sort lights ELO rating on load; other ranked/player tables stay legacy. |
| 2026-05 | **Agent git habit** — `.cursor/rules/kool-workspace.mdc` now tells agents to use PowerShell-safe git commit/push commands, not Bash heredocs/`&&`. |
| 2026-05 | **Games tab 7-day buckets** — `server3.php` now renders Today/Yesterday/day-6 static tables from shared rated-game rows; `elolist.js` removed from Games tab. |
| 2026-05 | **Records page refactor** — `server2.php` now uses page-local row/date helpers and explicit `generalstatstable` columns; no shared `h()`/`player_link()` added. |
| 2026-05 | **Activity monthly chart grids** — graphs 1, 3, and 4 on `server1.php` use softer grid lines for narrow bar readability. |
| 2026-05 | **Records label styling** — removed dummy links from `server2.php` left-column record labels; player links remain. |
| 2026-05 | **Records opponent/victim labels** — shortened `server2.php` rows to “Most opponents” and “Most victims”. |
| 2026-05 | **Records footnote spacing** — removed extra break between `server2.php` table and explanatory text. |
| 2026-05 | **Records table order/copy** — `server2.php` rows reordered into Dagh’s groups, sentence-case labels, average-opponent-rating row removed. |
| 2026-05 | **Records footnote accent** — `server2.php` footnote wraps `(New!)` in `.blue` to match table record markers. |
| 2026-05 | **Server records (`server2.php`)** — removed **Biggest Rating Ascent** row (profile audit drop; peak covered by Highest Peak Rating). |
| 2026-05 | **Cumulative established players chart** — line extends flat through today via `chart-date-range.js`. |
| 2026-05 | **Established players per year chart** — API includes current calendar year with count 0 when no one established yet. |
| 2026-05 | **Server chart hints trim** — removed hint under games-per-year and established rating distribution (graph 7). |
| 2026-05 | **Server games per year chart copy** — removed block hint under heading; chrome legend label → “Projected”. |
| 2026-05 | **Chart colour naming cleanup** — removed JS aliases green/blue/coral/purple; CSS `--k2-chart-pitch` / `--k2-chart-chrome`; three commits on `main`. |
| 2026-05 | **Activity chart palette shipped** — six inks (B1 winner): amber goals, magenta established + dist, holo cumulative; lab deleted; `design-direction.md` + `chart-theme.js` refactor. |
| 2026-05 | **Status leaderboard panel copy** — “Full ladder” → “Full leaderboard” link in `status_room_section.php`. |
| 2026-05 | **Agent doc hygiene:** MEMORY trim, root **README**, **AGENTS** / **UPDATE_DOCS** cross-links; stale Status Phase B Next removed. |
| 2026-05 | **SCH-003** — DROP 28 ratio leader cols on `generalstatstable` (local); Records from `playertable`; PG-004 Steve = migration 002 + drop C++ ratio writes. |
| 2026-05 | **Records `(New!)` window** — `server2.php` uses calendar one month (`strtotime('-1 month')`), footnote updated (was 48 hours). |
| 2026-05 | **Status Phase B v1.2 in repo** — 4-col rooms, league month toggle, panel headings; spec `docs/STATUS_PAGE_DATA.md`. Deploy + prod DB still open. |
| 2026-05 | **Agent rituals** — `AGENTS.md`, `docs/PROJECT_MAP.md`, `docs/UPDATE_DOCS.md`, `feature-log`, `.cursor/rules/kool-workspace.mdc`. |
| 2026-05 | **Steve post-game handoff** — C++ snippet packs: `docs/coordination/post-game-cpp-handoff.md`, `cpp-snippets/`. |
| 2026-05 | **Prod coordination track** — `docs/prod-coordination.md`, registers, `schema/migrations/`, staging no live writes. |
| 2026-05 | **Staging ladder replay** on `kooldb` — `docs/STAGING_REPLAY.md`; P3–P5 deferred. |
| 2026-05 | **Profile feast shipped** — `individual1.php` only; `docs/player-profile-feast.md`; mock lab removed. |
| 2026-05 | **`ratedresults` player indexes** — local + staging; prod pending Steve; big `individual1` win. |
| 2026-05 | **Phase A hub + cosmetics** — `hub_nav.php`, wing tabs, theme tokens, `docs/design-direction.md`. |
| 2026-05 | **Chart wave 2** — H2H, compare rating, top opponents, win rate vs opp rating, server distribution charts. |
| 2026-05 | **`index.php` → `status.php`** hub landing; Git `main` on GitHub. |
| 2026-05 | **Ladder replay v2 + `generalstatstable`** — `scripts/ladder/README.md`. |

---

## Deferred / blocked

- GitHub branch protection — when collaborators land.
- **Amiga/offline** datasets — after dev migration workflow is routine.
- **Pretty URLs** (`/online/{id}`) — needs Steve (`.htaccess` / vhost).

---

## Quick facts

| Item | Value |
|------|--------|
| GitHub | https://github.com/DaghN/ratings.kickoff2 · branch `main` |
| Staging SFTP | `ratings.kickoff2.com:5322` · user `dagh@ratings.kickoff2.com` |
| Deploy | WinSCP **Synchronize** `site/public_html/` → remote `public_html/` |
| Legacy reference | https://joshua.kickoff2.net/ratings/ |
| Local site | `http://ratingskickoff.test` — **`docs/LOCAL_DEV.md`** |
| Staging DB | MariaDB 10.11 · `kooldb` writable · **no live game writes** |
| Local DB | `ko2unity_db` · dump `data/dumps/` · replay `scripts/run_local_replay.ps1` |
| Ops cheatsheet | **`docs/OPERATIONS_QUICK_START.md`** |
| Prod coordination | **`docs/prod-coordination.md`** · **`docs/coordination/`** |
| Config | `site/config/ko2unitydb_config.php` — **never commit** |
| Throwaway probes | **`scripts/`** only — copy to `public_html` manually, delete from server after |
| `ratedresults` indexes | `idx_ratedresults_idA`, `idx_ratedresults_idB` — local + staging; prod via Steve |

**Agent hygiene:** one Recent log line per shipped slice; never commit secrets; use `js/` not `javascript/` on server paths.
