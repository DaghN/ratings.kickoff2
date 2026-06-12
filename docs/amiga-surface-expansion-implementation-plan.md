# Amiga surface expansion — implementation plan (agent slices)

**Status:** **Complete** (Jun 2026, slices 0–8). **Overview:** [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md)  
**Closure handoff:** [`orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md`](orchestration/agent-handoffs/2026-06-10-009-amiga-surface-expansion-slice-8.md)

**In scope:** Read-path PHP and thin leaderboard pages reusing derived tables from player-universe track (slices 0–14).  
**Out of scope:** New derived writers, DDL (unless user explicitly expands scope), milestones, match streaks, calendar play streaks, UTC league features, cross-realm H2H, `amiga_player_tournament_slice_totals`, live incremental matchup/generalstats on single-game finalize.

**Authority:** [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 (stored truth, no hot-path `amiga_games` aggregation on profile/LB).

---

## How to use this plan

1. User says **“Do slice N”** (or **“Continue with the next slice”**).
2. Agent completes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs the slice **Verification** before stopping.
4. Agent writes handoff: `docs/orchestration/agent-handoffs/2026-06-09-0XX-amiga-surface-expansion-slice-N.md` (increment `XXX`).
5. At **STOP gates**, agent lists browser checks and **waits** for user OK before the next slice.
6. **Do not git commit** unless the user asks.
7. **Do not** read or display `amiga_player_stats` streak columns in new PHP.

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| S1 | No new derived tables or rebuild writers in this track |
| S2 | No hot-path aggregation over `amiga_games` on profile or leaderboard pages |
| S3 | **No streaks wing** — match streak columns are not product truth offline |
| S4 | Profile `event_points` suffix rules unchanged — see contract §5.2.1 (omit for league+cup marathons and WCs) |
| S5 | WC finish on history/profile: **medal podium only** — not group `overall_position` |
| S6 | Perf rating LB (if shipped): minimum **2 games** in event; NULL for perfect 0%/100% — per [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| S7 | Port online LB wing **layout and column semantics** from `leaderboards/*.php`; Amiga SQL from `amiga_player_stats` + `amiga_player_base_from_sql()` |
| S8 | H2H v1: **realm-internal** pair page (two `amiga_players.id`); no cross-realm |

---

## Slice map (overview)

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Profile honours strip (`tournament_totals`) | — |
| **1** | Tier A LB wings (Goals, DDs, Victims, Peak) + `amiga_lb_nav` | **A** — profile + LB wings |
| **2** | HoF deep links to new LB wings | — |
| **3** | Top opponents goals + H2H pair page | **B** — opponents + H2H |
| **4** | `tournament.php` event stats from participation | **C** — tournament page |
| **5** | Perf rating profile highlight + best-event LB | **D** — perf surfaces |
| **6** | Profile moments block (stats `*GameID`) | **E** — profile moments |
| **7** | Honours LB podiums/cups + history filters + recent-tournament light enrich | **F** — honours + history |
| **8** | Documentation closure | — |

---

## Slice 0 — Profile honours strip

### Goal

Surface career tournament honours on `/amiga/profile.php` from `amiga_player_tournament_totals` (row already loaded).

### Tasks

- [ ] Render honours block: WC medals (if any), `tournaments_won`, `podiums`; optional `last_event_date` line
- [ ] Reuse existing load in `profile.php` — extend `amiga_profile_render_*` in `amiga_profile_blocks.php`
- [ ] Link to `/amiga/leaderboards/tournament-honours.php` and/or filtered `player-tournaments.php?filter=world-cup`
- [ ] Match feast spacing/typography of career strip

### Verification

- [ ] `python -m scripts.amiga verify-player-participation` (no regression)
- [ ] Manual: profile for a player with WC medals and one with none

### Files (expected)

- `site/public_html/includes/amiga_profile_blocks.php`
- `site/public_html/amiga/profile.php` (minimal if any)

---

## Slice 1 — Tier A leaderboard wings

### Goal

Ship four thin wings under `/amiga/leaderboards/` reading `amiga_player_stats` only.

### Tasks

- [ ] Add `goals.php`, `double-digits.php`, `victims.php`, `peak-rating.php` (mirror online column sets where applicable)
- [ ] Extend `includes/amiga_lb_nav.php` — wings: Rating, Goals, DDs & CSs, Victims, Peak rating, Tournament honours
- [ ] Point Rating wing at `/amiga/leaderboards/rating.php` (thin move from `/amiga/rating.php` or redirect — keep Ladder tab working)
- [ ] Shared player link helper: `k2_amiga_player_link()` / `amiga_player_base_from_sql()`
- [ ] Hub copy: Honours tab may land on tournament-honours or a leaderboard index — keep `amiga_hub_nav.php` consistent

### Verification

- [ ] Existing verify suite (all four commands)
- [ ] Spot-check sort orders vs online wings for one metric each

### STOP GATE A

User checks in browser:

- Profile honours strip (slice 0)
- Each new LB wing loads, sorts, links to profiles
- Ladder hub tab still reaches rating leaderboard

---

## Slice 2 — HoF deep links

### Goal

Wire HoF ratio/career rows to new LB wings where online has parity.

### Tasks

- [ ] Extend `includes/amiga_records_hof_links.php` — map metrics to wing URLs + `k2_sort` params
- [ ] Update `amiga/hall-of-fame.php` rows that currently show `-` or only rating deep links (DD %, CS %, goals extremes if applicable)

### Verification

- [ ] Click HoF “full leaderboard” / value links land on correct wing sort

---

## Slice 3 — Top opponents + H2H pair page

### Goal

Complete the H2H read path started in player-universe slice 10.

### Tasks

- [ ] Top opponents table: add goals (GF – GA) from `amiga_player_matchup_summary`
- [ ] New page e.g. `/amiga/h2h.php?id1=&id2=` — both directions not required; show directed row for id1 vs id2 + reverse summary line or single canonical ordering
- [ ] Validate both IDs exist; 404 otherwise
- [ ] Optional: link to `games.php` with opponent filter if games lib supports it without new aggregation
- [ ] Link from top opponents rows to H2H page

### Verification

- [ ] `python -m scripts.amiga verify-player-matchups`
- [ ] Pair with most games matches summary row

### STOP GATE B

User checks: top opponents goals column; H2H page for two known rivals; links work.

---

## Slice 4 — Tournament page event stats

### Goal

Add participation-backed per-player event stats on `/amiga/tournament.php` without scanning all games on load.

### Tasks

- [x] Read helper: participation rows for `tournament_id` (new function in `amiga_player_tournament_lib.php` or `amiga_tournament_lib.php`)
- [x] UI: tab, section, or expandable “Event stats” table — W-D-L, GF/GA/GD, GF/g, GA/g, Pts, rating columns, **Perf. rating** per [`amiga-performance-rating.md`](amiga-performance-rating.md) tooltips
- [x] Join player names from `amiga_players`; respect public visibility like participation reads
- [x] WC rows: medal column; do not mislabel group rank as finish (`wc_medal` / `event_finish_position` policy)

### Verification

- [x] Compare one marathon event row to same player on `player-tournaments.php`
- [x] Verify suite

### STOP GATE C

User checks: Athens-style league+cup event; WC event; knockout-only cup.

---

## Slice 5 — Performance rating discovery

### Goal

Surface perf rating beyond per-player history sort.

### Tasks

- [x] Profile: compact “Best event perf” / “Recent perf” line from participation (max `performance_rating` with games ≥ 2, or last finalized event)
- [x] New LB e.g. `/amiga/leaderboards/performance-rating.php` — best single-event perf rating (tie-break rules documented in handoff)
- [x] Add wing to `amiga_lb_nav.php` if shipped
- [x] Tooltips from `amiga-performance-rating.md`

### Verification

- [x] Spot-check player with known strong event vs `amiga_rating_events`
- [x] `verify-player-participation` (rating join invariant)

### STOP GATE D

User checks profile highlight + perf LB; NULL cases show em dash.

---

## Slice 6 — Profile moments block

### Goal

Trophy games from `amiga_player_stats` game-id pointers — single-game fetches only.

### Tasks

- [x] Extend `amiga_player_load.php` or dedicated moments loader for needed `*GameID` columns + opponent names
- [x] `amiga_profile_render_moments()` — reuse online CSS classes from `player-feast.css` where sensible
- [x] Cards: biggest win, most goals in one game, peak rating game (subset — match online feast restraint)
- [x] Link each moment to `/amiga/games.php` or game detail if exists

### Verification

- [x] Player with known `MostGoalsScoredGameID` shows correct scoreline
- [x] No full-table scans in page load path

### STOP GATE E

User checks moments on 2–3 profiles.

---

## Slice 7 — Honours, filters, recent tournaments polish

### Goal

Close remaining **ready** items from overview §3.7–§3.9.

### Tasks

- [x] Honours LB: add `podiums`; optional `cup_gold/silver/bronze` columns if table width acceptable
- [x] `player-tournaments.php`: filter pills for cups (`is_cup`) and/or `country` (query param + `amiga_player_tournament_participation_filter_events` extension)
- [x] Recent tournaments: light enrich — e.g. perf rating or winner badge; **do not** break event_points suffix policy

### Verification

- [x] Filters reduce row set correctly on sample player
- [x] Honours LB sort by podiums

### STOP GATE F

User checks filters + honours columns + recent tournament lines.

---

## Slice 8 — Documentation closure

### Goal

Register shipped surfaces; point deferred work to overview §4.

### Tasks

- [x] Update `docs/amiga-profile-v0.md` — new blocks and routes
- [x] Update `docs/amiga-player-universe-contract.md` §4 surfaces register (mark shipped/deferred)
- [x] Update `docs/amiga-realm-vision.md` backlog line (Tier A wings shipped)
- [x] Update `docs/amiga-performance-rating.md` read paths if slice 5 shipped
- [x] Handoff summary + full verify suite output

### Verification

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

---

## Handoff template

Each slice handoff should include:

- Goal (one sentence)
- Checklist mirrored from this plan
- Files changed
- Verification command output (pass/fail)
- Browser notes for any STOP gate
- Known limitations / follow-ups → overview §4 Potential

---

## Explicitly deferred (document only — not in slices 0–8)

- `amiga_player_tournament_slice_totals`
- Tournament games tab (scoped `amiga_games`)
- `performance_rating − rating_before` column
- Live incremental matchup/generalstats on finalize
- Tier C activity tables
- Cross-realm H2H API

See [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4.
