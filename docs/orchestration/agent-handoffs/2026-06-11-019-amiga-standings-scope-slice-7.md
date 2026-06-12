# Amiga standings scope unification — slice 7 handoff

**Date:** 2026-06-11  
**Slice:** 7 — Documentation closure  
**Plan:** [`amiga-standings-scope-implementation-plan.md`](../../amiga-standings-scope-implementation-plan.md)

---

## Goal

Policy **Implemented**; registers and specs aligned with shipped `league`/`knockout` model.

---

## Checklist

- [x] `amiga-standings-scope-policy.md` — status **Implemented**; migration register shipped
- [x] `amiga-data-contract.md` — Tournament standings § `league`/`knockout`; `league_scopes` on index
- [x] `amiga-tournament-honours-rules.md` — Tier A/B/C use `resolve_primary_league_standings()` wording
- [x] `scripts/amiga/README.md` — migration `020` complete note + replay pointer
- [x] `PROJECT_MEMORY.md` — track complete
- [x] `feature-log.md` — L1 row updated (slices 0–7 local done)
- [x] Starter prompt **COMPLETE**
- [x] Implementation plan status **Complete**

### Verification

- [x] Grep docs `scope_type='overall'` — only history/archive/superseded prompts + handoffs (live specs use `league`)

---

## Files changed

| File | Change |
|------|--------|
| `docs/amiga-standings-scope-policy.md` | Implemented status; verification counts |
| `docs/amiga-standings-scope-implementation-plan.md` | Complete; slice 7 boxes |
| `docs/amiga-data-contract.md` | Standings scopes + catalog column |
| `docs/amiga-tournament-honours-rules.md` | Tier B/C resolver + WC medal note |
| `scripts/amiga/README.md` | `020` complete |
| `docs/coordination/feature-log.md` | L1 row |
| `docs/orchestration/agent-handoffs/amiga-standings-scope-STARTER-PROMPT.md` | COMPLETE |
| `docs/amiga-player-universe-implementation-plan.md` | Post-020 SQL note |
| `docs/amiga-track-b-tournament-standings-agent-prompt.txt` | SUPERSEDED header |
| `docs/orchestration/browser-organizer-workflow-checkpoint.md` | `league` scope |
| `PROJECT_MEMORY.md` | Track complete |

---

## Track summary (slices 0–7)

| Slice | Deliverable |
|-------|-------------|
| 0 | Migration `020`; enum `league`\|`knockout`; `league_scopes` |
| 1 | Python writers |
| 2 | PHP post-game parity |
| 3 | `resolve_primary_league_standings()` + honours Tier B/C |
| 4 | Readers/URLs + legacy redirects |
| 5 | Parity/verify tooling |
| 6 | Full replay + verify suite |
| 7 | Docs closure |

**Staging:** apply `020` on server DB + WinSCP sync when Dagh deploys (not part of this slice).

---

## Fast follow (out of scope)

- Tournament tab IA — hide redundant league tab on pure single-table events (policy §6)
