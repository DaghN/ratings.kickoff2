# Records page (`hall-of-fame.php`) — data sources



## Context links (May 2026)



- **All record values** link to the matching Leaderboards wing via `includes/records_hof_links.php`, plus `k2_sort` / `k2_dir` on ranked wings. **Ratio/average rows only** also pass `provisional=0` (same ≥20 pool as HoF eligibility). Count/streak/single-game rows omit that param so leaderboards open with default include toggles **on**. `js/k2-table.js` applies sort on load for `ranked-pages-table` only. Labels stay plain text.

- **`*GameID` columns** (`MostGoalsScoredInOneGameGameID`, etc.) remain in `generalstatstable` (post-game writers); HoF does not SELECT them — establishing games are on the linked leaderboards / Highlights boards.

| Activity wing (peaks / participation / in a row) | value → Activity wing with `k2_sort` on matching column (peaks → `lb-activity-peaks`; in-a-row day/week/month/year → `lb-activity-in-a-row`; participation counts + longevity → `lb-activity-participation`) |

| Milestones + league honours | `lb-milestones` (total col) · `lb-league-honours?cup=overall` (gold / silver / bronze cols) |



## `generalstatstable` row `id=1`



Headline server totals and **non-ratio** hall-of-fame rows (most games, streaks, single-game records, pair rows, rating ascent/peak, etc.). Updated by production C++ per game; local replay via `scripts/ladder/server_records.py` + `generalstats.py`.



**Rated play streaks (May 2026):** `LongestDailyPlayStreak*` / `LongestWeeklyPlayStreak*` on row `id=1` — consecutive UTC days / Mon–Sun weeks with ≥1 rated game. Shown on `hall-of-fame.php` as **Most days in a row** and **Most weeks in a row** (after peak “most games in …”, before participation). Backfill: REP-015; post-game: contract § `player_play_streaks`. **Staging verified** May 2026: daily **87** (player 582), weekly **126** (player 344).



**Rated play streaks month/year (Jun 2026):** `LongestMonthlyPlayStreak*` / `LongestYearlyPlayStreak*` (SCH-023) — same UTC rules; HoF rows **Most months in a row** / **Most years in a row** after day/week streaks. Post-game: PHP ops P7.



**Schema (May 2026):** Ratio **player** leader columns were **dropped** from this table locally via `schema/migrations/002_generalstatstable_drop_ratio_leader_columns.sql`. Steve should apply the same on staging/prod (`SCH-003`). Server-wide `DoubleDigitsRatio` / `CleanSheetsRatio` **remain** (all-games totals, not player leaders).



## Career celebration leaders (read-time)



Loaded at page render: `site/public_html/includes/records_career_leaders.php`



| Records label | Source | Tie order | Date column |

|---------------|--------|-----------|-------------|

| Most milestones | `player_milestone_totals.total` (fallback `COUNT` unlock rows) | Same as milestones meta LB | Latest `player_milestones.achieved_at` for holder |

| Most league gold | `player_league_totals.gold` | gold DESC, podiums DESC, name | Latest `player_league_award.period_end` where `medal=gold` |

| Most league silver | `.silver` | silver DESC, podiums DESC, name | Latest award `period_end` for silver |

| Most league bronze | `.bronze` | bronze DESC, podiums DESC, name | Latest award `period_end` for bronze |



League rows = **career overall** totals (all period leagues · points + activity), not a slice. Requires SCH-009 + SCH-020 tables on the DB.



## Activity participation leaders (read-time)



Loaded at page render when `player_activity_participation` exists: `site/public_html/includes/records_activity_leaders.php`



| Records label | Source column | Order |
|---------------|---------------|-------|
| Most active days | `active_days` | DESC, Nth-period establishing game ASC, lowest `ID` |
| Most active weeks | `active_weeks` | DESC, Nth-period establishing game ASC, lowest `ID` |
| Most active months | `active_months` | DESC, Nth-period establishing game ASC, lowest `ID` |
| Most active years | `active_years` | DESC, Nth-period establishing game ASC, lowest `ID` |
| Longest longevity | `DATEDIFF(last_rated_day, first_rated_day) + 1` | DESC, lowest `ID` |

Date column: counts use stored **`active_*_reached_at`** (SCH-025, establishing game when count last rose). Longevity shows first–last rated day span.



## Ratio / average leaders (from `playertable`)



Loaded at page render: `site/public_html/includes/records_ratio_leaders.php`



| Records label | Column | Order |

|---------------|--------|-------|

| Best attack average | `AverageGoalsFor` | DESC |

| Best defense average | `AverageGoalsAgainst` | ASC |

| Best goal ratio | `GoalRatio` | DESC, `GoalRatio > -1` |

| Highest winning frequency | `WinRatio` | DESC |

| Highest double digit frequency | `DoubleDigitsRatio` | DESC |

| Highest clean sheet frequency | `CleanSheetsRatio` | DESC |



Eligible: `NumberGames >= 20` (`K2_ESTABLISHED_MIN_GAMES` in `lb_player_filters.php` — same as leaderboard “exclude provisional” and `established_20` milestone). Ties: lowest `ID`.



Steve: [`records-post-game-exception.md`](coordination/records-post-game-exception.md) — remove C++ post-game writes for ratio leaders on `generalstatstable`.

