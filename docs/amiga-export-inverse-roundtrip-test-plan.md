# Amiga export / inverse changelog round-trip test plan

> **Status:** Phase A **PASS** · Phase B **PASS** · Phase C **PASS** (2026-07-23) — export packing + Case C inverse seed proven on staged; **triple agreement** GitHub seal `work-2026-07-23-inverse-roundtrip` ≡ work ≡ staged (tip #607, #16 present, inverse **3423**).  
> **Purpose:** Prove (1) full simul rebuilds trustworthy TT inverse counts, (2) JSON-driven export/seal packs ship that data, (3) staged L5 backup ↔ restore round-trips with full parity, then (4) Case C mid-delete stays inverse-correct.  
> **Authority:** Staged `ko2amiga_db` = prod · local `ko2amiga_work` = repair shop — [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md).  
> **Related:** [`amiga-player-inverse-count-timeline-policy.md`](amiga-player-inverse-count-timeline-policy.md) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) · L5 [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) **Implemented**. Build **`l5-case-c-inv-seed-2026-07-23`**.

---

## 0. Why this exists

Jul 15 shipped sparse `amiga_player_inverse_count_at_event` (TT authority for four inverse counts). Local verify was green (~3.4k rows), but the Jul 18 forum GitHub seal shipped **schema without a data part** (PS1 multipart dump was a second hardcoded list). Staged / seal restores showed **0** changelog rows → **present** inverse can still look OK (pointer recount / current), but **TT** mosaic / Victims LB under `as=` were empty/wrong.

Jul 23 fixed both exporters to dump **every** registry table from JSON (games/ratings chunked only). This plan proves that fix end-to-end before another Case C trial.

---

## 1. Hard gates (do not skip)

| Gate | Fail if |
|------|---------|
| **G1 Inverse pack size** | Changelog row count &lt; **1000** after simul (healthy ≈ **3.2k–3.5k** on full tip-607 realm) |
| **G2 Present oracle** | Any `amiga_player_current` inverse col ≠ pointer recount on current |
| **G3 TT oracle** | Sampled cutoffs: changelog latest ≤ cutoff ≠ pointer recount at that cutoff |
| **G4 Sanity bounds** | Any inverse count &gt; rated-player population (or other bound below) |
| **G5 Manifest data part** | New export/seal `ko2amiga_manifest.json` has **no** inverse data file, or file is empty of rows |
| **G6 Round-trip parity** | Side-pull staged vs work: inverse count + signature mismatch (hard); other soft float noise OK |

**Pass phrase:** present + TT oracles green, pack non-empty, staged restore matches work.

---

## 2. Phase A — Local rebuild + new GitHub seal

### A1. WinSCP sync (can wait until before Phase B L5 backup)

Sync at least:

- `site/public_html/amiga/includes/amiga_backup_seal_lib.php` (JSON-driven seals; build `l5-export-json-2026-07-23`)
- `site/public_html/amiga/ops/includes/amiga_event_snapshot_persist.php` (player WC slice participate checkpoints — for later Finish / Case C)

`scripts/lib/Export-Ko2AmigaStaging.ps1` is **local only** — no WinSCP.

### A2. Restore forum GitHub seal onto local work

Source: `data/amiga/checkpoints/work-2026-07-18-forum/`  
Import parts in `ko2amiga_manifest.json` order into **`ko2amiga_work`** (fresh DB or replace).

**Expect after restore:**

```sql
SELECT COUNT(*) FROM ko2amiga_work.amiga_player_inverse_count_at_event;
-- expect 0  (schema-only omission in that seal)
```

Tip should be **#607** (Nottingham III) if seal matches prior baseline.

### A3. Simul + hardened inverse checks

```powershell
python -m scripts.amiga simul
# If video_align fails on manifest drift: ladder replay OK is enough for this gate; do not block on video.
```

#### A3.1 Automated (required)

```powershell
python -m scripts.amiga verify-inverse-count-changelog
```

Must print `OK` with `rows=` ≈ 3.4k (script already fails if rows &lt; 1000, present≠pointer, and Nazim/Athens I TT sample).

#### A3.2 Pack size + population sanity (required)

```sql
SELECT COUNT(*) AS changelog_rows FROM amiga_player_inverse_count_at_event;
SELECT COUNT(*) AS players FROM amiga_players;
SELECT COUNT(*) AS rated_players FROM amiga_player_current WHERE COALESCE(NumberGames, 0) > 0;
```

| Check | Rule |
|-------|------|
| Changelog rows | ≥ 1000 (prefer ~3200–3500) |
| Per-player present inverse | Each of the four cols on `amiga_player_current` is **0 … rated_players−1** (cannot exceed other players) |
| Sum vs pointers | For each metric, `SUM(inverse_col)` on current **equals** `COUNT(*)` of non-null matching pointer cols on current (every pointer credits exactly one hero) |

Example (MGS culprits):

```sql
-- Bound
SELECT MAX(MostGoalsScoredCulprits) AS mx,
       (SELECT COUNT(*) FROM amiga_player_current WHERE COALESCE(NumberGames,0) > 0) AS rated
FROM amiga_player_current;
-- require mx < rated (strictly ≤ rated-1)

-- Conservation: sum of culprits == number of victims who point
SELECT
  (SELECT COALESCE(SUM(MostGoalsScoredCulprits),0) FROM amiga_player_current) AS sum_counts,
  (SELECT COUNT(*) FROM amiga_player_current
   WHERE MostGoalsScoredVictimID IS NOT NULL AND MostGoalsScoredVictimID > 0) AS pointer_rows;
-- require equal
```

Repeat for `BiggestWinCulprits` / `BiggestWinVictimID`, `MostGoalsConcededVictims` / `MostGoalsConcededCulpritID`, `BiggestLossVictims` / `BiggestLossCulpritID`.

#### A3.3 TT: changelog vs pointer oracle at cutoff (required)

**Idea (yes — this is the right double-check):** at cutoff tournament *T*, website TT reads **latest changelog row ≤ T**. Independently, recount pointers from **latest event snapshot ≤ T per player**. Those two must match for sampled heroes (same oracle as present, but on the TT snapshot lens).

Pick at least **two** cutoffs:

| Sample | Why |
|--------|-----|
| **Athens I (`id=27`)** | Built into `verify-inverse-count-changelog` (Nazim `327` / `mgc_victims` = 2) |
| **One mid-realm WC or kitchen** (e.g. tip−50 or a known WC) | Catches “only tip present is OK” |

For a chosen `(player_id, metric, cutoff_tid)`:

1. **Changelog path** — latest `value_after` with `(event_date, event_chrono, tournament_id) ≤ cutoff`.  
2. **Pointer path** — among players with a snapshot ≤ cutoff, `COUNT(*)` where pointer column = hero.  
3. Require **equal**.

Sketch (MGC victims for hero `H` at tournament `T`):

```sql
-- (1) Changelog latest ≤ T
SELECT value_after FROM (
  SELECT c.value_after,
         ROW_NUMBER() OVER (
           ORDER BY c.event_date DESC, c.event_chrono DESC, c.tournament_id DESC
         ) AS rn
  FROM amiga_player_inverse_count_at_event c
  INNER JOIN tournaments t ON t.id = ?
  WHERE c.player_id = ? AND c.metric = 'mgc_victims'
    AND (c.event_date, c.event_chrono, c.tournament_id)
        <= (t.event_date, t.chrono, t.id)
) x WHERE rn = 1;

-- (2) Pointer oracle at cutoff (latest snap ≤ T per player)
SELECT COUNT(*) AS oracle_n
FROM (
  SELECT s.MostGoalsConcededCulpritID AS ptr,
         ROW_NUMBER() OVER (
           PARTITION BY s.player_id
           ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
         ) AS rn
  FROM amiga_player_event_snapshots s
  INNER JOIN tournaments t ON t.id = ?
  WHERE (s.event_date, s.event_chrono, s.tournament_id)
        <= (t.event_date, t.chrono, t.id)
) latest
WHERE rn = 1 AND ptr = ?;  -- hero id
```

Bind: cutoff tid, hero id, cutoff tid, hero id.

Also re-check **bounds at TT**: oracle_n ≤ number of players with a snapshot ≤ cutoff.

**Optional UI:** same hero under `as=` on mosaic / Victims LB — numbers must match SQL.

**Do not** treat “present mosaic looks fine” as TT proof (L5 pointer recount heals present without a changelog).

### A4. New GitHub / local checkpoint with new exporter

```powershell
python -m scripts.amiga audit-staging-export --database ko2amiga_work
powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label inverse-roundtrip
# Do NOT use -SkipExport
```

**G5 checks on the new pack:**

1. `ko2amiga_manifest.json` `parts[]` includes a file whose name contains `inverse_count` (or `player_inverse_count_at_event`).  
2. That SQL part contains `INSERT` rows (not empty dump).  
3. Optional: restore that pack into a scratch DB and `COUNT(*)` changelog ≈ work.

Commit / gitignore allowlist when ready — **not** required before Phase B, but recommended as the recoverable milestone.

**Stop Phase A** if any A3/A4 gate fails.

---

## 3. Phase B — Staged push + L5 seal round-trip

Only after Phase A green.

### B5. Export work → staged import payload

```powershell
# Prefer: pull staged first if community may have drifted since last sync
powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force -TargetDatabase ko2amiga_staging_cmp
# Spot-check tip vs work; then:

powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_work.ps1
```

Confirm `_import` manifest lists inverse data part (same G5).

### B6. Build staged from export

WinSCP `amiga/_import/` → browser import (`run_import_ko2amiga.php` preview → Apply).  
Destructive full replace — expected.

**After import (on staged or via Diagnose):**

```sql
SELECT COUNT(*) FROM amiga_player_inverse_count_at_event;
-- expect ≈ work (~3.4k), not 0
```

### B7. Manual L5 Backup now on staged

Requires A1 WinSCP of seal lib. Confirm seal build **`l5-export-json-2026-07-23`** (or synced equivalent).  
Open new seal under `amiga/_backups/` — G5 again (inverse data part + rows).

### B8. Restore staged from that backup

Prefer **Restore into DB now** from `_backups/` ([L5 BA4](amiga-staging-backup-admin-delete-policy.md)).  
Tip / tournament count sanity check.

### B9. Side-pull staged → compare to work

```powershell
powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force -TargetDatabase ko2amiga_staging_cmp
python scripts/oneoff/compare_work_vs_staging_cmp.py
# or equivalent P-1 compare used in Case C thorough test
```

**G6:** `amiga_player_inverse_count_at_event` count + signature **match**. Soft Elo float noise elsewhere OK.

**Stop Phase B** if G5/G6 fail — do not start Case C.

---

## 4. Phase C — Case C thorough parity

Only after Phase B green.

1. **Backup / reserve** on staged before delete.  
2. Case C delete chosen mid tournament **M** (record id; prefer non-WC kitchen if avoiding video_align noise).  
3. Side-pull staged → `ko2amiga_staging_cmp`.  
4. On work: ground-delete same **M** (not Case C PHP) + `simul --allow-ground-shrink` (video_align may fail).  
5. Compare work vs `staging_cmp` as before.

**Expect:**

| Area | Expectation |
|------|-------------|
| Games / standings / catalog / realm / community / WC HoF | Strong match |
| Inverse changelog | **Full history** still present on both if pack was full; Case C only rewrites **forward** after N — do not misread thin forward-only as pack failure |
| Present inverse oracle (G2) | **0** pointer mismatches on both DBs after Case C + tip finalize |
| Player `slice_at_event` | Closer after Jul 23 PHP participate persist (still may differ if Case C forward-only vs full simul densify — note, don’t confuse with inverse) |

### 4.1 Result — M=#16 World Cup XXI (2026-07-23)

**First run (pre-fix):** Case C seal OK; ground strong OK; **FAIL** — cmp present inverse **13** pointer mismatches (stored &gt; oracle); inverse 3413 vs work simul 3406.

**Root cause:** PHP finalize bootstrapped inverse counts from **prior snapshot columns** (participant-only / ghost-stale). Case C forward re-finalize then persisted that inflation into changelog + current. Simul keeps full in-memory state — no reload from snapshots — so work stayed clean.

**Fix:** `amiga_ops_seed_inverse_counts_from_changelog()` after participant snapshot load and ghost `amiga_post_game_player_load` (no-op when changelog pack empty). Build **`l5-case-c-inv-seed-2026-07-23`**.

**Re-proof (local `ko2amiga_staging_cmp`, M already gone):** truncate &gt; N=#15 → project N → finalize 10 forward → present mismatches **13→0**; inverse rows **3413→3406**; key-for-key **≡ `ko2amiga_work` simul**. **PASS.**

**Staged retest (same day):** sync seed fix → restore pre-#16 → Case C delete #16 → seal `seal-20260723-120630Z-case_c_delete` → side-pull cmp vs work: present_mm **0**, inverse **3406≡3406**. **PASS.**

### 4.2 Reset to full tip (after Case C experiments)

Both sides restored to GitHub seal **`work-2026-07-23-inverse-roundtrip`** (#16 present, inverse **3423**). Side-pull staged vs work: full P-1 **PASS** (triple agreement: seal ≡ work ≡ staged).

---

## 5. Checklist (copy into chat)

```text
[x] A2 forum seal restore — inverse COUNT = 0
[x] A3 simul (ladder OK)
[x] A3.1 verify-inverse-count-changelog OK (~3.4k rows)
[x] A3.2 bounds + sum(counts)=count(pointers) ×4 metrics
[x] A3.3 TT changelog = pointer oracle (Athens I + one more cutoff)
[x] A4 new checkpoint — manifest has inverse data part with INSERTs
[x] A1 WinSCP seal lib (+ snapshot persist) before B7
[x] B5 export — inverse in _import manifest
[x] B6 staged import — inverse COUNT ≈ work
[x] B7 L5 Backup — seal build JSON-export; inverse part OK
[x] B8 restore from that seal
[x] B9 side-pull cmp — inverse parity hard match
[x] C10 Case C + parity — PASS after inverse seed (M=#16 staged retest)
[x] Reset — seal ≡ work ≡ staged with #16 (triple agreement)
```

---

## 6. What “smart double-check” means (A3)

Yes — **changelog vs pointer recount** is the intended independent check:

| Lens | TT / present authority | Independent oracle |
|------|------------------------|--------------------|
| Present | `amiga_player_current` inverse cols | COUNT pointers on **current** |
| TT | Latest changelog ≤ cutoff | COUNT pointers on **latest snapshot ≤ cutoff** per player |

Sanity bounds (count ≤ other players; sum of counts = number of pointers) catch absurd packs even if two broken writers agree.

Do **not** “fix” TT by copying snapshot inverse columns into the changelog — that reintroduces the Jul 15 bug.

---

*Last updated: 2026-07-23 — A/B/C PASS; L5 inverse seed; triple agreement seal ≡ work ≡ staged.*