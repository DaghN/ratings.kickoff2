# Amiga modern simul — S-1 implementation plan

**Status:** **P-1 shipped** — next: PROMOTE-1 or V-1.

**Policy:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (§9 simul, §10 S-1, **MG11** copy-do-not-mutate)

**Parent track:** Modern ground cutover — bootstrap step 3 of §10.1 (`apply_structure` + simul on **`ko2amiga_work`**).

**Prerequisites shipped:**

| ID | Requirement |
|----|-------------|
| **D0-1** | `day0-2026-07-08` sealed in `data/amiga/day0/` (git) |
| **D0-2** | Local **`ko2amiga_db`** frozen — parity oracle only; no `prove`, no writes |
| **W-1** | **`ko2amiga_work`** seeded from day 0; L3 counts match manifest; derived cleared |

**Not in S-1:** **P-1** parity vs oracle · **PROMOTE-1** config/export switch · staging merge import · full **V-1** video port — policy: [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) (optional tail in S-1.8 — see below).

**Execution:** Slices **S-1.0 → S-1.9** in order. Each slice **Verification** must pass before the next. **Do not** edit `prove.py`, `import_access.py`, or other legacy prove orchestration (**MG11**).

---

## 1. Why this plan

| Artifact | Role |
|----------|------|
| **Policy** (`amiga-modern-ground-platform.md`) | What simul is / is not; DB names; MG11 compartment |
| **This plan** | Ordered slices, fork map, verify subset, STOP gates, risks, time budget |
| **Legacy `prove.py`** | Frozen oracle path on **`ko2amiga_db`** — reference only |

S-1 is the first time we prove the **bootstrap path end-to-end**: day 0 L3 → work → L4 disposition → L5 replay → (optional video) → verify — **without** Access, nuclear import, or ground truncate.

---

## 2. Goal (exit criteria)

After **S-1.9**, all of the following hold on **`ko2amiga_work`**:

1. **L3 ground unchanged by simul** — tournament / player / game row counts identical before and after the run (living work may exceed day 0).
2. **L4 structure materialized** — disposition dispatch ran; fixture/stage rows exist for handled tournaments (~515 materialized on oracle; pending_review skipped).
3. **L5 derived rebuilt** — full chronological replay; `amiga_game_ratings` row count = game count; snapshots/current populated.
4. **Modern verify suite green** — subset in §6 (no Access-era oracles).
5. **Legacy tree untouched** — `git diff` shows no edits under legacy prove path except allowed shared DDL (`scripts/amiga/sql/` if needed).
6. **`ko2amiga_db` untouched** — row counts on oracle unchanged (spot-check).

**CLI sign-off (target):**

```powershell
python -m scripts.amiga simul
# or: powershell -ExecutionPolicy Bypass -File scripts\run_amiga_simul.ps1
```

---

## 3. Locked implementation decisions

| # | Decision |
|---|----------|
| **S1-1** | All forward orchestration lives in **`scripts/amiga/modern/`** — primarily `simul.py` + forked helpers. **No** imports from `prove.py` or `import_access.py`. |
| **S1-2** | Simul target DB is **`ko2amiga_work` only** — hardcoded allow-list in modern modules (mirror legacy `ko2amiga_db` guards). |
| **S1-3** | Simul **does not** truncate L3 or strip L4 after first run. First run on W-1 seed: **run L4** from disposition, then L5. Later simuls on living work: **skip L4** unless disposition changed or `--apply-structure` passed. |
| **S1-4** | **DDL** continues via `schema_bundles.apply_schema` on work (same bundles as legacy — shared read-only SQL). `apply_schema(drop_existing=False)` on normal simul; `drop_existing=True` only with explicit `--recreate-schema` (dev disaster). |
| **S1-5** | **MG11:** need legacy behaviour → **copy file or function body** into `modern/`, rename, adapt DB connect. Do not change `replay.py` / `apply_structure.py` in place. |
| **S1-6** | **Verify reuse:** prefer **one** shared config hook (`load_work_db_config()` + env `KO2AMIGA_DATABASE` fallback in `config.py`) so existing `verify_*.py` modules run against work **without** forking 20 files. This is **shared infrastructure**, not legacy prove mutation. |
| **S1-7** | **Access-era verifiers excluded** from modern simul gate: `verify-import-manifest`, `verify-l2-l3`. |
| **S1-8** | **Video tail:** S-1 ships with `--skip-video` default **off** only after `modern/video_align.py` fork exists; otherwise default **on** until S-1.8 complete (V-1 can deepen). |
| **S1-9** | **Time budget:** expect **~5–30 min** wall clock for full replay + verify on laptop (same order as legacy prove L5 tail). |

---

## 4. Starting state (post W-1)

| Check | Expected |
|-------|----------|
| `ko2amiga_work.tournaments` | 605 |
| `ko2amiga_work.amiga_players` | 469 |
| `ko2amiga_work.amiga_games` | 27,418 |
| L4 tables | **empty** (schema only) |
| L5 derived | **empty** (placeholder id=1 rows in generalstats/community_stats only) |
| `ko2amiga_db` | Full legacy prove output — **read-only oracle** |

---

## 5. Target architecture (`scripts/amiga/modern/`)

```text
modern/
  constants.py          # WORK_DB, DAY0_DIR (exists)
  work_db.py              # connect_work, ensure_work_database (exists)
  db_config.py            # load_work_db_config() — S-1.1
  clear_derived.py        # fork from replay.clear_derived — S-1.0
  apply_structure.py      # fork from apply_structure.run_apply_structure — S-1.3
  replay.py               # fork from replay.run_replay — S-1.4
  simul.py                # orchestrator — S-1.5
  verify_suite.py         # modern _VERIFY_STEPS list + runner — S-1.6
  video_align.py          # fork from tournament_videos/sync_db_ids — S-1.8 (optional)
```

**Repo root:**

```text
scripts/run_amiga_simul.ps1     # thin wrapper: python -m scripts.amiga simul
site/config/ko2amiga_config_work.local.php.example   # optional; work uses Python override
```

**W-1 debt:** `seed_work.py` imports `replay.clear_derived` — fix in **S-1.0**.

---

## 6. Modern verify suite (simul gate)

### 6.1 Included (copy list from `prove.py` minus exclusions)

| Step | Module | Notes |
|------|--------|-------|
| verify-chronology | `verify_chronology` | L3 ordering |
| verify-is-world-cup | `verify_is_world_cup` | Flag column |
| verify-rating-events | `verify_rating_events` | Post-replay |
| verify-event-snapshots | `verify_event_snapshots` | |
| verify-player-participation | `verify_player_participation` | |
| verify-player-matchups | `verify_player_matchups` | |
| verify-player-slice | `verify_player_slice` | |
| verify-country-slice | `verify_country_slice` | |
| verify-wc-hof | `verify_wc_hof` | |
| verify-realm-snapshots | `verify_realm_snapshots` | |
| verify-community-stats | `verify_community_stats` | |
| verify-world-cup-stats | `verify_world_cup_stats` | |
| verify-php-community-parity | `verify_php_community_parity` | |
| verify-hof-geo-year | `verify_hof_geo_year` | |
| verify-perfect-event | `verify_perfect_event` | |
| verify-hof-holder-projection | `verify_hof_holder_projection` | |
| verify-hof-peak-rating-holder | `verify_hof_peak_rating_holder` | |
| verify-stored-id-date-pairs | `verify_stored_id_date_pairs` | |
| verify-country-registry | `verify_country_registry` | |
| verify-player-create | `verify_player_create` | |
| verify-running-tournament-boundary | `verify_running_tournament_boundary` | RTB |
| verify-tournament-formats | `verify_tournament_formats` | |

### 6.2 Excluded (Access / wrong era)

| Step | Reason |
|------|--------|
| verify-import-manifest | Access L3 manifest oracle — day 0 era |
| verify-l2-l3 | L2→L3 boundary — Access pipeline |
| verify-tournament-videos | **S-1.8** — include when `video_align` fork lands; else skip flag |

### 6.3 Work DB routing for verifiers

Legacy verifiers call `load_amiga_db_config()` → `ko2amiga_config.local.php` → usually `ko2amiga_db`.

**S-1.1 approach (locked):**

1. Add `load_work_db_config()` in `modern/db_config.py` — same credentials, `database=ko2amiga_work`.
2. Add optional env override in `scripts/amiga/config.py`:

   ```python
   # If KO2AMIGA_DATABASE is set, override $database from PHP (simul subprocesses only).
   ```

3. `verify_suite.py` sets `os.environ["KO2AMIGA_DATABASE"] = "ko2amiga_work"` before each verify `main()` call (or once at simul start).

**Forbidden:** editing each `verify_*.py` to hardcode work — use config hook only.

---

## 7. Simul loop (canonical)

```text
preflight     WORK_DB exists; L3 has tournaments + games (day 0 manifest = reference only)
→ schema      apply_schema(ko2amiga_work, drop_existing=False)  # migrate DDL only
→ L4          apply_structure --from-disposition  (first bootstrap: required)
→ L5          clear_derived → replay (full, no --limit)
→ video       video_align (optional / --skip-video)
→ verify      modern verify suite
→ postcheck   L3 counts unchanged; log derived row totals
```

**Flags (target CLI):**

| Flag | Effect |
|------|--------|
| `--skip-structure` | Dev only — not bootstrap sign-off |
| `--skip-video` | Skip video align + verify-tournament-videos |
| `--skip-verify` | Replay smoke only |
| `--recreate-schema` | `apply_schema(drop_existing=True)` — **destructive** to all tables; dev only |
| `--dry-run` | Structure/replay dry-run where supported |

---

## 8. Slices

### S-1.0 — MG11 hygiene (`clear_derived` fork)

**Work:**

1. Copy `replay.clear_derived` → `modern/clear_derived.py` (use `connect_work`).
2. Update `seed_work.py` to import from `modern.clear_derived`.
3. Grep `scripts/amiga/modern/` — zero imports from `replay` / `prove` / `import_access`.

**Verification:**

- [ ] `python -m scripts.amiga seed-work` still green.
- [ ] `rg "from scripts.amiga.replay" scripts/amiga/modern/` → no matches.

---

### S-1.1 — Work DB config hook

**Work:**

1. Add `modern/db_config.py` with `load_work_db_config()`.
2. Extend `config.load_amiga_db_config()` to honor `KO2AMIGA_DATABASE` env override (document in plan + `scripts/amiga/README.md`).
3. Add `ko2amiga_config_work.local.php.example` (optional mirror; Python override is sufficient).

**Verification:**

- [ ] One-liner: env set → `load_amiga_db_config().database == "ko2amiga_work"`.
- [ ] Unset env → still `ko2amiga_db` (legacy path unchanged).

---

### S-1.2 — Preflight helper

**Work:**

1. `modern/preflight.py` — assert work DB exists with L3 witness rows; day 0 manifest optional baseline (no count pin).

**Verification:**

- [ ] `python -c "… preflight …"` passes on current W-1 seed.

---

### S-1.3 — Fork L4 dispatch (`modern/apply_structure.py`)

**Work:**

1. Copy `run_apply_structure` + needed helpers from `apply_structure.py` into `modern/apply_structure.py`.
2. Replace `ko2amiga_db` guard with **`WORK_DB`**; use `connect_work()`.
3. Keep calling existing `apply_structure_from_disposition` logic (import from legacy module is **OK** only if that module does not connect — today it receives `conn`; **do not** call `run_apply_structure` legacy entry).

**Note:** `apply_structure.py` imports `connect_mysql` from `import_access` — **avoid**. Use `connect_work()` only.

**Verification:**

- [ ] `python -m scripts.amiga apply-structure-work --from-disposition` (new CLI alias) exits 0.
- [ ] `tournament_fixtures` row count > 0; L3 game count still 27,418.
- [ ] `verify-structure` optional smoke (or count fixtures > 0).

---

### S-1.4 — Fork L5 replay (`modern/replay.py`)

**Work:**

1. Copy `run_replay`, `clear_derived` usage, `tournament_ids_for_replay`, post-checks from `replay.py`.
2. `WORK_DB` connect only; call existing `finalize_tournament` (accepts `conn` — no db name guard).
3. Expose `run_replay_work(dry_run=, limit=)` — **`limit` forbidden for sign-off**.

**Verification:**

- [ ] After S-1.3 + S-1.4: `COUNT(amiga_game_ratings) = 27418`.
- [ ] `rating_finalized` set on tournaments with games.
- [ ] Wall time logged.

---

### S-1.5 — Simul orchestrator (`modern/simul.py`)

**Work:**

1. Implement `run_simul()` per §7.
2. Wire `python -m scripts.amiga simul` in `__main__.py`.
3. Add `scripts/run_amiga_simul.ps1`.

**Verification:**

- [ ] `python -m scripts.amiga simul --skip-verify` completes (replay only).
- [ ] L3 counts unchanged pre/post (script logs).

---

### S-1.6 — Modern verify suite (`modern/verify_suite.py`)

**Work:**

1. Copy step list from §6.1 into `verify_suite.py`.
2. Set `KO2AMIGA_DATABASE=ko2amiga_work` before running steps.
3. Fail fast with step name on non-zero exit.

**Verification:**

- [ ] `python -m scripts.amiga simul` full run exits **0**.

---

### S-1.7 — Post-simul invariants

**Work:**

1. Add postcheck to `simul.py`: L3 ground counts unchanged pre/post (not pinned to day 0); log summary JSON to `data/amiga/modern/simul-last.json` (gitignored).

**Verification:**

- [ ] `simul-last.json` documents counts + duration + git head.

---

### S-1.8 — Video align fork (optional in same PR; may follow immediately)

**Policy:** [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) — full **V-1** slice list §8.

**Work:**

1. Copy `tournament_videos/sync_db_ids.run` → `modern/video_align.py` using `connect_work` / `load_work_db_config`.
2. Add `verify-tournament-videos` to suite when not `--skip-video`.

**Verification:**

- [ ] `verify-tournament-videos` green on work.

*If deferred:* ship S-1 with `--skip-video` default **on**; track as **S-1.8** before P-1.

---

### S-1.9 — Docs + STOP gate

**Work:**

1. Mark S-1 **Done** in `amiga-modern-ground-platform.md` §10.
2. `PROJECT_MEMORY` line; `scripts/amiga/README.md` simul section.
3. `docs/UPDATE_DOCS.md` registry row if needed.

**STOP gate (non-negotiable):**

- [ ] `python -m scripts.amiga simul` exit **0** on `ko2amiga_work`.
- [ ] `ko2amiga_db` L3 counts unchanged (manual or script spot-check).
- [ ] No `git diff` on `prove.py`, `import_access.py`, `replay.py`, `apply_structure.py`.
- [ ] W-1 debt closed (`seed_work` uses `modern.clear_derived`).

---

## 9. Risks and mitigations

| Risk | Mitigation |
|------|------------|
| Agent edits legacy `replay.py` to “quick fix” work connect | **MG11** + STOP gate `git diff` |
| `apply_structure` imports `import_access.connect_mysql` in fork | Use `connect_work()` only in modern copy |
| Verify runs against wrong DB | Env override + preflight assert |
| L4 disposition failures (44 `pending_review`) | Expected skip; compare fixture counts to oracle order-of-magnitude |
| Replay runtime / laptop sleep | Log progress every 50 tournaments (copy legacy) |
| DDL drift between work and oracle | Same `schema_bundles`; simul runs `apply_schema` first |
| Video id mismatch on work | S-1.8 align; P-1 compares to oracle after |

---

## 10. Out of scope (explicit)

| Item | Slice |
|------|-------|
| Parity diff work vs `ko2amiga_db` | **P-1** |
| Point PHP / export at work | **PROMOTE-1** |
| Merge staging import | Follow-on |
| Retire legacy `prove` CLI | Post-promote |
| Fork `finalize_tournament` | **Not needed** — takes `conn` |
| Change disposition register | Read-only input |

---

## 11. Suggested session prompts

**Cold start (new agent chat):**

```text
Read: docs/amiga-modern-ground-platform.md, docs/amiga-modern-simul-implementation-plan.md.
Prereq: W-1 done (ko2amiga_work seeded from day0).
Execute slice S-1.0 only. MG11: copy legacy code into scripts/amiga/modern/, do not mutate prove.py/import_access.py/replay.py.
```

**Continue:**

```text
Continue Amiga S-1 from docs/amiga-modern-simul-implementation-plan.md — next unchecked slice after S-1.x.
```

---

## 12. Changelog

| Date | Change |
|------|--------|
| 2026-07-08 | **S-1 shipped** — all slices S-1.0–S-1.7 + S-1.9; S-1.8 video fork deferred (`--with-video` when ready). |
| 2026-07-08 | Plan authored — S-1.0–S-1.9, MG11 fork map, verify subset, work DB config hook. |
