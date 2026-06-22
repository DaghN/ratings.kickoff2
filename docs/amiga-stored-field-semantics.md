# Amiga stored id/date semantics — manifest

**Status:** Phase A complete (Jun 2026)  
**Plan:** [`amiga-stored-field-semantics-plan.md`](amiga-stored-field-semantics-plan.md)  
**Semantic classes:** participation · rise · peak-year · game-anchor · holder-projection

---

## How to read this doc

| Verify | Meaning |
|--------|---------|
| **yes** | Dedicated semantic or replay oracle in `prove` (not just self-consistency) |
| **partial** | Column parity or batch oracle uses the **same writer** as production (catches drift, not wrong semantics) |
| **no** | Not checked in `prove` |
| **n/a** | No date/id semantics on hot path |

**Writers (short):** Py = Python finalize/replay · PHP = `site/public_html/amiga/ops/` finalize path.

---

## 1. Player timeline — `amiga_player_event_snapshots` + `amiga_player_current`

### 1.1 Event keys & current meta

| Field(s) | Table | Class | Meaning | Py writer | PHP writer | Verify |
|----------|-------|-------|---------|-----------|------------|--------|
| `tournament_id`, `event_date`, `event_chrono` | snapshots | participation | Event identity / ordering | `snapshot_row` / `snapshot_persist` | `amiga_event_snapshot_persist` | **partial** — `verify_event_snapshots` (FK, counts) |
| `last_tournament_id`, `last_event_date`, `last_finalized_at` | current | participation | Latest finalized event for player | `current_row_from_snapshot` | same | **partial** — current = latest snapshot meta |
| `honours_last_tournament_id`, `honours_last_event_date` | snapshots only | participation | Last honours event (same as last played at finalize) | `honours_totals` → snapshot | `amiga_honours_totals_lib` | **partial** — rise oracle replays honours; not isolated |

### 1.2 Rise fields (SCH-029) — twelve columns, identical on snapshots + current

| Field pattern | Class | Meaning | Py writer | PHP writer | Verify |
|---------------|-------|---------|-----------|------------|--------|
| `{metric}_last_rise_tournament_id` | rise | Event where cumulative `{metric}` last **strictly increased** | `honours_totals` / `player_geo_year` | honours + geo libs → persist | **yes** — `verify_hof_geo_year` replay oracle |
| `{metric}_last_rise_event_date` | rise | Denormalized date for same event | same | same | **yes** — same + id/date pairing per player |

Metrics: `tournaments_played`, `event_gold`, `wc_played`, `countries_played_in`, `opponent_countries_faced`, `opponent_countries_beaten`.

### 1.3 Calendar-year peaks (values + year; HoF dates derived separately)

| Field(s) | Class | Meaning | Py writer | PHP writer | Verify |
|----------|-------|---------|-----------|------------|--------|
| `peak_year_games`, `peak_year_games_year` | peak-year | Best calendar-year game count + winning year | `player_geo_year` | `amiga_player_geo_year_lib` | **yes** — geo scalar oracle in `verify_hof_geo_year` |
| `peak_year_tournaments`, `peak_year_tournaments_year` | peak-year | Best calendar-year event count + year | same | same | **yes** — same |

### 1.4 Career game anchors (on snapshots + current)

| Field(s) | Class | Meaning | Py writer | PHP writer | Verify |
|----------|-------|---------|-----------|------------|--------|
| `LastGameGameID`, `LastWinGameID`, `LastDrawGameID`, `LastLossGameID` | game-anchor | Game id for last overall / W / D / L | `PlayerState` / post-game | `k2_post_game_player_state` | **partial** — career column parity current vs latest snapshot |
| `PeakRatingGameID`, `LowestRatingGameID`, `MostGoalsScoredGameID`, … (see `PlayerState.to_db_row`) | game-anchor | Game where career extreme was set/refreshed | same | same | **partial** — column parity only |
| `career_best_performance_tournament_id` (+ rating) | game-anchor | Tournament where best perf rating (≥2 games) was set | `snapshot_row.career_best_performance_fields` | `amiga_event_snapshot_persist` | **yes** — `verify_stored_id_date_pairs` (FK + replay oracle) |

*No stored dates on these career game-id columns — dates come from joined `amiga_games` / tournament when projected to HoF.*

### 1.5 Event-local participation (snapshots)

| Field(s) | Class | Meaning | Verify |
|----------|-------|---------|--------|
| `player_id`, `finalized_at`, `is_winner`, `event_finish_position`, … | participation | Per-event facts | **partial** — rollup vs participation table in `verify_event_snapshots` |

---

## 2. Realm timeline — `amiga_realm_snapshots` event keys

| Field(s) | Class | Meaning | Py writer | PHP writer | Verify |
|----------|-------|---------|-----------|------------|--------|
| `tournament_id`, `event_date`, `event_chrono`, `tournament_name`, `finalized_at` | participation | One realm row per finalized event | `realm_persist` / incremental | `amiga_realm_snapshot_lib` | **partial** — count/FK/missing snapshot checks in `verify_realm_snapshots` |

Payload columns below are holder-projection mirrors of `amiga_generalstats`.

---

## 3. HoF / realm payload — `amiga_generalstats` + `amiga_realm_snapshots`

Projection writers: Py `realm_incremental` + `server_records` · PHP `amiga_realm_incremental_lib` + realm snapshot lib.  
Read: `/amiga/hall-of-fame.php` — **must not** compute record dates at read time.

### 3.1 Career cumulative holders (value + ID + Name + Date)

| HoF prefix | Value source (player row) | Date source | Class | Verify |
|------------|---------------------------|-------------|-------|--------|
| `MostGamesInOneYear` | `peak_year_games` | `peak_year_games_year` → Dec 31 | peak-year | **yes** — `verify_hof_geo_year` (8-row set) |
| `MostTournamentsInOneYear` | `peak_year_tournaments` | `peak_year_tournaments_year` → Dec 31 | peak-year | **yes** — same |
| `MostTournamentsPlayed` | `tournaments_played` | `tournaments_played_last_rise_event_date` | rise | **yes** — same + Alkis regression |
| `MostTournamentWins` | `event_gold` | `event_gold_last_rise_event_date` | rise | **yes** — same |
| `MostWcPlayed` | `wc_played` | `wc_played_last_rise_event_date` | rise | **yes** — same |
| `MostCountriesPlayedIn` | `countries_played_in` | `countries_played_in_last_rise_event_date` | rise | **yes** — same |
| `MostOpponentCountriesFaced` | `opponent_countries_faced` | `opponent_countries_faced_last_rise_event_date` | rise | **yes** — same |
| `MostOpponentCountriesBeaten` | `opponent_countries_beaten` | `opponent_countries_beaten_last_rise_event_date` | rise | **yes** — same |
| `MostGamesPlayed` | `NumberGames` | `number_games_last_rise_event_date` | rise | **yes** — `verify_hof_geo_year` (18-row set) |
| `MostWins` | `NumberWins` | `number_wins_last_rise_event_date` | rise | **yes** — same |
| `MostGoalsScored` | `GoalsFor` | `goals_for_last_rise_event_date` | rise | **yes** — same |
| `MostDoubleDigits` | `DoubleDigits` | `double_digits_last_rise_event_date` | rise | **yes** — same |
| `MostCleanSheets` | `CleanSheets` | `clean_sheets_last_rise_event_date` | rise | **yes** — same |
| `MostDifferentOpponents` | `DifferentOpponents` | `different_opponents_last_rise_event_date` | rise | **yes** — same |
| `MostDifferentVictims` | `DifferentVictims` | `different_victims_last_rise_event_date` | rise | **yes** — same |
| `MostDoubleDigitsVictims` | `DoubleDigitsVictims` | `double_digits_victims_last_rise_event_date` | rise | **yes** — same |
| `MostCleanSheetsVictims` | `CleanSheetsVictims` | `clean_sheets_victims_last_rise_event_date` | rise | **yes** — same |
| `BiggestRatingAscent` | `BiggestRatingAscent` | `biggest_rating_ascent_last_rise_event_date` | rise | **yes** — same |

*SCH-030 (Jun 2026): legacy career rows moved from `record_date` (last game) to rise-style event dates — see [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) D11–D13.*

### 3.2 Single-game / pair-game holders (+ GameID)

| HoF prefix | ID(s) | Date | GameID | Date class | Verify |
|------------|-------|------|--------|------------|--------|
| `MostGoalsScoredInOneGame` | player | tournament/game date of record game | yes | game-anchor | **yes** — `verify_hof_holder_projection` |
| `BiggestWinDifference` | player | same | yes | game-anchor | **yes** — same |
| `BiggestDrawSum` | two players | same | yes | game-anchor | **yes** — same |
| `BiggestSumOfGoals` | two players | same | yes | game-anchor | **yes** — same |
| `BiggestPeakRating` | player | same | no | game-anchor | **yes** — same (in-game peak oracle) |

### 3.3 Ratio leaders (no dates)

| Fields | Class | Verify |
|--------|-------|--------|
| `BiggestWinRatio`, `BiggestGoalsForAverage`, … + `*ID` + `*Name` | holder-projection | **yes** — `verify_hof_holder_projection` (player-row + SQL oracle; no dates) |

### 3.4 Realm-wide aggregates (no holder ids/dates)

`GamesPlayed`, `GoalsScored`, ratios, etc. — on **`amiga_community_stats`** / snapshots; **`verify-community-stats`** + **`verify-php-community-parity`** in `prove`. Dropped from `amiga_generalstats` / realm snapshots (`035`).

---

## 4. Verify module index

| Module | What it guards |
|--------|----------------|
| `verify_hof_geo_year` | Geo/year scalars; 32 rise columns on current; 18 HoF rows value/ID/**Date**; realm vs `generalstats`; Alkis regression |
| `verify_hof_holder_projection` | All career holders `*Date` = `_holder_record_date` on holder row; game-anchored rows vs SQL game oracle + `*GameID` date; ratio leaders vs player-row + SQL oracle; realm vs `generalstats` |
| `verify_stored_id_date_pairs` | Rise id/date null symmetry + FK + `tournaments.event_date` on current + snapshots; honours_last on snapshots; `last_*` participation on current; career-best replay + FK |
| ~~`verify_php_finalize_parity`~~ | **Retired Jun 2026** with refinalize — [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md). Community PHP **build** parity = `verify-php-community-parity` |
| `verify_realm_snapshots` | Snapshot count; `generalstats` = latest realm row; **full payload** oracle via `build_generalstats_payload` (self-consistent) |
| `verify_event_snapshots` | Snapshot/current row counts; latest snapshot parity (incl. rise + career cols); participation rollups |
| `test_honours_rise_dates`, `test_career_rise_dates`, `test_player_geo_year`, `test_realm_holder_dates`, `test_hof_holder_projection`, `test_stored_id_date_pairs` | Unit tests for increment / projection helpers |

---

## 5. Unverified backlog (ranked for Phase B–D)

Priority = risk if projection or pairing is wrong **and** `prove` stays green.

| Prio | Gap | Phase | Notes |
|------|-----|-------|-------|
| **P1** | ~~Legacy career HoF `*Date` uses `record_date`~~ | **done** | **SCH-030** — rise-style dates for ten career rows |
| **P2** | ~~Holder `*ID` / `*Date` / `*GameID` for non–geo/honours rows vs explicit source-field map~~ | **done** | **Phase B** — `verify_hof_holder_projection` |
| **P3** | ~~Game-anchored HoF rows: `*GameID` exists; `*Date` matches game/tournament date~~ | **done** | **Phase B** — same module |
| **P4** | ~~Global id/date pairing: rise `*_tournament_id` ↔ `*_event_date` ↔ `tournaments.id`~~ | **done** | **Phase C** — `verify_stored_id_date_pairs` |
| **P5** | ~~`honours_last_*` / `last_*` participation consistency~~ | **done** | **Phase C** — same module |
| **P6** | ~~`career_best_performance_tournament_id` FK + replay oracle~~ | **done** | **Phase C** — same module |
| **P7** | ~~PHP reopen+finalize vs Python on fixed tournament (rise + HoF slice)~~ | **retired** | **Jun 2026** — removed with refinalize; use full `prove` ([`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md)) |

---

## 6. Ritual reminder

New stored id/date field → add a row here → ship `test_*` or `verify_*` before slice sign-off. See plan § Ritual.

---

## Changelog

| When | What |
|------|------|
| 2026-06 | Phase D — `verify-php-finalize-parity` **retired** with refinalize; repair = `prove` only |
| 2026-06 | Phase C — `verify_stored_id_date_pairs` in `prove`; P4–P6 closed |
| 2026-06 | Phase B — `verify_hof_holder_projection` in `prove`; game + ratio + career source-field checks |
| 2026-06 | SCH-030 — ten career HoF rows moved to rise-style dates; verify index updated |
| 2026-06 | Phase A manifest + backlog (this file) |
