# Amiga event finish migration — implementation plan (agent slices)

**Status:** **Complete** (Jun 2026) — all slices 0–10 shipped.  
**Policy (locked):** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md)  
**Parent track:** [`amiga-player-universe-implementation-plan.md`](amiga-player-universe-implementation-plan.md) § Event finish migration

**In scope:** Replace legacy `overall_position` with `event_finish_position`, fix honours derivation (podiums, cup medals, wins), populate `best_knockout_phase`, PHP/Python writer parity, UI read paths, verify + rebuild.

**Out of scope (defer unless user expands):**

- WC holistic `event_finish_position` (NULL on WC rows until import job)
- Finish bands (5–8) without exact rank
- `league_position` / `group_position` on participation (**rejected**)
- Populating Tier E overrides beyond empty table + hook (curated import rows later)
- Staging export / WinSCP (user deploys separately)

**Authority:** honours rules doc + [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.2–§6.

---

## How to use this plan

1. User says **“Do slice N”** or **“Continue with the next slice”**.
2. Agent executes **only that slice** unless user explicitly asks for multiple slices in one session.
3. Agent runs slice **Verification** before stopping; fix failures before handoff.
4. Agent writes handoff: `docs/orchestration/agent-handoffs/2026-06-11-0XX-amiga-event-finish-slice-N.md` (increment `XXX` from last event-finish handoff).
5. At **STOP gates**, agent lists exact browser/SQL checks and **waits** for user OK.
6. **Do not git commit** unless user asks.
7. After slices that change stored truth: run **UPDATE_DOCS** Part A; Part B when schema/writers ship (slice 0+).

---

## Locked product decisions (do not re-open without user)

| # | Decision |
|---|----------|
| E1 | `event_finish_position` **NULL** = finish not defined; never use `0` as unknown |
| E2 | Retire `overall_position` after backfill — do not keep both permanently |
| E3 | No `league_position` / `group_position` on participation |
| E4 | Phase tables stay in `amiga_tournament_standings` only |
| E5 | Shared semi bronze: no 3rd-place match → both semi losers **`event_finish_position = 3`**; rank 4 unused |
| E6 | WC rows: `event_finish_position` always **NULL**; podium from `wc_medal` only |
| E7 | WC `wc_medal`: shared bronze when no 3rd-place match (same detection as E5) |
| E8 | Podiums: `event_finish_position <= 3` OR `wc_medal IN (gold,silver,bronze)` |
| E9 | `is_winner`: `event_finish_position = 1` (non-WC) or `wc_medal = gold` (WC) |
| E10 | Main-bracket **Final** label only for gold/silver (not `Silver Cup Final`, etc.) unless Tier E override |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | DDL: add `event_finish_position`, `best_knockout_phase`; fresh `010` update | — |
| **1** | Derivation Tier A (pure KO) + Tier C (pure league); Python tests | — |
| **2** | Tier B (league+cup); shared semi bronze in KO helper | **A** — SQL spot checks |
| **3** | WC medals shared bronze; WC finish NULL in derivation | — |
| **4** | `best_knockout_phase` population | — |
| **5** | Participation writer + totals SQL + `is_winner` | **B** — honours LB / podiums |
| **6** | PHP parity + post-game participation path | — |
| **7** | UI/libs read `event_finish_position` | **C** — profile + history |
| **8** | Drop `overall_position`; remove legacy paths; verify extensions | — |
| **9** | Tier E override table (empty hook) | — |
| **10** | Docs closure + UPDATE_DOCS Part B | — |

---

## Slice 0 — Schema (additive only)

### Goal

Add new columns without dropping `overall_position` yet (safe rollback during migration).

### Tasks

- [x] New migration `scripts/amiga/sql/017_event_finish_position.sql`:
  - `event_finish_position` `SMALLINT NULL` after `overall_position`
  - `best_knockout_phase` `VARCHAR(50) NULL` after `wc_medal`
  - **Do not** drop `overall_position` in this slice
- [x] Update `scripts/amiga/sql/010_player_tournament_participation.sql` for fresh installs (both columns + keep `overall_position` until slice 8)
- [x] Document migration in honours rules §7 and data contract table register
- [x] Apply locally: `mysql ko2amiga_db < scripts/amiga/sql/017_event_finish_position.sql`

### Verification

- [x] Column exists: `SHOW COLUMNS FROM amiga_player_tournament_participation LIKE 'event_finish_position'`
- [x] Existing verify suite still passes (writers unchanged this slice)

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-001-amiga-event-finish-slice-0.md`](orchestration/agent-handoffs/2026-06-11-001-amiga-event-finish-slice-0.md)

### Files (expected)

- `scripts/amiga/sql/017_event_finish_position.sql`
- `scripts/amiga/sql/010_player_tournament_participation.sql`

---

## Slice 1 — Derivation engine (Tier A + Tier C)

### Goal

Refactor `participation_placement.py` to expose `derive_event_finish_position()` with:

- **Tier A:** pure knockout (no `overall` scope, not WC) — Final 1/2, 3rd-place 3/4, shared semi bronze, rest 5+
- **Tier C:** pure league — `overall` scope position

WC and league+cup return partial/NULL here (filled in slices 2–3).

### Tasks

- [x] Rename or wrap legacy `derive_participation_positions` — document transition
- [x] Implement `derive_event_finish_position(standing_rows, *, tournament_name, has_league, has_cup)` with tier routing
- [x] Tier A + Tier C; WC + league+cup return empty (slices 2–3)
- [x] Unit tests: Bournemouth II-style KO; London XXIII-style league; 3rd-place match; shared semi bronze (no 3rd-place scope)
- [x] **Do not** wire writer yet (slice 5)

### Verification

- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 14 OK
- [x] All new tests pass

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-002-amiga-event-finish-slice-1.md`](orchestration/agent-handoffs/2026-06-11-002-amiga-event-finish-slice-1.md)

### Files (expected)

- `scripts/amiga/participation_placement.py`
- `scripts/amiga/test_participation_placement.py`

---

## Slice 2 — Tier B (league + cup)

### Goal

League+cup marathons: cup final → 1/2; cup 3rd-place or shared semi bronze; non-finalists from league `overall`; cup assignments override league for finalists.

### Tasks

- [x] Implement Tier B in `derive_event_finish_position`
- [x] Tests: league+cup synthetic + Milan-style semi bronze + Athens LXXXV DB integration
- [x] Placement finals (3rd/5th/7th…) rank helper fix in Tier A (shared by Tier B)
- [x] DB dry-run test `tournament_id=592`

### Verification

- [x] Unit tests pass — 19 OK
- [ ] Manual SQL (after slice 5 rebuild): compare old `overall_position` vs new finish for one league+cup id user cares about

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-003-amiga-event-finish-slice-2.md`](orchestration/agent-handoffs/2026-06-11-003-amiga-event-finish-slice-2.md)

### STOP GATE A

**Stop after slice 2 only if slice 5 not yet done** — otherwise defer gate to after slice 5 rebuild.

When writer is wired and rebuild run, report to user:

- Count rows with `event_finish_position IS NOT NULL` vs legacy `overall_position > 0`
- 3 example tournaments: pure KO, pure league, league+cup — player finish before/after
- **Wait for user OK** before slice 3 if gate triggered here

---

## Slice 3 — World Cup medals

### Goal

- `event_finish_position` = NULL for all WC rows in derivation
- `compute_wc_medals_from_standings`: shared bronze to both semi losers when no 3rd-place final and main Final complete
- Tests for medal path

### Tasks

- [x] Update `scripts/amiga/tournament_honours.py`
- [x] Update `scripts/amiga/test_tournament_honours.py`
- [x] Ensure derivation tier D returns empty finish map for WC name pattern

### Verification

- [x] `python -m unittest scripts.amiga.test_tournament_honours -v` — 8 OK
- [x] `python -m unittest scripts.amiga.test_participation_placement -v` — 19 OK

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-004-amiga-event-finish-slice-3.md`](orchestration/agent-handoffs/2026-06-11-004-amiga-event-finish-slice-3.md)

### Files (expected)

- `scripts/amiga/tournament_honours.py`
- `scripts/amiga/test_tournament_honours.py`
- `scripts/amiga/participation_placement.py`

---

## Slice 4 — `best_knockout_phase`

### Goal

Populate deepest main-bracket KO round label per player from standings depth logic.

### Tasks

- [x] Add `derive_best_knockout_phase(standing_rows, player_id) -> str|None` in placement module
- [x] Unit tests for QF/SF exit labels + WC semi + placement final + DB Bournemouth II
- [x] Wire into writer in slice 5 (deferred)

### Verification

- [x] Unit tests pass — 27 placement + 8 honours OK

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-005-amiga-event-finish-slice-4.md`](orchestration/agent-handoffs/2026-06-11-005-amiga-event-finish-slice-4.md)

---

## Slice 5 — Writers and career totals

### Goal

Participation rebuild writes `event_finish_position` + `best_knockout_phase`; totals use new rules; `is_winner` from E8/E9.

### Tasks

- [x] `player_tournament_participation.py`: INSERT/UPDATE new columns; call `derive_event_finish_position` + `derive_best_knockout_phase`
- [x] Keep writing `overall_position` via legacy derive until slice 8
- [x] `_TOTALS_AGG_SELECT`: podiums, cup_*, tournaments_won per honours rules §4
- [x] `participation_is_winner` uses new rules
- [x] `refresh_wc_medals` after participation (unchanged order)
- [x] Run `python -m scripts.amiga participation-rebuild` — 4517 rows

### Verification

- [x] `python -m scripts.amiga verify-player-participation` OK
- [x] `python -m scripts.amiga verify-chronology` OK
- [x] `python -m scripts.amiga verify-rating-events` OK
- [x] Spot SQL: Dagh podiums 16; Copenhagen WC `event_finish_position` NULL, not a finish podium

**Handoff:** [`orchestration/agent-handoffs/2026-06-11-006-amiga-event-finish-slice-5.md`](orchestration/agent-handoffs/2026-06-11-006-amiga-event-finish-slice-5.md)

### STOP GATE B

Report to user:

- `amiga_player_tournament_totals` podiums delta for 2–3 known players (Dagh, a WC medalist, a KO cup finalist)
- Tournament honours LB sort sanity (`/amiga/leaderboards/tournament-honours.php`)
- **Wait for OK** before slice 6

---

## Slice 6 — PHP parity

### Goal

Live/post-game path matches Python derivation.

### Tasks

- [x] `includes/amiga_participation_placement.php` — parity with Python tiers A–D + best_knockout_phase
- [x] `amiga/ops/includes/amiga_post_game_participation.php` — write new columns
- [x] Smoke: finalize path or participation refresh for one tournament if CLI exists

### Verification

- [x] PHP syntax lint on touched files
- [x] Python verify suite still passes after another `participation-rebuild`

### Files (expected)

- `site/public_html/includes/amiga_participation_placement.php`
- `site/public_html/amiga/ops/includes/amiga_post_game_participation.php`

---

## Slice 7 — UI read paths

### Goal

Profile and tournament history show `event_finish_position`; WC unchanged (medal only).

### Tasks

- [x] `amiga_player_tournament_lib.php` — SELECT `event_finish_position` AS position (or explicit alias)
- [x] `amiga_profile_blocks.php` — finish label from new column; comments updated
- [x] `player-tournaments.php` / event-stats if they reference position directly
- [x] Remove misleading comments about `overall_position`

### Verification

- [x] `python -m scripts.amiga verify-player-participation`
- [ ] Manual browser list in STOP GATE C

### STOP GATE C

User checks:

- `/amiga/profile.php?id=73` — recent tournaments finish sane; Copenhagen WC not “1st” from group
- `/amiga/player-tournaments.php?id=73` — Finish column
- Pure KO cup — winner/loser/bronze tiers look right

**Wait for OK** before slice 8.

---

## Slice 8 — Drop `overall_position`

### Goal

Remove legacy column and all code references.

### Tasks

- [x] Migration `018_drop_overall_position.sql` (or append to 017 if not yet deployed — prefer new file)
- [x] Remove from `010`, writers, PHP, tests, verify
- [x] Grep repo for `overall_position` — zero product references (orchestration archives OK)
- [x] `participation-rebuild` full pass

### Verification

- [x] Full verify suite:
  ```powershell
  python -m scripts.amiga verify-chronology
  python -m scripts.amiga verify-rating-events
  python -m scripts.amiga verify-player-participation
  python -m scripts.amiga verify-player-matchups
  ```
- [x] Extend `verify-player-participation`: no `event_finish_position = 0`; WC finish NULL; `event_points` invariant unchanged

### Files (expected)

- `scripts/amiga/sql/018_drop_overall_position.sql`
- `scripts/amiga/verify_player_participation.py` (or equivalent)
- All grep hits for `overall_position`

---

## Slice 9 — Tier E override hook (minimal)

### Goal

Table + derivation hook for future import curation; no bulk data yet.

### Tasks

- [x] `scripts/amiga/sql/019_tournament_finish_override.sql`:
  - `amiga_tournament_finish_override (tournament_id, player_id, event_finish_position)` PK `(tournament_id, player_id)`
- [x] Derivation: if override row exists, use it (wins over generic tiers)
- [x] Document in honours rules §3 Tier E

### Verification

- [x] Unit test: override beats generic KO finish
- [x] Verify suite passes

---

## Slice 10 — Documentation closure

### Goal

Mark policy implemented; registers updated.

### Tasks

- [x] `amiga-tournament-honours-rules.md` — status **Implemented**; §7 checklist ticked
- [x] `amiga-player-universe-contract.md` — remove legacy wording; `overall_position` gone
- [x] `amiga-profile-v0.md`, `amiga-data-contract.md`, `scripts/amiga/README.md`
- [x] `PROJECT_MEMORY.md` recent log
- [x] UPDATE_DOCS Part B: `feature-log.md` row (Amiga migrations `017`–`019`; no online `schema-register` — ko2amiga DDL lives under `scripts/amiga/sql/`)

### Verification

- [x] Grep docs for “legacy overall_position” only in archive/history context

---

## Environment reference

| Item | Value |
|------|--------|
| DB | `ko2amiga_db` |
| MySQL (Dagh Windows) | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe` |
| PHP CLI | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Rebuild | `python -m scripts.amiga participation-rebuild` |
| Full replay | `python -m scripts.amiga replay` (~23s) — only if participation verify fails mysteriously |

---

## Handoff template

Each slice handoff file should include:

1. **Goal** (one line)
2. **Checklist** (copied from plan, boxes marked)
3. **Files changed**
4. **Verification output** (paste pass/fail summary)
5. **STOP gate notes** (what user should check, if any)
6. **Known limitations / next slice**

---

## Related

- Starter prompt: [`orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-event-finish-STARTER-PROMPT.md)
- Policy: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md)
