# Parity audit backlog (work vs dev / staging)

**Purpose:** Capture discrepancies and UX issues found during the **parity audit** without committing to fixes mid-audit. Each row is evidence + first-pass options; **resolution** is filled only in a **post-audit review** (see [Process](#process)).

**Environments:** **work** = `ko2unity_work` / `work.ratingskickoff.test` (prepare + simul); **dev** = frozen `ko2unity_db` snapshot. Forward truth for new PHP ops policy is **work**, not byte-for-byte dev match.

**Related:** [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md), [`post-game-php-development.md`](../post-game-php-development.md), [`playertable-schema.md`](../playertable-schema.md).

---

## Audit closed (Jun 2026)

| | |
|--|--|
| **Status** | **Complete** — spot-check across leaderboards, honours, victims/culprits, goals, player games; not every UI corner visited. |
| **Verdict** | **No critical blockers** found for continuing on **work + PHP ops** as forward truth. Dev remains a **legacy reference**, not a byte-for-byte target. |
| **Shipped during audit** | Leaderboard display policy (`k2_fmt_*`, ranked1–7); ranked2 draw/MGC fixes; **individual3** / game row unprocessed display (**AUD-006**). |
| **Post-audit (not blocking)** | **AUD-004** ops pipeline (one-click simul = daily ops, not batch rebuilds); **AUD-001** writer NULL→0 optional; **AUD-003** ranked5 tie policy accepted; league honours empty until finalize (**AUD-005**). |
| **Follow-ups (low)** | `individual3` win/draw/loss filters on `ActualScore` for unprocessed rows (**AUD-006**); sweep other `ratedresults` listings if needed. |
| **Next step** | **[Ops completeness programme](ops-completeness-charter.md)** (charter + [ADR](ops-orchestration-adr.md) + [DDR](ops-derived-data-registry.md)) — implement **AUD-004**; then post-audit triage for deferred writer/display items. |

---

## Process

| Phase | Do | Do not |
|--------|-----|--------|
| **During audit** | Note observation, hypothesis, impact, options (first look), open questions, links | Lock resolution, run replays, large refactors |
| **After audit** | Triage table: resolution, rationale, verify, priority/batch | Re-litigate every row from scratch |

**Status (audit phase):** `noted` | `verified` | `deferred` — not `done` / `wontfix` until post-audit.

---

## Audit items

### AUD-001 — NULL stored for zero career counts (DD / CS / similar)

| Field | Content |
|--------|---------|
| **Status** | `verified` (display on **ranked1–7** + **ranked10** games Jun 2026); writer/storage **deferred** |
| **Found** | **ranked3** (and same pattern on other wings): players with **derived `NumberGames` > 0** show **`-`** in Double Digits / Clean Sheets (made & conceded) when the true count is **zero**. Ratios often still show **`0.0%`** because ratios are written even when counts are NULL. |
| **Observation** | Post-game persists several counters as **`NULL` when value is 0**, e.g. `'DoubleDigits' => $st['double_digits'] > 0 ? … : null` in `site/public_html/ops/includes/post_game_player_state.php`. On work (spot check): **217** rows with `NumberGames > 0` and `DoubleDigits IS NULL`; **0** rows with `DoubleDigits = 0`. After null-safe display (`k2_fmt_count` NULL → `-`), zero DDs look “unknown” instead of **0**. Pre-change PHP used `(int) $row[4]` → **0** on screen. |
| **Hypothesis / cause** | Long-standing **writer** convention (sparse NULL = zero) + **reader** change (honour NULL as dash) = regression visible on ranked3. Not a simul bug by itself. |
| **Impact** | Misleading parity/UI (“dash” for active players with no DDs); SQL/reporting confusion; audit noise if work/dev compared on raw NULL counts. |
| **Options (first look)** | **(a)** Display: when derived `NumberGames > 0`, treat NULL counts as **0**; unplayed rows: **Games = 0**, **Rating = 1600**, other career fields **`-`** — rolled out to all **ranked1–7** via `k2_fmt_*` + `k2_fmt_lb_stat` (Jun 2026). **(b)** Writer: persist **0** instead of NULL (e.g. align with `k2_post_game_player_db_count`). **(c)** Both. **(d)** Document-only. |
| **Open questions** | Full list of `> 0 ? x : null` fields in post-game; whether dev snapshot should influence writer change; mixed NULL/0 rows after partial replay; profile/feast pages beyond leaderboards. |
| **Links** | `post_game_player_state.php` ~607–610; `includes/k2_safety.php`; commit `8373a5b` (null-safe display). |
| **Resolution** | *(post-audit)* |

### AUD-002 — ranked2 goals wing: optional_int + draw column vs games-started policy

| Field | Content |
|--------|---------|
| **Status** | `verified` (ranked2 display fix Jun 2026) |
| **Found** | **ranked2** — e.g. **SneakusBeakus** (2 games, two 0–0 draws): **Games = 2** but **Most Scored**, margins, **Goal sum** showed **`-`**; **BWOLF99** (2 games with goals) looked fine. |
| **Observation** | Sneakus: `GoalsFor`/`GoalsAgainst` = 0 (display OK); `MostGoalsScored`, `BiggestWinDifference`, `BiggestSumOfGoals`, etc. **NULL** (writer `> 0 ? x : null`). Draw column used `NumberDraws != 0` but formatted **`BiggestDrawSum`** (NULL for 0–0) → broken/empty scoreline. **`GoalRatio = -1`** sentinel (0 GF / 0 GA) still **`-`** by design. |
| **Hypothesis / cause** | First display pass updated `k2_fmt_count` columns but left **`k2_fmt_optional_int`** on “record” columns and legacy draw logic. |
| **Impact** | Active low-scoring / all-draw players look “empty” on goals leaderboard. |
| **Options (first look)** | **(a)** Use `k2_fmt_count(…, NumberGames)` on ranked2 record columns; draw: if `NumberDraws > 0`, show `(BiggestDrawSum??0)/2` as `n-n` (match `individual2b.php`). **(b)** Writer: persist 0 for `BiggestDrawSum` when draw sum is 0 (replay). **(c)** Both. |
| **Open questions** | Same NULL-as-zero pattern on ranked4 optional columns; whether goal ratio **-1** should ever show a numeric value for 0–0 careers. |
| **Links** | `ranked2.php`; work DB id **579**; `individual2b.php` draw display. |
| **Resolution** | *(post-audit)* |

### AUD-003 — ranked5 Victims & Culprits: work vs dev (inverse counts / tie policy)

| Field | Content |
|--------|---------|
| **Status** | `verified` (initial investigation Jun 2026 — **no suspicious findings**; optional double-check deferred) |
| **Found** | **ranked5.php** — many work/dev gaps on inverse columns (MGC/BL/MGS/BW victims & culprits), far more than unprocessed tail games. Example: **hanso** (id **302**), same **`NumberGames` = 4391** on both DBs: **MGC Victims** dev **8** vs work **11**; **BL Victims** 11 vs 14; personal **MostGoalsConceded** still **15** but credited opponent differs (dev **GianniT** / game **10686** vs work **kof2** / game **2229**). |
| **Observation** | Inverse counts = players whose **current** personal-record pointer names this opponent (`MostGoalsConcededCulpritID`, etc.). **Contract + PHP ops (Jun 2026):** strict **`>`** on personal extremes — on a **tie**, first credited opponent keeps credit; inverse counts move only when margin is **strictly** beaten and credit shifts. **Legacy prod C++ / dev snapshot:** **`>=`** — later opponent can take credit on a tie (`docs/ratings_cpp.txt` ~516–531). Holder diffs for hanso match that story (e.g. **ColonelMcCoy**, **fusionsynth**, **CRASHOVERRIDE** credit hanso on work but another culprit on dev at the **same** MGC margin; **gabry1980** the reverse). Same pattern appears on other names with identical game counts (e.g. **Logos** MGC Victims +7 on work). Site tooltips: `k2_lb_help_victims_wing_tie()` — *“In a tie, the first offender gets the credit.”* |
| **Hypothesis / cause** | **Expected parity drift** between dev (legacy `>=` semantics) and work (full PHP replay with contract `>`). Not ranked5 display; not explained by a small unprocessed-game tail alone. |
| **Impact** | Audit noise if work is scored against dev byte-for-byte; Steve/questions on “lost” or “gained” victims. Forward truth = **work + contract**, same stance as other post-game policy deltas. |
| **Initial read** | **As intended** — implementation matches documented contract; hanso-style deltas are consistent with credit reassignment on tied records, not a rogue simul bug. **Nothing in first pass looked suspicious.** |
| **Options (first look)** | **(a)** Accept as documented behaviour difference; no change. **(b)** Post-audit **double-check**: trace 1–2 holder flips (e.g. gabry1980 / fusionsynth) through `ratedresults` chronology for tied MGC games. **(c)** Verify inverse-count integrity (e.g. hanso work column **11** vs **10** rows with `MostGoalsConcededCulpritID = 302`). **(d)** Do not force work → dev. |
| **Open questions** | Whether post-audit spot traces are worth the time; whether any player-level counter/pointer mismatch is systematic or one-off. |
| **Links** | [`website-data-contract.md`](../website-data-contract.md) § Personal record pointers; [`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md) (P2 **Fixed**); `post_game_player_state.php` ~409–476; `ranked5.php`; `lb_column_help.php` (`k2_lb_help_mgc_victims`, tie line). |
| **Resolution** | *(post-audit)* |

### AUD-004 — **Fundamental gap:** simul ≠ daily ops pipeline (derived data coverage)

| Field | Content |
|--------|---------|
| **Status** | `verified` (audit Jun 2026); **planned comprehensive ops audit + fix** (same day — owner) |
| **Found** | **League honours (ranked9)** on **work**: `player_league_award` / `player_league_totals` / `league_period` **empty** after game-only simul; `player_period_league` populated (~38k rows). Steve staging runbook §4 = `replay-to` only; §5 finalize is optional/separate. User intent: **one-click simul** should mirror **day-to-day ops**, **not** depend on batch rebuilds as the definition of “simul complete.” |
| **Observation — intent vs today** | **Intent:** Simul = faithful replay of **live pipeline**: per-game post-game + **periodic** jobs (at minimum **league finalize ~00:00:01 UTC**, plus other calendar-bound writers). **Today:** Default path is **Mode A** (`run_process_game.php replay-to` / `CMD=ProcessCompletedGame` only) = per-game derived truth. **Mode B** (batch `rebuild_website_derived_data_local.ps1`, `player_milestones_rebuild.sql`, `run_finalize_league.php rebuild-all`) is a **shortcut**, explicitly **not** the simul definition in [`work-db-prepare.md`](../work-db-prepare.md) §5. **Mode C** (`run_timeline_sim.php`) exists but is partial / not the default Steve path. |
| **Observation — derived layers** | **Per-game** (in `replay-to`): Elo, `playertable`, GST, `player_period_games` / `player_period_league`, most P6 game keys → `ProcessCompletedGame`. **Periodic** (not in `replay-to`): PER-003 league finalize (awards, honours), PER-001 rating fade, day-close milestones → cron / `FinalizeLeagueDue`. **Register:** `entered_arena` → `ProcessPlayerRegistered` only. **Batch rebuild** (Mode B — not target simul semantics): full `player_league_award`, ~20 league medal keys in `player_milestones_rebuild.sql`, some aggregates — often used to backfill gaps. |
| **Observation — league milestones (subset)** | Even **`run_finalize_league.php`** (`finalize-due` / `rebuild-all`) only runs **`k2_league_sync_win_milestones()`** → **`league_wins_10/50/100/500`**. The ~20 **league event** keys (`league_daily_points_winner`, `activity_king`, podium medals, …) are **not** incremental in finalize; contract says PER-003; code path is **`player_milestones_rebuild.sql`** / batch ([`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md) P6). |
| **Observation — other “not per game” (non-exhaustive)** | **`perfect_day` / `nightmare_day`** + league awards/milestones — need **Mode C** / `FinalizeUtcDay` (shipped Jun 2026). **`entered_arena`** — **prepare §4.7**, not timeline sim ([`ops-simul-runbook.md`](ops-simul-runbook.md)). **Rating fade** — retired. Default **`replay-to`** still omits day tick → use [`ops-simul-runbook.md`](ops-simul-runbook.md). |
| **Hypothesis / cause** | Ops platform grew **game processor first**; periodic + milestone league block stayed **documented separately** or **batch-backed**. Staging handoff optimized for **long `replay-to`**; easy to assume “simul done” = all derived data. Dev snapshot may include **batch-finalized** league + milestones while work does not. |
| **Impact** | **Parity audit:** false comparisons (honours, league milestones, possibly status/league UI). **Staging:** Steve/site see empty or partial features after “full” simul. **Cutover risk:** live = post-game + cron; if simul ≠ that union, pre-prod verification is incomplete. **Not** a single ranked-page bug — **pipeline architecture** gap. |
| **Initial read** | **Real fundamental shortcoming** in current ops packaging, not a small oversight. League honours empty is **symptom**; root issue is **no one-click simul of daily ops**. Batch rebuilds are **stopgaps**, not the target definition of simul. |
| **Planned work (owner, post-audit item)** | **Ops completeness programme** — see [`ops-completeness-charter.md`](ops-completeness-charter.md): DDR inventory → `FinalizeUtcDay` + sim orchestrator → incremental league/day-close milestones. **Out of scope for parity audit pass** until programme closes this item. |
| **Options (first look)** | **Adopted (Jun 2026):** [`ops-orchestration-adr.md`](ops-orchestration-adr.md) — one midnight CMD, ordered steps, sim interleave, batch = repair only. **(a–e)** from original list map to charter phases 2–3. |
| **Open questions** | Python ladder tail batch fields; `rebuild-aggregates` without `k2_league_sync_win_milestones`; exact sim midnight convention. **Closed:** PER-001 fade — **not in scope** (charter §3). |
| **Links** | [`ops-completeness-charter.md`](ops-completeness-charter.md) · [`ops-orchestration-adr.md`](ops-orchestration-adr.md) · [`ops-derived-data-registry.md`](ops-derived-data-registry.md) · [`work-db-prepare.md`](../work-db-prepare.md) §5.1–5.2 · [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md) §4–5 · [`ops-dispatch.md`](ops-dispatch.md) · [`periodic-register.md`](periodic-register.md) · [`post-game-php-development.md`](../post-game-php-development.md) §2.2–2.3 |
| **Resolution** | **Partial (Jun 2026):** `FinalizeUtcDay` + `run_ops_sim.php` + [`ops-simul-runbook.md`](ops-simul-runbook.md). **Steve:** adopt nightly `FinalizeUtcDay` ([`steve-nightly-ops.md`](steve-nightly-ops.md)). Close fully after staging full `run_ops_sim` + site smoke. |

### AUD-005 — League honours empty on work (symptom of AUD-004)

| Field | Content |
|--------|---------|
| **Status** | `verified` |
| **Found** | **ranked9** — “no league resolutions”; work DB counts: `player_league_award` **0**, `player_league_totals` **0**, `league_period` **0**; dev **~21k** awards, **7.4k** periods. |
| **Observation** | UI: `dataReady` if table exists → table of **zeros** or muted “not available”. Inputs exist: `player_period_league` **~38k** on work. |
| **Hypothesis / cause** | **PER-003 never run** on work after prepare truncate + game-only simul. Subsumed by **AUD-004**. |
| **Impact** | Parity noise vs dev; misleading staging demo. |
| **Options (first look)** | Fix via **AUD-004** orchestration; interim: `run_finalize_league.php rebuild-all --target …` (batch — not target simul semantics). |
| **Open questions** | None separate from AUD-004. |
| **Links** | **AUD-004**; `ranked9.php`, `league_honours_leaderboard.php`. |
| **Resolution** | *(post-audit)* |

### AUD-006 — `individual3.php` / game rows: unprocessed `ratedresults` (NULL derived)

| Field | Content |
|--------|---------|
| **Status** | `verified` (display fix Jun 2026) |
| **Found** | **Dagh** (id **291**): tail games on **work** with `NewRatingA IS NULL` (~45 unprocessed) showed **Loss** in Result, **0%** ES, **0** ratings — while **F/A** (goals) correct. Cause: `k2_player_game_normalize_row()` coerced NULL `ActualScore` → **-1** (loss), NULL expected scores → **0%**. |
| **Observation** | **Ground truth** on import: `Date`, names, `GoalsA`/`GoalsB`. **Derived** (post-game): `RatingA/B`, `ExpectedScore*`, `ActualScore`, `Adjustment*`, `NewRating*`, `GoalDifference`, `SumOfGoals` (often NULL until processed). Ops marker: **`NewRatingA IS NULL`** = unprocessed (same as `k2_ops_rated_game_skip_reason`). |
| **Display policy (implemented)** | If **unprocessed**: show **Win/Draw/Loss**, **F**, **A**, **Diff**, **Sum** from **scoreline (goals)**; **`-`** for **ratings**, **ES**, **adjustment**. If **processed**: unchanged (use stored derived fields). Shared: `k2_rated_game_is_processed()`, `k2_player_game_row.php`, `k2_rated_game_row.php` (`game.php`, highlights compact). |
| **Impact** | Misleading audit on partial simul; players look like losing streak when Elo not replayed yet. |
| **Open questions** | Win/draw/loss **filters** on `individual3.php` still SQL-filter on `ActualScore` — unprocessed games may drop out of filtered views; extend WHERE to goal-based logic if needed. Other pages listing `ratedresults`? |
| **Links** | `individual3.php`; `includes/k2_player_game_row.php`; `includes/k2_rated_game_row.php`; `includes/k2_safety.php`; `process_completed_game.php` (`NewRatingA`). |
| **Resolution** | *(post-audit)* |

---

## Post-audit triage (empty until review)

| AUD | Resolution | Rationale | Verify | Priority |
|-----|------------|-----------|--------|----------|
| AUD-001 | | | | |
| AUD-002 | | | | |
| AUD-003 | | | | |
| AUD-004 | | | | |
| AUD-005 | | | | |
| AUD-006 | | | | |

---

## Adding rows

Copy the **AUD-001** table skeleton. Use the next ID (`AUD-002`, …). Link terminal output, player ids, or scripts in **Links**. Keep **Options** as hypotheses, not decisions.
