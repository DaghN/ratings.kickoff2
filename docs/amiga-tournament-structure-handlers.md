# Amiga tournament structure — handler contracts

**Authority:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) §4–5  
**Register:** `scripts/amiga/tournament_structure/disposition_register.json` (generated + hand-edited)  
**Review workflow:** [`orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md)

---

## Model

Every catalog `tournament_id` has **one row** in the disposition register:

```json
{ "handler": "pure_knockout", "notes": "15p bye cup" }
```

Import dispatches `handler` → shared script. **No implicit defaults.**

| Handler | Module | Review question |
|---------|--------|-----------------|
| `pure_rr` | `materialize_legacy` (NULL-phase path) | Does RR math pass? |
| `pure_knockout` | [`pure_knockout.py`](../scripts/amiga/tournament_structure/pure_knockout.py) | Does preview match reality? |
| `structure_spec` | `apply.py` + spec slug | Needs curated spec |
| `wc_deferred` | skip structure + log | WC track later |
| `pending_review` | skip structure + log | Not settled |
| `no_games` | skip | Empty event |

---

## Pure knockout

**Contract:** [`amiga-tournament-structure-pure-knockout-handler.md`](amiga-tournament-structure-pure-knockout-handler.md)

**Preview (review chat):**

```powershell
python -m scripts.amiga tournament-structure preview-pure-knockout --tournament-id <id>
```

**Apply (dev / until import hook):**

```powershell
python -m scripts.amiga tournament-structure materialize-pure-knockout --tournament-id <id> [--replace]
```

---

## Pure round-robin

**Contract:** Policy T11 — NULL phase + complete k-leg RR + equal per-player games.

**Preview:**

```powershell
python -m scripts.amiga tournament-structure verify-legacy --tournament-id <id>
```

Tier **A** in audit output → candidate for `pure_rr` register row.

---

## Disposition register maintenance

**Generate bootstrap (all ids, proposed handlers):**

```powershell
python -m scripts.amiga tournament-structure generate-disposition-register --out scripts/amiga/tournament_structure/disposition_register.json
```

**Verify coverage:**

```powershell
python -m scripts.amiga tournament-structure verify-disposition-register
```

After review: edit JSON row `handler` field (or re-run generator after code changes).

### `notes` field (human oddities)

**Handler** = format type. **`notes`** = why this row is non-obvious (one line).

| Dagh says | handler | notes |
|-----------|---------|-------|
| RR, Sandro left early, missed 5 games | `pure_rr` | `RR; Sandro withdrawal — 5 games unplayed` |
| KO cup, one bye R1 | `pure_knockout` | `15p single-elim, 1 bye` |
| Groups then KO | `structure_spec` | `4×4 groups → knockout` |

Skip `notes` on boring bulk marathons. Overwrite bootstrap boilerplate when promoting from `pending_review`.

---

## Superseded (interim)

Until slice 10 import hook ships:

- `tier_b_non_wc_register.py` frozensets — migrate into disposition JSON
- `materialize-tier-a` / `materialize-tier-b-non-wc` bulk CLIs — dev repair only
