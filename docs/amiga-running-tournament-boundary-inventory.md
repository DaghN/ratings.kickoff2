# Amiga running tournament boundary — inventory

**Status:** **Inventory for implementation (Jul 2026, rev. 2)** — engineering checklist for [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (RTB rev. 1). **Shipped Jul 2026** (RTB-1–RTB-8).

**PHP/Python asymmetry (today):** browser `record_result` calls `amiga_ops_process_derived_for_game()` → standings + catalog; Python `record-result` inserts `amiga_games` only. RTB removes PHP L5 writes; parity = fixture columns only on both sides.

---

## 1. Problem summary (current vs target)

| Action today | Writes to | Target |
|--------------|-----------|--------|
| `record_result` (browser + CLI) | `amiga_games`, `tournament_fixtures.status`, `amiga_tournament_standings`, `amiga_tournament_catalog_stats` | **Fixture running columns only** + `status=played` |
| Each score (ops path) | `amiga_ops_standings_apply_game()` | **Removed** pre-official |
| `Make official` / finalize | Reads `amiga_games`, writes L5 | **Promote** running → `amiga_games`, then **same** finalize derive |
| Live detail page | `LEFT JOIN amiga_games` for scores | Read fixture running columns |
| Orphan delete | `COUNT(amiga_games)` | Official games only (unchanged once RTB1 shipped) |

---

## 2. Schema pin (decision required before RTB-1)

**Recommendation (policy §5.1):** extend `tournament_fixtures` with running result columns.

| Column | Type | Notes |
|--------|------|-------|
| `goals_a` | `smallint unsigned NULL` | NULL = not played |
| `goals_b` | `smallint unsigned NULL` | |
| `extra` | `varchar` NULL | ET / pens |
| `result_recorded_at` | `datetime NULL` | optional audit / display order within event (policy §5.1: align on this name; table already has `scheduled_at`) |

- **DDL canon:** edit **`scripts/amiga/sql/structure/006_tournament_fixtures.sql`** only; apply via **`python -m scripts.amiga prove`** ([`amiga-running-tournament-boundary-implementation-plan.md`](amiga-running-tournament-boundary-implementation-plan.md) § DDL — holy ops only). **Forbidden:** manual `mysql ALTER`, numbered `047_*.sql` (same trap as removed `047_player_source.sql`).
- Existing `amiga_games.fixture_id` FK: `ON DELETE SET NULL` — promote creates rows; abandon before promote leaves no games; undo clears fixture columns only.
- **Running integrity rule (verify):** `status = played` ⇒ `goals_a`/`goals_b` non-null and **no** `amiga_games` row for that `fixture_id` while `rating_finalized = 0`.
- Alternative (`tournament_fixture_results` 1:1 table) — only if fixture row width is undesirable; inventory assumes fixture columns unless Dagh chooses otherwise.

---

## 3. Writers to change (pre-official must stop)

### 3.1 PHP — browser ops

| File | Symbol / action | Change |
|------|-----------------|--------|
| [`site/public_html/amiga/ops/fixtures.php`](../site/public_html/amiga/ops/fixtures.php) | `amiga_fixture_record_result()` | Update fixture running columns; **no** `INSERT amiga_games`; **no** `amiga_ops_process_derived_for_game()` |
| Same | POST `record_result` handler | Flash copy; remove game id messaging or use fixture id |
| Same | `amiga_fixture_undo_unprocessed_result()` | Clear fixture result columns; reopen fixture; **recompute broadcast standings** (no persist); **no** `DELETE amiga_games` |
| Same | `amiga_fixture_reprocess_tournament_derived()` | **Keep** — this *is* Make official (`POST reprocess_tournament_derived` → `amiga_finalize_tournament()`). Add **promote** prefix inside finalize path; do not retire |
| Same | `amiga_fixture_partition_for_results()` | Played partition: fixture `status` + goals columns, not `game_id` from `amiga_games` |
| Same | Assign-players / record guards | Replace `COUNT(amiga_games WHERE fixture_id=?)` with played-fixture / goals-null checks |
| Same | Fixtures tab / tournament picker queries | Drop `amiga_games` joins for running `game_count`; use played-fixture counts |
| [`site/public_html/amiga/ops/modules/process_completed_game.php`](../site/public_html/amiga/ops/modules/process_completed_game.php) | `amiga_ops_process_derived_for_game()` | Must not run on running score entry (caller removal) |
| Same | `amiga_process_completed_game()` `tournament_use_finalize` skip | Keep — non-tournament games only |
| [`site/public_html/amiga/ops/includes/amiga_post_game_standings.php`](../site/public_html/amiga/ops/includes/amiga_post_game_standings.php) | `amiga_ops_standings_apply_game()` | **Official path only** (called from finalize after promote) |
| Same | `amiga_ops_catalog_stats_refresh_tournament()` | **Official path only** |

### 3.2 Python — CLI parity

| File | Symbol | Change |
|------|--------|--------|
| [`scripts/amiga/tournament_fixtures.py`](../scripts/amiga/tournament_fixtures.py) | `record_fixture_result()` | Mirror PHP: fixture columns only |
| Same | `list_fixtures()` / `fixture_detail()` | Scores from fixture columns; drop `amiga_games` join (~807–835) |
| Same | `fixtures record-result` CLI | Help text; print `fixture_id` not `game_id` (~2204) |
| Same | `attach_game()` / `fixtures attach-game` | **CLI-only today** — retire for running path or official repair only (~562–627) |
| Same | `audit_fixture_integrity()` | Running: `played` ⇒ goals on fixture, **zero** attached games (~1810–1822) |
| Same | `fixtures verify` / `verify-lifecycle` | Update draft/ready/running + games rules (~1731–1747) |
| [`scripts/amiga/tournament_builder.py`](../scripts/amiga/tournament_builder.py) | test helpers + `verify_built_tournament()` | Fixture-column results; game attach counts (~903–906) |
| [`scripts/amiga/finalize_tournament.py`](../scripts/amiga/finalize_tournament.py) | `finalize_tournament()` | Add **promote** step before game loop OR shared promote module |

### 3.4 Lifecycle gate helpers (PHP)

Replace **`amiga_games` counts** with **played-fixture** (or goals-present) counts while `rating_finalized = 0`:

| File | Symbol / area | Notes |
|------|---------------|-------|
| [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) | `amiga_fixture_count_tournament_games()` | Rename or dual-path: official games vs running played fixtures |
| Same | Void transition, `can_void`, `complete_blocked_reason` | ~1162 — zero **official** games; running scores OK to void per product rules |
| Same | `$tournamentCanMakeOfficial` | ~3304–3309 — all scheduled fixtures played (fixture columns), not games attached |
| Same | Generated tournament list `game_count` join | ~3199–3203 — played-fixture count for running |

### 3.3 New module (promote)

| Deliverable | Responsibility |
|-------------|----------------|
| `amiga_promote_running_tournament()` (PHP) | Transaction: fixture played rows → `amiga_games` batch; assign `source_scores_id` + `game_date`; idempotency guard |
| `promote_running_tournament()` (Python) | Parity for CLI finalize |
| Wire into | `amiga_finalize_tournament()` / `finalize_tournament()` **before** rating loop |

**Promote input shape** (per played fixture):

```text
fixture_id, stage_id, tournament_id, player_a_id, player_b_id,
goals_a, goals_b, extra, phase_label, leg_no
→ amiga_games row + fixture_id FK
```

**Finalize input unchanged after promote:** `amiga_ops_load_tournament_games_for_finalize()` / `GAME_SELECT_FOR_TOURNAMENT`.

---

## 4. Readers to change (broadcast)

### 4.1 Public live hub

| File | Function | Change |
|------|----------|--------|
| [`site/public_html/includes/amiga_tournament_lib.php`](../site/public_html/includes/amiga_tournament_lib.php) | `amiga_live_tournament_fixture_groups()` | Scores from fixture columns; drop `amiga_games` join |
| Same | `amiga_tournament_game_count()` | Count played fixtures (or running goals present), not `amiga_games` |
| [`site/public_html/amiga/live-tournament.php`](../site/public_html/amiga/live-tournament.php) | — | Uses above helpers |

### 4.2 Organizer UI

| File | Area | Change |
|------|------|--------|
| [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) | Results tab queries | Join fixture columns not `amiga_games` |
| Same | Table tab | **Compute** standings from running fixtures (new helper); no `amiga_tournament_standings` read while running |
| Same | Fixtures tab preview | Scores from fixture columns when played |
| Same | Make official button | Promote + finalize; copy update; gates per §3.4 |

### 4.3 New broadcast helpers (suggested)

| Helper | Role |
|--------|------|
| `amiga_running_tournament_games($con, $tournamentId)` | Array of game-shaped dicts from fixtures for standings math |
| `amiga_running_tournament_standings_rows($con, $tournamentId)` | Reuse shared compute on running array — **read-only, no persist** |

Refactor note: **single source of truth** for standings math:

- PHP: extract from `amiga_post_game_standings.php` (`amiga_ops_compute_tournament_standings()`)
- Python: [`scripts/amiga/tournament_standings.py`](../scripts/amiga/tournament_standings.py) `compute_tournament_standings()` (builder smokes ~983–1025)
- Input = list of game dicts; output = standings rows. **Broadcast compute, promote batch, and finalize must share semantics** — highest drift risk.

---

## 5. Official readers (should already gate running — verify)

These must **exclude** `lifecycle_status = running` or require `rating_finalized = 1`:

| Surface | File / note |
|---------|-------------|
| Tournament catalog index | `amiga_tournament_lib.php` catalog queries + `amiga_tournament_catalog_stats` |
| Games hub / recent | `amiga_realm_games_all.php` — rated join |
| Player profile | `amiga_player_load.php` — career join |
| Activity growth lede | `amiga_community_stats` — post-official only |
| Header player search | Shows `amiga_players` — OK; profile still official |

**Audit task:** grep `amiga_games` + `tournament_id` on public paths; ensure running tournaments invisible.

---

## 6. Lifecycle and delete

| Verb | File | RTB behaviour |
|------|------|---------------|
| Start tournament | `fixtures.php` | Unchanged — `lifecycle_status = running` |
| Make official | `finalize_tournament.php` via `amiga_fixture_reprocess_tournament_derived()` | Promote + derive + `completed` |
| Browser **Void** | `fixtures.php` | Today: only when zero `amiga_games` — retarget to zero **official** games / running package rules |
| `cleanup-generated` / delete league | `tournament_fixtures.py` (~1576–1610); PHP if wired | **CLI today** for full workspace delete; delete running package only; no L3 if RTB1 compliant |
| Orphan sweep | `player_orphans.py`, `k2_amiga_player_naming.php` | `COUNT(amiga_games)` — correct after RTB1 (running scores don't count) |

---

## 7. Chronology and IDs

| Concern | Today | Target |
|---------|-------|--------|
| `source_scores_id` | Allocated at `record_result` | Allocated at **promote** per game |
| `game_date` | `amiga_fixture_next_game_date()` at record | Batch at promote (preserve relative order from fixtures) |
| `fixtures attach-game` | Links existing `amiga_games` to fixture | **CLI-only** (`tournament_fixtures.py`); retire for running path or official repair tool |

Files: `amiga_fixture_next_live_source_scores_id`, `amiga_fixture_next_game_date` in `fixtures.php` — move call site to promote.

---

## 8. Docs to update at ship

| Doc | Update |
|-----|--------|
| [`amiga-data-contract.md`](amiga-data-contract.md) | § record-result, fixture/game chain for Lane B |
| [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) | Lane B capability row; cross-link RTB policy |
| [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) | §1 live path: scores on fixtures until promote |
| [`scripts/amiga/README.md`](../scripts/amiga/README.md) | CLI record-result / finalize |
| [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) | Drill steps |
| [`amiga-player-create-policy.md`](amiga-player-create-policy.md) | §6.4 surfacing — align with RTB9 |
| `AGENTS.md` | Cold-start row |

---

## 9. Verification / oracles

| Check | Command / surface |
|-------|-------------------|
| Zero `amiga_games` for running TID after scores | SQL assert in new verify script |
| Promote idempotency | Refuse second promote |
| Make official parity | PHP finalize == Python finalize on same DB |
| Holy loop | `python -m scripts.amiga prove` still green (Lane A unchanged) |
| Practice tournament | [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) Ref-League-A drill |

**New verify (RTB-8):** `verify-running-tournament-boundary.py` — generated/live-ops tournaments with `rating_finalized = 0` must have **no** `amiga_games` rows.

**Existing oracles to update (will fail after RTB-2 without changes):**

| Oracle | File | Issue |
|--------|------|-------|
| `verify-rating-events` | `scripts/amiga/verify_rating_events.py` | Flags `rating_finalized=0` + games — needs **live-ops scoped exception** or staging hygiene |
| `fixtures verify` | `tournament_fixtures.py` `audit_fixture_integrity()` | `played` ⇒ exactly one game — becomes fixture goals, zero games while running |
| `verify-lifecycle` | `tournament_fixtures.py` (~1731–1747) | draft/ready + games rules |
| Builder smokes | `tournament_builder.py` `verify_built_tournament()` | Attached game counts |
| `prove` suite | `prove.py` | Includes `verify-rating-events` — not “no change”; scope live-ops vs Lane A |

Run oracle updates on **staging before cutover**; do not ship RTB-2 to a DB with old running leagues that still have `amiga_games` rows without migration/cleanup.

**Export / staging:**

| Tool | Note |
|------|------|
| `scripts/amiga/export_packs.py` | Includes `amiga_games` — mid-tournament export must not imply official games for running events |
| `scripts/audit_ko2amiga_export_tables.py` | Audit table list includes `amiga_games` |

---

## 10. Implementation slices (proposed order)

| Slice | Goal | Depends |
|-------|------|---------|
| **RTB-1** | Schema: fixture running result columns | — |
| **RTB-2a** | PHP `record_result` / undo → fixture only; remove `amiga_ops_process_derived_for_game` on score entry; undo triggers broadcast recompute | RTB-1 |
| **RTB-2b** | Lifecycle gates (§3.4): Make official, void, complete, picker counts → played-fixture not `amiga_games` | RTB-2a |
| **RTB-2c** | Organizer + live **broadcast reads** (§4.1–4.2) — **ship with RTB-2** so UI does not go blank | RTB-2a |
| **RTB-3** | Python `record_fixture_result`, `list_fixtures`/`fixture_detail`, CLI output | RTB-1 |
| **RTB-4** | Shared standings compute refactor (PHP + `tournament_standings.py`); Table tab broadcast compute | RTB-2c |
| **RTB-5** | Promote module (PHP + Python) | RTB-1 |
| **RTB-6** | Wire promote into Make official / finalize (`reprocess_tournament_derived` path) | RTB-5 |
| **RTB-7** | Verify oracles + `verify-running-tournament-boundary` + practice-track staging drill | RTB-6 |
| **RTB-8** | Docs + data-contract + export notes | RTB-7 |

**STOP gates:**

- After **RTB-2b + RTB-2c**: enter scores on staging — `amiga_games` count = 0 while running; Results/Fixtures/Live hub show scores; Make official button eligible when all fixtures played.
- After **RTB-6**: Make official — profile + Activity counts update; games in canon.
- Before **RTB-7** on staging: clean or migrate any pre-RTB running leagues that still have `amiga_games` rows.

---

## 11. Risk register

| Risk | Mitigation |
|------|------------|
| Staging DB already has running tournaments with `amiga_games` | One-time migration or delete test leagues before cutover |
| Standings compute drift (broadcast vs finalize) | Single shared compute (PHP + `tournament_standings.py`); see §4.3 |
| Lifecycle/Make-official still keyed on `amiga_games` after RTB-2a | RTB-2b before STOP gate |
| `verify-rating-events` red on staging | Scoped live-ops exception or DB cleanup before cutover |
| Two-leg KO ordering at promote | Use `leg_no`, `fixture.id` ordering when assigning `game_date` |
| Prove/import confusion | RTB11 — Lane A untouched; verify script scoped to live-generated tournaments |
| Export to staging mid-tournament | Running package exports in L4; no spurious games in dump |

---

## 12. Files quick index

**Touch (write path):** `fixtures.php`, `process_completed_game.php`, `amiga_post_game_standings.php`, `finalize_tournament.php`, `tournament_fixtures.py`, `tournament_standings.py`, `finalize_tournament.py`, `tournament_builder.py`, new promote module(s).

**Touch (read path):** `amiga_tournament_lib.php`, `live-tournament.php`, `fixtures.php` (table/results/fixtures/picker views).

**Touch (verify):** `verify_rating_events.py`, `prove.py`, `tournament_fixtures.py` verify helpers, `export_packs.py`.

**Lane A unchanged:** `import_access.py`, `replay.py`, historical `tournament.php` (post-official). **`prove.py` wiring changes** for live-ops oracle scope only.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | **Rev. 3** — schema pin: prove-only DDL (no 047 migration); cross-link plan § DDL holy ops. |
| 2026-07-07 | **Implementation plan** — [`amiga-running-tournament-boundary-implementation-plan.md`](amiga-running-tournament-boundary-implementation-plan.md). |
| 2026-07-07 | **Rev. 2** — audit fold-in: lifecycle gates §3.4, CLI readers, verify oracles, undo recompute, `reprocess` correction, slice reorder, export row. |
| 2026-07-07 | Initial inventory for RTB policy rev. 1. |