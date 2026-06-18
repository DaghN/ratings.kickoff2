# Starter prompt — Amiga tournament structure (modules vs legacy backfill)

**Status:** **RESUME** — slice **5 applied**; **slice 6** next (41 non-WC bulk only). **Slice 6a** = 8 parser-fix events (separate).  
**Read first:** [`2026-06-13-018-amiga-tournament-structure-slice-6-curation.md`](2026-06-13-018-amiga-tournament-structure-slice-6-curation.md)  
**Register:** `scripts/amiga/tournament_structure/tier_b_non_wc_register.py`

---

## RESUME prompt (copy into implementation chat)

```
Read docs/archive/orchestration/agent-handoffs/2026-06-13-018-amiga-tournament-structure-slice-6-curation.md FIRST.

You are continuing the Amiga **tournament structure** track. Slice **5** is applied (504 materialized).

**Slice map (non-WC tier B) — do not merge:**
| Slice | Count | Action |
|-------|------:|--------|
| **6** (NOW) | **41** | Bulk `materialize-tier-b-non-wc` — NON_WC_TIER_B_AUTO_MATERIALIZE_IDS only |
| **6a** (LATER) | **8** | Parser-fix queue — NON_WC_PARSER_FIX_FIRST_IDS — **NOT slice 6** |
| **6b** | **11** | Manual review — refuse materialize |
| **6wc** | **23** | World Cups — WC track |

**Slice 6 — your task now:**
- Implement `materialize-tier-b-non-wc` using **only** the 41 auto-OK ids
- Dry-run count must be **41**
- Wait for GATE E before --apply

**Slice 6a — NOT your task in slice 6:**
- ids **48, 145, 152, 166, 198, 267, 269, 284**
- materialize **refuses** these (NON_WC_PARSER_FIX_FIRST_IDS) until parser fixed + re-curated in slice 6a
- Do not include them in slice 6 bulk; do not ad-hoc materialize them

Register: scripts/amiga/tournament_structure/tier_b_non_wc_register.py

**Smoke:**
python -m unittest scripts.amiga.test_tournament_structure -q
python -m scripts.amiga tournament-structure materialize --tournament-id 75 --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id 48
python -m scripts.amiga tournament-structure materialize --tournament-id 592
python -m scripts.amiga tournament-structure materialize --tournament-id 5 --dry-run
Expect: 75 OK; **48 FAIL (slice 6a)**; 592 FAIL (review); 5 not in slice-6 bulk (WC).

**Do NOT:** bulk or materialize WCs (23); parser-fix ids (8); review ids (11); dematerialize Homburg (137); start slice 6a unless Dagh says so.

Confirm understanding, then implement slice 6 bulk CLI + dry-run (=41).
```

---

## After slice 6

- **6a** — parser-fix 8 (separate handoff when Dagh starts it)  
- **6b** — manual review queue  
- **6wc** — World Cups
