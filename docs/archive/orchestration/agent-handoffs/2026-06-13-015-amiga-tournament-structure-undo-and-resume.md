# Amiga tournament structure — undo pilot damage & resume track

**Date:** 2026-06-13  
**Audience:** Dagh + implementation agent  
**Context:** First implementation chat shipped slice 3 pilot before policy v2 was locked. Commit `882b440` on `main` includes slices 1–2, corrected 3b code, and policy docs. **Slice 3 pilot is void** (never passed GATE B).

**Local `ko2amiga_db` (Jun 2026):** Athens IV (74) already dematerialized and standings rebuilt (6× `league`). Agent should **verify** §2C, not assume damage remains.

---

## 1. What was actually damaged?

| Area | Damage? | Detail |
|------|---------|--------|
| **Git / code** | **Low** | Slice 3 *pilot logic* (NULL⇒KO) was **replaced** by 3b before commit. `materialize_legacy.py` on `main` matches policy v2.1. Slices 1–2 are sound. |
| **Local DB `ko2amiga_db`** | **Fixed** (Jun 2026) | Athens IV (74): pilot materialize **dematerialized**; standings **rebuilt** → 6× `league`/`''` (phase-parser path). No stages, no `fixture_id` links. |
| **Homburg (137)** | **Not slice 3** | 13 stages, 86 linked games = curated format-backbone / `StructureSpec` path. **Do not dematerialize.** |
| **Migration `023`** | **Keep** | Stage enum `round_robin`\|`knockout` — correct, unrelated to pilot. |
| **Staging / prod** | **Unknown** | If first chat ran `materialize` on staging, repeat §2 there. Repo push does not mutate remote DB. |
| **Bulk backfill** | **None** | Slice 5 never ran. |

**Slice 3 pilot never passed STOP GATE B** — no authority to bulk materialize or treat Athens IV as validated.

---

## 2. Undo checklist (run on each DB that might have pilot data)

### A. Find imported tournaments with legacy materialize footprint

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "
SELECT t.id, t.name,
  (SELECT COUNT(*) FROM tournament_stages s WHERE s.tournament_id=t.id) AS stages,
  (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id=t.id AND g.fixture_id IS NOT NULL) AS linked
FROM tournaments t
WHERE t.source_id IS NOT NULL
  AND (
    (SELECT COUNT(*) FROM tournament_stages s WHERE s.tournament_id=t.id) > 0
    OR (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id=t.id AND g.fixture_id IS NOT NULL) > 0
  );"
```

**Expect on local (Jun 2026):** empty, or only tournaments you intentionally materialized after 3b.

**Do not include** generated/curated events (Homburg, kitchen tests) — they have `source_id` from Access but also `format_overrides.generated_by` or `structure_spec`; `dematerialize` refuses those.

### B. Per affected imported id (e.g. was 74)

```powershell
python -m scripts.amiga tournament-structure dematerialize --tournament-id <ID>
python -m scripts.amiga standings-rebuild --tournament-id <ID>
```

### C. Verify Athens IV (reference)

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "
SELECT COUNT(*) stages FROM tournament_stages WHERE tournament_id=74;
SELECT COUNT(*) linked FROM amiga_games WHERE tournament_id=74 AND fixture_id IS NOT NULL;
SELECT scope_type, COUNT(*) FROM amiga_tournament_standings WHERE tournament_id=74 GROUP BY scope_type;"
```

Expect: `stages=0`, `linked=0`, `league` only (6 rows).

### D. Confirm migration 023 (keep)

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -e "SHOW COLUMNS FROM tournament_stages LIKE 'stage_type';"
```

Expect: `enum('round_robin','knockout')`.

---

## 3. Should we revert commit `882b440`?

| Option | When |
|--------|------|
| **Keep commit (recommended)** | Slices 1–2 + 3b code + policy v2.1 are what we want. Slice 3 pilot is documented as superseded. Resume at slice 4. |
| **Revert only `materialize_legacy.py` + CLI** | Only if you want materialize **re-landed from scratch** in a fresh chat with zero pilot history. Unlikely needed — 3b code matches policy. |
| **Revert entire `882b440`** | Would lose `023`, builders, policy — **do not**. |

**Verdict:** Commit timing was early, but **content after 3b is aligned**. Problem was **pilot on DB + GATE B skipped**, not the commit itself.

---

## 4. What the first track chat got wrong (do not repeat)

1. NULL-phase `not full RR ⇒ knockout` (reverted in policy T20).
2. One event-wide KO **stage** for Athens IV (reverted — tie = stage, T3).
3. Athens IV as auto-materialize pilot (tier C — manual `StructureSpec` only).
4. Proceeding toward bulk slice 5 without policy sign-off.

---

## 5. Resume track — new implementation chat

Dagh opens a **new** Cursor agent chat and pastes the RESUME block from `docs/archive/orchestration/agent-handoffs/amiga-tournament-structure-STARTER-PROMPT.md`. No manual git steps for Dagh.

### Agent reads (order)

1. This file (015)
2. [`2026-06-13-013-amiga-tournament-structure-restart-handoff.md`](2026-06-13-013-amiga-tournament-structure-restart-handoff.md)
3. [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) — T1–T22, especially §1
4. [`docs/amiga-tournament-structure-implementation-plan.md`](../../amiga-tournament-structure-implementation-plan.md) — **slice 4**

### Slice status

| Slice | Status |
|-------|--------|
| 1–2 | Done — do not redo |
| 3 pilot | Void — superseded |
| 3b | Code on `main`; verify GATE B′ (§6) then proceed |
| **4** | **Next** — `verify-legacy` CLI + tier A/C inventory |
| 5 | Tier-A bulk only — after GATE C |

---

## 6. GATE B′ (before slice 4) — quick user checks

1. `python -m scripts.amiga tournament-structure materialize --tournament-id 74` → **FAIL** `needs_structure_review`
2. `python -m unittest scripts.amiga.test_tournament_structure -q` → **OK**
3. Optional: `--dry-run` materialize on one known full NULL-phase marathon id
4. Reply **OK for slice 4**

---

## 7. Staging reminder

After WinSCP sync, apply `023` on staging DB if not already applied. **Do not** run bulk materialize on staging until slice 5 + GATE C.

---

*Planning chat assessed damage Jun 2026; Athens IV local standings restored.*
