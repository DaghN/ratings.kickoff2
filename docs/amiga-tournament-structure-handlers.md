# Amiga tournament structure — handler contracts

**Authority:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) §4–5  
**Forward ritual:** [**manual materialize runbook**](amiga-tournament-structure-manual-materialize-runbook.md) — **non-WC tail complete** (Jul 2026); remaining structure work = **`wc_deferred`** World Cups (slice **6wc**).  
**Register:** `scripts/amiga/tournament_structure/disposition_register.json` (generated + hand-edited)  
**Decision log:** [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md)

Bulk `apply-structure --from-disposition` remains for **replay / simul / oracle** and the mostly-finished catalog mass — not the default for new triage.

---

## Model

Every catalog `tournament_id` has **one row** in the disposition register:

```json
{ "handler": "pure_knockout", "notes": "15p bye cup" }
```

**`handler`** = bulk routing hint + format class. **`notes`** = human oddities (one line).  
**May materialize?** → see [runbook § Gates](amiga-tournament-structure-manual-materialize-runbook.md#gates-cheat-sheet) — **not** handler alone (tier-B review frozensets can still block).

| Handler | Typical materialize path | Review question |
|---------|--------------------------|-----------------|
| `pure_rr` | `materialize` (legacy) | Does RR / phase bucket match? |
| `pure_knockout` | [`pure_knockout.py`](../scripts/amiga/tournament_structure/pure_knockout.py) | Does preview match reality? |
| `structure_spec` | Legacy `materialize` **or** `apply.py` + registry spec | Multi-stage; registry spec rare — bulk skips without active spec |
| `wc_deferred` | WC track later | Not slice-6 manual |
| `pending_review` | None until triaged | Not settled |
| `no_games` | skip | Empty event |

---

## Manual materialize (WC track — Jul 2026)

**Non-WC catalog:** materialized on `ko2amiga_work` (**583/606** with stages). **23** without stages = `wc_deferred` WCs only.

**Runbook:** [`amiga-tournament-structure-manual-materialize-runbook.md`](amiga-tournament-structure-manual-materialize-runbook.md)

```powershell
python -m scripts.amiga tournament-structure materialize --tournament-id <id> --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id <id> [--replace]
python -m scripts.amiga backfill-standings-stage-id --tournament-id <id>
python -m scripts.amiga verify-standings-stage-id --tournament-id <id>
```

Living ground: **`ko2amiga_work`**.

---

## Pure knockout

**Contract:** [`amiga-tournament-structure-pure-knockout-handler.md`](amiga-tournament-structure-pure-knockout-handler.md)

```powershell
python -m scripts.amiga tournament-structure preview-pure-knockout --tournament-id <id>
python -m scripts.amiga tournament-structure materialize-pure-knockout --tournament-id <id> [--replace]
```

---

## Pure round-robin (NULL-phase tier A)

**Contract:** Policy T11 — NULL phase + complete k-leg RR + equal per-player games.

```powershell
python -m scripts.amiga tournament-structure verify-legacy --tournament-id <id>
```

Tier **A** in audit output → often already bulk-materialized; remaining NULL-phase edge cases → [runbook](amiga-tournament-structure-manual-materialize-runbook.md).

---

## Disposition register maintenance

**Verify coverage:**

```powershell
python -m scripts.amiga tournament-structure verify-disposition-register
```

**Generate bootstrap (overwrites proposals — merge hand `notes` carefully):**

```powershell
python -m scripts.amiga tournament-structure generate-disposition-register --out scripts/amiga/tournament_structure/disposition_register.json
```

After review: edit JSON row `handler` + `notes`; log line in [review queue](amiga-tournament-structure-review-queue.md).

### `notes` field (human oddities)

| Dagh says | handler | notes |
|-----------|---------|-------|
| RR, Sandro left early, missed 5 games | `pure_rr` | `RR; Sandro withdrawal — 5 games unplayed` |
| KO cup, one bye R1 | `pure_knockout` | `15p single-elim, 1 bye` |
| Groups then KO | `structure_spec` | `4×4 groups → knockout` |

Overwrite bootstrap boilerplate when promoting from `pending_review`.

---

## Parser-fix gate (`NON_WC_PARSER_FIX_FIRST_IDS`)

**Slice 6a** — `materialize` **refuses** ids listed in `tier_b_non_wc_register.py` until witness `g.phase` labels parse as knockout/league correctly.

**Jul 2026 shipped (partial):** Play Outs · Finals (plural → Final scope key) · Nth Place Finals (singular/plural) · **`Place N Final`** (Cologne witness, e.g. `Place 15 Final`); **`Playouts Group` / `Playoffs Group`** = league cross pools (not `Playouts 5-8` KO bands); PHP parity in `amiga_tournament_phases.php`; legacy materialize sets **`phase_label = NULL`** on KO fixtures so finish reads `tournament_stages.name`. Graduated: **145**, **166**, **269**, **284**. Parser queue **empty** (Jul 2026).

After parser fix: remove id from frozenset → manual materialize per [runbook](amiga-tournament-structure-manual-materialize-runbook.md) → log in [review queue](amiga-tournament-structure-review-queue.md).

---

## Bulk / oracle (demoted — not daily triage)

| Tool | Role |
|------|------|
| `apply-structure --from-disposition` | Simul / prove replay after L3 witness |
| `materialize-tier-a` / `materialize-tier-b-non-wc` | Historical dev repair CLIs |
| `tier_b_non_wc_register.py` frozensets | **Materialize gates** until merged into disposition (see runbook §2) |

Future optional: `materialize` field on disposition rows — [runbook anti-patterns](amiga-tournament-structure-manual-materialize-runbook.md#anti-patterns).
