# Amiga data contract

**Purpose:** One canonical description of how `ko2amiga_db` is structured — ground truth vs derived truth vs reference parity — and how import, replay, and the website read path must behave.

**Database:** `ko2amiga_db` only. Separate from online `kooldb*` / `ko2unity*`. No cross-realm player linking.

**Online analogue:** [`website-data-contract.md`](website-data-contract.md) — same *philosophy* (replay = live simulation), much smaller scope.

---

## Authority map

| Topic | Document |
|--------|----------|
| Access inventory, quirks, chronology | [`amiga-schema-discovery.md`](amiga-schema-discovery.md) |
| **Import layer** (archival → ground truth) | [`amiga-import-layer.md`](amiga-import-layer.md) |
| **Ground layers L0–L5** (koatd → mirror → prune → witness → structure → product) | [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) · plan [`amiga-ground-layers-implementation-plan.md`](amiga-ground-layers-implementation-plan.md) |
| Chronology fix | [`amiga-chronology-fix-plan.md`](amiga-chronology-fix-plan.md) |
| Profile / games UI (v0) | [`amiga-profile-v0.md`](amiga-profile-v0.md) |
| **Realm vision & roadmap** (inventory, hub IA, phases) | [`amiga-realm-vision.md`](amiga-realm-vision.md) |
| **Tournament format system** (legacy phases → templates/fixtures vision) | [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) · handoff [`amiga-tournament-format-handoff-prompt.md`](amiga-tournament-format-handoff-prompt.md) |
| **Tournament structure** (stage types, legacy materialize, modules vs graph) | [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) · plan [`amiga-tournament-structure-implementation-plan.md`](amiga-tournament-structure-implementation-plan.md) |
| **Tournament finalize & rating events** (commit boundary, replay oracle) | [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) · plan [`amiga-tournament-finalize-implementation-plan.md`](amiga-tournament-finalize-implementation-plan.md) |
| **Player universe** (derived expansion, participation, honours, H2H) | [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) · slices [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md) |
| **Event performance rating** (TPR per tournament) | [`amiga-performance-rating.md`](amiga-performance-rating.md) |
| **Historical rating ladder snapshots** (V1 shipped) | [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) · plan [`amiga-rating-history-implementation-plan.md`](amiga-rating-history-implementation-plan.md) |
| **Event snapshots** (canonical player timeline + `amiga_player_current`) | [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · plan [`amiga-event-snapshot-implementation-plan.md`](amiga-event-snapshot-implementation-plan.md) |
| **Time travel** (realm-wide `as=` lens; phase 1 **shipped** — LB 8 wings, HoF; **T19** fixed mode-toggle homes; ribbon for in-lens time) | [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · plan [`amiga-time-travel-implementation-plan.md`](amiga-time-travel-implementation-plan.md) · smoke [`scripts/oneoff/amiga_time_travel_smoke.php`](../scripts/oneoff/amiga_time_travel_smoke.php) |
| **Community stats** (realm-wide Activity aggregates; separate from HoF) | [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) · v1 [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) · v2 catalog [`amiga-community-stats-catalog-plan.md`](amiga-community-stats-catalog-plan.md) |
| **Where to store player×event derived stats** | [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.0 |
| Staging deploy | [`amiga-staging-handoff.md`](amiga-staging-handoff.md) |
| **Live ops platform** (staging authority, repair, media, lanes A/B/C) | [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) |
| Import + replay commands | [`scripts/amiga/README.md`](../scripts/amiga/README.md) |
| DDL (current) | [`scripts/amiga/schema_bundles.py`](../scripts/amiga/schema_bundles.py) · bundles [`sql/ground/`](../scripts/amiga/sql/ground/), [`structure/`](../scripts/amiga/sql/structure/), [`derived/`](../scripts/amiga/sql/derived/) |

This document owns **layer definitions**, **table register**, **post-game/replay rules**, and **read-path policy**. It does not duplicate Access discovery or page mockups.

---

## Data layers

Archival Access (`koatd.mdb`) is **L0 input**, not website ground truth. **L3 import** reads **L2 pruned witness SQL** only (strict stack — [`amiga-ground-stack.md`](amiga-ground-stack.md)). Import applies documented transforms (see [`amiga-import-layer.md`](amiga-import-layer.md)) and writes audit output to `data/amiga/exports/import_manifest.json`.

**Pipeline:** **L0** koatd · **L1** full mirror · **L2** prune + `witness_player_identity` · **L3** witness · **L4** structure · **L5** product — [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md). This section describes **L3–L5** as stored in `ko2amiga_db`.

### 1. Ground truth (L3 witness)

Canonical facts in MySQL after **import** or **future live submission** — never written by replay.

| Fact | Notes |
|------|--------|
| Tournament catalog | Names, dates, chrono, verbatim Access cup flag, country, format template + league/cup flags |
| Match results | Players, goals, tournament, phase |
| Player identity | Name, country — registry from **L2 games scan** + merges; nationality from L2 **`witness_player_identity`**; not `added_players` |
| Tournament host country | From L2 `Tournament players`; L3 WC overrides in `import_corrections.py` |
| Curated claims | Tier E finish overrides (`amiga_tournament_finish_override`) — manifest-audited |
| Provenance | `source_scores_id`, `source_id` where applicable |

Replay may **read** ground truth; it must not invent or overwrite canonical match facts. Replay game order follows § Chronology (`ORDER BY game_date ASC, id ASC`).

### 2. Structure overlay (L4)

Stages, fixtures, entrants, lifecycle — **not** per-game Elo. Authority: [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md). `amiga_games.phase` is **witness** (koatd label), not structure authority.

### 3. Derived truth (L5)

Computed from ground truth by chronological replay or per-game ops. **Always rebuildable** from canonical games in order.

| Fact | Notes |
|------|--------|
| Per-game Elo | Ratings before/after, adjustments, outcome flags |
| Player career stats | W/D/L, goals, peaks, opponent networks — **not match streaks** (see § Match streaks; columns exist but are not product truth) |
| Tournament standings | Points tables, group tables — from games via `scripts/amiga/tournament_standings.py` |
| Future aggregates | H2H summaries, period activity, etc. — when needed |

**Rule:** After one new canonical game, derived tables must match what a full replay from empty would produce.

### 4. Reference truth (parity only — L1 mirror)

Legacy Access precomputes. **Neither L3 ground nor L5 derived.** Read from **L1** full mirror or live `.mdb` for parity tooling.

| Source (Access) | Use |
|-----------------|-----|
| `Tables`, `World Cup * Tables`, … | Tournament standing parity |
| `Rankings` monthly grid | Elo history parity (optional) |
| `added_players` | Career-total spot checks |

Reference data is **never** written by post-game or replay. Store in `data/amiga/exports/` or optional `reference_*` tables loaded by one-off tooling — not in the website hot path.

---

## Chronology (ground truth)

Access has no per-game timestamp. **Import sort key** (walk only — not used at read time):

1. `tournaments.event_date` ASC
2. `tournaments.chrono` ASC (same-day tie-break, e.g. cup/main pairs)
3. `source_scores_id` ASC within the same tournament

**Synthetic `game_date` on each game row:**

- Base = parent tournament `event_date` at UTC midnight
- **Running second counter per calendar day** across the sorted walk (does not reset when tournament changes on the same day)
- After import: `id` (insert order) and `game_date` are the canonical sequence

**Read path** (replay, API, ops, charts):

```sql
ORDER BY g.game_date ASC, g.id ASC
```

`tournaments.chrono` remains imported metadata for import tie-breaks and catalog display — not for replay or API game walks. Verify: `python -m scripts.amiga verify-chronology`. Spec history: [`amiga-chronology-fix-plan.md`](amiga-chronology-fix-plan.md).

### Match streaks — off the table (product policy)

**Do not surface match streaks anywhere in the Amiga realm** — leaderboard wing, Hall of Fame rows, profile panels, or APIs.

**Why:** Access has no per-game timestamp. Import assigns a **synthetic** within-day order (`running second counter` on `game_date`; tie-break `id` / `source_scores_id` within tournament). That sequence is correct enough for **Elo**, cumulative **W/D/L**, goals, peaks, and opponent networks, but it is **not** the real order matches were played on tournament day. Consecutive-win / draw / loss streaks depend on that unknown order, so any `Longest*` or current `*Streak` value is **arbitrary**, not a historical fact.

**What agents should know:**

| Topic | Policy |
|--------|--------|
| **Website / hub** | **Skip** streaks leaderboard wing; **omit** HoF longest-streak records; **no** profile “moments” for streaks |
| **Calendar play streaks** (`player_play_streaks`, day/week) | Also **skip** — offline batch play ≠ UTC daily habit |
| **`amiga_player_stats` columns** | `WinningStreak`, `LongestWinningStreak`, `LongestNonLossStreak`, etc. still exist — shared `PlayerState` / replay engine writes them for schema parity with online. Treat as **non-authoritative for Amiga product**; do not read them in PHP templates or new features |
| **Removing columns / stopping replay writes** | Not required for v1; product simply never displays them. A future cleanup could zero or drop streak writers if desired |

Roadmap detail: [`amiga-realm-vision.md`](amiga-realm-vision.md) § Leaderboard wings (Streaks), § Hall of Fame.

---

## Post-game / replay

**Authoritative contract:** [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) — global derived state commits at **tournament finalize**, not per game.

**Batch oracle (implemented Jun 2026):**

```
import (ground only)  →  python -m scripts.amiga replay
                         →  tournament-order finalize_tournament(T) for each event
                         →  amiga_game_ratings + amiga_player_event_snapshots + amiga_player_current
```

- **Elo:** start 1600, K=32; **frozen within-event** ratings at batch start; per-game adjustments on `amiga_game_ratings`; event rating block + career/honours on snapshots/current at tournament finalize
- **Rating authority:** Python replay + `amiga_player_event_snapshots` event rating columns — never legacy Access `Rankings`
- **Connection:** `SET time_zone = '+00:00'` before period/date logic

**Derived sign-off (L5 + verify):**

```bash
python -m scripts.amiga prove   # nuclear reset + replay + verify (only supported path)
```

Step-by-step equivalent: `import --recreate-schema` → `replay` → verify CLIs. Wrong derived state → **`prove` again**, not repair jobs. **Derived write policy:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md). Incremental `import` / manual `014–023` are archived — [`scripts/amiga/sql/archive/incremental/README.md`](../scripts/amiga/sql/archive/incremental/README.md).

Full `replay` (~27k games): **~180s local** (Jun 2026) — each tournament finalize writes `amiga_game_ratings`, snapshots/current, matchup at-event + summary, and network/peaks from cumulative pairs. No end-of-replay tail batches.

Live `finalize-tournament` (PHP or Python CLI): warms cumulative matchups from `amiga_player_matchup_summary`, applies event games, derives network/peaks at finalize — no full-history rescan.

`replay --limit N` finalizes tournaments until **≥ N games** are covered (not N tournaments).

**Live ops:** result entry writes **ground truth + standings only** (`fixtures.php`, `amiga_ops_process_derived_for_game`). Global rating commit is **`finalize-tournament` only** (PHP + Python). PHP `process-one` **hard-fails** for tournament-tagged games; PHP `replay-to` is **removed** — use `python -m scripts.amiga replay`.

**Batch oracle:** `python -m scripts.amiga replay` → tournament-order finalize + `verify-rating-events`. Numeric ratings **intentionally differ** from the old per-game sequential model.

---

## Read path (website)

Pages read through **Amiga PHP helpers** in `site/public_html/includes/amiga_*.php` — not raw storage tables in templates.

| Helper | Role |
|--------|------|
| `amiga_player_load.php` | Profile hero + career strip |
| `amiga_db.php` | Join ground + derived for read queries |
| `amiga_player_games_lib.php` | Games list filters/pagination |
| `api/player_rating_history.php?realm=amiga` | Rating chart JSON |

**Do not** add SQL views named `ratedresults` / `playertable` to fake the old shape. Join logic lives in `amiga_db.php` only.

---

## Table register

### Current (Phase A2 — split)

| Table | Layer | Writer | Status |
|-------|-------|--------|--------|
| `tournament_format_templates` | Ground/config | Import seed / future admin-managed templates | Active |
| `tournaments` | Ground | Import / submission | Active |
| `tournament_entrants` | Ground | Future live tournament ops / fixture tooling | Active |
| `tournament_stages` | Ground | Live + legacy module atoms (`round_robin` \| `knockout`) | Active |
| `tournament_stage_players` | Ground | Stage rosters (live ops) | Active |
| `tournament_fixtures` | Ground | One match per row (live schedule or legacy materialize); `stage_id` → module; **running scores** `goals_a`/`goals_b`/`extra`/`result_recorded_at` until Make official (DDL `sql/structure/006_tournament_fixtures.sql` via `prove`) | Active |
| `amiga_players` | Ground | Import / browser organizer create / `players create` CLI (`player_source`: `import` \| `live_ops`) | Active |
| `amiga_games` | Ground | Import / submission | Active |
| `amiga_game_ratings` | Derived | Tournament finalize (`finalize_tournament` / `replay`) — per-game facts, not global rating commit | Active |
| `amiga_player_event_snapshots` | Derived | Tournament finalize / `replay` — sparse timeline (event-local + career + honours + rating block + **`elo_rank`** + **`peak_rating_tournament_id`** / **`lowest_rating_tournament_id`** + geo/year scalars + **HoF rise dates** per [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) · [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md)) | **Active** |
| `amiga_player_elo_rank_at_event` | Derived | Tournament finalize / `replay` — one row per (player, tournament) with ladder rank after that event (all players with games > 0; **`peak_elo_rank`** + **`peak_elo_rank_tournament_id`** running career best; supports TT hero + rank chart + peak-rating LB) | **Active** |
| `amiga_player_current` | Derived | Tournament finalize / `replay` — present projection (career + honours + geo/year scalars + rise dates + **`peak_elo_rank`** denorm); **no `wc_*`** (WC → slice tables) | **Active** |
| `amiga_player_slice_totals` | Derived | Tournament finalize / `replay` — present WC career stats per player (`slice_key = 'world_cup'`). V1 honours/results sums + **V2** goal texture, DD/CS, network/geo (`039`). Policy [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) · V2 [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md) | **Active** |
| `amiga_player_slice_at_event` | Derived | Tournament finalize / `replay` — WC slice timeline at each participated event (sparse: only players with `tournaments_played > 0`); same V1+V2 column set as totals | **Active** |
| `amiga_country_slice_totals` | Derived | Tournament finalize / `replay` — present WC career stats per nation (`slice_key = 'world_cup'`). Policy [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md). DDL `040` | **Active** |
| `amiga_country_slice_at_event` | Derived | Tournament finalize / `replay` — country WC slice timeline at each WC finalize cutoff (eligible nations: ≥1 WC player) | **Active** |
| `amiga_rating_events` | Derived | **Retired slice 8** — replaced by snapshot event rating block | Retired |
| `amiga_player_stats` | Derived | **Retired slice 8** — replaced by `amiga_player_current` | Retired |
| `amiga_player_tournament_participation` | Derived | **Retired slice 8** — event-local block on snapshots | Retired |
| `amiga_player_tournament_totals` | Derived | **Retired slice 8** — honours on `amiga_player_current` | Retired |
| `amiga_tournament_standings` | Derived | Per-tournament finalize (`rebuild_standings_for_tournament`) or standings rebuild on result entry | Active |
| `amiga_tournament_catalog_stats` | Derived | Tournament finalize / `replay` (`refresh_catalog_stats_for_tournament`) | Active |
| `amiga_tournament_finish_override` | **L3 witness** (curated) | Manual import / ops; Tier E historical claims. DDL `sql/ground/002_tournament_finish_override.sql` | Active |
| `amiga_player_matchup_at_event` | Derived | Tournament finalize — cumulative directed pair stats (+ SCH-031 extremes) as of each participated event. Read: future Opponents wing at cutoff | **Active** |
| `amiga_player_matchup_summary` | Derived | Tournament finalize (`upsert_matchup_summary`); SCH-031 goal extremes. Read: future Opponents wing | **Active** |
| `amiga_realm_snapshots` | Derived | Tournament finalize / `replay` — full HoF record-book payload per finalized event. Policy [`amiga-realm-snapshot-policy.md`](amiga-realm-snapshot-policy.md). Realm headline totals on community stats (`035` dropped legacy aggregate cols) | **Active** |
| `amiga_generalstats` | Derived | Tournament finalize / `replay` — present projection (latest realm snapshot). Ratio leaders on row. Read: `/amiga/hall-of-fame.php` (career block) | **Active** |
| `amiga_wc_hof_snapshots` | Derived | WC finalize only — full WC HoF payload (28 records) per World Cup `tournament_id`. Policy [`amiga-wc-hof-policy.md`](amiga-wc-hof-policy.md) | **Active** |
| `amiga_wc_hof_present` | Derived | Present projection of latest WC HoF snapshot (`id = 1`). Same policy | **Active** |
| `amiga_community_stats` | Derived | Tournament finalize / `replay` — present headline community scalars (`id = 1`); v2 extension cols (`036`: tournaments finalized, host countries, WC games, pairs, debuts). Policy [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) | **Active** |
| `amiga_community_stats_snapshots` | Derived | Tournament finalize / `replay` — headline scalars per finalized `tournament_id` (includes v2 extension cols) | **Active** |
| `amiga_community_stat_facts` | Derived | Tournament finalize / `replay` — period × slice × metric facts per `tournament_id` (v1 + v2 registry grains; **Jul 2026** nationality player grains incl. **`wc_active_players`** for Q-WC-006/007 tooltips — no DDL) | **Active** |
| `amiga_world_cup_stats` | Derived | Tournament finalize / `replay` — one wide row per World Cup `tournament_id`. Policy [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md). DDL `037` | **Active** |
| `reference_*` (optional) | Reference | Parity tooling only | — |

DDL bundles: [`schema_bundles.py`](../scripts/amiga/schema_bundles.py) — `sql/ground/` (**L3**), `sql/structure/` (**L4**), `sql/derived/` (**L5**). Archived flat files and incremental `010–023`: [`sql/archive/incremental/README.md`](../scripts/amiga/sql/archive/incremental/README.md). Fresh schema = `python -m scripts.amiga prove`.

### HoF record rise dates (SCH-029)

Ten nullable columns on **`amiga_player_event_snapshots`** and **`amiga_player_current`** (identical set): per metric `{metric}_last_rise_tournament_id` + `{metric}_last_rise_event_date` for `tournaments_played`, `event_gold`, `countries_played_in`, `opponent_countries_faced`, `opponent_countries_beaten`. **`wc_played` rise retired** — `tournaments_played_last_rise_*` on **`amiga_player_slice_*`** (SCH-033). DDL: `sql/derived/029_hof_record_rise_dates.sql`.

### Career cumulative rise dates (SCH-030)

Twenty nullable columns on **`amiga_player_event_snapshots`** and **`amiga_player_current`** (identical set): per career scalar `{prefix}_last_rise_tournament_id` + `{prefix}_last_rise_event_date` for `number_games`, `number_wins`, `goals_for`, `double_digits`, `clean_sheets`, `different_opponents`, `different_victims`, `double_digits_victims`, `clean_sheets_victims`, `biggest_rating_ascent`. DDL: `sql/derived/030_career_rise_dates.sql`.

| Writer | Path |
|--------|------|
| Honours rise | `scripts/amiga/honours_totals.py` · `site/public_html/amiga/ops/includes/amiga_honours_totals_lib.php` |
| Geo rise | `scripts/amiga/player_geo_year.py` · `site/public_html/amiga/ops/includes/amiga_player_geo_year_lib.php` |
| Career rise | `scripts/amiga/career_rise.py` · `site/public_html/amiga/ops/includes/amiga_career_rise_lib.php` |
| Snapshot persist | `snapshot_persist.py` · `amiga_event_snapshot_persist.php` |
| HoF `*Date` projection | `realm_incremental.py` · `amiga_realm_incremental_lib.php` from holder’s `*_last_rise_event_date` |

`honours_last_event_date` / `honours_last_tournament_id` stay **last participation** only. Year-peak HoF dates unchanged (`peak_year_*_year`). Legacy career HoF rows (`MostGamesPlayed`, …) use rise dates per SCH-030 (not `record_date`). Verify: `verify-hof-geo-year` + `verify-hof-holder-projection` + `verify-stored-id-date-pairs` in `prove`. Policy: [`amiga-hof-record-date-policy.md`](amiga-hof-record-date-policy.md) D1–D13. **Broader id/date guardrails:** [`amiga-stored-field-semantics-plan.md`](amiga-stored-field-semantics-plan.md) (phases A–C) · manifest [`amiga-stored-field-semantics.md`](amiga-stored-field-semantics.md).

### Tournament format metadata

- `tournament_format_templates` is canonical format/config metadata in `ko2amiga_db`, not an Access import table. Import seeds stable template slugs, including `legacy_inferred` for historical events and starter templates for future live tournament creation.
- `tournaments.format_template_id` points to the selected template. Legacy imports default to `legacy_inferred`; future live events may use concrete templates such as `kitchen_marathon`, `group_knockout`, or `world_cup_class`.
- `tournaments.has_league` and `tournaments.has_cup` are **non-exclusive** ground catalog flags computed at import from canonical game phase labels plus the verbatim Access `is_cup` flag. A tournament with games must have at least one of these flags true; verify with `python -m scripts.amiga verify-tournament-formats`.
- `tournaments.is_cup` remains the raw imported Access `Cup?` value. Do not use it as the product definition of cup play or honours eligibility.

### Tournament lifecycle

- `tournaments.lifecycle_status` is **ground truth** for whether an event is draft, in preparation, running, finished, archived, or void. Statuses: `draft`, `registration`, `ready`, `running`, `completed`, `archived`, `void`.
- `tournaments.started_at` and `tournaments.completed_at` are nullable UTC timestamps set on transition to `running` and `completed`/`archived` respectively (when not already set).
- **Defaults:** Access import sets `lifecycle_status = completed` with `completed_at` from `event_date`. Internal builders and `/amiga/ops/fixtures.php` kitchen create set `draft` so generated events do not look like historical imports.
- **Result entry:** fixture-backed result entry (`fixtures record-result`, browser ops) is allowed only when `lifecycle_status = running`. Refused for `completed`, `archived`, and `void`.
- **Ops (CLI):** `python -m scripts.amiga fixtures set-tournament-status --tournament-id N --status STATUS` with optional `--dry-run` and `--force`. Imported historical tournaments refuse transitions away from `completed`/`archived` without `--force`. Transition to `completed` refuses when scheduled fixtures remain unplayed unless `--force`.
- **Ops (browser):** password-gated `/amiga/ops/fixtures.php` (Setup tab) shows organizer-friendly status labels (`Not started` for `draft`/`registration`, `Ready to start` for `ready`, `In progress` for `running`, `Finished` for `completed`/`archived`, `Void` for `void`) with **Start tournament**, **Mark complete**, and secondary **Void tournament** actions that route through the same guardrails: `draft`/`registration`→`ready`→`running` on start, `ready`→`running`, `running`→`completed` when no scheduled fixtures remain, `running`→`void` when no games exist. Raw `lifecycle_status` values and single-step transitions remain on the Advanced tab for operators. Imported Access tournaments refuse all browser lifecycle changes. No `--force` equivalent in the browser; use CLI for forced transitions.
- **Verify:** `python -m scripts.amiga fixtures verify-lifecycle` (imported rows must be `completed` or `archived`; generated rows with games must not stay in `draft`/`registration`/`ready`).
- **Public historical read path:** `/amiga/tournaments.php` and `/amiga/tournament.php` list or load only tournaments with `lifecycle_status IN ('completed', 'archived')`. Generated events in `draft`, `registration`, or `ready` are ops-only. Player profile recent-tournament links use the same historical filter (`AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES` in `includes/amiga_tournament_lib.php`).
- **Public live read path:** `/amiga/live-tournaments.php` (index) and `/amiga/live-tournament.php?id=N` (detail) are **read-only**. Eligibility (Jul 2026 — **no config allowlist**):
  1. `lifecycle_status = running` only — not `draft`, `registration`, `ready`, `completed`, `archived`, or `void`.
  2. Fixture-backed generated structure: `source_id IS NULL`, at least one `tournament_stages` row, and `format_overrides.generated_by` prefix matching approved fixture tooling (`scripts.amiga.tournament_builder` or `site.public_html.amiga.ops.fixtures`).
  - **Visibility rule:** **Start tournament** in ops ⇒ `running` ⇒ appears on the Live hub automatically. Setup (`draft`/`ready`) stays off public pages. No hide toggle in v1.
  - **Make official (finalize):** organizer **Make official** on Table tab (browser) or `finalize-tournament` (CLI) commits L5 derived truth (ratings, event snapshots, chronology N→N+1). Community-facing label; same verb as `rating_finalized` on `tournaments`.
  - **After complete:** when lifecycle moves to `completed`/`archived`, the event leaves the Live hub and appears on the historical tournament catalog (`/amiga/tournament.php`) per lifecycle filter above.
  - **Display:** lifecycle metadata, date/country, registered entrants (or stage players fallback), fixtures grouped by stage with player links, regulation scores for played fixtures, muted void rows. No result entry, lifecycle controls, or fixture assignment.
  - **Ops boundary:** public pages must not embed the ops password or password-bearing ops URLs. Operators use `/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot` (**password-only gate** — create or pick a league inside; optional `tournament_id=` deep link preserved when already in URL). Live index may link to that path without `pwd=`.
  - **Retired (Jun 2026):** `AMIGA_PUBLIC_LIVE_TOURNAMENT_IDS` / `$amigaPublicLiveTournamentIds` config allowlist — replaced by start=public policy Jul 2026.

### Tournament entrants, stages, and fixtures

- `tournament_entrants` is **tournament-level registration ground truth** for future live events: one row per player per tournament with seed, status (`registered`, `withdrawn`, `replaced`), and optional admin `note`. Player display names remain canonical in `amiga_players`; `display_name_snapshot` is deferred to avoid drift on rename. Legacy Access imports leave entrants empty; internal builders populate entrants before stage players.
- Verify entrant integrity with `python -m scripts.amiga fixtures verify-entrants` (stage players and fixture participants must be active `registered` entrants). List with `python -m scripts.amiga fixtures list-entrants --tournament-id N`.
- `python -m scripts.amiga fixtures backfill-entrants` conservatively inserts missing `registered` entrants for tournaments generated by approved fixture tooling (`format_overrides.generated_by` prefixes `scripts.amiga.tournament_builder` or `site.public_html.amiga.ops.fixtures`). It preserves existing entrant rows (including `withdrawn` / `replaced`), does not touch imported Access tournaments, and supports `--tournament-id N` and `--dry-run`.
- `python -m scripts.amiga fixtures withdraw-entrant` marks a `registered` entrant as `withdrawn` for generated tournaments only. It refuses when the player has tournament games or played fixtures; for scheduled unplayed fixtures it clears that player's slot and removes them from stage players so `verify-entrants` stays green. Supports `--note TEXT` and `--dry-run`.
- `python -m scripts.amiga fixtures replace-entrant` marks the old entrant `replaced`, inserts the new player as `registered` (reusing the old seed), updates scheduled unplayed fixtures and stage players, and refuses when the old player has games or played fixtures. Does not create players. Supports `--note TEXT` and `--dry-run`.
- `python -m scripts.amiga fixtures add-entrant` registers an existing `amiga_players` row as a `registered` tournament entrant for generated tournaments only. Allowed when `lifecycle_status` is `draft`, `registration`, or `ready`. Refuses imported Access tournaments, duplicate active entrants, and `withdrawn` / `replaced` rows (no silent reactivation). Supports `--seed-no`, `--note TEXT`, and `--dry-run` (no persistence).
- `python -m scripts.amiga fixtures onboard-newcomer` atomically creates a newcomer via KOA naming checks and registers them as an entrant. Provide either `--name` (explicit canonical name, validated through `players check-name`) or `--full-name` (first available KOA-style suggestion), not both. Uses the same tournament/lifecycle/duplicate guardrails as `add-entrant`. If entrant registration fails, the new player row is rolled back. Does not insert `tournament_stage_players`. Supports `--country`, `--seed-no`, `--note TEXT`, and `--dry-run`.
- `python -m scripts.amiga fixtures add-stage-player` (alias: `place-entrant`) inserts or updates `tournament_stage_players` for a registered entrant on generated tournaments only. Allowed when `lifecycle_status` is `draft`, `registration`, or `ready`. Refuses imported Access tournaments, non-entrants, and `withdrawn` / `replaced` entrants. Does not auto-create players, entrants, or fixtures. Supports `--seed-no`, `--group-key`, and `--dry-run` (no persistence). Use after `add-entrant` or `onboard-newcomer` to place a late entrant into a stage before fixture assignment.
- `tournament_stages` and `tournament_fixtures` are **ground** for both live and legacy tournament structure once materialized. Legacy Access imports leave them empty until structure apply runs.

- **Import closure (shipped Jun 2026):** `python -m scripts.amiga run` / `prove` runs L3 witness then L4 `apply-structure --from-disposition` from `disposition_register.json`. See [`amiga-tournament-structure-handlers.md`](amiga-tournament-structure-handlers.md) and [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) §8.
- **Stage / fixture / game chain** (policy [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) §1, T8–T9, T14): **`tournament_stages`** = module atom (RR scope or KO tie); **`tournament_fixtures`** = exactly **one match → one result** (never a multi-leg bundle in one row); **`amiga_games`** = score row linked via `fixture_id`. Pointer chain: `game.fixture_id → fixture.stage_id → stage`. Scores live on games; match identity on fixtures; module physics on stages. **Two-leg KO:** one KO stage + two fixtures + two games; `leg_no` orders legs within the tie.
- **Live path (RTB shipped Jul 2026):** software creates scheduled fixtures first; `fixtures record-result` (or browser ops Results tab) writes **running result columns** on `tournament_fixtures` only — **zero** `amiga_games` until **Make official**. Make official runs `promote_running_tournament` (batch L3 insert + `chrono` if null) then existing `finalize_tournament` derive. Policy [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md).
- **Legacy path:** imported games are ground truth; materialize creates one fixture per game, assigns fixtures to stages, sets `fixture_id`. Same tables — different creation order (T9).
- `amiga_games.fixture_id` is nullable until materialize or live result entry. Unlinked games use `tournament_phases.py` standings fallback.
- Fixture attachment must preserve canonical game facts: tournament ids must match, and fixture players must match the game players when both fixture players are known. Verify integrity with `python -m scripts.amiga fixtures verify` (also flags fixture-backed games whose players are not active `registered` entrants).
- `python -m scripts.amiga fixtures attach-game` links an existing unattached `amiga_games` row to a scheduled fixture. Requires `lifecycle_status = running`, both game players to be active (`registered`) tournament entrants, fixture players already assigned and matching the game (unordered pair), no prior `amiga_games.fixture_id`, no game already on the target fixture, and fixture status `scheduled` (refuses `played` and `void`). On success sets `amiga_games.fixture_id` and marks the fixture `played`. Supports `--dry-run` (no persistence). Does not auto-fill fixture players; use `set-players` first when slots are empty.
- `tournament_stages.stage_type` is **`round_robin`** or **`knockout`** only (migration `023`). Structure graph references **`stage_id`** and reads module outcomes (RR ranks, tie winners); tournament-level `event_finish_position` is derived separately (honours rules).
- **Legacy materialize (policy v2, slice 3b+):** `python -m scripts.amiga tournament-structure materialize --tournament-id N` — tier A NULL-phase complete k-leg RR (`round_robin_legs()`); tier C refuses (`needs_structure_review`, `STRUCTURE_REVIEW` / `NON_WC_STRUCTURE_REVIEW_IDS`); labeled phases bucket RR scopes / per-tie KO stages; one fixture per game; `dematerialize` for rollback. **Slice 6:** non-WC tier B bulk allow-list only (`tier_b_non_wc_register.py`); World Cups deferred to WC track. See structure policy T11–T13.
- Standings scope resolution prefers fixture metadata when `amiga_games.fixture_id` is present: `round_robin` stages feed `league` standings scopes (empty `scope_key` when `stage_key` is `overall`), and `knockout` stages feed per-pair `knockout` scopes. If `fixture_id` is NULL, `scripts/amiga/tournament_phases.py` remains the legacy parser.
- Public tournament-builder UI is deferred. Until then, use internal ops/tooling only (`scripts/amiga/tournament_builder.py` and `scripts/amiga/tournament_fixtures.py`) and keep website reads behind existing Amiga helpers.
- `python -m scripts.amiga build-tournament create-kitchen-marathon` is the first internal builder: it creates one new `tournaments` row from the `kitchen_marathon` template, one `overall` league stage, stage players, and scheduled round-robin fixtures. It does **not** create `amiga_games`; use fixture result entry for that.
- `python -m scripts.amiga build-tournament create-group-knockout` is a minimal starter for group round robins plus a final placeholder. Advancing winners into knockout fixtures remains an explicit manual ops step until the promotion policy is modelled.
- `python -m scripts.amiga build-tournament smoke-fixture-result` creates a tiny generated tournament, records one fixture result, verifies the generated structure, and rolls back. Use it as the local end-to-end guard for the live fixture path.
- `python -m scripts.amiga fixtures record-result` — updates **fixture running columns** only (`goals_a`, `goals_b`, `extra`, `result_recorded_at`, `status=played`); returns `fixture_id`. No `amiga_games` until Make official ([`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md)). Both fixture players must be active (`registered`) tournament entrants.
- `python -m scripts.amiga fixtures list|detail` are read-only schedule inspection commands. `fixtures set-players` assigns participants to scheduled placeholder fixtures only when both players are active (`registered`) tournament entrants, are placed in `tournament_stage_players` for the fixture's exact `stage_id`, and no game is attached. `fixtures create-fixture` enforces the same entrant rule when `player_a_id` / `player_b_id` are non-null. `fixtures attach-game` is the guarded path for linking pre-existing unattached games to scheduled fixtures (see attachment rules above); prefer `record-result` for new fixture-backed results.
- `/amiga/ops/fixtures.php` is the password-gated **tournament organizer** for internal ops (tabbed `view` navigation: setup, players, fixtures, table, results, advanced). It may create kitchen-marathon leagues with server-side player search at create time, **create newcomers on the compose screen** (full name + nationality → KOA name preview → confirm), list/search/manage entrants on generated tournaments, place registered entrants into stages, assign scheduled placeholder fixture players, and record scheduled fixture-backed results, but remains internal tooling rather than public UI. Successful league create POST-redirects to `view=fixtures` for the new tournament id. Assignment and result entry refuse withdrawn, replaced, or non-entrant players with a clear error.
- **Ops (browser entrants):** on generated tournaments only (`source_id IS NULL` and approved `format_overrides.generated_by` prefix), the fixture manager lists entrants (player id, name, seed, status, note), searches existing `amiga_players` by id or name fragment, and supports add (`draft`/`registration`/`ready` only), withdraw, and replace with the same guardrails as `fixtures add-entrant`, `withdraw-entrant`, and `replace-entrant` (compose **Create player** for newcomers — see § Player identity; no reactivation of `withdrawn`/`replaced` rows, transactional fixture/stage cleanup on withdraw, fixture/stage swap on replace).
- **Ops (browser stage placement):** on the same generated tournaments, lists each stage and its current stage players, and supports place/update via POST `place_stage_entrant` with the same guardrails as `fixtures add-stage-player` / `place-entrant` (`draft`/`registration`/`ready` only; active `registered` entrant required; refuses imported Access tournaments and non-entrant/withdrawn/replaced players). Optional seed and group key; upserts `tournament_stage_players` without generating or rescheduling fixtures. Late-entrant workflow: add entrant → place in stage → assign fixture slots.
- **Ops (browser fixture assignment):** on generated tournaments, incomplete scheduled fixtures without attached games show stage-scoped player selects on the **Advanced** tab (fixture id, key, stage metadata). POST `assign_players` still calls `amiga_fixture_assign_players` with the same guardrails as `fixtures set-players` (active `registered` entrants, membership in `tournament_stage_players` for the fixture's exact stage, distinct players, scheduled status only, refuses fixtures with attached games). Numeric player-id inputs remain as fallback when a stage has fewer than two stage players. Assignment does not require `running` lifecycle.
- **Ops (browser fixtures preview):** the **Fixtures** tab shows a read-first match schedule grouped by round (parsed from `fixture_key`, else `leg_no` or `phase_label`) with player names, friendly status badges, and scores for played fixtures. Fixture id, `fixture_key`, and stage internals are hidden from this tab. When lifecycle is `running`, a link points operators to the Results tab for score entry. Status filtering is Advanced-only but still applies to the underlying query when set.
- **Ops (browser results entry):** the **Results** tab is the primary score-entry workspace when lifecycle is `running`: grouped playable scheduled fixtures (both players assigned, no attached game) with compact goal forms; played fixtures listed for context; void and incomplete slots omitted with a short note. Result entry requires `running` lifecycle (same guardrails as `fixtures record-result`). POST `record_result` redirects to `view=results` with session flash. Imported tournaments show read-only copy on this tab.
- **Ops (browser table preview):** the **Table** tab shows standings while running — **today** from `amiga_tournament_standings`; **target:** compute from running fixture scores only ([`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) RTB3).
- `python -m scripts.amiga fixtures cleanup-generated` may delete only unplayed tournaments generated by approved fixture tooling; imported Access tournaments and generated tournaments with games are intentionally refused. Before the tournament row is removed, an **orphan sweep** deletes eligible `live_ops` players with zero games and no entrant link on any other tournament ([`amiga-player-create-policy.md`](amiga-player-create-policy.md) §6.3).
- Import supplements (games documented outside Access `Scores`, e.g. Rodenbach II) use reserved `source_scores_id >= 500000000` (`IMPORT_SUPPLEMENT_SCORES_ID_BASE` in `import_corrections.py`). Listed in `import_manifest.json` → `transforms.score_supplements`.
- Fixture-entered games use reserved synthetic `source_scores_id >= 1000000000` so they never collide with Access `Scores.ID` or import supplements. They must be chronologically append-only: default `game_date` is the current max `game_date` + 1 second, and explicit `--played-at` values must be later than the current last game.

### Player identity and KOA naming (internal ops)

**Browser organizer create:** policy locked Jul 2026 — [`amiga-player-create-policy.md`](amiga-player-create-policy.md) (rev. 2.1). **Shipped (Jul 2026):** compose-league **Create player** in `fixtures.php` (preview KOA name + confirm); `player_source` column; orphan delete on draft chip remove; orphan sweep on `fixtures cleanup-generated`. Plan: [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md).

- `amiga_players.name` is the canonical display identity. Import normalizes spacing, collapses duplicate case/spacing variants, and strips trailing periods (`scripts/amiga/player_names.py`). The column uses `utf8mb4_bin` collation — exact spelling is unique; identity checks also use casefolded `identity_key` to refuse likely duplicates.
- **`player_source`** (`enum('import','live_ops')`, default `import`) — live organizer / CLI create sets `live_ops`; import corpus rows stay `import`. Orphan cleanup only deletes `live_ops` rows with zero games and no other entrant links ([`amiga-player-create-policy.md`](amiga-player-create-policy.md) §6.3). DDL in [`ground/001_core.sql`](../scripts/amiga/sql/ground/001_core.sql) via `prove`.
- **Public newcomer registration is deferred.** Internal ops use `python -m scripts.amiga players`:
  - `players check-name --name TEXT` — normalize and report availability; exit `1` when a case-insensitive identity collision exists.
  - `players suggest-name --full-name TEXT` — conservative KOA-style abbreviation (`First S`, `First Su`, …) skipping names already taken under `identity_key`; does not auto-merge with existing players.
  - `players create --name TEXT --country OFFICIAL_NAME [--dry-run]` — insert one ground-truth player row (`display=1`, `player_source=live_ops`). Country required (choosable registry token). Refuses identity/exact collisions. Does **not** create `tournament_entrants`; register entrants separately via `fixtures add-entrant` or atomically via `fixtures onboard-newcomer`.
- Player creation for live events can be separate (`players create`) or combined with entrant registration (`fixtures onboard-newcomer`). `fixtures replace-entrant` still refuses to create players.

**Tournament index (`/amiga/tournaments.php`):** read **`amiga_tournament_catalog_stats`** only — one row per tournament (`game_count`, `standing_players`, `league_scopes`, `knockout_ties`). Do **not** aggregate `amiga_games` × `amiga_tournament_standings` at page load (cartesian explosion). Populated by tournament finalize / `replay`. Time travel: filter catalog with `amiga_snapshot_tournament_cutoff_and_sql()` (stats columns are event-intrinsic).

### Tournament standings rules (Track B v1)

- **Source:** `amiga_games` grouped per `tournament_id`, ordered by `source_scores_id` within tournament.
- **Points:** 3 per win, 1 per draw, 0 per loss (W×3 + D×1). Tie-break: goal difference, goals scored.
- **Scopes:** `scope_type` is **`league`** (points table) or **`knockout`** (elimination tie) only — migration `020` merged legacy `overall`+`group` into `league`; phase identity = `scope_key` (`''` = implicit single-phase table). Source: fixture stage metadata when `amiga_games.fixture_id` is present; otherwise phase labels (`scripts/amiga/tournament_phases.py`). Knockout phases (`Semi Finals`, `Places 9-16`, …) → `knockout` per **player pair** (`scope_key` = `{phase}|{id}-{id}`), two rows per tie. Policy: [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md).
- **Goals:** Regulation `goals_a` / `goals_b` only for `league` tables (Elo uses the same). `extra` column stores Access `Scores.Extra` (ET/penalties text); does not affect Elo.
- **Knockout tie winner** (per pair scope, all legs in that phase between the two players): (1) higher aggregate goal difference; (2) if tied, higher aggregate goals scored; (3) if still tied, `parse_standings_winner` on any leg with non-empty `extra` (penalties); (4) if unresolved, UI shows “Tie unresolved” and falls back to derived `position` order. Same rules in `scripts/amiga/tournament_standings.py` (`_knockout_positions`) and `includes/amiga_tournament_lib.php` (`amiga_tournament_knockout_resolve_winner`). Website knockout view lists per-leg fixtures via `amiga_tournament_knockout_fixture_games`.
- **Parity:** Access `Tables` / `World Cup * Tables` are reference only — `python -m scripts.amiga standings-parity` (spot check) or `standings-parity --sweep` (full report → `data/amiga/exports/standings_parity_report.json`). Player names normalized via `normalize_display_name` at compare time; Silver/Bronze cup groups map to Access `Group A`…`H` labels.
- **PHP incremental:** per-game rebuild from rated `amiga_games` for the touched tournament (`amiga_post_game_standings.php`); knockout positions use aggregate GD/GF + `extra` via `amiga_parse_standings_winner`.
- **Future gaps:** full knockout bracket advancement; cross-stage promotion (Tier 4).

---

## Migration status

| Item | Status |
|------|--------|
| Access import → `ko2amiga_db` | **Done** (A1) |
| Elo replay, leaderboard, profile, games | **Done** (A1) |
| This contract (layer intent) | **Done** |
| Schema split (`amiga_games` / …) | **Done** (A2) |
| Staging multi-part browser import | **Done** (Jun 2026) |
| Amiga tournament finalize ops | **Done** — PHP `finalize-tournament` + live standings-only entry; Python `replay` batch oracle |
| Amiga rating events + read path | **Done** — `amiga_rating_events`, profile chart from events, `verify-rating-events` |
| Tournament standings (derived) | **Done** (Track B — `league` + `knockout`; migration `020`; PHP incremental post-game) |
| Reference parity tables / diffs | **Done** (`standings-parity --sweep` vs Access ODBC; 0 engine FAILs Jun 2026) |
| Amiga hub nav (v0) | **Done** — `includes/amiga_hub_nav.php` (Ladder · Tournaments · Hall of Fame); HoF stub `/amiga/hall-of-fame.php` |
| Tournament format foundation | **In progress** — `tournament_format_templates` + non-exclusive `tournaments.has_league` / `has_cup` import flags |
| Stage/fixture foundation | **In progress** — ground tables + internal CLI; no public builder UI yet |
| Tournament entrants foundation | **In progress** — `tournament_entrants` + builder population + verify CLI + withdraw/replace + add-entrant/onboard-newcomer ops |
| Tournament lifecycle foundation | **In progress** — `lifecycle_status` + internal transition CLI + browser ops controls + result-entry guardrails |
| Internal tournament builder | **Started** — `kitchen_marathon` round-robin generator only; no result-entry UI yet |

---

## Known parity exceptions (reference only)

Full sweep (Jun 2026): **684 PASS**, **116 SKIP** (no Access reference or no derived rows), **26 EXCEPTION** (documented below), **0 FAIL** (engine matches game aggregation). Report: `data/amiga/exports/standings_parity_report.json`.

| Reason | Count | Meaning |
|--------|------:|---------|
| `ref_stale_tables` | 24 | Access `Tables` / `World Cup * Tables` disagree with aggregating `Scores` — legacy snapshot not updated after late result entry. Derived engine matches ground-truth games. |
| `ref_alias_merge` | 0 | _(Gloucester III Team split to id 605 Jun 2026 — was merged via alias.)_ |
| `mixed_overall_league_only` | 1 | **Athens LXXXV** — overall derived table = null-phase round-robin only; Access `Tables` includes knockout legs in the same overall row. |

Do not “fix” these by importing Access snapshots as truth. Re-run: `python -m scripts.amiga standings-parity --sweep`.

---

## Agent policy

- **Import:** ground truth only — see `scripts/amiga/import_access.py` and [`amiga-import-layer.md`](amiga-import-layer.md). Corrections to legacy Access belong in the import layer (`import_corrections.py`, `player_names.py`, `tournament_names.py`, `tournament_format.py`), not in edited `koatd.mdb`. `import_corrections.py` also appends **supplemental Scores** when Access has a tournament catalog row but no game rows (currently Rodenbach II, +10 games → **27,418** ground-truth games from Access **27,408**). Each import writes `data/amiga/exports/import_manifest.json`. A full import **truncates** derived player tables (`amiga_game_ratings`, snapshots, current — FK order) but does not repopulate them. **`import` alone leaves the website read path empty** until replay. Use `python -m scripts.amiga prove` for full rebuild, or `run` for import + replay without verify.
- **Replay:** derived truth only — clears derived rows, never truncates canonical game rows
- **Live result entry:** browser `/amiga/ops/fixtures.php` or `fixtures record-result` — **running package only** (fixture columns; broadcast table compute). **Make official** = `promote_running_tournament` + `finalize_tournament` ([`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md)).
- **Finalize:** `php site/public_html/amiga/ops/run_process_game.php finalize-tournament --tournament-id=T` or `python -m scripts.amiga finalize-tournament --tournament-id=T`. Ops bootstrap reads **prior snapshots** only (S4); `amiga_player_current` is write + website read. Post-finalize verify rejects cumulative `NumberGames` ≠ rated game count through the event.
- **Batch rebuild:** `python -m scripts.amiga prove` (preferred) or `replay` then verify suite. PHP `replay-to` removed.
- **Corrections / derived repair:** `python -m scripts.amiga prove` only — reopen/refinalize retired Jun 2026 ([`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md)).
- **New derived tables:** add row to § Table register + post-game rule before implementing
- **Website:** extend `includes/amiga_*.php`, not online `k2_*` game loaders
- **Match streaks:** never ship UI or APIs that display `*Streak` / `Longest*Streak` on Amiga — see § Match streaks
