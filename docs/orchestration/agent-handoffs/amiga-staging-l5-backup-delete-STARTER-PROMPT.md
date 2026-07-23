# Starter prompt — Amiga Track L5 (CLOSED — archive note)

**Track status (2026-07-23):** **L5 v1 Complete** — slices 0–6. Do **not** start a new L5 implementation chat from this file unless Dagh reopens a bug.

**Policy:** [`amiga-staging-backup-admin-delete-policy.md`](../../amiga-staging-backup-admin-delete-policy.md) **Implemented (v1)**.

**Plan:** [`amiga-staging-l5-backup-delete-implementation-plan.md`](../../amiga-staging-l5-backup-delete-implementation-plan.md) **Complete (v1)**.

**Build:** `l5-case-c-inv-seed-2026-07-23`.

## What shipped

| Slice | What |
|-------|------|
| 0–2 | Seals + Finish hook + Restore into DB now |
| 3 | Case A unfinalized delete |
| 4 | Case B tip delete + `project-present-at` (pointer inverse, JOIN matchups) |
| 5 | Case C mid-delete + truncate > N + refinalize forward |
| 5+ | Inverse finalize seed (Case C thorough M=#16 PASS) |
| — | Export/seal JSON data parts; round-trip A/B/C; triple agreement seal ≡ work ≡ staged |
| 6 | Docs close (this session wrap) |

## Hard lessons (keep)

| Lesson | Rule |
|--------|------|
| Gateway ~30s | Phase HTTP: one Case C finalize per request |
| Inverse present re-project | Pointer recount — never snapshot cols / empty-changelog zero-fill |
| Inverse Case C forward | **Seed from changelog** at PHP finalize bootstrap — never reload snapshot inverse cols |
| Export packing | Data parts = `staging_export_tables.json` only (no second hardcoded list) |
| Compare | Side-pull `-TargetDatabase ko2amiga_staging_cmp` — never Force-pull work as compare target |

## Healthy baseline seal

`data/amiga/checkpoints/work-2026-07-23-inverse-roundtrip/` — tip #607, #16 present, inverse **3423**. Prefer over Jul 18 forum seal when TT inverse matters.

## If Dagh reports a new L5 bug

1. Read policy + plan + [`amiga-export-inverse-roundtrip-test-plan.md`](../../amiga-export-inverse-roundtrip-test-plan.md) + inverse policy §5.3.
2. Reproduce on work or side-pull cmp.
3. Fix process (not a one-off DB patch) unless he asks for a temporary repair.

---

*Archived as active starter 2026-07-23 — L5 closed.*