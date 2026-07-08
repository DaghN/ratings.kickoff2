# Amiga standings scope unification — implementation plan (agent slices)

> **Historical execution record (Jul 2026):** Feature **shipped** via **`prove`** on frozen **`ko2amiga_db`**. Steps below are archaeology — **do not re-run for new work**. Forward: **`simul`** on **`ko2amiga_work`** → **`export_ko2amiga_work.ps1`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Complete** — slices 0–7 shipped Jun 2026.  
**Derived repair (Jun 2026):** Batch `*-rebuild` CLIs retired — [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md). Slice notes may name removed commands historically; **corrections = `prove` only**.  
**Policy (locked):** [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) § Tournament standings · [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) Tier B/C

**In scope:** Merge standings `overall` + `group` → `league`; keep `knockout`; primary league resolver for honours; Python + PHP writer parity; readers/URLs; catalog stats; verify + replay.

**Out of scope (defer unless user expands):**

- Format-template / stage-graph system
- Tournament tab presentation polish (league-only single table — fast follow)
- `tournament_fixtures.stage_type` enum changes
- Staging export / WinSCP
- Online ladder realm

**Authority:** policy doc S1–S10 + honours rules for finish semantics.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue with the next slice”**.
2. Agent executes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs slice **Verification** before stopping; fix failures before handoff.
4. Agent writes handoff: `docs/archive/orchestration/agent-handoffs/2026-06-11-0XX-amiga-standings-scope-slice-N.md` (increment `XXX` from last standings-scope handoff; start at **012** if none exist — check folder).
5. At **STOP gates**, agent lists exact browser/SQL checks and **waits** for user OK.
6. **Do not git commit** unless user asks.
7. After slices that change stored truth: **UPDATE_DOCS** Part A; Part B when schema migration ships (slice 0+).

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| S1 | One points-table primitive: `league` (merge `overall` + `group`) |
| S2 | Empty `scope_key` = implicit single-phase league table |
| S3 | `knockout` unchanged |
| S4 | Standings enum final state: `league` \| `knockout` only |
| S5 | `scope_type` ≠ format module catalog — future stages can grow |
| S6 | Keep synthetic `league`+`''` aggregate for mixed NULL + labeled phases |
| S7 | `resolve_primary_league_standings()` per policy §3 |
| S8 | Legacy URL params redirect/map to `league` |
| S9 | `group_scopes` → `league_scopes` on catalog stats |
| S10 | No “overall” vocabulary for standings scope in product code/docs |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | DDL `020`: migrate rows; enum shrink; catalog column rename; fresh DDL | **A** — SQL spot checks |
| **1** | Python: `tournament_phases`, `tournament_standings`, tests | — |
| **2** | PHP post-game standings + `amiga_tournament_phases.php` parity | — |
| **3** | Primary league resolver; honours Python/PHP; unit tests | **B** — participation verify |
| **4** | Readers: `tournament.php`, `amiga_tournament_lib`, URLs, ops labels | **C** — browser 22/24 |
| **5** | `standings_parity.py`, `verify_player_participation`, catalog stats writer | — |
| **6** | Full `replay` + full verify suite | — |
| **7** | Docs closure: data contract, honours rules, MEMORY, feature-log | — |

---

## Slice 0 — Schema migration

### Goal

Stored standings rows and enum reflect target model before writers change (or in same slice: migrate data then update writers — prefer **data migration first**, then slice 1 writers emit `league` only on rebuild).

### Tasks

- [ ] New migration `scripts/amiga/sql/020_unify_league_standings_scope.sql`:
  - `UPDATE amiga_tournament_standings SET scope_type = 'league' WHERE scope_type IN ('overall', 'group')`
  - Alter `scope_type` enum to `league`, `knockout` only (MySQL-safe sequence: expand enum → update → shrink)
  - `ALTER TABLE amiga_tournament_catalog_stats CHANGE group_scopes league_scopes …` (same type/default)
- [ ] Update fresh-install `002_tournament_standings.sql` — default `league`, enum `league`|`knockout`
- [ ] Update fresh-install `004_tournament_catalog_stats.sql` — `league_scopes` column name
- [ ] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/020_unify_league_standings_scope.sql`
- [ ] Document in policy §8 and data contract (full prose in slice 7)

### Verification

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT scope_type, COUNT(*) FROM amiga_tournament_standings GROUP BY scope_type;"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT scope_type, scope_key, COUNT(*) FROM amiga_tournament_standings WHERE tournament_id IN (22,24) GROUP BY scope_type, scope_key;"
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW COLUMNS FROM amiga_tournament_catalog_stats LIKE 'league_scopes';"
```

- [ ] Only `league` and `knockout` scope types remain
- [ ] Tournament 24: `league` + `''`; tournament 22: `league` + `League Stage`
- [ ] No `overall` / `group` rows

### STOP GATE A

User confirms SQL spot checks OK before slice 1.

### Files (expected)

- `scripts/amiga/sql/020_unify_league_standings_scope.sql`
- `scripts/amiga/sql/002_tournament_standings.sql`
- `scripts/amiga/sql/004_tournament_catalog_stats.sql`

---

## Slice 1 — Python standings writers

### Goal

Emit `ScopeType.LEAGUE` only for points tables; remove `OVERALL` / `GROUP` from Python enum.

### Tasks

- [ ] `tournament_phases.py`: `ScopeType.LEAGUE`; `parse_phase` NULL → `LEAGUE, ''`; labeled RR → `LEAGUE, label`
- [ ] `is_league_scope()` → `scope_type == LEAGUE`
- [ ] `tournament_standings.py`: `_fixture_scope` league stages → `LEAGUE`; synthetic aggregate key `(LEAGUE, '')`
- [ ] `tournament_builder.py`, `standings_parity.py` (minimal), `tournament_catalog_stats.py` — `league` / `league_scopes`
- [ ] Unit tests: `test_tournament_phases.py`, `test_tournament_structure.py`, any standings tests
- [ ] `python -m scripts.amiga standings-rebuild` or targeted replay for one tournament smoke

### Verification

- [ ] `python -m unittest discover -s scripts/amiga -p "test_tournament*.py" -v`
- [ ] Rebuild tournament 24: all rows `scope_type = league`

### Files (expected)

- `scripts/amiga/tournament_phases.py`
- `scripts/amiga/tournament_standings.py`
- `scripts/amiga/tournament_catalog_stats.py`
- Tests under `scripts/amiga/test_*.py`

---

## Slice 2 — PHP post-game standings parity

### Goal

`amiga_post_game_standings.php` and `amiga_tournament_phases.php` match Python emission.

### Tasks

- [ ] Replace `AMIGA_SCOPE_TYPE_OVERALL` / `GROUP` with `AMIGA_SCOPE_TYPE_LEAGUE`
- [ ] `amiga_ops_is_league_scope()` → league only
- [ ] Synthetic aggregate block uses `LEAGUE` + `''`
- [ ] `amiga/ops/fixtures.php` — stage labels (“Overall” → “League table” or template key) where standings scope is meant
- [ ] `process_completed_game.php` if it references scope types

### Verification

- [ ] PHP lint/syntax on touched files
- [ ] Optional: ops simul record-result on generated marathon if available

### Files (expected)

- `site/public_html/amiga/ops/includes/amiga_post_game_standings.php`
- `site/public_html/amiga/ops/includes/amiga_tournament_phases.php`
- `site/public_html/amiga/ops/fixtures.php`

---

## Slice 3 — Primary league resolver + honours

### Goal

Implement `resolve_primary_league_standings()` per policy §3; wire Tier B/C; PHP parity.

### Tasks

- [ ] Python: `resolve_primary_league_standings()` in `participation_placement.py`; replace `_overall_positions()` calls
- [ ] `derive_wc_group_positions()` → filter `league`
- [ ] `derive_event_finish_position()` routing uses resolver
- [ ] PHP: `amiga_participation_overall_positions()` → resolver or rename `amiga_participation_primary_league_positions()`
- [ ] Unit tests: NULL-phase league; single `League Stage`; multi-scope pick largest; Athens 22-style group-only league+cup Tier B spot case
- [x] ~~`participation-rebuild`~~ — retired Jun 2026; slice used historical CLI; corrections = `prove`

### Verification

- [ ] `python -m unittest scripts.amiga.test_participation_placement -v`
- [ ] `python -m scripts.amiga verify-player-participation`
- [ ] SQL: `event_finish_position` for tournaments 22, 24, 544 unchanged vs pre-slice snapshot (agent records snapshot in handoff)

### STOP GATE B

User OK on participation verify + spot SQL before slice 4.

---

## Slice 4 — Readers and URLs

### Goal

Public tournament page and libs use `league`; legacy query params compat.

### Tasks

- [ ] `tournament.php`: list `league` scopes for tabs; remove hardcoded “Overall” tab; default `scope=league` + `''`
- [ ] `amiga_tournament_lib.php`: `amiga_tournament_url()`, `list_scopes()`, `resolve_phase_scope()` — `league` not `group`/`overall`
- [ ] Accept `?scope=overall` and `?scope=group` → redirect to `league` + key
- [ ] `amiga_tournament_format_kind()` — counts league scopes if needed
- [ ] Profile/games phase links title text (“Group standings” → “League standings” or “Phase standings”)

### Verification

- [ ] Manual browser: `http://ratingskickoff.test/amiga/tournament.php?id=24`
- [ ] Manual browser: `http://ratingskickoff.test/amiga/tournament.php?id=22`
- [ ] Legacy URL `?scope=overall` still loads id=24 table

### STOP GATE C

User browser check on 22/24 before slice 5.

---

## Slice 5 — Parity and verify extensions

### Goal

Tooling speaks `league`; guards prevent regression.

### Tasks

- [x] `standings_parity.py` — CLI may keep `overall`/`group` as Access comparison labels; derived side uses `league`
- [x] `verify_player_participation.py` — SQL references `league` not `overall`
- [x] `tournament_catalog_stats.py` rebuild uses `league_scopes`
- [x] Grep product code for standings `scope_type.*overall|scope_type.*group` — zero hits (archives/handoffs OK; `standings_parity.py` CLI labels exempt)

### Verification

- [x] ~~`catalog-stats-rebuild`~~ (retired Jun 2026) — historical slice 5
- [x] `python -m scripts.amiga verify-player-participation`

---

## Slice 6 — Full replay proof

### Goal

End-to-end derived truth matches migration intent.

### Tasks

- [x] `python -m scripts.amiga replay` (~23s)
- [x] Full verify suite:

```powershell
python -m scripts.amiga verify-chronology
python -m scripts.amiga verify-rating-events
python -m scripts.amiga verify-player-participation
python -m scripts.amiga verify-player-matchups
```

- [x] Optional: `python -m scripts.amiga standings-parity --sweep` — PASS=683 SKIP=112 EXCEPTION=27 FAIL=0

### Verification

- [x] All verify commands exit 0
- [x] `SELECT scope_type, COUNT(*) FROM amiga_tournament_standings GROUP BY scope_type` — only league/knockout (5544 + 2320)

---

## Slice 7 — Documentation closure

### Goal

Policy **Implemented**; registers updated.

### Tasks

- [x] `amiga-standings-scope-policy.md` — status **Implemented**
- [x] `amiga-data-contract.md` § Tournament standings — `league`/`knockout` wording; `league_scopes`
- [x] `amiga-tournament-honours-rules.md` — Tier B/C resolver wording
- [x] `scripts/amiga/README.md` — migration `020` note
- [x] `PROJECT_MEMORY.md` recent log
- [x] `docs/coordination/feature-log.md` — L1 row (ko2amiga migration `020`)
- [x] Mark starter prompt **COMPLETE**

### Verification

- [x] Grep docs for standings `scope_type='overall'` — only history/archive context

---

## Environment reference

| Item | Value |
|------|--------|
| DB | `ko2amiga_db` |
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| PHP CLI | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Corrections | `python -m scripts.amiga prove` |
| Full replay | `python -m scripts.amiga replay` |

---

## Handoff template

Each slice handoff should include:

1. **Goal** (one line)
2. **Checklist** (from plan, boxes marked)
3. **Files changed**
4. **Verification output**
5. **STOP gate notes** (if any)
6. **Pre/post SQL snapshots** (slices 0, 3, 6)

---

## Related

- Starter prompt: [`archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md)
- Prior track pattern: [`amiga-event-finish-implementation-plan.md`](amiga-event-finish-implementation-plan.md)
