# PROJECT_MEMORY — running context for agents

**Who reads this:** Primarily Cursor agents between sessions. Keep it **short and current**; trim or archive stale bullets rather than appending forever.

**Authority (when documents disagree):** `PROJECT_BRIEF.md` defines purpose and taste. **`docs/design-direction.md`** governs visual identity and cosmetics-track work. **Dagh’s latest message in chat wins** on scope and direction. This file records **logistics, recent work, and near-term intent** — not a second brief. Rituals and agent rules: **`AGENTS.md`**.

---

## Current focus

- **Milestones project:** **Staging DB done** May 2026. Catalog **112** — `play_streak_100` (0 holders) + **`year_in_heaven`** (**5** holders, verified on `kooldb`). **Next:** prod schema+REP+C++ M1–M7; hub Home [`docs/milestones-hub-ia.md`](docs/milestones-hub-ia.md). **`club_*`:** contract = **`Rating`** unlock; no prod C++ writer today; rebuild OK on legacy data — drop `PeakRating` join when career peak-at-20 replay ships ([`post-game-cutover-checklist.md`](docs/coordination/post-game-cutover-checklist.md)).

- **Rated play streaks:** **Staging DB + UI done** May 2026 (SCH-014, REP-015; `ranked4` Days/Weeks, HoF `server2`). **Next:** prod schema + C++ post-game; profile surface TBD.

- **Leagues integration:** Awards DB + **League honours v1** on **local + staging** (`ranked9.php`, SCH-009/010, REP-012/013 verified May 2026). **Next:** profile league block; prod schema/REP when cutover; daily finalize (PER-003) only if/when wanted.
- **Status Leagues Phase 1:** shipped in repo (nav, single table, prewarm, lock-step floor). **Phase 1.5** optional polish — [`docs/status-period-competitions-wip.md`](docs/status-period-competitions-wip.md) (day games list deferred).
- **Design / cosmetics track:** Phase A hub shell + Status Phase B v1.2 room grid; `docs/STATUS_PAGE_DATA.md`. Steve for prod DB read + joshua redirect. **Realm switcher** markup kept in header; **hidden in CSS** until Amiga ships.

- **Charts:** **six-colour palette signed** (May 2026) — canonical tokens `pitch` / `chrome` / `holo` / `amber` / `teal` / `magenta` in `theme.css` + `chart-theme.js`; Activity on `server1.php`; profile uses pitch/chrome or `profileCompare*` helpers. No legacy green/blue/coral/purple names.

- **DB performance (May 2026):** Profile load fixed mainly via **`idx_ratedresults_idA` / `idx_ratedresults_idB`** — local + staging; **production still pending** (Steve when agreed). Heavy profiles ~**8s → ~1s** locally. **Status page local + staging DB fixed** via `idx_ratedresults_date`, `idx_resulttable_live_status`, and `player_monthly_league`; local HTTP ~8.5s → ~0.28s, staging SQL verified by Steve (2,674 monthly rows / 149,740 appearances). **`server1.php` shell is fast locally** (~40–120ms HTML); remaining Activity cost is async chart APIs (~2.8s concurrent / ~7s sequential), mainly established-player and active-player aggregates.

- **Profile feast (shipped):** production **`individual1.php`** only — **`docs/player-profile-feast.md`**. Further work = gradual copy/UX, not mock lab (`docs/archive/`).

- **Hub IA (May 2026):** Status · Activity · Leaderboards · **Milestones** · HoF — **Games** off hub (`server3.php` via Status). See [`docs/hub-ia-agreement.md`](docs/hub-ia-agreement.md).
- **Product tone:** ladder stays **truthful and data-rich**; surface **inclusive, playful, welcoming**. Profile above-the-fold = participation first; deep analytics lower (“matchup lab”).

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
3. **Launch polish** — unhide realm switcher when Amiga realm ships (`theme.css` + `site_header.php`).
4. **Profile gradual improvements** — `docs/player-profile-feast.md`; archived planning in `docs/archive/`.
5. **Prod coordination** — when stored truth changes: `docs/prod-coordination.md`, registers, `schema/migrations/`. **Staging:** SCH-008 + REP-007–011 **done** May 2026; prod cutover + contract post-game still pending Steve.
6. **Optional** — local `ko2unitydb_config.php` template from Steve (gitignored).

---

## Recent log

*(Newest first. Keep this to the latest high-signal handoff rows; older phase detail lives in feature docs / git.)*

| When | What |
|------|------|
| 2026-05 | **LB header tooltips** — `lb_column_help.php`; hub wings: no Elo label-echo; Games/abbrev/formulas; ranked5 inverse-record tie rule in tooltips; footer legend removed; Peak/Nadir 20-game copy. |
| 2026-05 | **Post-game cutover index** — [`post-game-cutover-checklist.md`](docs/coordination/post-game-cutover-checklist.md): peak-at-20, `club_*` on `Rating`, pointers `>`, HoF, replay ritual; contract § Rating club implementation notes (investigation closed). |
| 2026-05 | **Leagues picker slot** — fixed row width = max(day, week, month, year) pickers; tab switch stable. |
| 2026-05 | **Day picker label** — `May 27, 2026` (no weekday); meta ticker unchanged. |
| 2026-05 | **Listbox fixed width** — measure longest option label; lock trigger width (Leagues + Flatpickr + Daily). |
| 2026-05 | **Leagues labels/meta** — full month names; meta `League` (plain) + blue label; listbox open state without accent ring on scroll. |
| 2026-05 | **Listbox typography** — archive/Flatpickr pickers: secondary + weight 500; subtle hover mix (not full primary); design-direction row. |
| 2026-05 | **Leagues day picker chrome** — Daily trigger matches archive listbox (date label + chevron, no calendar icon); opens Flatpickr on click. |
| 2026-05 | **Leagues picker visibility** — one picker per tab via `data-active-period` CSS + formatted date on Daily; fixes week picker stuck between arrows. |
| 2026-05 | **Flatpickr listbox fix** — month/year dead clicks: capture `stopPropagation` on `click` blocked trigger handler; shield uses mousedown capture only; day grid `pointer-events` layering. |
| 2026-05 | **Flatpickr listbox** — re-init month/year on calendar open if DOM replaced; archive pickers close when switching to Day. |
| 2026-05 | **KOOL listbox pickers** — Leagues week/month/year + Flatpickr month/year use `k2-archive-listbox` (accent hover); native `<select>` removed from those controls. |
| 2026-05 | **Career peak/nadir contract** — `website-data-contract.md`: unset until 20 games; establish both from post-game `Rating` at game 20; max/min every game after; no gain/loss gate; full replay on cutover. `club_*` milestones = **`Rating`** (provisional OK); rebuild/C++ alignment **TBD**. |
| 2026-05 | **Playertable tie policy in contract** — `website-data-contract.md`: personal pointers + inverse BL/BW/MGC/MGS need `>` (not `>=`); HoF handoff separate; `ranked5` tooltips/footer called out at cutover. |
| 2026-05 | **Streaks LB headers** — `ranked4.php`: Wins, Undefeated, Draws, Decided, Losses, Win drought, Days, Weeks (no “streak” in labels). |
| 2026-05 | **Hub LB padding reset** — all `ranked-pages-table` wings: uniform 8px in CSS; stripped `k2-table-cell--pad-left-*` from ranked1–7,10 + league honours (Goals/DD/CS already clean). |
| 2026-05 | **LB column padding** — removed legacy `k2-table-cell--pad-left-*` on Goals + DD/CS wings (`ranked2`/`ranked3`); was widening cols after full-word headers. |
| 2026-05 | **DD/CS LB headers** — `ranked3.php`: Double Digits, Clean Sheets, DD conceded, CS conceded; ratio cols still abbreviated; footer removed. |
| 2026-05 | **k2-table sort toggle** — same-column click re-sorts asc/desc (was DOM reverse only); fixes second click not reaching true descending order. |
| 2026-05 | **Goals LB headers** — `ranked2.php`: Scored/Conceded, Most Scored/Most Conceded, Draw/Goal sum, Win/Loss margin; footer trimmed. |
| 2026-05 | **LB filter toggles keep sort** — `k2_sort`/`k2_dir` on inactive/provisional toggle hrefs; `k2-table.js` syncs URL + filter links on column sort (same wing only; wing tabs unchanged). |
| 2026-05 | **Established = 20 games aligned** — `K2_ESTABLISHED_MIN_GAMES` in `lb_player_filters.php`; HoF ratio leaders + footer 20 (was 30); HoF LB links add `provisional=0`. C++ ratio blocks still documented as legacy 30. |
| 2026-05 | **HoF context links** — values → LB wings + `k2_sort`; `provisional=0` only on ratio/average rows; activity peaks → `ranked8#…`. |
| 2026-05 | **Milestone catalog copy pass** — 26 keys (`display_name`/`rule_short`); seed + `milestone_catalog_copy_patches.json` + `apply_milestone_catalog_copy_patch.py`; staging `patch_milestone_catalog_copy.php`; local DB applied. |
| 2026-05 | **Six-hour tint schedule** — local slots; manual pill lasts **current period only** (`k2-accent-manual-period`); `k2-tint-schedule.js` + boot; optional UTC via `k2-accent-clock`. |
| 2026-05 | **Realm switcher hidden** — Online/Amiga toggle not shown in production header; markup + `realm-switch.js` kept; `status-realm-lab.php` unchanged. |
| 2026-05 | **Play & Setup hub tab** — `join.php` in `hub_nav.php` (2nd tab); header utility link removed; spec [`docs/join-play-setup.md`](docs/join-play-setup.md), [`hub-ia-agreement.md`](docs/hub-ia-agreement.md). |
| 2026-05 | **`k2-link-star` links** — default hover/focus underline site-wide; `k2_player_link()` emits class; Elo stays `<span class="k2-link-star">`. |
| 2026-05 | **Color primitives** — `--k2-pure-*` + pointer chain documented in `design-direction.md` § Color System; code in `theme.css` / `chart-theme.js`. |
| 2026-05 | **Milestones garden order** — Legendary: **`year_in_heaven`** after **`merchant_trade_fair`**; **`play_streak_100`** before **`club_10000`** (10K last). |
| 2026-05 | **Peer pill carry-scroll** — hub / `lb_nav` / `player_nav` keep `window.scrollY` on pill navigation; same active pill click does not reload (`preventDefault`); short pages extend min-height; other links unchanged. |
| 2026-05 | **Hall of Fame layout** — Peak performance panel: spacer row after Best goal ratio (before frequency rows). |
| 2026-05 | **`year_in_heaven` staging verified** — catalog **112**, **5** unlock rows on `kooldb` (2018–2025; geo4444=344/2021); establishing game on completing week; profile Played weeks + add-one playbook local-verify note. |
| 2026-05 | **Milestone `year_in_heaven` shipped** — 52 UTC weeks/calendar year; `gen_milestone_year_in_heaven_sql.py` + PHP post-game; handoff [`milestones-year-in-heaven-handoff.md`](docs/coordination/milestones-year-in-heaven-handoff.md). |
| 2026-05 | **Profile Played weeks** — career map (52 UTC week tiles/year from **first rated game**); `player_calendar_weeks.php` + `player-calendar-weeks.js`; tooltips show range + `games` from `player_period_games`. |
| 2026-05 | **Profile Personal bests** — busiest day/month/year read `player_peak_period_games` (one query) instead of three `ratedresults` GROUP BY scans; matches ranked8 peak cache. |
| 2026-05 | **`play_streak_100` catalog on staging** — `milestone_definitions` **111** rows; key verified (**100 days**, UTC-day rule); 0 holders until someone hits 100-day streak. |
| 2026-05 | **Milestones catalog total** — garden + hero read `k2_milestone_catalog_total()` from DB (111); `play_streak_100` after `merchant_trade_fair` in legendary garden order. |
| 2026-05 | **Milestone `play_streak_100` (100 days)** — first post-v0 catalog add; `rule_short` in `milestone_definitions`; rebuild SQL + post-game on day streak 100; playbook [`docs/coordination/milestones-add-one-playbook.md`](docs/coordination/milestones-add-one-playbook.md); 0 holders on current import (max day streak 87). |
| 2026-05 | **Rated play streaks — staging verified** — Steve SCH-014 + REP-015 on `kooldb` (max day **87**, week **126**; HoF 582/344); UI **ranked4** Days/Weeks + **server2** Most days/weeks in a row; registers + handoff updated; prod C++ post-game pending. |
| 2026-05 | **Profile hero milestones typography** — milestone row matches 20px stat values; shared value min-height + baseline alignment so numbers rest on same floor as rank/rating/games. |
| 2026-05 | **Profile hero — milestones, no peak** — `player_hero.php`: rank · rating · games · milestones (`{n}/110` + tier dots); peak removed from hero (still on `ranked1` + rating charts); glance strip removed; all player tabs via `player_hero_vars.php`. |
| 2026-05 | **`diversity_merchant` staging verified** — REP-008b: **25** holders, **6615** total rows, tier `key`/`amber`; matches local; [`milestones-staging-diversity-merchant-fix.md`](docs/coordination/milestones-staging-diversity-merchant-fix.md). |
| 2026-05 | **Milestones staging DB (wave 1)** — Steve REP-008/014: 110 keys, full rebuild + giant_slayer=31; superseded row count 6658 → **6615** after diversity fix. |
| 2026-05 | **Milestones staging — MariaDB period SQL fix** — removed `LATERAL` from `player_milestones_rebuild_period.sql`; staging bootstrap runs statements individually; Steve re-upload + re-run REP-008. |
| 2026-05 | **Milestones staging cutover packet** — [`docs/coordination/milestones-staging-cutover-packet.md`](docs/coordination/milestones-staging-cutover-packet.md): WinSCP manifest, Steve commands, expected counts (incl. **giant_slayer = 31**), staging PHP rebuild scripts. |
| 2026-05 | **Milestones post-game contract** — `website-data-contract.md` § full write rules (game/league/lobby), M1–M7 Steve phases. |
| 2026-05 | **Milestones v0 sanity** — `milestone_v0_sanity_check.py` passed (PHP helpers = SQL; browser spot-check). |
| 2026-05 | **Hub IA — Milestones tab + Games off hub** — `hub_nav.php`: Status · Activity · Leaderboards · **Milestones** · HoF; `milestones.php` stub; `server3.php` via Status only; WIP [`docs/milestones-hub-ia.md`](docs/milestones-hub-ia.md). |
| 2026-05 | **Leaderboards wing order (scenario A)** — `lb_nav.php`: classic block unchanged (Rating→Victims), then League honours · Milestones · Activity peaks, **Peak rating** last (`ranked1`); hub default still `ranked7.php`. |
| 2026-05 | **Milestones LB polish** — `ranked10.php` + League honours: **ELO rating** header + `k2-table-cell--pad-left-sm` on Games (matches classic ranked tables). |
| 2026-05 | **`giant_slayer` rule fix** — active #1 (365d rolling UTC); `milestone_giant_slayer.py` + chrono regen; surgical `player_milestones_rebuild_giant_slayer.sql`; contract post-game §; holders 22→31 (geo4444 unlocks). |
| 2026-05 | **Milestones Phase 4 v0** — garden page, profile `{n}/110` glance + tier dots, `ranked10.php` meta-leaderboard, `server2.php` DD Merchant achiever trial; read-only on local DB. |
| 2026-05 | **Games hub (`server3.php`)** — day headings for days older than yesterday show weekday + date (`Monday · May 26, 2026`); Today/Yesterday unchanged. |
| 2026-05 | **Milestones rebuild complete** — `gen_milestone_tail_sql.py` (30 playertable/matchup keys); **110/110** in `player_milestones`, 0 null `source_kind`; tail parity 0 mismatches. |
| 2026-05 | **Milestones chrono wave** — `gen_milestone_chrono_sql.py` → 16 keys; `milestone_chrono_parity.py` 0 mismatches. |
| 2026-05 | **Milestones period wave** — `player_milestones_rebuild_period.sql` wired (5 keys); **64/110**; `milestone_period_parity.py` 0 mismatches. |
| 2026-05 | **Milestones streaks wave** — `gen_milestone_streak_sql.py`; 8 streak keys; `milestone_streak_parity.py`. |
| 2026-05 | **Milestones wave 2** — peak `club_*`, `club_10000`, 18 exists feats; exists parity script. |
| 2026-05 | **Milestones wave 2a** — `debut`, `persistence`, `club_500` (Nth game + `source_game_id`). |
| 2026-05 | **`entered_arena` locked** — register = lobby → `JoinDate`, `source_kind=lobby`; distinct from `debut`. SCH-013. |
| 2026-05 | **Milestones source pointers** — SCH-012 on `player_milestones` (`source_kind`, game id or league period); wave 1 rebuild populates 22 league/game key types. |
| 2026-05 | **Milestones Phase 3 kickoff** — SCH-011 `milestone_definitions`, seed loader, facilitation doc, league wave in `player_milestones_rebuild.sql`; rebuild order = milestones after REP-012. |
| 2026-05 | **Leaderboards wing tab** — `ranked8` sub-nav label **Activity peaks** (was Activity). |
| 2026-05 | **League honours grain persistence** — Activity ↔ Points tab links keep current day/week/month/year (`league_honours_panel.php`). |
| 2026-05 | **Milestones Phase 2 trim** — cut `period_champion`, `six_goal_draw`; `persistence` = 10 games; `milestones_definitions_seed.json` export (`--export-seed`). |
| 2026-05 | **Milestones league names locked** — last 12 placeholders named in `milestones_curated_meta.json` (Burned the day, Honour board, Almost the headline, siege/ledger/monthly/yearly set, Cupboard filling up). |
| 2026-05 | **Milestones naming pass** — display names + Name Q (1–5) in `data/milestones_curated_meta.json`; curated doc regen adds weak-name index; `clean_sheet_merchant` → `clean_sheet_artist`. |
| 2026-05 | **`milestones-tier-curated.md`** — authoritative four-band snapshot (auto-regen from probe); cut nemesis, elite_customer, podium_month, still_here, monthly activity winner. |
| 2026-05 | **Milestones want/maybe by theme** — [`docs/milestones-want-maybe-by-theme.md`](docs/milestones-want-maybe-by-theme.md): ~112 deduped items in 22 thematic groups (A–V) for manual tier assignment; no tiers assigned. |
| 2026-05 | **Milestones tier plan** — [`docs/milestones-product-spec.md`](docs/milestones-product-spec.md): four color bands (garden + story + leaderboard tie-break); Key = amber ~15–20 completeness set (same as achiever lists); plan not locked. [`milestones-project.md`](docs/milestones-project.md) updated. |
| 2026-05 | **Status Leagues toolbar** — period segment left, ←/picker/→ centered in remaining row width; nav nowrap; wraps to full-width centered row via `is-period-nav-stacked` + ResizeObserver (`theme.css`, `status-period-competitions.js`). |
| 2026-05 | **League awards on staging** — Steve SCH-009/010 + REP-012/013 on `kooldb` (7424 instances, 21873 awards; matches local); Dagh confirmed League honours + Status leagues UI parity. |
| 2026-05 | **League period pills** — segment labels Daily / Weekly / Monthly / Year (`k2_status_period_segment_label`); panel titles use “Year league” not Yearly. |
| 2026-05 | **`player_league_slice_totals` (SCH-010)** — per-player gold/silver/bronze by league_kind × period_type; REP-013 rebuild; League honours + `k2_league_player_slice_totals()` for profile. |
| 2026-05 | **League honours views** — `ranked9.php` pills Overall / Activity / Points + Day–Year; URL `cup` & `grain`. |
| 2026-05 | **League honours v1** — `ranked9.php` wing; spec [`docs/leagues-career-leaderboard-proposal.md`](docs/leagues-career-leaderboard-proposal.md). |
| 2026-05 | **Activity league uncapped on Status** — all players with ≥1 game shown; `limit=0` default in API/SSR. |
| 2026-05 | **Rating wing anchor** — `ranked1.php`: Peak (col 4) is link-star anchor; current Elo is neutral like other columns. |
| 2026-05 | **Status league cross-tint anchors** — `k2-table--league-anchor-cross`: Games/Pts use `--k2-league-anchor-ink` (chrome on amber/pitch tint, pitch on chrome/holo), not `--k2-link-star`. |
| 2026-05 | **Status league calm-stats fix** — `status-period-competitions.js` rebuilds league HTML client-side; matched PHP calm-stats/anchors + `window.k2TableApplyAnchors` after inject/cache restore. |
| 2026-05 | **Calm-stats site-wide (hub tables)** — `k2-table--calm-stats` + anchors on ranked8 activity peaks, Status league tables, `server2.php` record values; `initAnchorTables()` for non-sortable tables. Profile `individual2a/b/c` unchanged. |
| 2026-05 | **Leaderboard calm-stats** — all hub sortable LBs + Status active board: neutral cells, anchor link-star; active sort = bold grey until tuned. |
| 2026-05 | **Leaderboard anchor columns** — `data-k2-anchor-col` + `k2-table.js`: one permanent link-star column per wing (Elo on Rating/Results/Status only); lighter `k2-table-col-sorted` when sorting a non-anchor column. |
| 2026-05 | **League awards Track 1 local** — `league_standings.php`, REP-012 backfill, finalize script; Status points/activity use tie-break sort; `player_league_totals` + win milestones synced. |
| 2026-05 | **League career wins** — `league_wins_*` = #1 in any of 8 (period × points/activity); `player_league_totals.wins`. |
| 2026-05 | **Leagues rules + SCH-009** — tie-breaks locked (points: Pts→GD→GF→Pld→first_game_id→idB; activity: games→first_game_id→idB); `period_end` = achievement time; player-centric `player_league_award`; deep-link `status.php?league_kind=&period=&start=`; PER-003 daily finalize. |
| 2026-05 | **Milestones Phase 1 closed** — idea creation done: [`docs/milestones-project.md`](docs/milestones-project.md), discussion paper, pass 1 catalog (draft, not final). Naming: Milestones + Key subset; own hub tab + profile count + meta-leaderboard planned. Monthly regular rule: game every day of a calendar month. Phase 2 = definition/spec. |
| 2026-05 | **Staging SCH-008 + REP-007–011 done** — Steve applied stored-truth expansion on `kooldb`; milestones re-run after MariaDB fix; verify all 15 checks pass (74,870 games, `established_20_diff=0`). Registers updated. |
| 2026-05 | **Removed dev period activity preview** — deleted `dev-period-activity.php` + `js/status-period-activity.js`; activity league lives on Status Leagues only. |
| 2026-05 | **Leagues cleanup + docs** — removed dead legacy league panel PHP; docs: Phase 1 shipped / 1.5 next. |
| 2026-05 | **Phase 1.5 backlog** — wip checklist + day games list; handoff [`docs/coordination/status-period-competitions-phase-1.5-handoff.md`](docs/coordination/status-period-competitions-phase-1.5-handoff.md). |
| 2026-05 | **Status Leagues lock-step floor** — `first_rated_day` from `ratedresults`; clamp day/week/month after derive; picker labels `Jul 2017` for synthetic options. |
| 2026-05 | **Status Leagues rapid ←/→** — abort stale foreground fetch; nav seq for errors; prewarm debounced + max 2 parallel; clear error on cache hit. |
| 2026-05 | **Status Leagues day ← fix** — `day_min` falls back to `ratedresults` when `player_period_games` has one day; prewarm default on. |
| 2026-05 | **Status Leagues day calendar** — icon toggle close fix; custom month dropdown (12 months, disable out-of-range vs Flatpickr hiding). |
| 2026-05 | **Status Leagues nav fix** — JSON keys attrs (single-quoted); showView uses `hidden` attr; Flatpickr on separate anchor not day value field. |
| 2026-05 | **Status Leagues nav** — ←/→ + picker; removed scope toggle; SSR current period per tab; medals when period ended. |
| 2026-05 | **Status year leagues meta** — end date includes year (`ended Jan 1, 2026 UTC` for 2025 leagues). |
| 2026-05 | **Status Leagues layout** — points centered in space after activity; wrap only when insufficient room (not scope-based gaps). |
| 2026-05 | **Status Leagues meta** — ended periods: end **date** in blue (`ended May 25, 00:00 UTC`); live countdown duration only in blue. |
| 2026-05 | **Status Leagues scope UX** — 3-way segment (Today / Last week / Earlier); period pickers visible only for Earlier; prev labels Last week/month/year. |
| 2026-05 | **Status period competitions Phase 1** — replaced four stacked points-only league panels with paired Activity + Points block (`status_period_competitions_section.php`, `status-period-competitions.js`, `api/status_period_points_league.php`); WIP spec [`docs/status-period-competitions-wip.md`](docs/status-period-competitions-wip.md). |
| 2026-05 | **Policy doc sweep** — deleted `cpp-snippets/`; merged `post-game-cpp-handoff` into `post-game-register`; archived refactor plan + period-games handoff; fixed `PROJECT_MAP`, `STATUS_PAGE_DATA`, `player-profile-feast`, `UPDATE_DOCS` contract row; clarified SCH-008 staging vs local in feature-log/schema-register. |
| 2026-05 | **Post-game snippet workflow retired** — behavior only in `docs/website-data-contract.md`; local/staging = SCH + REP; deleted `cpp-snippets/` PG-005–013; kept `docs/coordination/records-post-game-exception.md` (ex-HoF PG-004). Agents must not cite PG-NNN as blocking work. `feature-log` uses **Prod live** not Post-game column. |
| 2026-05 | **Staging HoF record defects catalogued** — [`docs/staging-post-game-record-defects.md`](docs/staging-post-game-record-defects.md): Gianni streak dates, Fiery CS victims, Eternalstudent opp/vic, etc. (C++ post-game); golden checks extended; ops doc clarifies ladder replay vs website-derived rebuild. |
| 2026-05 | **Post-game replay contract** — Python replay now pins `SET time_zone = '+00:00'` at connection, so `generalstatstable` record dates are UTC-correct. `docs/website-data-contract.md` expanded with full `generalstatstable` semantics (tie policy: strict `>`, ratio leaders excluded, UTC rule, victim-count gates). Golden record checks added (`scripts/ladder/golden_record_checks.py`). PG-004 rewritten as explicit behavior-change handoff (DELETE ratio blocks, CHANGE `>=` to `>`, ADD UTC pin). Replay architecture section documents event engine as behavior authority, SQL rebuilds as parity helpers. Local replay rerun: all golden checks pass. |
| 2026-05 | **Derived-data contract refactor** — `docs/website-data-contract.md` is now the behavior authority for project-owned aggregate tables, rebuild rules, parity checks, and post-game requirements. `scripts/rebuild_website_derived_data_local.ps1` is the one-command local rebuild path; old period/monthly rebuild wrappers now point to it. `docs/stored-truth-expansion.md` and `docs/player-period-games.md` are redirects, while registers track status only. |
| 2026-05 | **UTC period-boundary fix** — `ratedresults.Date` is `timestamp`, so local Estonia MySQL sessions were rebuilding day buckets three hours ahead of UK/staging. Added `SET time_zone = '+00:00'` to PHP DB connections and rebuild scripts, reran local aggregate rebuilds, and verified daily stored rows now match UTC buckets (e.g. 2026-05-17=26, 2026-05-18=31). `api/server_matchup_breadth.php` now also uses the UTC pin and `server_period_matchups`. |
| 2026-05 | **Stored truth expansion** — Five aggregate tables locally (SCH-008 + REP-007–011): `player_period_league`, `player_milestones`, `player_matchup_summary`, `server_period_game_totals`, `server_period_matchups`; parity-checked vs `ratedresults`. Status/Activity/Profile PHP reads stored truth when tables exist; staging SCH-008 + rebuilds still pending Steve. Contract: `docs/website-data-contract.md`. |
| 2026-05 | **Daily active players chart** — `server_daily_activity` (SCH-007); stored path ~73× faster than raw `ratedresults` in local perf test; API `source=stored|raw`. |
| 2026-05 | **Dev period activity date picker affordance** — the Daily panel date input now has a visible accent calendar button and a brightened native picker indicator, so users can open the calendar instead of typing a date. |
| 2026-05 | **Top activity eras chart shipped locally** — `server1.php` now has a "Top activity eras" multi-player line chart: each month shows the top 10 players by rated games, lines appear/disappear as players enter/leave the top 10, hover highlights one player and dims others; powered by new `api/server_top_activity_eras.php` reading `player_period_games` (L0, no new stored truth). |
| 2026-05 | **Realm header identity layout promoted** — shared `site_header.php` now uses the first lab direction: Online/Amiga beside the Kick Off 2 wordmark, with player search isolated on the right; strip variant remains lab-only for comparison. |
| 2026-05 | **Stored truth performance policy added** — agent instructions now say DB-backed features should actively consider indexes, aggregate tables, replay outputs, `playertable` fields, periodic jobs, and post-game C++ updates as normal options for hot stats/profile/achievement work, not burdens to avoid. |
| 2026-05 | **Ranked8 phone activity layout fix** — Calendar and All time activity tables now keep their intended two-column layout below tablet widths, with horizontal overflow only if a very narrow viewport needs it. |
| 2026-05 | **Period activity staged preview unblocked** — `dev-period-activity.php` now permits the staging host (`ratings.kickoff2.com`) while remaining host-guarded elsewhere; page copy now says dev/staging preview. |
| 2026-05 | **Status panel action-link alignment** — the active leaderboard `Leaderboards →` link now uses the same compact Status action styling as `Activity →` and `Games →`. |
| 2026-05 | **Activity Graph Roadmap shipped** — five new Activity features: 12-month daily heatmap (GitHub-style), participation depth stacked bars (1/2-4/5-9/10+ bands), play-texture small-multiples (goals/game, draw %, DD/100, CS/100), unique matchups per month, and a recent milestone digest card; all L0 read-time from `ratedresults`+`playertable`. |
| 2026-05 | **Double Digit Merchant charts** — Activity now has a read-time chart trio for first 10+ goal games: new merchants by year, cumulative merchants, and merchant rating distribution; data is derived from `ratedresults`, not stored on `playertable`. |
| 2026-05 | **Activity copy sharpened** — `server1.php` no longer says "server" in user-facing chart headings/status/aria copy; the past-month daily games chart now shows the same `Games` legend chip as the longer-horizon charts. |
| 2026-05 | **Tooltip microcopy audit** — redundant chart helper under the Activity daily chart removed; table/header tooltip copy now favors abbreviation definitions, formulas, and contextual rules while obvious labels fall back to the shared `Click to sort.` affordance; tint picker native hover titles are removed. |
| 2026-05 | **Chart semantics pass** — chart colors now follow a first-pass vocabulary: pitch = games/wins/profile subject, amber = goals, chrome = active players/projections/opponent focus, holo = cumulative history, magenta = milestones, teal = distributions; dense monthly bars stay borderless. |
| 2026-05 | **Activity recent daily chart** — `server1.php` now opens its chart stack with a past-month games-per-day bar chart from `api/server_games_by_day_recent.php`, including zero-game days. |
| 2026-05 | **Hub nav reordered** — top nav is now `Status · Activity · Games · Leaderboards · Hall of Fame`, frontloading life/evidence before competition and records. |
| 2026-05 | **Status leaderboard sorting** — Status active leaderboard now loads `k2-table.js` for sortable Rank/Player/Elo/Games columns with compact header help, autorank on resort, `past year` heading copy, and `Leaderboards →` destination meta. |
| 2026-05 | **Game table tooltips** — `server3.php` keeps all-column header popups and `game.php` mirrors them as non-sortable help; deep Elo explanation lives on `Fav ES` and visible `Adjustment`. |
| 2026-05 | **Activity summary completes legacy stats merge** — `server1.php` now folds the old Overall Server Stats table into a key sentence, four fact cards (goals/draws/DD/CS), and a quiet games/opponents line before charts. |
| 2026-05 | **Status arc → Activity landing** — Status rated-games arc links to `server1.php` with a discreet left-aligned action below the sentence; Activity opens with the all-time activity story before the historical charts. |
| 2026-05 | **Table spacing cleanup + Games detail path** — inline table `&nbsp;`/`text-align` hacks removed from ranked/player/server/game table families in favor of `theme.css` utilities; `server3.php` now shows 14 day buckets with fully sortable game tables (`GD`, `Elo Diff`, `Fav ES`, `Adjustment`), and Status recent games links to the full Games list via `Games →`. |
| 2026-05 | **Status league stack shipped locally** — `status.php` now stacks uncapped Daily, Weekly (Monday-start), Monthly, and Yearly league panels where the monthly league was; each has its own current/previous toggle, shared 3/1/0 table logic, MySQL `NOW()` server-clock boundaries, and live countdown/end meta. |
| 2026-05 | **Period activity + daily activity on staging** — `kooldb`: SCH-006/007 + REP-003 (week), REP-005, REP-006 backfills done May 2026; stored-truth PHP OK on staging; prod live C++ at cutover per contract. |
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
| 2026-05 | **Games tab shared row renderer** — `game.php` and the Games tab share `includes/k2_rated_game_row.php`; current Games tab behavior is recorded in the newer table-spacing cleanup row above. |
| 2026-05 | **Status Phase B v1.2 in repo** — `status.php` has 4-col room grid, active leaderboard, monthly league toggle, recent logins/registrations/games; prod DB read + joshua redirect still open. |
| 2026-05 | **Profile feast shipped** — production `individual1.php` feast layout only; mock lab removed; further profile work should be gradual copy/UX. |
| 2026-05 | **Core migration/prod coordination set up** — `prod-coordination.md`, registers, schema migrations, staging replay docs; prod post-game from `website-data-contract.md`; prod live ratings still C++. |
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
