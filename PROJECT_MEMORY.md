# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Design / cosmetics track:** **Phase A hub shell + Status Phase B v1.2 shipped in repo** (May 2026) — `status.php` 4-col room grid, league month toggle, shared `.k2-panel-heading`, softer `--k2-text-primary` (`#d0d7de`). Spec: `docs/STATUS_PAGE_DATA.md`. **Next:** WinSCP to staging; Steve for prod DB read + joshua redirect; realm switcher when Amiga exists.

- **Charts:** **six-colour palette signed** (May 2026) — canonical tokens `pitch` / `chrome` / `holo` / `amber` / `teal` / `magenta` in `theme.css` + `chart-theme.js`; Activity on `server1.php`; profile uses pitch/chrome or `profileCompare*` helpers. No legacy green/blue/coral/purple names.

- **DB performance (May 2026):** Profile load fixed mainly via **`idx_ratedresults_idA` / `idx_ratedresults_idB`** — local + staging; **production still pending** (Steve when agreed). Heavy profiles ~**8s → ~1s** locally. **Status page local + staging DB fixed** via `idx_ratedresults_date`, `idx_resulttable_live_status`, and `player_monthly_league`; local HTTP ~8.5s → ~0.28s, staging SQL verified by Steve (2,674 monthly rows / 149,740 appearances). **`server1.php` shell is fast locally** (~40–120ms HTML); remaining Activity cost is async chart APIs (~2.8s concurrent / ~7s sequential), mainly established-player and active-player aggregates.

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
2. **Status on prod data** — Steve: prod DB read for live panels; joshua redirect when agreed (`docs/STATUS_PAGE_DATA.md`).
3. **Launch polish** — realm switcher when Amiga exists.
4. **Profile gradual improvements** — `docs/player-profile-feast.md`; archived planning in `docs/archive/`.
5. **Fun stats block (v1)** — trophy cabinet from `playertable` + monthly SQL; exclude longest game.
6. **Prod coordination** — when stored truth changes: `docs/prod-coordination.md`, registers, `schema/migrations/`.
7. **Optional** — local `ko2unitydb_config.php` template from Steve (gitignored).

---

## Recent log

*(Newest first. Keep this to the latest high-signal handoff rows; older phase detail lives in feature docs / git.)*

| When | What |
|------|------|
| 2026-05 | **Status online presence fix** — Online now uses nonzero `IsOnline` directly, without the `Display = 1` ladder/public-stats gate, and `status.php` sends no-cache headers so frozen lobby presence is not hidden by stale pages. |
| 2026-05 | **Status recent games simplified** — recent games on `status.php` now show player names and score only; rating adjustment deltas were removed from that compact lane. |
| 2026-05 | **Status column balance tweak** — `status.php` room grid now runs ticker/new players, online/logins, live/recent games, then a strengthened art/leaderboard lane, with the first column slightly widened. |
| 2026-05 | **Leaderboard filter docs cleanup** — stale open/todo references removed from hub/status docs; current Leaderboard filters are treated as shipped, not next-step experiments. |
| 2026-05 | **Persistent tint preference** — tint picker now stores `k2-accent-tune` in `localStorage`, migrates old session-only values, boots before first paint, and syncs across open tabs. |
| 2026-05 | **Status realm header lab** — `status-realm-lab.php` compares two mock shells on real Status content: A realm beside wordmark, B realm strip above hub nav; shared header remains unchanged. |
| 2026-05 | **Status performance staging DB done** — Steve ran SCH-005 + REP-004 on staging `kooldb`; indexes exist and `player_monthly_league` check passed (`SUM(played)` 149,740 = `ratedresults` × 2). Monthly row count differs from local (2,674 vs 2,679), which is OK; appearances are the invariant. |
| 2026-05 | **`elolist.css` cleanup** — legacy stylesheet removed from shared head; ranked table cloak now lives in `theme.css`; K2 table plan open-work item closed. |
| 2026-05 | **Hub nav preview scaffolding pruned** — removed `nav-preview.php`, `?k2_hub_nav`/session style overrides, and solid/soft CSS branches; segment nav is now the fixed contract. |
| 2026-05 | **Tint picker docs settled** — hidden-by-default behavior remains current; stale launch-decision wording pruned. |
| 2026-05 | **Chart helper tone audit** — stale chart/helper tone backlog pruned; current chart contract/copy already covers canonical colours, context, sample-size, and matchup framing. |
| 2026-05 | **K2 table plan cleanup** — stale open-work entries pruned; remaining follow-ups now reflect only active table work. |
| 2026-05 | **Status page performance fix** — local schema `004_status_performance_and_monthly_league.sql` adds `ratedresults.Date` + live `resulttable` indexes and `player_monthly_league`; Status monthly league now prefers the aggregate with raw SQL fallback. Loader ~6.6s → ~51ms; local HTTP ~8.5s → ~0.28s. |
| 2026-05 | **Current-truth docs prune** — MEMORY recent log trimmed; `design-direction.md`, `hub-ia-agreement.md`, and `k2-table-and-games-plan.md` now foreground current contracts/open work instead of phase diary history. |
| 2026-05 | **Replay/ops safety gates** — ladder replay now has explicit `--target local|staging`, refuses staging `kooldb` unless target is explicit, logs DB identity preflight, staging wrapper passes `--target staging`; local schema/index/period rebuild wrappers refuse non-local DBs without `-AllowNonLocal`. |
| 2026-05 | **Period activity staging DB done** — Steve ran `player_period_games` schema + rebuild on staging `kooldb`; expectation test passed; note MariaDB requires `COUNT(*)`, not `COUNT()`. |
| 2026-05 | **Legacy PHP safety pass** — added `includes/k2_safety.php`; `individual2a/b/c.php` now validate player `id`, use safe DB connect/query errors, and escape opponent links; `ranked1`–`ranked5`/`ranked7` use the same helper for DB connect/query errors and escaped player links. |
| 2026-05 | **Sortable header help tooltips** — `k2-table.js` now uses a styled shared tooltip for sortable headers, combining abbreviation/activity/player-table explanations with the “Click to sort.” hint, including server-side Games history sort links. |
| 2026-05 | **Realm switch flash fix** — header toggle initial paint now follows early `html[data-realm]` boot state, so Amiga no longer flashes Online during main-nav page loads. |
| 2026-05 | **Leaderboard/player table modernization** — `ranked1`–`ranked5`, `ranked7`, `ranked8`, and `individual2a/b/c` use opt-in `k2-table.js`; profile Games uses server-side Result/Opponent filters, URL sort links, 100-row slices, and shared row rendering. |
| 2026-05 | **Activity / Hall of Fame / Records polish** — `ranked8.php` period/all-time activity tables, `server2.php` two-panel Hall of Fame split, peak-period aggregate fallback, and natural-width table polish are in repo. |
| 2026-05 | **Games tab 7-day buckets** — `server3.php` renders Today/Yesterday/day-6 static tables from shared rated-game rows; `game.php` and Games tab share `includes/k2_rated_game_row.php`. |
| 2026-05 | **Status Phase B v1.2 in repo** — `status.php` has 4-col room grid, active leaderboard, monthly league toggle, recent logins/registrations/games; prod DB read + joshua redirect still open. |
| 2026-05 | **Profile feast shipped** — production `individual1.php` feast layout only; mock lab removed; further profile work should be gradual copy/UX. |
| 2026-05 | **Core migration/prod coordination set up** — `prod-coordination.md`, registers, schema migrations, staging replay docs, and C++ snippet handoff pattern exist; prod live ratings still C++. |
| 2026-05 | **Chart/theme foundation shipped** — six-ink chart palette, dark theme tokens, shared header/nav/wing tabs, and `status.php` hub landing are in repo. |

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
