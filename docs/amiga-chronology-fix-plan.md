# Amiga chronology fix — plan

**Status:** Implemented (Jun 2026)  
**Owner doc:** [`amiga-data-contract.md`](amiga-data-contract.md) § Chronology  
**Context:** Profile rating chart bug ([player 73 calendar view](../site/public_html/amiga/profile.php)) exposed ordering issues in synthetic `game_date` and tournament sort keys.

---

## Summary

Fix game order **once at import**, materialize it on each row (`game_date`, `id`), then have replay, APIs, and ops **read it back** with:

```sql
ORDER BY game_date ASC, id ASC
```

Same habit as online (`Date ASC, id ASC`). `tournaments.chrono` remains imported metadata for **same-day tie-breaks during import only** — not for replay or API queries.

**Requires:** full schema recreate + re-import + full replay. Derived ratings **will change** for games after the first historically mis-ordered block (~9% of corpus repositioned; World Cup VIII cluster is the main case).

---

## Problem

### 1. Cross-day: `chrono` before `event_date`

Current contract (Phase A1):

1. `tournaments.chrono` ASC  
2. `tournaments.event_date` ASC  
3. `source_scores_id` ASC  

Access `Chrono` was treated as the primary global timeline. For **602 of 604** tournaments it aligns with calendar order; **two adjacent inversions** break calendar monotonicity:

| Processed first (chrono) | Calendar date | Processed second | Calendar date | Gap |
|--------------------------|---------------|------------------|---------------|-----|
| Newent XIV | 2008-11-03 | World Cup VIII | 2008-09-08 | 56 days |
| Wiesbaden IX | 2009-04-07 (Access) | Newent XVI | 2009-02-13 | 53 days — **fixed at import:** Wiesbaden IX → 2009-01-25 |

Effect: ~8.8% of games sit in a different position under calendar order vs current chrono-first order. Rating history plotted by `game_date` jumps backward in time (e.g. Sep 20 → Sep 8 for player 73).

Likely cause: late batch entry of results (especially World Cup VIII) into the legacy Access ladder, not random noise across the whole catalog.

### 2. Same-day: per-tournament midnight reset

Synthetic `game_date` is assigned as tournament `event_date` at UTC midnight **+ 1 second per game within that tournament**. Each new tournament **resets** to `00:00:00`.

On **91 calendar days** with multiple tournaments (~4,554 games), the sort order can be correct while timestamps go backward, e.g.:

```
Gloucester I      … 2002-03-23 00:04:35  (last game)
Gloucester I Cup  … 2002-03-23 00:00:00  (next tournament — clock reset)
```

This causes ~79 backward `game_date` transitions under **both** chrono-first and date-first ordering. Chart.js line+fill renders illegal geometry (zigzags, triangular fill).

### 3. Architecture mismatch with online

Online: each game row stores real time; consumers use `ORDER BY Date ASC, id ASC`.

Amiga today: order is **re-derived** at query time via chrono joins in replay, API, and post-game ops — even though import already assigned `id` in insert order. `id` and `game_date` are not authoritative.

---

## Target contract

### Sort key (import walk only)

When sorting games before insert, use:

```
event_date ASC
→ chrono ASC              (tie-break: multiple tournaments same day)
→ source_scores_id ASC
→ id ASC                  (stable tie-break after insert)
```

`chrono` is **not** deleted from `tournaments`; it resolves cup/main pairs and other same-day events (e.g. Gloucester I = 7.0, Gloucester I Cup = 8.5).

### Synthetic `game_date` (import)

Walk the sorted game list once:

- Base = parent tournament `event_date` at UTC midnight.
- Maintain a **running second counter per calendar day** across all tournaments.
- Do **not** reset the counter when the tournament changes on the same day.

After import:

- `id` (auto-increment insert order) = canonical sequence.
- `game_date` = monotonic timeline for reads and charts.

### Read path (replay, API, ops)

All game walks use:

```sql
ORDER BY g.game_date ASC, g.id ASC
```

No `COALESCE(t.chrono, …)` in the hot path. Display-only sorts (e.g. tournament index “recent events”) may still use chrono for UX.

### Post-game (live / simul)

Unchanged in spirit: new games must be **append-only** — chronologically last (`game_date` ≥ all prior; `id` > all prior). Enforce in `process-one` and `replay-to` against the new order.

---

## Implementation phases

### Phase 1 — Document contract

- [x] Update [`amiga-data-contract.md`](amiga-data-contract.md) § Chronology to target rules (this plan becomes the migration spec until done).
- [x] Note in [`amiga-schema-discovery.md`](amiga-schema-discovery.md) that Phase A1 chrono-first order is superseded.

### Phase 2 — Fix import

**File:** [`scripts/amiga/import_access.py`](../scripts/amiga/import_access.py)

1. Change `sort_key()` to `event_date` first, then `chrono`, then `source_scores_id`.
2. Replace per-tournament second counter with **per-calendar-day** counter across the sorted walk.
3. Insert `amiga_games` in that order (ids 1…N).

**Pre-replay checks** (script or verify step):

- [x] 0 backward `game_date` transitions in `ORDER BY game_date, id`.
- [x] 0 cross-day backward transitions.

### Phase 3 — Simplify consumers

Replace chrono-based game order with `game_date ASC, id ASC`:

| File | Notes |
|------|--------|
| [`scripts/amiga/replay.py`](../scripts/amiga/replay.py) | `GAME_SELECT` |
| [`site/public_html/amiga/ops/includes/amiga_ops_bootstrap.php`](../site/public_html/amiga/ops/includes/amiga_ops_bootstrap.php) | Renamed `AMIGA_GAME_CHRONOLOGY_ORDER_*` |
| [`site/public_html/amiga/ops/modules/process_completed_game.php`](../site/public_html/amiga/ops/modules/process_completed_game.php) | Append-only / simul |
| [`site/public_html/api/player_rating_history.php`](../site/public_html/api/player_rating_history.php) | Fix header comment; align ORDER BY |

Grep for `COALESCE(t.chrono` on game-list queries and align or mark display-only exceptions.

Optional: shared helper `amiga_game_chronology_order_sql()` in [`amiga_db.php`](../site/public_html/includes/amiga_db.php) so PHP/docs stay in sync.

### Phase 4 — Full rebuild

On a **copy** of `ko2amiga_db` first, then staging, then production:

```powershell
# Backup first
python -m scripts.amiga run --recreate-schema
python -m scripts.amiga replay
# PHP simul parity (existing gates)
php site/public_html/amiga/ops/run_process_game.php replay-to --limit 500
```

Re-import **is** the migration — no separate hand-edit of 27k `game_date` rows on a live schema.

### Phase 5 — Validation

| Check | Expected |
|-------|----------|
| Backward `game_date` in canonical order | 0 |
| Python replay vs PHP `replay-to --limit N` | Parity |
| `/amiga/profile.php?id=73` calendar chart | Monotonic; no Sep 2008 zigzag |
| “By game #” view | Works; values may differ where order changed |

Optional sanity: spot-check a few players vs Access `Rankings` monthly grid (2008–2009). Reference only — not a gate.

**Implemented:**

- `scripts/amiga/verify_chronology.py` (`python -m scripts.amiga verify-chronology`)
- `scripts/amiga/verify_import_manifest.py` (`python -m scripts.amiga verify-import-manifest`)
- `scripts/amiga/audit_catalog_dates.py` (`python -m scripts.amiga audit-catalog-dates`)

### Phase 6 — Chart / UI QA

Calendar chart should work from API data without JS workarounds. If still noisy at decade scale, optional **end-of-day collapse** in [`player-rating-chart.js`](../site/public_html/js/player-rating-chart.js) (display-only). Prefer trying without it first.

### Phase 7 — Deploy

1. Rebuild staging `ko2amiga_db`; run QA checklist.
2. Document rating changes for operators (“calendar-correct replay — not a random bug”).
3. Rebuild production when satisfied.

---

## Non-goals

- Chart-only / JS-only patch while keeping chrono-first replay.
- Deleting `tournaments.chrono` (keep for import tie-break and catalog display).
- Sorting calendar chart by date while replay stays on chrono-first (creates fake rating dips).
- Hand-fixing chrono on outlier tournaments unless Access evidence shows **`event_date`** is wrong.

---

## Risks

| Risk | Mitigation |
|------|------------|
| Derived ratings change | Expected; full replay; communicate as calendar-correct |
| `id` remapping on recreate | All FKs rebuilt; nothing external should hard-code old ids |
| Backdated live insert later | Same as online: use `game_date` primary, `id` tiebreak; enforce append-only in ops |
| `source_scores_id` | Preserved for Access traceability; not used in normal read order |

---

## Outlier review (optional)

After rebuild, catalog date fixes live in [`import_corrections.py`](../scripts/amiga/import_corrections.py) (see [`amiga-import-layer.md`](amiga-import-layer.md)):

- **World Cup VIII** — Access `event_date` 2008-09-08; **import canonical 2008-11-09** (chrono 325 fits November slot).
- **Wiesbaden IX** — Access `event_date` 2009-04-07; **import canonical 2009-01-25** ([forum source](https://ko-gathering.com/forum/viewtopic.php?p=247684#p247684)). Resolves the apparent Newent XVI inversion — Newent XVI (2009-02-13) is unchanged.

Confirm calendar dates are trustworthy before changing source data.

---

## Files touched (summary)

| Area | Files |
|------|--------|
| Plan | `docs/amiga-chronology-fix-plan.md` (this doc) |
| Contract | `docs/amiga-data-contract.md`, `docs/amiga-schema-discovery.md` |
| Import | `scripts/amiga/import_access.py` |
| Replay | `scripts/amiga/replay.py` |
| Ops | `amiga_ops_bootstrap.php`, `process_completed_game.php` |
| API | `api/player_rating_history.php` |
| Tests | new/extended verify under `scripts/amiga/` |
| Charts | likely none |

---

## Principle (online parity)

```
Import  →  define order once, write game_date + id
Replay  →  ORDER BY game_date, id  →  derived ratings
API/UI  →  ORDER BY game_date, id  →  charts and lists
```

Order is ground truth **on the row**, not re-computed from `chrono` at read time.

**Agent handoff:** [`amiga-chronology-fix-agent-prompt.txt`](amiga-chronology-fix-agent-prompt.txt)
