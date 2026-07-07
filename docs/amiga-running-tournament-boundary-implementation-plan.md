# Amiga running tournament boundary — implementation plan

**Status:** **RTB shipped (Jul 2026)** — slices RTB-PREFLIGHT through RTB-8 complete; `python -m scripts.amiga prove` green with `verify-running-tournament-boundary`.

**Policy:** [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (rev. 1, RTB1–RTB12)  
**Inventory:** [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) (rev. 2, audit fold-in)  
**Parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (Lane B) · [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md)

**Prerequisites shipped:** Player create **PC-1–PC-7** ([`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md)); country registry CR-5+; organizer `fixtures.php` compose + Results/Table tabs; CLI `fixtures` / `finalize-tournament`.

**Execution:** Slices **RTB-PREFLIGHT → RTB-8** in order. Run each slice **Verification** before continuing. **Lane A (`prove`, import, materialize)** must stay green — RTB is **Lane B live ops only** (policy RTB11).

**DDL (RTB-1):** Schema changes **only** through the **holy ops** path — see § **DDL — holy ops only** below. **Not** manual `mysql`, numbered `047_*.sql`, or one-off `apply_schema_*` on a live DB.

---

## Why a plan (not just policy + inventory)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product — two universes, allow/deny, Make official contract |
| **Inventory** | Engineering grep checklist — what touches what, audit gaps |
| **This plan** | Ordered slices, file-level tasks, STOP gates, oracle strategy, staging cutover, parity contracts |

The inventory is necessary but not sufficient: it does not specify **slice bundling** (e.g. RTB-2 writers without RTB-2c leaves the UI blank), **promote ordering**, **game-shaped dict** for standings, **pre-RTB staging hygiene**, or **DDL holy-ops-only** (RTB-1 trap — Jul 2026).

---

## DDL — holy ops only (mandatory — read before RTB-1)

**Project policy is explicit:** Amiga schema is applied **only** through the nuclear holy loop, not ad-hoc SQL on `ko2amiga_db`.

| Authority | Rule |
|-----------|------|
| [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) **G12** | Strict chain — **no side doors** (no L0→L3 shortcuts; L5 via `prove` only) |
| [`amiga-data-contract.md`](amiga-data-contract.md) § Derived sign-off | **Fresh schema = `python -m scripts.amiga prove`**; incremental `014–023` **archived** — do not run after recreate-schema |
| [`scripts/amiga/sql/archive/incremental/README.md`](../scripts/amiga/sql/archive/incremental/README.md) | Numbered flat `sql/NNN_*.sql` files are **upgrade archaeology**, not wired into `prove` |
| [`schema_bundles.py`](../scripts/amiga/schema_bundles.py) | **`STRUCTURE_SQL`** includes `sql/structure/006_tournament_fixtures.sql` only — prove calls `apply_schema_structure()` |
| [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md) **PC-1** | Precedent: `player_source` in **`ground/001_core.sql`** via prove; **`047_player_source.sql` removed** after wrongful manual mysql |

RTB fixture columns are **L4 structure** — same rule as `player_source` is L3 ground: **edit the bundle file, then `prove`.**

### RTB-1 — authorized (only these steps)

1. **Edit** [`scripts/amiga/sql/structure/006_tournament_fixtures.sql`](../scripts/amiga/sql/structure/006_tournament_fixtures.sql) — add `goals_a`, `goals_b`, `extra`, `result_recorded_at` to the `CREATE TABLE tournament_fixtures` definition.
2. **Optional mirror** [`scripts/amiga/sql/006_tournament_fixtures.sql`](../scripts/amiga/sql/006_tournament_fixtures.sql) (archaeology / docs — **not** applied by prove).
3. **Apply to local DB:** run **`python -m scripts.amiga prove`** (full holy loop). This nuclear-resets `ko2amiga_db` and applies all bundles.
4. **Staging schema:** **`prove` → `scripts\export_ko2amiga_db.ps1` → WinSCP → browser import** ([`amiga-staging-handoff.md`](amiga-staging-handoff.md)) — part `ko2amiga_01_schema.sql` carries the new columns.

**Do not** edit `schema_bundles.py` for RTB-1 unless adding a **new** bundle file (not needed — `006` is already listed).

### RTB-1 — forbidden (agent STOP — do not proceed)

| Forbidden action | Why |
|------------------|-----|
| `mysql … ALTER TABLE tournament_fixtures` (CLI, Heidi, Laragon UI) | Side door — bypasses holy loop |
| New `scripts/amiga/sql/047_fixture_running_results.sql` (or any numbered migration) | Not in `STRUCTURE_SQL`; repeats **047_player_source** mistake |
| `python -c` / one-off `apply_schema_structure()` on existing DB without full prove | Partial apply — not sign-off path |
| `SHOW COLUMNS` → "columns missing" → **ALTER instead of prove** | Wrong fix — run **`prove`** after bundle edit |
| `site/public_html/ops/sql/migrations/` | **Online ladder** (`kooldb*`) — wrong realm |
| Backfill running scores from `amiga_games` into new columns | Lane A / repair — out of scope |

**If a prior agent already ALTERed local `ko2amiga_db`:** do not "sync" with more manual SQL. **Revert repo files** if needed, fix bundle, run **`prove`** — nuclear reset is the repair.

### RTB-1 STOP gate (non-negotiable)

- [ ] Bundle file edited (`structure/006` only).
- [ ] **`python -m scripts.amiga prove`** exit **0** (~28 min).
- [ ] **Then** `SHOW COLUMNS` / RTB-2 code — **never** the reverse order.

**No RTB-2 PHP/Python** until prove is green on the bundle edit.

---

## Locked implementation decisions (do not re-open without Dagh)

| # | Decision |
|---|----------|
| **IP1** | Running scores on **`tournament_fixtures`** columns: `goals_a`, `goals_b`, `extra`, `result_recorded_at` (policy §5.1). No separate `tournament_fixture_results` table unless Dagh explicitly chooses mid-RTB-1. |
| **IP2** | **`status = played` ⇔ `goals_a`/`goals_b` non-null** while `rating_finalized = 0`; zero `amiga_games` rows for that `fixture_id`. |
| **IP3** | **`amiga_fixture_record_result()`** returns **`fixture_id`** (or void), not `game_id`. Remove `amiga_ops_process_derived_for_game()` from score-entry POST handler. |
| **IP4** | **Promote** runs inside Make official **before** `amiga_finalize_tournament()` / `finalize_tournament()` game loop. **`amiga_fixture_reprocess_tournament_derived()` is kept** — not retired. |
| **IP5** | **Chronology** (`source_scores_id`, `game_date`) allocated only in **promote** transaction, ordered by `s.sequence_no`, `f.leg_no`, `f.id`, optional `f.result_recorded_at`. |
| **IP6** | **Void (running):** allowed when **zero official** `amiga_games` for tournament — **played fixtures with running scores do not block void** (inventory §3.4). Void does not require DELETE of fixture rows; lifecycle `void` is sufficient. |
| **IP7** | **Make official gate:** all non-void fixtures **`played`** (fixture columns), lifecycle `running`, not `rating_finalized`, zero pre-existing `amiga_games` for TID (idempotent promote guard). |
| **IP8** | **Mark complete** (lifecycle → `completed` without Make official): still allowed when zero scheduled fixtures remain; **`complete_blocked_reason`** uses **played-fixture count**, not `amiga_games`. |
| **IP9** | **Live-ops tournament** = `source_id IS NULL` AND (`format_overrides` LIKE `%tournament_builder%` OR `%fixtures%`) — same as generated list in `fixtures.php` (~3195–3208). Oracles scope RTB rules to this set only. |
| **IP10** | **`attach-game` / `attach_game_to_fixture`:** not used on running path; CLI help marks **official repair only** (or refuse when `rating_finalized = 0`). |
| **IP11** | **Broadcast standings:** call **`amiga_ops_compute_tournament_standings()`** on fixture-derived game dicts (PHP); parity with **`tournament_standings.compute_tournament_standings()`** (Python). |
| **IP12** | **`verify-rating-events`** unfinalized+tournament games rule: exclude **IP9** live-ops running tournaments (games must be zero) OR fail with actionable message — do not break Lane A import tournaments. |
| **IP13** | **Staging cutover:** before RTB-2 on any DB with old running leagues that already have `amiga_games`, run **RTB-PREFLIGHT** cleanup (delete test leagues or one-off repair). |
| **IP14** | **DDL holy ops only:** L4 columns in **`sql/structure/006_tournament_fixtures.sql`** only; DB apply **only** via **`python -m scripts.amiga prove`**. Staging = prove → export → import. **No** manual ALTER, **no** numbered `047_*.sql`, **no** `ops/sql/migrations/`. |
| **IP15** | **PHP/Python parity:** every RTB writer slice ships both sides before STOP, or documents explicit asymmetry with follow-up slice same day. |

---

## How to use this plan

1. Read **policy + inventory + this plan** at RTB-0 (done when Dagh approved plan).
2. Run **RTB-PREFLIGHT** on target DB before RTB-2 if any live-ops running tournament has `amiga_games` rows.
3. **RTB-1** — edit **`sql/structure/006_tournament_fixtures.sql`**, then **`python -m scripts.amiga prove`** (§ DDL — holy ops only). **No** manual mysql.
4. **RTB-2 tranche** (writers + lifecycle + broadcast reads + league table compute) ships **together** — single STOP gate.
5. **RTB-3** Python CLI parity — can start after RTB-1 in parallel with RTB-2 but must pass same STOP gate.
6. **RTB-4** standings parity hardening (knockout broadcast, `standings_parity.py`) before Ref-Cup-A drill.
7. **RTB-5 + RTB-6** promote + finalize wire — STOP when Make official works end-to-end locally.
8. **RTB-7** verify + prove wiring — full `prove` green.
9. **RTB-8** docs + staging drill + UPDATE_DOCS Part A (+ Part B for DDL).

**Do not** ship RTB-2a alone to staging without RTB-2c (scores would write but not display).

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **RTB-0** | Policy + inventory + this plan | Dagh OK — **done** |
| **RTB-PREFLIGHT** | Staging/local DB hygiene for pre-RTB running leagues | No IP9 tournament with `rating_finalized=0` AND `amiga_games` rows |
| **RTB-1** | Fixture running result columns (structure bundle + **prove**) | **`prove` exit 0**; columns present on fresh DB |
| **RTB-2** | PHP running path: write, lifecycle, broadcast read, table compute, entrant guards, UI copy | Scores on fixtures; zero games while running; Live hub + organizer show scores + table |
| **RTB-3** | Python `record_fixture_result`, list/detail, verify helpers, CLI output | CLI record + list match PHP |
| **RTB-4** | Shared standings parity (KO broadcast, `standings_parity`, builder smokes) | Parity report clean on test tournament |
| **RTB-5** | `promote_running_tournament` PHP + Python | Unit/integration promote on fixture-only league |
| **RTB-6** | Wire promote into Make official / finalize | Make official → games + full L5 derive |
| **RTB-7** | `verify-running-tournament-boundary` + oracle updates + prove | `python -m scripts.amiga prove` exit 0 |
| **RTB-8** | Docs, practice track, staging drill, UPDATE_DOCS | Ref-League-A drill on staging |

---

## Slice dependency matrix

| Slice | Depends on | Blocks |
|-------|------------|--------|
| RTB-PREFLIGHT | — | RTB-2 on dirty DB |
| RTB-1 | — | RTB-2, RTB-3, RTB-5 |
| RTB-2 | RTB-1, RTB-PREFLIGHT | RTB-7 staging |
| RTB-3 | RTB-1 | RTB-7 |
| RTB-4 | RTB-2 (league path) | Ref-Cup-A drill |
| RTB-5 | RTB-1 | RTB-6 |
| RTB-6 | RTB-5, RTB-2 | RTB-7 |
| RTB-7 | RTB-2, RTB-3, RTB-6 | RTB-8 |
| RTB-8 | RTB-7 | — |

**Parallel OK:** RTB-3 with RTB-2 after RTB-1; RTB-5 with RTB-2/3 after RTB-1.

---

## Complete touchpoint registry (audit — do not skip)

Use this as a **checkbox** during RTB-2/3/6. Inventory §3–§5 is the source; this consolidates **every known symbol**.

### A. Writers — stop pre-official L3/L5

| File | Symbol / area | RTB action |
|------|---------------|------------|
| `site/public_html/amiga/ops/fixtures.php` | `amiga_fixture_record_result()` | UPDATE fixture columns + `status=played` + `result_recorded_at`; no INSERT |
| Same | POST `record_result` (~2958) | Remove `amiga_ops_process_derived_for_game`; flash fixture id |
| Same | `amiga_fixture_undo_unprocessed_result()` | Clear goals + `status=scheduled`; no DELETE games |
| Same | `amiga_fixture_reprocess_tournament_derived()` | Call promote-if-needed then finalize |
| Same | `amiga_fixture_partition_for_results()` (~993) | `played` / goals columns, not `game_id` |
| Same | `amiga_fixture_assign_players()` (~2472) | Guard: no goals on fixture, not game count |
| Same | `amiga_fixture_count_tournament_games()` (~661) | Split: **official** vs **running played** helpers |
| Same | `amiga_fixture_count_tournament_games_for_player()` (~1849) | Official games only for entrant guards |
| Same | `amiga_fixture_load_player_fixtures()` (~1820) | Drop game subquery; use fixture goals |
| Same | `amiga_fixture_validate_withdrawal_eligibility()` (~1885) | Official game count; played = fixture status/goals |
| Same | `amiga_fixture_validate_replacement_eligibility()` | Same pattern as withdrawal |
| Same | `amiga_fixture_browser_allowed_lifecycle_targets()` (~701) | Void: zero **official** games |
| Same | `amiga_fixture_organizer_lifecycle_ui()` (~779) | `game_count` → played-fixture count for complete gate |
| Same | `amiga_fixture_set_lifecycle_status()` void (~1162) | Zero **official** games |
| Same | Tournament list SQL (~3195) | `game_count` → played fixtures |
| Same | Fixture load SQL (~3239) | Select `f.goals_a`, `f.goals_b`, `f.extra`; drop `g` join |
| Same | Table tab standings load (~3273) | Broadcast compute, not `amiga_tournament_standings` |
| Same | `$tournamentCanMakeOfficial` (~3304) | All fixtures played; not `tournamentGameCount` from games |
| Same | `$fixtureResultRated` map (~3311) | Remove or official-only after promote |
| Same | Results/Fixtures/Table templates (~3869+) | Drop `game_id` display; show fixture scores |
| `site/public_html/amiga/ops/modules/process_completed_game.php` | `amiga_ops_process_derived_for_game()` | Callers must not invoke on running score entry |
| `site/public_html/amiga/ops/includes/amiga_post_game_standings.php` | `amiga_ops_standings_apply_game()` | Official path only (post-promote finalize) |
| Same | `amiga_ops_catalog_stats_refresh_tournament()` | Official path only |
| `scripts/amiga/tournament_fixtures.py` | `record_fixture_result()` (~683) | Fixture columns only |
| Same | `list_fixtures()` / `fixture_detail()` (~786) | Scores from fixtures |
| Same | `attach_game_to_fixture()` (~562) | Repair-only / refuse running |
| Same | `cleanup_generated_tournament()` (~1598) | Guard: zero **official** games (already true if RTB1) |
| Same | `audit_fixture_integrity()` (~1772) | Played ⇒ goals on fixture, zero attached games |
| Same | `verify-lifecycle` block (~1731) | Draft/ready + games rule → fixture-based |
| `scripts/amiga/tournament_builder.py` | `verify_built_tournament()` (~807) | `allow_attached_games` / fixture results |
| `scripts/amiga/finalize_tournament.py` | `finalize_tournament()` | Promote prefix |

### B. New modules (promote + broadcast)

| Deliverable | Path (suggested) | Responsibility |
|-------------|------------------|----------------|
| PHP promote | `site/public_html/amiga/ops/includes/amiga_promote_running_tournament.php` | Batch INSERT `amiga_games`, chronology, idempotency |
| Python promote | `scripts/amiga/promote_running_tournament.py` | Parity with PHP |
| PHP running games | `site/public_html/includes/amiga_running_tournament_lib.php` (or section in `amiga_tournament_lib.php`) | `amiga_running_tournament_games()`, `amiga_running_tournament_standings_rows()` |
| PHP broadcast | reuse `amiga_ops_compute_tournament_standings()` | Input adapter from fixtures |

### C. Readers — broadcast (running package only)

| File | Symbol | RTB action |
|------|--------|------------|
| `site/public_html/includes/amiga_tournament_lib.php` | `amiga_live_tournament_fixture_groups()` (~464) | Fixture columns |
| Same | `amiga_tournament_game_count()` (~982) | Played fixtures when running / unfinalized |
| `site/public_html/amiga/live-tournament.php` | — | Uses lib helpers (verify after lib change) |
| `site/public_html/amiga/ops/fixtures.php` | Results, Fixtures, Table views | See registry A |

### D. Readers — official (verify gates — no change expected if RTB1 correct)

| Surface | File | Verify |
|---------|------|--------|
| Historical catalog | `amiga_tournament_lib.php` catalog queries | `rating_finalized = 1` or not IP9 running |
| Games hub | `amiga_realm_games_all.php` | Rated join excludes running-only |
| Player profile | `amiga_player_load.php` | Career from official games only |
| Activity lede | `amiga_community_stats_lib.php` | Post-official counts |
| Player search | `api/player_search.php` | OK — identity only |

**Audit task (RTB-7):** `rg 'amiga_games.*tournament_id' site/public_html/amiga` on public paths; confirm running events invisible in official surfaces.

### E. Chronology helpers (move call site to promote)

| File | Symbol |
|------|--------|
| `fixtures.php` | `amiga_fixture_next_live_source_scores_id()` (~113) |
| Same | `amiga_fixture_next_game_date()` (~134) |
| `tournament_fixtures.py` | `_next_live_source_scores_id`, `_next_append_only_game_date` |

### F. Verification / oracles

| Oracle | File | RTB action |
|--------|------|------------|
| **New** `verify-running-tournament-boundary` | `scripts/amiga/verify_running_tournament_boundary.py` | IP9 + `rating_finalized=0` ⇒ zero games |
| `verify-rating-events` | `verify_rating_events.py` (~38–49) | IP12 scope |
| `fixtures verify` | `tournament_fixtures.py` `audit_fixture_integrity` | Fixture-column rules |
| `verify-lifecycle` | `tournament_fixtures.py` (~1731) | See above |
| Builder smokes | `tournament_builder.py` | Fixture results |
| `prove` suite | `prove.py` | Wire new verify; ensure green |
| `standings_parity` | `standings_parity.py` | RTB-4 broadcast vs finalize |
| `verify-player-create` | `verify_player_create.py` | Must stay green (orphan = official games) |
| Export note | `export_packs.py`, `audit_ko2amiga_export_tables.py` | Document mid-tournament export semantics |

### G. Intentionally unchanged (Lane A)

| Path | Note |
|------|------|
| `scripts/amiga/import_access.py` | INSERT `amiga_games` — import only |
| `scripts/amiga/replay.py` | Historical replay |
| `scripts/amiga/tournament_structure/materialize_legacy.py` | Links import games to fixtures |
| `scripts/amiga/apply_structure.py` | L4 overlay from import |
| `prove` L1–L5 nuclear path | Must remain green after oracle updates |

---

## Game-shaped dict contract (standings input)

Broadcast compute and promote must feed the **same semantic fields** into standings math.

**Minimum keys** (match `amiga_ops_compute_tournament_standings()` / `compute_tournament_standings()` expectations):

| Key | Source (fixture row) |
|-----|----------------------|
| `player_a_id` | `f.player_a_id` |
| `player_b_id` | `f.player_b_id` |
| `goals_a` | `f.goals_a` |
| `goals_b` | `f.goals_b` |
| `phase` | `f.phase_label` |
| `fixture_id` | `f.id` (optional for compute; required for promote) |
| `leg_no` | `f.leg_no` (promote ordering) |
| `extra` | `f.extra` (promote only) |

**PHP adapter:** `amiga_running_tournament_games(mysqli $con, int $tournamentId): array` — SELECT played fixtures with both players; ORDER BY `s.sequence_no`, `f.leg_no`, `f.id`.

**Python adapter:** `running_tournament_games(conn, tournament_id)` in `promote_running_tournament.py` or `tournament_fixtures.py`.

**STOP:** League Ref-League-A — broadcast table matches pre-RTB standings for same results after Make official (RTB-4 parity).

---

## Promote algorithm (RTB-5/6 — locked behaviour)

```text
promote_running_tournament(tournament_id):
  ASSERT tournament is IP9 generated
  ASSERT lifecycle allows finalize (running)
  ASSERT rating_finalized = 0
  ASSERT COUNT(amiga_games WHERE tournament_id) = 0  -- idempotent guard
  ASSERT all non-void fixtures status = played (or product-documented exceptions)

  BEGIN TRANSACTION
  games_ordered = SELECT played fixtures ORDER BY stage sequence, leg_no, fixture id
  FOR each fixture in games_ordered:
    source_scores_id = next_live_source_scores_id()
    game_date = next_append_game_date()  -- monotonic; preserve relative order
    INSERT amiga_games (..., fixture_id, tournament_id, goals, phase, extra)
  COMMIT
  RETURN list of new game ids
```

Then **existing** `amiga_finalize_tournament($con, $tournamentId, false)` / `finalize_tournament()` — **no change** to rating loop semantics after ground exists.

**Failure modes:**

| Failure | Behaviour |
|---------|-----------|
| Second Make official | Promote guard: games already exist → skip promote or refuse with clear message |
| Partial promote (crash mid-tx) | Transaction rollback; no partial games |
| Played fixture missing goals | Refuse promote; list fixture ids |
| Official games exist pre-promote | Refuse — RTB-PREFLIGHT or repair verb |

---

## Reference files (copy patterns)

| Area | Reference |
|------|-----------|
| Policy | [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) |
| Inventory | [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) |
| Player create (orphan guards) | [`k2_amiga_player_naming.php`](../site/public_html/includes/k2_amiga_player_naming.php), [`player_orphans.py`](../scripts/amiga/player_orphans.py) |
| Organizer ops | [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) |
| Standings compute PHP | [`amiga_post_game_standings.php`](../site/public_html/amiga/ops/includes/amiga_post_game_standings.php) — `amiga_ops_compute_tournament_standings()` |
| Standings compute Python | [`tournament_standings.py`](../scripts/amiga/tournament_standings.py) |
| Standings parity | [`standings_parity.py`](../scripts/amiga/standings_parity.py) |
| Finalize PHP | [`finalize_tournament.php`](../site/public_html/amiga/ops/modules/finalize_tournament.php) |
| Finalize Python | [`finalize_tournament.py`](../scripts/amiga/finalize_tournament.py) |
| Live hub reads | [`amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) |
| Fixture DDL | [`sql/structure/006_tournament_fixtures.sql`](../scripts/amiga/sql/structure/006_tournament_fixtures.sql) |
| Practice drill | [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) |
| PC plan shape | [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md) |

---

## RTB-0 — Policy + inventory + plan (done)

### Goal

Decisions locked; touchpoint registry agreed before code.

### Deliverables

- [x] [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) rev. 1
- [x] [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) rev. 2
- [x] This plan

### Starter prompt (new chat — continuous track)

```text
Track: Amiga running tournament boundary (RTB) — continuous implementation in this chat.

Read first:
- docs/amiga-running-tournament-boundary-policy.md
- docs/amiga-running-tournament-boundary-implementation-plan.md (full plan; § "DDL — holy ops only" is mandatory before RTB-1)
- docs/amiga-running-tournament-boundary-inventory.md (touchpoint checklist)

Execute slices in order: RTB-PREFLIGHT (if needed) → RTB-1 → RTB-2 tranche → RTB-3 → RTB-4 → RTB-5 → RTB-6 → RTB-7 → RTB-8. Run each slice Verification / STOP gate before continuing to the next. Do not skip ahead.

Resume rule: inspect git status and the plan to see which slice is done; continue at the first incomplete slice unless I say otherwise.

DDL (RTB-1): edit ONLY scripts/amiga/sql/structure/006_tournament_fixtures.sql; apply ONLY via python -m scripts.amiga prove. FORBIDDEN: mysql ALTER, 047_*.sql, apply_schema_structure one-off, ops/sql/migrations.

Do not git commit unless I ask. UPDATE_DOCS Part A (+ Part B for DDL) at RTB-8 in the same turn as shipping code.
```

---

## RTB-PREFLIGHT — DB hygiene before RTB-2

### Goal

Old pre-RTB running leagues may have `amiga_games` rows while `rating_finalized = 0`. RTB-2 will create **new** fixture-only scores; mixed state breaks oracles.

### Tasks

- [ ] On **staging** and any **local ko2amiga_db** used for drills: list offenders:
  ```sql
  SELECT t.id, t.name, t.lifecycle_status, COUNT(g.id) AS games
  FROM tournaments t
  INNER JOIN amiga_games g ON g.tournament_id = t.id
  WHERE t.rating_finalized = 0
    AND t.source_id IS NULL
  GROUP BY t.id;
  ```
- [ ] **Preferred:** `python -m scripts.amiga fixtures cleanup-generated --tournament-id N` for each **test** generated league (no rated games / not needed).
- [ ] **If rated partial state exists:** do not auto-delete — manual repair or finish via old path then archive; document TID in pain log.
- [ ] Optional one-off script `scripts/oneoff/rtb_preflight_audit.py` — prints IP9 running tournaments with games (delete after RTB-8 or keep as ops tool).

### Verification

```sql
-- Must return 0 rows before RTB-2 on target DB:
SELECT t.id FROM tournaments t
WHERE t.rating_finalized = 0 AND t.source_id IS NULL
  AND (COALESCE(t.format_overrides,'') LIKE '%tournament_builder%'
    OR COALESCE(t.format_overrides,'') LIKE '%fixtures%')
  AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id);
```

**STOP** if any row returned.

---

## RTB-1 — Schema: fixture running result columns (holy ops only)

### Goal

L4 storage for running scores (policy §5.1, IP1). **Canon DDL in structure bundle; DB apply via `prove` only** — § **DDL — holy ops only**.

### Tasks

- [ ] Add to **`CREATE TABLE tournament_fixtures`** in **`scripts/amiga/sql/structure/006_tournament_fixtures.sql`**:
  - `goals_a` SMALLINT UNSIGNED NULL
  - `goals_b` SMALLINT UNSIGNED NULL
  - `extra` VARCHAR(255) NULL (match `amiga_games.extra` width)
  - `result_recorded_at` DATETIME NULL
- [ ] **Optional:** mirror same `CREATE TABLE` in **`scripts/amiga/sql/006_tournament_fixtures.sql`** (archaeology — not applied by prove).
- [ ] Run **`python -m scripts.amiga prove`** on local `ko2amiga_db` — **this is the only authorized apply step**.
- [ ] **Do not** backfill from existing `amiga_games` on import tournaments — Lane A materialize unchanged.
- [ ] **Do not** create `047_*.sql`, run `mysql ALTER`, or call `apply_schema_structure()` standalone.

### Files

| Action | Path | Applied by prove? |
|--------|------|-------------------|
| **Edit (required)** | `scripts/amiga/sql/structure/006_tournament_fixtures.sql` | **Yes** (`STRUCTURE_SQL`) |
| Edit (optional) | `scripts/amiga/sql/006_tournament_fixtures.sql` | No |
| **Forbidden** | `scripts/amiga/sql/047_*.sql` | No — do not add |
| **Forbidden** | `site/public_html/ops/sql/migrations/*` | Wrong DB realm |

### Verification

```powershell
# 1. Edit structure/006 first, then:
python -m scripts.amiga prove
# 2. Only after prove exit 0:
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -e "SHOW COLUMNS FROM ko2amiga_db.tournament_fixtures LIKE 'goals_a'"
```

**STOP** if prove fails.

**STOP** if you applied schema any way other than **`prove`** after editing the bundle — re-run **`prove`**; do not ALTER to "fix".

**Do not start RTB-2** until this STOP passes.

---

## RTB-2 — PHP running path (single tranche)

**Sub-slices 2a–2e ship together.**

### RTB-2a — Writers

- [ ] Rewrite `amiga_fixture_record_result()` — transaction: SET goals, extra, `result_recorded_at = UTC_TIMESTAMP()`, `status='played'`.
- [ ] Remove INSERT `amiga_games` and chronology calls from record path.
- [ ] Change return type to `int` fixture id (or void).
- [ ] POST `record_result`: remove `amiga_ops_process_derived_for_game`; update flash strings (no "game #").
- [ ] Rewrite `amiga_fixture_undo_unprocessed_result()` — clear goal columns; `status='scheduled'`; remove game DELETE branch.
- [ ] Set `result_recorded_at` NULL on undo.

### RTB-2b — Lifecycle + counts

- [ ] Add `amiga_fixture_count_played_fixtures($con, $tournamentId)` and `amiga_fixture_count_official_tournament_games()` (rename/clarify existing).
- [ ] Update **void** guards: official games only (IP6).
- [ ] Update **complete** blocked reason: played-fixture count (IP8).
- [ ] Update `$tournamentCanMakeOfficial`: all scheduled fixtures played; lifecycle running; not finalized (IP7).
- [ ] Fix **assign_players** guard: refuse if fixture has goals or `status=played`.
- [ ] Update **withdrawal/replacement** eligibility to use official games + fixture played state.

### RTB-2c — Broadcast reads

- [ ] Fixture list SQL (~3239): read `f.goals_*`, `f.extra`; remove `amiga_games` join.
- [ ] Tournament picker `game_count` (~3199): COUNT played fixtures.
- [ ] `amiga_live_tournament_fixture_groups()` — fixture columns.
- [ ] `amiga_tournament_game_count()` — if running/unfinalized IP9: played fixtures; else official games.
- [ ] Implement `amiga_running_tournament_games()` + `amiga_running_tournament_standings_rows()`.
- [ ] Table tab: use broadcast standings rows instead of `amiga_tournament_standings` SELECT (~3273).
- [ ] `amiga_fixture_partition_for_results()` — use `status` + goals.

### RTB-2d — Organizer UI templates

- [ ] Remove/disable result entry when `game_id` null check was wrong — use `status !== scheduled` or goals present.
- [ ] Results tab: show score from fixture columns; remove "game #N" muted text (~3918, ~4047).
- [ ] Make official button copy: "Promote & commit ratings" / keep existing if clear.
- [ ] `$fixtureResultRated` — remove pre-official rated map or show "not official yet".

### RTB-2 STOP scope

RTB-2 STOP covers **running** behaviour: fixture scores, broadcast UI, zero `amiga_games`, undo. **Make official** is verified at **RTB-6 STOP** (promote + finalize).

### Verification (RTB-2 STOP)

Local organizer Ref-League-A (4 players):

1. Create league → start → enter all scores on Results tab.
2. SQL: `SELECT COUNT(*) FROM amiga_games WHERE tournament_id = ?` → **0**.
3. Fixtures tab + Live hub show correct scores.
4. Table tab shows computed standings (not empty).
5. Void **refused** if... (verify IP6: void allowed with scores? policy says running scores OK — void should be **allowed** with played fixtures).
6. Undo one result → score clears, table updates, still zero games.

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SELECT id, status, goals_a, goals_b FROM tournament_fixtures WHERE stage_id IN (SELECT id FROM tournament_stages WHERE tournament_id = TID)"
```

**STOP** until all pass.

---

## RTB-3 — Python CLI parity

### Goal

`fixtures record-result` and readers match PHP (RTB12).

### Tasks

- [ ] `record_fixture_result()` — mirror PHP: UPDATE fixture columns only; return `fixture_id`.
- [ ] Remove INSERT from record path; remove chronology from record path.
- [ ] `list_fixtures()` / `fixture_detail()` — scores from fixture columns.
- [ ] CLI `record-result` output: print `fixture_id` not `game_id` (~2204 in `__main__.py` / fixtures CLI).
- [ ] `attach_game_to_fixture` — refuse when tournament `rating_finalized = 0` and IP9 (or document repair-only).
- [ ] Update `audit_fixture_integrity()` + lifecycle verify blocks per inventory §9.
- [ ] `cleanup_generated_tournament` — already refuses games; confirm zero official games semantics.

### Verification

```powershell
python -m scripts.amiga fixtures record-result --fixture-id F --goals-a 2 --goals-b 1
python -m scripts.amiga fixtures list --tournament-id TID
python -m scripts.amiga fixtures verify --tournament-id TID
```

Compare PHP browser entry on same fixture — same DB state.

**STOP** if CLI creates `amiga_games` rows.

---

## RTB-4 — Standings compute parity

### Goal

Broadcast table matches post-official standings for same results (highest drift risk).

### Tasks

- [ ] Ensure fixture→game dict adapters on both sides use identical ordering.
- [ ] Run / extend **`standings_parity.py`** on generated round-robin test tournament.
- [ ] Knockout: `amiga_ops_compute_tournament_standings()` knockout scopes — verify Ref-Cup-A path uses broadcast compute on Table tab.
- [ ] Update `tournament_builder.py` `verify_built_tournament()` — fixture goals vs attached games flags.
- [ ] Add pytest or builder smoke: record results on fixtures only → compute standings → compare to post-finalize DB rows.

### Verification

```powershell
python -m scripts.amiga standings-parity --tournament-id TID
```

**STOP** before Ref-Cup-A drill if parity fails.

---

## RTB-5 — Promote module (PHP + Python)

### Goal

Batch L3 insert from running package (policy §6.2).

### Tasks

- [ ] Create **`amiga_promote_running_tournament.php`** with algorithm above.
- [ ] Create **`promote_running_tournament.py`** with parity tests.
- [ ] Move chronology helper **calls** to promote only (helpers may stay in fixtures.php / tournament_fixtures.py).
- [ ] Unit test: 2-leg KO ordering preserves leg order in `game_date`.
- [ ] Idempotency: second call refuses when games exist.

### Verification

```powershell
# After RTB-2 scores on fixtures only:
python -m scripts.amiga promote-running-tournament --tournament-id TID --dry-run
# Then wet run on throwaway DB copy
```

SQL: game count = played fixture count; each `fixture_id` linked once.

**STOP** if duplicate games or chronology inversion.

---

## RTB-6 — Wire Make official

### Goal

Browser Table tab + CLI `finalize-tournament` = promote + existing finalize.

### Tasks

- [ ] `amiga_fixture_reprocess_tournament_derived()`:
  - If zero games: call `amiga_promote_running_tournament()` then `amiga_finalize_tournament()`.
  - If games exist and unrated: existing path (repair) OR refuse — document.
  - Update empty-game early return (~2648) — was "nothing to do", now must promote.
- [ ] `finalize_tournament.py` — call `promote_running_tournament()` before game load.
- [ ] Wire CLI `finalize-tournament` in `__main__.py` if separate entry.
- [ ] Flash messages (~2993–3013): distinguish promote failure vs finalize failure.
- [ ] Lifecycle → `completed` + `rating_finalized = 1` unchanged (finalize module).
- [ ] Confirm **`amiga_ops_process_derived_for_game`** never runs during running; only finalize batch.

### Verification

Full Ref-League-A:

1. Running: zero games.
2. Make official → N games, ratings, standings, catalog stats, community updated.
3. Player profile appears for debuted players.
4. `python -m scripts.amiga finalize-tournament --tournament-id TID` on clone → same row counts as PHP.

**STOP** until Make official drill passes.

---

## RTB-7 — Verification + prove

### Goal

Regression gates; Lane A green (RTB11).

### Tasks

- [ ] Add **`scripts/amiga/verify_running_tournament_boundary.py`**:
  - IP9 tournaments with `rating_finalized = 0` have zero `amiga_games`.
  - Played fixtures have non-null goals.
  - No `amiga_tournament_standings` rows for unfinalized IP9 tournaments (optional strengthen).
- [ ] Wire into **`__main__.py`** and **`prove.py`** (after `verify-player-create` or documented order).
- [ ] Update **`verify_rating_events.py`** per IP12.
- [ ] Update **`audit_fixture_integrity`**, **`verify-lifecycle`**, builder smokes.
- [ ] Run full **`python -m scripts.amiga prove`** (~28 min) — exit 0.
- [ ] Run **`verify-player-create`** — still green.
- [ ] Public surface grep audit (registry §D).

### Verification

```powershell
python -m scripts.amiga verify-running-tournament-boundary
python -m scripts.amiga prove
```

**STOP** if prove fails.

---

## RTB-8 — Docs + staging

### Goal

Contract docs match shipped behaviour; staging drill.

### Tasks

- [ ] UPDATE_DOCS Part A: MEMORY, policy link to this plan, `feature-log.md`, `AGENTS.md` cold-start row.
- [ ] UPDATE_DOCS Part B: `amiga-data-contract.md` record-result + fixture chain; **`tournament_fixtures` running columns** in table register (structure bundle `006`, applied via `prove` — **not** a numbered migration).
- [ ] Update per inventory §8: `amiga-live-ops-platform.md`, `amiga-tournament-structure-policy.md`, `scripts/amiga/README.md`, `amiga-live-ops-practice-track.md` (step 3: scores on fixtures; step 4: promote+finalize), `amiga-player-create-policy.md` §6.4.
- [ ] WinSCP sync; `scripts\export_ko2amiga_db.ps1` if schema changed; staging import apply.
- [ ] Run **Ref-League-A** checklist on staging ([`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) §3).

### Verification

Staging drill + `verify-running-tournament-boundary` on staging DB.

---

## Staging drill (updated — copy into practice track at RTB-8)

```text
[ ] 1. CREATE — browser compose league (4–6 players)
[ ] 2. START  — lifecycle running; Live hub lists event
[ ] 3. PLAY    — all results on fixtures ONLY
[ ] 4. SQL     — zero amiga_games for TID while running
[ ] 5. TABLE   — broadcast standings match expectation
[ ] 6. MAKE OFFICIAL — games appear; profiles + Activity update
[ ] 7. WEBSITE — live page still OK; historical tournament + LB spot-check
[ ] 8. CLEANUP — cleanup-generated removes workspace; zero L3 orphan games
```

---

## Out of scope / phase 2 (not RTB-1–8)

| Item | Track |
|------|-------|
| Pre-debut public profile links on Live hub | Policy §9 UI polish |
| Browser Delete league (Advanced tab) | PC-9 analogue — CLI `cleanup-generated` today |
| Per-match Make official | RTB5 |
| Lane A import / materialize changes | RTB11 |
| Anchored repair / truncate-after-N | [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) |
| `attach-game` as supported running workflow | Retired — repair only |

---

## Risk register

| Risk | Mitigation |
|------|------------|
| RTB-2a without RTB-2c | **Tranche rule** — single STOP gate |
| Staging DB mixed state | RTB-PREFLIGHT |
| Standings drift broadcast vs official | RTB-4 parity; shared game dict |
| `verify-rating-events` red | IP12 scope + preflight cleanup |
| Prove runtime regression | Full prove at RTB-7; no skip on sign-off |
| Two-leg KO chronology wrong | leg_no + fixture id ordering in promote |
| Undo leaves stale table | Undo triggers broadcast recompute (RTB-2a) |
| Operator confusion (no game id) | Flash + UI copy RTB-2d |
| Agent runs manual ALTER / 047 migration | § **DDL — holy ops only** + IP14; precedent `047_player_source` removed |

---

## Starter prompt (fresh agent chat — one track, all slices)

Use this single prompt to run **RTB-PREFLIGHT through RTB-8** in one chat. Continue across slices without waiting for a new chat per slice.

```text
Track: Amiga running tournament boundary (RTB) — continuous implementation in this chat.

Read first:
- docs/amiga-running-tournament-boundary-policy.md
- docs/amiga-running-tournament-boundary-implementation-plan.md (full plan; § "DDL — holy ops only" is mandatory before RTB-1)
- docs/amiga-running-tournament-boundary-inventory.md (touchpoint checklist)

Execute slices in order: RTB-PREFLIGHT (if needed) → RTB-1 → RTB-2 tranche → RTB-3 → RTB-4 → RTB-5 → RTB-6 → RTB-7 → RTB-8. Run each slice Verification / STOP gate before continuing to the next. Do not skip ahead.

Resume rule: inspect git status and the plan to see which slice is done; continue at the first incomplete slice unless I say otherwise.

DDL (RTB-1): edit ONLY scripts/amiga/sql/structure/006_tournament_fixtures.sql; apply ONLY via python -m scripts.amiga prove. FORBIDDEN: mysql ALTER, 047_*.sql, apply_schema_structure one-off, ops/sql/migrations.

Do not git commit unless I ask. UPDATE_DOCS Part A (+ Part B for DDL) at RTB-8 in the same turn as shipping code.
```

**Mid-track steer (same chat):** e.g. “pause after RTB-2”, “skip to RTB-5”, “only fix RTB-3 parity” — agent stays in this track; do not require a new chat.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | **RTB shipped** — RTB-PREFLIGHT through RTB-8 complete; `prove` green. |
| 2026-07-07 | **Rev. 3** — single continuous starter prompt (all slices one chat); removed per-slice-only prompts. |
| 2026-07-07 | **Rev. 2** — § **DDL — holy ops only**; IP14 prove-only; RTB-1 forbids manual ALTER / 047 migrations (post agent trap). |
| 2026-07-07 | **Plan created** — slices RTB-PREFLIGHT through RTB-8; touchpoint registry; locked IP1–IP15; promote algorithm; tranche RTB-2 rule. |
