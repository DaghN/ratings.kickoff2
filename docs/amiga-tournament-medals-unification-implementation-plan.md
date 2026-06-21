# Amiga tournament medals unification — implementation plan (v2)

**Status:** **Complete** (Jun 2026) — slices 0–8 shipped locally on `ko2amiga_db`.  
**Policy (locked):** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) **v2**  
**Supersedes:** v1 honours rollups + event-finish locked decisions E6–E9 ([`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md) remains historical)

**In scope:** Unified `event_finish_position` for WC podium; career totals `event_*` + `wc_*` + `wc_played`; drop `cup_*` and `wc_medal`; Python + PHP writer parity; verify invariants; read paths + honours LB; docs closure.

**Out of scope (defer unless user expands):**

- WC holistic rank 4+ for non-podium entrants
- Bulk fix of ~58 non-WC NULL `event_finish_position` rows (Tier E / derivation backlog)
- `amiga_player_tournament_slice_totals` (kitchen/milan tabs)
- Staging WinSCP / server import (user deploys)
- Git commit unless user asks

**Authority:** honours rules v2 + [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2–§5.3.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue with the next slice”**.
2. Agent executes **only that slice** unless user asks for multiple in one session.
3. Run slice **Verification** before stopping; fix failures before handoff.
4. Handoff: `docs/archive/orchestration/agent-handoffs/YYYY-MM-DD-NNN-amiga-tournament-medals-unification-slice-N.md`
5. At **STOP gates:** list SQL + browser checks; **wait** for user OK.
6. **Do not git commit** unless user asks.
7. Stored-truth slices: **UPDATE_DOCS** Part A same turn; Part B when schema/writers ship.

**Starter prompt:** [`archive/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md)

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| M1–M12 | From honours rules v2 §1 |
| M13 | Migration `021` before writer changes; backfill WC finish before dropping `wc_medal` |
| M14 | `podiums` column renamed to `event_podiums` in migration `021` |
| M15 | Honours LB: Elo between Player and Country; event block then WC block (UI slice 7) |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | DDL `021` — totals columns (`event_*`, `wc_played`, `wc_podiums`); rename `podiums`; drop `cup_*`; fresh `011` | SQL columns ✓ |
| **1** | Tier D: WC podium → `event_finish_position` 1/2/3 (Python + tests) | Unit tests ✓ |
| **2** | Backfill SQL: existing WC rows from `wc_medal` → finish 1/2/3 | SQL: 0 WC medal rows with NULL finish at 1/2/3 ✓ |
| **3** | Totals aggregation + `is_winner` single-path (Python + PHP) | Unit tests ✓ |
| **4** | `participation-rebuild` + verify extensions | **A** — verify + Alkis P spot SQL ✓ (await user OK) |
| **5** | Read paths: profile, player-tournaments, libs (one finish source) | **B** — browser profile + history ✓ (await user OK) |
| **6** | DDL `022` — drop `wc_medal`; remove writer/read references | Grep clean |
| **7** | Tournament honours LB (columns + medal headers + Elo) | **C** — browser honours LB ✓ |
| **8** | Contract/docs closure + UPDATE_DOCS Part B + starter COMPLETE | — ✓ |

---

## Environment

| Item | Value |
|------|--------|
| DB | `ko2amiga_db` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Rebuild | `python -m scripts.amiga participation-rebuild` |
| Verify suite | `verify-chronology`, `verify-rating-events`, `verify-player-participation`, `verify-player-matchups` |

**Spot-check player:** Alkis P — expect `event_gold = 58`, `wc_gold = 2` after slice 4 (approximate; confirm SQL in slice 4).

---

## Slice 0 — Totals schema (migration 021)

### Goal

Add v2 career columns; remove `cup_*`; rename `podiums` → `event_podiums`. Writers unchanged this slice (columns may be 0 until slice 3–4).

### Tasks

- [x] `scripts/amiga/sql/021_tournament_medals_totals.sql`:
  - Add `event_gold`, `event_silver`, `event_bronze` (INT NOT NULL DEFAULT 0)
  - Rename `podiums` → `event_podiums`
  - Add `wc_played`, `wc_podiums` (INT NOT NULL DEFAULT 0)
  - Drop `cup_gold`, `cup_silver`, `cup_bronze`
- [x] Update `scripts/amiga/sql/011_player_tournament_totals.sql` for fresh installs
- [x] Register migration in honours rules §7 and plan handoff
- [x] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/021_tournament_medals_totals.sql`

### Verification

```sql
SHOW COLUMNS FROM amiga_player_tournament_totals LIKE 'event_gold';
SHOW COLUMNS FROM amiga_player_tournament_totals LIKE 'event_podiums';
SHOW COLUMNS FROM amiga_player_tournament_totals LIKE 'cup_gold';
-- cup_gold should be empty
```

- [x] `python -m scripts.amiga verify-player-participation` — still passes (writers not yet updated)

---

## Slice 1 — Tier D derivation (Python)

### Goal

World Cup podium players receive `event_finish_position` 1/2/3 from existing WC knockout medal logic (`tournament_honours.py` / `compute_wc_medals_from_standings`).

### Tasks

- [x] Update `participation_placement.py` Tier D: map gold→1, silver→2, bronze→3
- [x] Integrate in `derive_event_finish_position` (replace empty Tier D return)
- [x] PHP parity: `amiga_participation_placement.php` Tier D
- [x] Unit tests: WC final gold/silver; 3rd-place bronze; shared semi bronze
- [x] **Do not** wire participation writer yet if slice 2 backfill handles existing rows first — OR wire in slice 3; document choice in handoff

### Verification

```powershell
python -m unittest scripts.amiga.test_participation_placement scripts.amiga.test_tournament_honours -v
```

---

## Slice 2 — Backfill WC participation rows

### Goal

Existing DB rows: set `event_finish_position` from current `wc_medal` before totals rewrite.

### Tasks

- [x] One-shot SQL or Python in migration `021b` / script:
  - `wc_medal = 'gold'` → `event_finish_position = 1`
  - `silver` → 2; `bronze` → 3
  - Only World Cup tournaments
- [x] Idempotent; safe to re-run
- [x] `verify_player_participation.py` — v1 “WC finish must be NULL” replaced with wc_medal/finish parity check

### Verification

```sql
SELECT COUNT(*) AS bad FROM amiga_player_tournament_participation
WHERE tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND wc_medal IN ('gold','silver','bronze')
  AND (event_finish_position IS NULL OR event_finish_position NOT IN (1,2,3));
-- expect 0

SELECT wc_medal, event_finish_position, COUNT(*) FROM amiga_player_tournament_participation
WHERE tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND wc_medal != 'none'
GROUP BY 1, 2;
```

---

## Slice 3 — Writers: totals + is_winner

### Goal

Single-path aggregation per honours rules v2 §4. Python + PHP parity.

### Tasks

- [x] Rewrite `_TOTALS_AGG_SELECT` in `player_tournament_participation.py`
- [x] Mirror in `amiga_post_game_participation.php`
- [x] `is_winner` = `event_finish_position = 1` only (participation insert/update)
- [x] Wire Tier D in participation rebuild writer if not done in slice 1 (already in `derive_event_finish_position`)
- [x] Update `test_player_tournament_incremental.py` / participation tests for new column names

### Verification

- [x] Unit tests pass (50 tests)
- [x] Manual SQL on Alkis P after incremental totals rebuild: `event_gold=58`, `wc_gold=2`

---

## Slice 4 — Full rebuild + verify

### Goal

All totals match v2 invariants on full dataset.

### Tasks

- [x] `python -m scripts.amiga participation-rebuild`
- [x] Extend `verify_player_participation.py`:
  - `event_podiums = event_gold + event_silver + event_bronze`
  - `wc_podiums = wc_gold + wc_silver + wc_bronze`
  - `wc_* <= event_*` subset checks
  - `tournaments_won = event_gold`
  - `is_winner` = `(event_finish_position = 1)` on participation
- [x] Run full verify suite

### Verification — **STOP GATE A**

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

```sql
SELECT p.name, t.event_gold, t.wc_gold, t.event_podiums, t.wc_podiums, t.tournaments_won
FROM amiga_player_tournament_totals t
JOIN amiga_players p ON p.id = t.player_id
WHERE p.name = 'Alkis P';
```

**Wait for user OK** before slice 5.

---

## Slice 5 — Read paths (PHP)

### Goal

Profile, player-tournaments, `amiga_player_tournament_lib.php`, `amiga_profile_blocks.php` — display finish from `event_finish_position` only; WC medal label derived when needed (until slice 6 drops `wc_medal`).

### Tasks

- [x] Remove `wc_medal` branch as primary finish source where duplicated
- [x] `amiga_player_tournament_totals_row()` — select `event_*`, `wc_*`, `event_podiums`, `wc_podiums`; drop `cup_*`
- [x] Profile honours strip — use new totals fields
- [x] Update `amiga-tournament-honours-rules.md` §6 if read behaviour differs

### Verification — **STOP GATE B**

- [x] `http://ratingskickoff.test/amiga/player/profile.php?id=14` (Alkis P) — honours 58 won / 85 podiums; WC ordinals where podium
- [x] `http://ratingskickoff.test/amiga/player/tournaments.php?id=14&filter=world-cup` — Finish column 1st/3rd on podium WCs

**Wait for user OK** before slice 6.

---

## Slice 6 — Drop `wc_medal`

### Goal

Remove duplicate authority column from participation.

### Tasks

- [x] `scripts/amiga/sql/022_drop_wc_medal.sql` — drop column; update fresh `010`
- [x] Remove from Python writer, PHP writer, tests, profile WC medal match expressions
- [x] Add helper `amiga_participation_wc_podium_word_from_finish()` + `amiga_profile_wc_podium_word()`
- [x] `tournament_honours.py` — `compute_wc_podium_finish_from_standings`; drop `refresh_wc_medals`

### Verification

```powershell
rg "wc_medal" --glob "*.{py,php}" 
# expect zero or comments only
python -m scripts.amiga verify-player-participation
python -m scripts.amiga participation-rebuild
```

---

## Slice 7 — Tournament honours leaderboard

### Goal

UI per user spec: Player · Elo · Country · Events (`tournaments_played`) · event medals · event podiums · WCs played · wc medals · wc podiums. Medal SVG headers from `k2_status_league_podium_medal()`.

### Tasks

- [x] `amiga_tournament_honours_leaderboard_rows()` — `amiga_player_current` for Elo + v2 honours columns (migrated from `amiga_player_tournament_totals` + `amiga_player_stats` at snapshot slice 8)
- [x] `tournament-honours.php` — table headers, medal SVG headers, sort indices
- [x] `lb_column_help.php` — help strings for event vs WC columns
- [x] CSS: reuse `.k2-lb-honours-medal-th` pattern (`.k2-lb-tournament-honours`)

### Verification — **STOP GATE C**

- [ ] `http://ratingskickoff.test/amiga/leaderboards/tournament-honours.php` — columns, sort, Alkis P sensible ranks

**Wait for user OK** before slice 8.

---

## Slice 8 — Documentation closure

### Goal

Mark track complete; registers updated.

### Tasks

- [x] Honours rules status → **Implemented** (v2)
- [x] Update `amiga-player-universe-contract.md` §5.3, §6, appendix
- [x] Update `amiga-data-contract.md` table register
- [x] `PROJECT_MEMORY.md` recent log
- [x] `docs/coordination/feature-log.md` if user-facing
- [x] Starter prompt → **COMPLETE**
- [x] UPDATE_DOCS Part B (`amiga-data-contract.md` DDL register)

---

## Files touched (expected, full track)

| Area | Files |
|------|--------|
| DDL | `021_*.sql`, `022_*.sql`, `010`, `011` |
| Python | `participation_placement.py`, `player_tournament_participation.py`, `tournament_honours.py`, `verify_player_participation.py`, tests |
| PHP | `amiga_participation_placement.php`, `amiga_post_game_participation.php`, `amiga_player_tournament_lib.php`, `amiga_profile_blocks.php`, `tournament-honours.php` |
| Docs | honours rules, universe contract, data contract, MEMORY, feature-log |

---

*Playbook: [`orchestration/agent-track-playbook.md`](orchestration/agent-track-playbook.md)*
