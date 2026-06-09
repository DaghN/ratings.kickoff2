# Amiga player universe — implementation plan (agent slices)

**Status:** Ready to execute (Jun 2026).  
**Contract (authority):** [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md)  
**Out of scope for this track:** online-style milestones, match streaks, calendar play streaks, UTC league honours, Tier C activity (`player_period_games`), leaderboard wings Tier A (separate track in [`amiga-realm-vision.md`](amiga-realm-vision.md) §7 Phase A).

---

## How to use this plan

1. User says **“Do slice N”** (or **“Continue with the next slice”**).
2. Agent completes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs the slice **Verification** commands before stopping.
4. Agent writes a handoff file: `docs/orchestration/agent-handoffs/2026-06-08-0XX-player-universe-slice-N.md` (increment `XXX`).
5. At **STOP gates**, agent tells user what to click/check in the browser; **waits** for user OK before the next slice.

**Do not commit** unless the user asks. **Do not** read or display `amiga_player_stats` streak columns in new PHP.

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| D1 | Denormalize `tournament_name` (+ catalog flags) on participation rows |
| D2 | Participation grain = played in event (≥1 game), not `tournament_entrants` |
| D3 | WC medals v1: derive from knockout/placement standings scopes where possible; `is_winner` = overall position 1 |
| D4 | Defer `amiga_player_tournament_slice_totals` |
| D5 | Profile tournament history: paginated later; slice 4 ships “recent 5” first |
| D6 | Access `added_players` medal parity = CLI report only, not a ship blocker |

---

## Slice map (overview)

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | DDL + schema wiring | Tables exist |
| **1** | Participation rebuild (no WC medals yet) | — |
| **2** | Totals rebuild + `replay.py` wire | **A** — replay + verify |
| **3** | `verify-player-participation` CLI | — |
| **4** | PHP read path + profile switch | **B** — browser profile |
| **5** | WC medal derivation | — |
| **6** | Incremental rebuild (one tournament) | — |
| **7** | Wire finalize (PHP + Python) | **C** — finalize smoke |
| **8** | H2H schema + bulk rebuild | — |
| **9** | `verify-player-matchups` CLI | **D** — optional SQL check |
| **10** | Profile top opponents block | **E** — browser profile |
| **11** | `amiga_generalstats` + rebuild | — |
| **12** | HoF page subset | **F** — browser HoF |
| **13** | Tournament honours leaderboard | **G** — browser LB |
| **14** | Docs register + README closure | Done |

---

## Slice 0 — Schema: participation + totals

### Goal

Create empty derived tables; wire into `apply_schema` / `clear_derived` without changing replay output yet.

### Tasks

- [ ] Add `scripts/amiga/sql/010_player_tournament_participation.sql`
- [ ] Add `scripts/amiga/sql/011_player_tournament_totals.sql`
- [ ] Append to `import_access.apply_schema()` sql bundle (after `009`)
- [ ] Add tables to `_AMIGA_TABLES_DROP_ORDER` (before `amiga_tournament_standings` or after catalog_stats — FK order: participation references tournaments + players; totals references players only)
- [ ] `replay.clear_derived`: `DELETE FROM amiga_player_tournament_totals` and `DELETE FROM amiga_player_tournament_participation`
- [ ] `truncate_ground_truth`: truncate new tables in FK-safe order
- [ ] Document in `scripts/amiga/README.md` (apply path only)

### DDL notes

**`amiga_player_tournament_participation`**

- PK `(player_id, tournament_id)`
- Index `(player_id, event_chrono, tournament_id)` and `(tournament_id, player_id)`
- FKs → `amiga_players`, `tournaments`
- Columns per contract §5.2; `wc_medal` ENUM(`none`,`gold`,`silver`,`bronze`) NOT NULL DEFAULT `none`
- Omit `best_knockout_phase` until slice 5 if easier

**`amiga_player_tournament_totals`**

- PK `player_id`; FK → `amiga_players`
- Columns per contract §5.3 (`cup_*` columns included)

### Verification

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python -m scripts.amiga import --recreate-schema
# Or if DB already has ground truth: apply 010+011 manually on ko2amiga_db
```

MySQL spot-check:

```sql
SHOW TABLES LIKE 'amiga_player_tournament%';
SELECT COUNT(*) FROM amiga_player_tournament_participation;  -- expect 0
SELECT COUNT(*) FROM amiga_player_tournament_totals;           -- expect 0
```

### Handoff

Record tables created, files touched, verification output.

---

## Slice 1 — Participation rebuild (core)

### Goal

`rebuild_all_participation()` fills participation from standings + catalog + rating events.

### Prerequisites

Slice 0 merged.

### Tasks

- [ ] New module `scripts/amiga/player_tournament_participation.py`
- [ ] `rebuild_all_participation(conn, *, dry_run=False) -> int` — truncate + insert
- [ ] Source query logic:
  - Base: `amiga_tournament_standings` WHERE `scope_type='overall'` AND `scope_key=''`
  - JOIN `tournaments` for denorm fields
  - LEFT JOIN `amiga_rating_events` ON `(tournament_id, player_id)`
  - Set `is_winner = (overall_position = 1)`
  - Set `wc_medal = 'none'` (slice 5 adds real medals)
- [ ] `rebuild_participation_for_tournament(conn, tournament_id)` — delete + reinsert for one tournament (stub OK for slice 1; complete in slice 6)
- [ ] Minimal unit test: synthetic standings + tournament + rating event → one participation row (mirror `test_tournament_format.py` style)

### Verification

```powershell
python -c "
from scripts.amiga.config import load_amiga_db_config
import pymysql
from pymysql.cursors import DictCursor
from scripts.amiga.player_tournament_participation import rebuild_all_participation
cfg = load_amiga_db_config()
conn = pymysql.connect(host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password, database=cfg.database, charset='utf8mb4', cursorclass=DictCursor)
conn.cursor().execute(\"SET time_zone = '+00:00'\")
rebuild_all_participation(conn)
conn.commit()
print('rows', conn.cursor().execute('SELECT COUNT(*) FROM amiga_player_tournament_participation'))
"

# Requires standings populated — if empty, run first:
python -m scripts.amiga replay
```

Parity SQL (manual):

```sql
-- Every overall standing row has participation
SELECT COUNT(*) AS missing
FROM amiga_tournament_standings s
WHERE s.scope_type = 'overall' AND s.scope_key = ''
  AND NOT EXISTS (
    SELECT 1 FROM amiga_player_tournament_participation p
    WHERE p.player_id = s.player_id AND p.tournament_id = s.tournament_id
  );
-- expect 0
```

### Handoff

Row count vs overall standings distinct (player, tournament) pairs.

---

## Slice 2 — Totals rebuild + replay wire

### Goal

Career rollups; full replay populates participation + totals automatically.

### Prerequisites

Slice 1.

### Tasks

- [ ] `rebuild_all_participation_totals(conn, *, dry_run=False)` in same module or `player_tournament_totals.py`
- [ ] Truncate `amiga_player_tournament_totals`; `INSERT … SELECT GROUP BY player_id` from participation
- [ ] `replay.py` `replay_all()` after `rebuild_all_standings`:
  1. `rebuild_all_participation`
  2. `rebuild_all_participation_totals`
  3. (keep existing `rebuild_all_catalog_stats`)
- [ ] `clear_derived` already clears new tables (slice 0)
- [ ] `python -m scripts.amiga participation-rebuild` CLI (optional alias) mirroring `catalog-stats-rebuild`

### Verification — **STOP GATE A**

```powershell
python -m scripts.amiga replay
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-chronology
```

SQL:

```sql
SELECT COUNT(*) AS participation_rows FROM amiga_player_tournament_participation;
SELECT COUNT(*) AS totals_rows FROM amiga_player_tournament_totals;
SELECT SUM(tournaments_played) FROM amiga_player_tournament_totals;
-- SUM(tournaments_played) should equal participation_rows count
```

**User checkpoint A:** Confirm replay completes (~23s local). No browser required yet.

---

## Slice 3 — `verify-player-participation` CLI

### Goal

Automate contract §8 parity gates for participation + totals.

### Prerequisites

Slice 2.

### Tasks

- [ ] `scripts/amiga/verify_player_participation.py`
- [ ] Register in `scripts/amiga/__main__.py` as `verify-player-participation`
- [ ] Checks:
  - participation ⊆ games (each row has ≥1 game)
  - overall standings ⊆ participation
  - rating columns match `amiga_rating_events` when present
  - `tournaments_played` sum = participation count per player
  - totals row count = players with ≥1 participation
- [ ] Exit code 1 on failure; print first 20 errors

### Verification

```powershell
python -m scripts.amiga verify-player-participation
```

---

## Slice 4 — PHP read path + profile

### Goal

Profile “recent tournaments” reads participation table (richer, canonical).

### Prerequisites

Slice 2 (data populated).

### Tasks

- [ ] New `site/public_html/includes/amiga_player_tournament_lib.php`
  - `amiga_player_tournament_participation_recent($con, $playerId, $limit = 5)`
  - `amiga_player_tournament_totals_row($con, $playerId)` for future hero use
- [ ] Update `amiga_player_recent_tournaments()` in `amiga_tournament_lib.php` to call participation helper **or** deprecate and switch `profile.php` / `amiga_profile_blocks.php` to new helper
- [ ] Preserve public visibility filter (`amiga_tournament_public_visibility_where`)
- [ ] Order: `event_chrono DESC`, `event_date DESC` (match contract)

### Verification — **STOP GATE B**

```powershell
python -m scripts.amiga verify-player-participation
```

**User checkpoint B (browser):**

1. Open `/amiga/profile.php?id=<busy_player>` (e.g. a WC regular — pick from `SELECT player_id, COUNT(*) c FROM amiga_player_tournament_participation GROUP BY player_id ORDER BY c DESC LIMIT 5`)
2. Confirm **Recent tournaments** block shows names, positions, links work
3. Open `/amiga/tournament.php?id=<one_id>` — standings unchanged

---

## Slice 5 — WC medal derivation

### Goal

Populate `wc_medal` on participation for World Cup events.

### Prerequisites

Slice 4 (optional); slice 2 required.

### Tasks

- [ ] `scripts/amiga/tournament_honours.py` (or submodule):
  - `is_world_cup_tournament(name)` — reuse PHP regex logic in Python
  - `derive_wc_medal(conn, tournament_id, player_id) -> str`
  - v1 rules (contract §6): inspect `amiga_tournament_standings` knockout/placement scopes; fallback overall position 1/2/3 only when no knockout scopes exist
- [ ] Call from `rebuild_all_participation` when inserting rows
- [ ] Rebuild totals after (wc_gold/silver/bronze columns)
- [ ] Optional CLI: `python -m scripts.amiga honours-parity-sample` — compare top 20 WC medal holders vs Access `added_players` (ODBC); report only

### Verification

```powershell
python -m scripts.amiga replay
python -m scripts.amiga verify-player-participation
```

```sql
SELECT wc_medal, COUNT(*) FROM amiga_player_tournament_participation
WHERE tournament_name REGEXP '^World Cup'
GROUP BY wc_medal;
```

**User checkpoint:** Optional — spot-check one known WC winner profile.

---

## Slice 6 — Incremental participation rebuild

### Goal

Rebuild participation + totals for one `tournament_id` without full replay.

### Tasks

- [ ] `rebuild_participation_for_tournament(conn, tournament_id)`
- [ ] `rebuild_totals_for_players(conn, player_ids: list[int])` — re-aggregate only affected players (or full totals rebuild if simpler v1)
- [ ] Used by live finalize path (slice 7)

### Verification

Pick tournament id `T`; run rebuild; row counts for `T` match full-replay subset.

---

## Slice 7 — Wire tournament finalize

### Goal

After live finalize + standings refresh, participation/totals stay current.

### Prerequisites

Slices 5–6.

### Tasks

- [ ] Python `finalize_tournament.py`: after standings rebuild for `T`, call incremental participation + totals
- [ ] PHP `site/public_html/amiga/ops/modules/finalize_tournament.php`: same hook after standings
- [ ] Document in `amiga-data-contract.md` table register writers

### Verification — **STOP GATE C**

```powershell
python -m scripts.amiga verify-player-participation
# If live tournament fixture exists: finalize one generated tournament and re-verify
```

---

## Slice 8 — H2H schema + bulk rebuild

### Goal

`amiga_player_matchup_summary` populated on replay.

### Tasks

- [ ] `scripts/amiga/sql/012_player_matchup_summary.sql`
- [ ] Wire schema + `clear_derived` + drop order
- [ ] `scripts/amiga/player_matchup_summary.py` — port pattern from `scripts/ladder/sql/archive/batch-2026-05/player_matchup_summary_rebuild.sql` using `amiga_games`
- [ ] `replay.py`: after participation totals, `rebuild_all_matchup_summary`
- [ ] CLI `matchup-rebuild`

### Verification

```powershell
python -m scripts.amiga replay
```

```sql
SELECT SUM(games) FROM amiga_player_matchup_summary;
SELECT COUNT(*) * 2 FROM amiga_games;
-- should match
```

---

## Slice 9 — `verify-player-matchups` CLI

### Tasks

- [ ] `verify_player_matchups.py` + `__main__.py` registration
- [ ] Directed pair spot-check vs raw games for sample pairs

### Verification — **STOP GATE D**

```powershell
python -m scripts.amiga verify-player-matchups
```

---

## Slice 10 — Profile top opponents

### Tasks

- [ ] `amiga_player_top_opponents($con, $playerId, $limit = 10)` in `amiga_player_tournament_lib.php` or new `amiga_player_matchup_lib.php`
- [ ] Profile block in `amiga_profile_blocks.php` (table: opponent name, W-D-L, games)
- [ ] Link to `/amiga/profile.php?id=` and future H2H

### Verification — **STOP GATE E**

**User checkpoint E:** Profile shows top opponents for a busy player; counts plausible.

---

## Slice 11 — `amiga_generalstats`

### Tasks

- [ ] `scripts/amiga/sql/013_generalstats.sql` — single row `id=1`
- [ ] Port `scripts/ladder/server_records.py` → `scripts/amiga/server_records.py` (read `amiga_games`, `amiga_game_ratings`, `amiga_player_stats`)
- [ ] **Exclude** streak records and play-day streaks
- [ ] `replay.py`: `rebuild_generalstats` last
- [ ] `clear_derived` clears generalstats

### Verification

```powershell
python -m scripts.amiga replay
```

```sql
SELECT * FROM amiga_generalstats WHERE id = 1;
```

---

## Slice 12 — Hall of Fame page subset

### Tasks

- [ ] Replace stub `site/public_html/amiga/hall-of-fame.php` with subset of online HoF (career + single-game records from generalstats + ratio leaders from stats)
- [ ] `includes/amiga_records_hof_links.php`, `amiga_records_ratio_leaders.php` (port patterns from online)
- [ ] **Omit** streak rows; profile links → `/amiga/profile.php`
- [ ] Small panel: WC medal leaders from `amiga_player_tournament_totals`

### Verification — **STOP GATE F**

**User checkpoint F:** `/amiga/hall-of-fame.php` loads; record holders link to profiles.

---

## Slice 13 — Tournament honours leaderboard

### Tasks

- [ ] `site/public_html/amiga/leaderboards/tournament-honours.php`
- [ ] `includes/amiga_lb_nav.php` or extend hub nav when leaderboards tab exists
- [ ] Sort: `wc_gold`, `wc_silver`, `wc_bronze`, `tournaments_won`, `tournaments_played`
- [ ] Read `amiga_player_tournament_totals` only

### Verification — **STOP GATE G**

**User checkpoint G:** Leaderboard page loads; top WC medalists look plausible.

---

## Slice 14 — Documentation closure

### Tasks

- [ ] Update `amiga-data-contract.md` table register (status Active for new tables)
- [ ] Update `amiga-player-universe-contract.md` §12 migration register
- [ ] `scripts/amiga/README.md` — all new CLIs
- [ ] Final handoff summarizing slices 0–13

### Verification

All verify commands pass in one script block:

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

---

## Reference patterns (copy from codebase)

| Pattern | Location |
|---------|----------|
| SQL migration bundle | `scripts/amiga/sql/009_rating_events.sql`, `import_access.apply_schema` |
| Bulk derived rebuild | `scripts/amiga/tournament_catalog_stats.py` |
| Replay orchestration | `scripts/amiga/replay.py` |
| Verify CLI | `scripts/amiga/verify_rating_events.py` |
| Online H2H rebuild SQL | `scripts/ladder/sql/archive/batch-2026-05/player_matchup_summary_rebuild.sql` |
| Online generalstats | `scripts/ladder/server_records.py` |
| PHP tournament reads | `site/public_html/includes/amiga_tournament_lib.php` |
| Agent handoff format | `docs/orchestration/agent-handoffs/2026-06-08-023-rating-events-slice-0-schema.md` |

---

## Full replay order (target state after slice 11)

```text
finalize all tournaments → amiga_game_ratings, amiga_rating_events, PlayerState
commit_heavy_player_derived → amiga_player_stats
rebuild_all_standings
rebuild_all_participation          # + wc_medal after slice 5
rebuild_all_participation_totals
rebuild_all_matchup_summary        # slice 8+
rebuild_all_catalog_stats
rebuild_generalstats               # slice 11+
```

---

*Parent: [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md)*
