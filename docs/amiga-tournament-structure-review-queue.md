# Amiga tournament structure — review queue

**Workflow:** [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md)  
**Handlers:** [`amiga-tournament-structure-handlers.md`](amiga-tournament-structure-handlers.md)  
**Register:** `scripts/amiga/tournament_structure/disposition_register.json`

---

## Status (bootstrap Jun 2026)

| Handler | Count | Action |
|---------|------:|--------|
| `pure_rr` | 504 | Assigned — verify spot-check only |
| `pure_knockout` | 6 | Assigned — preview confirmed |
| `structure_spec` | 3 | Homburg + league/placement events |
| `wc_deferred` | 23 | WC track later |
| **`pending_review`** | **67** | **Review chat — promote handlers** |

```powershell
python -m scripts.amiga tournament-structure verify-disposition-register --json
```

Regenerate after code changes: `generate-disposition-register` (overwrites proposals).

---

## Handler assignment (per tournament)

| Handler | When |
|---------|------|
| `pure_rr` | Round-robin format (complete or incomplete — note withdrawals in `notes`) |
| `pure_knockout` | Preview CLI matches reality — [`pure-knockout-handler.md`](amiga-tournament-structure-pure-knockout-handler.md) |
| `structure_spec` | Groups, multi-stage, exotic |
| `wc_deferred` | World Cups for now |
| `pending_review` | Format not settled |

**On confirm:** set `handler` + one-line `notes` from Dagh's description of oddities.

---

## URLs (local)

| View | URL |
|------|-----|
| Games | `http://ratingskickoff.test/amiga/tournament.php?id={id}&view=games` |
| Standings | `http://ratingskickoff.test/amiga/tournament.php?id={id}` |

---

## Review log

### 2026-06-13 — Disposition register bootstrap

- Generated `disposition_register.json` — **603/603** coverage
- Pure knockout handler + preview CLI shipped
- **70** `pending_review` to triage

- **17** Milan XXXIX → `pure_rr` — Double RR; Sandro T left early — 5 games unplayed (withdrawal).
- **22** Athens XCI → `structure_spec` (`league_placement`) — 12p league RR → two-leg placement finals (11th through Final).
- **29** Rome → `structure_spec` (`league_placement`) — 6p double RR + two-leg Final (g224–225); KOATD NULL phase.

---

## Superseded starters

- [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) — **use this**
- [`amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md) — cup subset; merged into disposition review
- [`amiga-tournament-structure-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md) — old non-WC queue
