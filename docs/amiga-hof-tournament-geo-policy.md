# Amiga HoF — tournament, calendar-year, and geography records

**Status:** Implemented (Jun 2026)  
**Implementation plan:** [`amiga-hof-tournament-geo-implementation-plan.md`](amiga-hof-tournament-geo-implementation-plan.md)  
**Parent:** [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.3

---

## Locked decisions

| # | Rule |
|---|------|
| **H1** | Calendar year = `YEAR(tournament.event_date)`; NULL `event_date` → exclude from year buckets |
| **H2** | Games in one year = sum `games_in_event` across events in that year |
| **H3** | Tournaments in one year = count of participated events in that year |
| **H4** | Career honours: `tournaments_played`, `event_gold` (wins), `wc_played` |
| **H5** | Countries played in = distinct tournament **host** countries on events the player entered (documented `tournaments.country`; no nationality seed) |
| **H6** | Opponent countries faced = distinct opponent nationalities from rated games only (no nationality seed) |
| **H7** | Opponent countries beaten = distinct opponent nationalities with ≥1 win (no seed) |
| **H8** | Opponent countries beaten by = distinct opponent nationalities with ≥1 loss (no seed) |
| **H9** | Country token = `TRIM(value)`; empty/NULL excluded from game-derived sets |
| **H10** | HoF holders on `amiga_generalstats` + full `amiga_realm_snapshots` row |
| **H11** | Per-player scalars on `amiga_player_event_snapshots` + `amiga_player_current` |
| **H12** | Tie policy: strict `>`; equal → lowest `player_id`; year peak tie → earliest calendar year |
| **H13** | Writer boundary: tournament finalize + full replay only |

---

## HoF rows

| Label | Holder column | Player source |
|-------|---------------|---------------|
| Most games in one year | `MostGamesInOneYear` | `peak_year_games` |
| Most tournaments in one year | `MostTournamentsInOneYear` | `peak_year_tournaments` |
| Most tournaments (career) | `MostTournamentsPlayed` | `tournaments_played` |
| Most tournament wins | `MostTournamentWins` | `event_gold` |
| Most countries played in | `MostCountriesPlayedIn` | `countries_played_in` |
| Most opponent countries faced | `MostOpponentCountriesFaced` | `opponent_countries_faced` |
| Most opponent countries beaten | `MostOpponentCountriesBeaten` | `opponent_countries_beaten` |

> **`MostWcPlayed` migrated out (Jun 2026-29, SCH-046).** "Most World Cups played" now lives solely on the World Cup Hall of Fame store (`amiga_wc_hof_present` / `amiga_wc_hof_snapshots`) — see [`amiga-wc-hof-policy.md`](amiga-wc-hof-policy.md). It is no longer projected onto `amiga_generalstats` / `amiga_realm_snapshots`, and the legacy `MostWcPlayed*` DDL columns there were **dropped** — `028_hof_tournament_geo.sql` no longer adds them, plus an idempotent `information_schema`-guarded drop helper in `schema_bundles.py` retires them on existing DBs.

Date column: year peaks → calendar year (`peak_year_*_year`); career honours + geography → **per-metric last-rise** `tournament_id` + `event_date` on player rows (SCH-029) — [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md). `honours_last_event_date` remains last participation only (not HoF record dates).
