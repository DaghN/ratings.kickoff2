# Amiga HoF record dates — stored-truth policy

**Status:** Complete (Jun 2026) — SCH-029 shipped; fixes date semantics for SCH-028 HoF rows  
**Parent:** [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) · [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.5  
**Implementation plan:** [`amiga-hof-record-date-implementation-plan.md`](amiga-hof-record-date-implementation-plan.md)

---

## Problem

SCH-028 shipped correct **holder values** (counts) but wrong **holder dates** for six of eight new HoF rows. Root cause: HoF `*Date` fields were projected from `honours_last_event_date`, which means **last tournament participated** — not **last time the held metric increased**.

Example: Alkis P’s 58th tournament win was **2025-09-20** (Athens XCII); HoF showed **2025-11-01** because he played World Cup XXIII without winning.

`verify-hof-geo-year` originally checked counts and holder ids only; SCH-029 added rise-field and HoF `*Date` oracles (incl. Alkis regression).

---

## Locked decisions

| # | Rule |
|---|------|
| **D1** | Fix at **finalize / replay writers** on `amiga_player_event_snapshots` + `amiga_player_current`. **Not** HoF PHP, **not** UI-only, **not** post-hoc `generalstats` patch without stored player truth. |
| **D2** | **Canonical anchor = `tournament_id`** for “when this metric last rose.” Counts only change at tournament finalize in this model; even career/geo totals rise at one specific event. |
| **D3** | **Co-store `event_date`** alongside each `*_last_rise_tournament_id` on the player row (denormalized at the same write). Matches `honours_last_*` habit; HoF hot path avoids join; verify can assert id ↔ date without scanning `tournaments`. |
| **D4** | **Do not store date-only** (lose event link / drift on date correction). **Id-only is acceptable** but this track uses **id + date** per D3. |
| **D5** | **Year-peak HoF rows unchanged** — `peak_year_games_year` / `peak_year_tournaments_year` are calendar-year semantics (H11); HoF date = that year (Dec 31 display). No tournament id for year peaks. |
| **D6** | `honours_last_event_date` + `honours_last_tournament_id` **remain “last participation”** for profile/other uses. **Do not** overload them for HoF record dates. |
| **D7** | Realm / `amiga_generalstats` `*Date` columns = **projection** from holder player’s stored `*_last_rise_event_date` at finalize (same pattern as other career holders). |
| **D8** | “Last rose” = event where the scalar **strictly increased** to its present value. If the holder’s count did not change at their latest event, date stays at the earlier rise event. |
| **D9** | Tie on value (holder selection) unchanged — strict `>`, then lowest `player_id` (H11). Date comes from the **winning holder’s** rise fields, not from tie-break logic. |
| **D10** | Writer boundary unchanged — tournament finalize + full replay only (H12). PHP ops finalize must mirror Python. |

### SCH-030 — legacy career cumulative holders (Jun 2026)

| # | Rule |
|---|------|
| **D11** | Ten legacy career HoF rows (`MostGamesPlayed` … `BiggestRatingAscent`) use **rise-style** `*Date` = holder’s `*_last_rise_event_date` at finalize — same D2/D3/D8 grain as SCH-029 honours/geo rows. **Not** `record_date` / last-game date. |
| **D12** | Rise fields co-stored on `amiga_player_event_snapshots` + `amiga_player_current` per metric (`number_games_last_rise_*`, …). Writer: `career_rise.py` / `amiga_career_rise_lib.php` at event finalize when career scalar strictly increases. |
| **D13** | Realm / `amiga_generalstats` projection uses `HOF_PREFIX_TO_CAREER_RISE_DATE` map (mirrors honours/geo `_HOLDER_DATE_FIELD`). |

Per-metric storage (SCH-030 DDL `030_career_rise_dates.sql` — 20 columns × snapshots + current):

| HoF row | Value column | Rise fields |
|---------|--------------|-------------|
| MostGamesPlayed | `NumberGames` | `number_games_last_rise_tournament_id`, `number_games_last_rise_event_date` |
| MostWins | `NumberWins` | `number_wins_last_rise_*` |
| MostGoalsScored | `GoalsFor` | `goals_for_last_rise_*` |
| MostDoubleDigits | `DoubleDigits` | `double_digits_last_rise_*` |
| MostCleanSheets | `CleanSheets` | `clean_sheets_last_rise_*` |
| MostDifferentOpponents | `DifferentOpponents` | `different_opponents_last_rise_*` |
| MostDifferentVictims | `DifferentVictims` | `different_victims_last_rise_*` |
| MostDoubleDigitsVictims | `DoubleDigitsVictims` | `double_digits_victims_last_rise_*` |
| MostCleanSheetsVictims | `CleanSheetsVictims` | `clean_sheets_victims_last_rise_*` |
| BiggestRatingAscent | `BiggestRatingAscent` | `biggest_rating_ascent_last_rise_*` |

---

## Per-metric storage (new columns on snapshots + current)

| HoF row | Value column (existing) | New rise fields |
|---------|-------------------------|-----------------|
| Most games in one year | `peak_year_games` | *(none — use `peak_year_games_year`)* |
| Most tournaments in one year | `peak_year_tournaments` | *(none — use `peak_year_tournaments_year`)* |
| Most tournaments (career) | `tournaments_played` | `tournaments_played_last_rise_tournament_id`, `tournaments_played_last_rise_event_date` |
| Most tournament wins | `event_gold` | `event_gold_last_rise_tournament_id`, `event_gold_last_rise_event_date` |
| Most World Cups played | `wc_played` | `wc_played_last_rise_tournament_id`, `wc_played_last_rise_event_date` |
| Most countries played in | `countries_played_in` | `countries_played_in_last_rise_tournament_id`, `countries_played_in_last_rise_event_date` |
| Most opponent countries faced | `opponent_countries_faced` | `opponent_countries_faced_last_rise_tournament_id`, `opponent_countries_faced_last_rise_event_date` |
| Most opponent countries beaten | `opponent_countries_beaten` | `opponent_countries_beaten_last_rise_tournament_id`, `opponent_countries_beaten_last_rise_event_date` |

All `*_event_date` columns: `date DEFAULT NULL`. All `*_tournament_id` columns: `int(11) DEFAULT NULL` (FK to `tournaments.id` optional in DDL; enforce in writers).

---

## Writer rules

### Career honours (`honours_totals.py`)

On each `increment_honours_totals` call for event `T`:

| Condition | Update |
|-----------|--------|
| `tournaments_played` increments | set rise id/date to `T` / `event_date` |
| `event_gold` increments (winner / pos 1) | set `event_gold_last_rise_*` |
| `wc_played` increments (WC name match) | set `wc_played_last_rise_*` |

If count unchanged at `T`, prior rise fields carry forward unchanged.

### Geography (`player_geo_year.py`)

After applying tournament games/host sets, for each participant:

| Condition | Update |
|-----------|--------|
| `len(host_countries)` increases | `countries_played_in_last_rise_*` |
| `len(opponent_faced)` increases | `opponent_countries_faced_last_rise_*` |
| `len(opponent_beaten)` increases | `opponent_countries_beaten_last_rise_*` |

### Realm holder projection (`realm_incremental.py`)

Replace `_HOLDER_DATE_FIELD` mappings that point at `honours_last_event_date` with the matching `*_last_rise_event_date` column. Year peaks still use `year_period_end(peak_year_*_year)`.

### HoF read path

`/amiga/hall-of-fame.php` unchanged — still reads `amiga_generalstats id=1`. Dates fix when stored truth + realm projection fix.

---

## Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Compute date at HoF read from snapshot walk | Violates stored-truth habit; expensive; diverges from realm timeline |
| Store only `event_date` | Loses tournament deep-link; fragile on date edits |
| Store only `tournament_id` | Works but inconsistent with `honours_last_*`; extra join on every holder projection |
| Reuse `honours_last_event_date` | Proven wrong — conflates last play with last rise |
| Store `game_id` | Wrong grain — these metrics are tournament-finalize scoped |

---

## Verification (must add)

Extend `verify-hof-geo-year` (or sibling verify) to assert:

1. Each rise id/date pair on `amiga_player_current` matches replay oracle (honours tracker + geo tracker).
2. Each HoF `*Date` on `amiga_generalstats` matches holder player’s rise date (or year-end for year peaks).
3. Spot oracle: Alkis `event_gold=58` → rise date **2025-09-20**, not 2025-11-01.

---

## Out of scope

- Changing year-peak display from calendar year to event date
- Streak / play-day records (excluded from Amiga HoF per universe contract)

---

## Schema ids

**SCH-029** — `029_hof_record_rise_dates.sql` (12 new columns × snapshots + current = 24 columns; no `generalstats` DDL — holder dates already exist).

**SCH-030** — `030_career_rise_dates.sql` (20 new columns × snapshots + current = 40 columns; no `generalstats` DDL).
