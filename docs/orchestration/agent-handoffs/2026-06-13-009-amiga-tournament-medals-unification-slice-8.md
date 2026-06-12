# Amiga tournament medals unification v2 — slice 8 handoff (track complete)

**Date:** 2026-06-13  
**Slice:** 8 — Documentation closure  
**Plan:** [`amiga-tournament-medals-unification-implementation-plan.md`](../../amiga-tournament-medals-unification-implementation-plan.md)

---

## Goal

Mark v2 track **complete**; update contracts, registers, MEMORY, feature-log, starter prompt.

---

## Checklist

- [x] `amiga-tournament-honours-rules.md` — status **Implemented** (v2)
- [x] `amiga-player-universe-contract.md` — §5.2.2, §5.3, §6, §7 (drop `wc_medal` / `cup_*`)
- [x] `amiga-data-contract.md` — participation/totals rows + DDL register (`021`–`022`)
- [x] `amiga-profile-v0.md` — honours LB smoke line
- [x] `scripts/amiga/README.md` — migration path + complete status
- [x] Starter prompt → **COMPLETE**
- [x] `feature-log.md` — **Done** local
- [x] `agent-track-playbook.md` — starter ✓

---

## Track summary (slices 0–8)

| Slice | Deliverable |
|-------|-------------|
| 0 | DDL `021` — `event_*`, `wc_*`, drop `cup_*` |
| 1 | Tier D — WC podium → finish 1/2/3 |
| 2 | `021b` backfill |
| 3 | Writers + `is_winner` single path |
| 4 | Full rebuild + verify |
| 5 | PHP read paths (profile/tournaments) |
| 6 | DDL `022` — drop `wc_medal` |
| 7 | Tournament honours LB v2 |
| 8 | Docs closure |

**Migrations (existing DBs):** `021` → `021b` → `022` → `participation-rebuild` → `verify-player-participation`

**Not in track:** staging re-import (user WinSCP); ~58 non-WC NULL finishes backlog; WC rank 4+ holistic job.

---

## Files changed (slice 8)

| File | Change |
|------|--------|
| `docs/amiga-tournament-honours-rules.md` | Implemented v2 |
| `docs/amiga-tournament-medals-unification-implementation-plan.md` | Complete |
| `docs/amiga-player-universe-contract.md` | §5.3 v2 totals; §6 summary |
| `docs/amiga-data-contract.md` | Table register + DDL `022` |
| `docs/amiga-profile-v0.md` | LB smoke |
| `scripts/amiga/README.md` | v2 complete |
| `docs/orchestration/agent-handoffs/amiga-tournament-medals-unification-STARTER-PROMPT.md` | COMPLETE |
| `docs/orchestration/agent-track-playbook.md` | ✓ |
| `docs/coordination/feature-log.md` | Done local |
| `PROJECT_MEMORY.md` | Track complete |

---

## Deploy note for Dagh

Local `ko2amiga_db` is migrated. Staging/prod Amiga DB needs the same `021`/`021b`/`022` sequence + `participation-rebuild` after WinSCP sync — see [`amiga-staging-handoff.md`](../../amiga-staging-handoff.md).
