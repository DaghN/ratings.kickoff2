# Starter prompt — Hertford IV split (187 + synthetic 606)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.  
**Plan:** [`docs/amiga-hertford-iv-split-implementation-plan.md`](../../amiga-hertford-iv-split-implementation-plan.md)  
**Status:** Planned — ground surgery + simul + materialize + import partition **not yet executed** (Jul 2026).

**Read first:**
1. [`docs/amiga-hertford-iv-split-implementation-plan.md`](../../amiga-hertford-iv-split-implementation-plan.md) (full slice contract)
2. [`docs/amiga-tournament-structure-manual-materialize-runbook.md`](../../amiga-tournament-structure-manual-materialize-runbook.md)
3. [`docs/amiga-import-layer.md`](../../amiga-import-layer.md) § catalog splits
4. `scripts/amiga/import_corrections.py` — `IMPORT_CATALOG_SPLITS`, Gloucester **605** precedent
5. `scripts/amiga/tournament_structure/disposition_register.json` — id **187** (`pending_review`)
6. Forum: https://ko-gathering.com/forum/viewtopic.php?t=12376

**Prior session context:** Tail materialize shipped **281, 399, 416, 108** on work. **187** reviewed — league+cup merged under one Access label; split plan agreed; simul will **not** wipe other tournaments' L4 materialize (plain simul skips apply-structure when fixtures exist).

---

## COPY INTO NEW CHAT

```
You are Dagh's **Hertford IV split** agent on ko2amiga_work.

**Mission:** Execute the locked plan in docs/amiga-hertford-iv-split-implementation-plan.md:
split catalog **187** Hertford IV into league (24g, stays 187) + append child **606** Hertford IV Cup (4g);
simul; materialize both; add L2→L3 import catalog split + same-label game partition hook; update registers and inventory.

**Locked facts:**
- Forum t=12376: same evening, league 4×RR (24g) + cup KO (4g). Wayne: Haydn won league and cup double.
- Cup partition by source_scores_id: 7579, 7580, 7581, 7582 → child 606 (amiga_games 7572–7575).
- League stays ssid 7555–7578 on parent 187 (amiga_games 7548–7571).
- Child: name Hertford IV Cup, source_id 900_000_003, chrono 154.5, has_cup=1 has_league=0.
- Expected league finish (3-1-0): 1 Haydn 25 · 2 Wayne 18 · 3 Mark 16 · 4 Darren 12.
- Expected cup finish: 1 Haydn · 2 Darren · 3 Wayne · 4 Mark.
- 187 today: 28 games, 0 stages — not materialized yet.
- No score corrections needed (forum matches DB).

**Read first:** implementation plan (above), manual materialize runbook, amiga-import-layer.md §3,
import_corrections.py, disposition_register.json id 187.

**Execution order (one slice per turn unless Dagh says do all):**

| Slice | Work |
|-------|------|
| 0 | Transactional work DB surgery: INSERT 606, reparent 4 cup games, optional phase labels, reset rating_finalized |
| 1 | `python -m scripts.amiga simul` — NO --apply-structure, NO --recreate-schema |
| 2 | materialize 187 (league --replace); materialize-pure-knockout 606; backfill/verify/refresh snapshots |
| 3 | IMPORT_CATALOG_SPLITS + SCORE_TOURNAMENT_PARTITION hook in import_access.py + tests |
| 4 | disposition_register, amiga-l3-legacy-fixes-inventory §3, amiga-import-layer, review queue, UPDATE_DOCS Part A |

**Hard rules:**
- NEVER insert child catalog in the middle (append id 606 only)
- NEVER simul --apply-structure or --recreate-schema (preserves other tail materialize on work)
- NEVER materialize 187 as one 28-game league
- NEVER run blind generate-disposition-register
- NEVER commit unless Dagh asks
- Key partition on source_scores_id (Access Scores.ID), not amiga_games.id
- Finish same turn as ship: UPDATE_DOCS Part A

**Simul note for Dagh:** plain simul clears L5 derived and rebuilds ratings/standings; L4 stages/fixtures on other events survive.

**First message:**
1. Confirm mission + read implementation plan slice 0
2. Run pre-flight SQL on ko2amiga_work (187 game counts, 606 absent)
3. Ask Dagh "go slice 0?" OR execute slice 0 if he already said go in the opener
4. Report post-surgery counts before simul
```

---

## Execution log

_(Agent appends one line per closed slice.)_
