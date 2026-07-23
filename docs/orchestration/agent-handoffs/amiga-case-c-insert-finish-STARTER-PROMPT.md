# Starter prompt — Amiga Case C insert / mid-history Finish

**Track:** Case C insert (mid-history **Finish and make official**).

**Status:** Design locked Jul 2026 — **code not started**. Do not implement until Dagh names a slice.

**Read first (order):**

1. [`amiga-case-c-insert-finish-implementation-plan.md`](../amiga-case-c-insert-finish-implementation-plan.md) — **authority for slices**
2. [`amiga-staging-backup-admin-delete-policy.md`](../amiga-staging-backup-admin-delete-policy.md) **AD7**
3. [`amiga-live-ops-platform.md`](../amiga-live-ops-platform.md) §7.3.1
4. [`amiga-running-tournament-boundary-policy.md`](../amiga-running-tournament-boundary-policy.md) §6.7
5. Case C delete reference: [`amiga-staging-l5-backup-delete-implementation-plan.md`](../amiga-staging-l5-backup-delete-implementation-plan.md) · `delete_finalized_mid_tournament.php`

**Problem:** Organizer sets `event_date` at create. Another kitchen may Finish first and become tip. Finishing M today runs plain finalize and **poisons** present when later finalized events exist.

**Fix:** Case C **insert** — truncate derived > N, reset forward, promote M, project N, finalize M then forward (phased HTTP), BA2 seal. Same family as Case C **delete** but keep M ground.

**Slice pick (one per chat):**

| Slice | Goal |
|-------|------|
| 1 | `amiga_case_c_insert_finish_probe()` |
| 2 | `insert-finish-prepare` verb |
| 3 | Phased project + finalize in fixtures |
| 4 | Organizer confirm UI + progress |
| 5 | Seal + smoke B + docs |

**Hard rules:** CI1–CI11 in plan §2. Inverse changelog seed on every forward finalize. No cheap tip-only refuse.

**Proof:** Plan §6 — smoke A tip unchanged; smoke B 1–2 forward; thorough C ~10 forward optional.