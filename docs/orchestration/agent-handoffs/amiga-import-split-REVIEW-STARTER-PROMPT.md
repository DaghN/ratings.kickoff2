# Starter prompt — Amiga **import split** track (before disposition review)

**Use a new chat** — one candidate per turn until the split queue is closed.  
**Goal:** Decide merge vs split for Scores labels wrongly folded into a parent tournament; implement approved splits via **append-only** synthetic catalog rows; patch disposition register. **ids 1–603 must not change.**

**Blocks:** [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) — resume disposition only after the gate below.

**Read first:**
1. [`docs/amiga-import-layer.md`](../../amiga-import-layer.md)
2. `scripts/amiga/tournament_names.py` — `TOURNAMENT_ALIASES`
3. `scripts/amiga/import_corrections.py`
4. [`docs/amiga-tournament-structure-policy.md`](../../amiga-tournament-structure-policy.md) §4
5. `scripts/amiga/tournament_structure/disposition_register.json`

---

## Dagh decisions (at a glance)

| # | Parent (mysql id) | Scores label | Games | Your decision |
|---|-------------------|--------------|------:|---------------|
| 1 | **Groningen VII** (48) | `Groningen VII Cup` | 14 | **Split** — two tournaments (koatd has one catalog row; cup is Scores-only) |
| 2 | **Gloucester III** (62) | `Gloucester III Team` | 10 | **Split** — synthetic id **605** |
| 3 | **Milan X** (156) | `Milan X Final`, `Quarter Finals`, … | fragments | **Not split** — one event; phases via `resolve_phase()` |

After each **split:** parent keeps same id; new tournament appends at end (e.g. id **604**). Big bang (date-ordered ids) is **later** — do not block on it.

---

## Gate — resume disposition review when

- [x] Split queue closed (every row in table above has a recorded outcome)
- [x] Approved splits implemented + local reimport + replay
- [x] `disposition_register.json` **patched** (parent `notes` + new id for each split child) — **not** full `generate-disposition-register`
- [x] `python -m scripts.amiga tournament-structure verify-disposition-register` passes
- [x] Splits documented in `import_corrections.py` + `docs/amiga-import-layer.md`

Then use the **disposition** starter prompt.

---

## COPY INTO NEW CHAT

```
You are Dagh's **Amiga import split** agent — not disposition review, not bulk materialize.

**Mission:** Close the import split queue: for each Scores label merged into a parent via
tournament_names.py, get Dagh's merge vs split decision; implement approved splits with
**append-only** synthetic catalog rows; reimport + replay; patch disposition_register.json.
MySQL tournament ids 1–603 must stay unchanged.

**Context (locked policy):**
- koatd often has one Access catalog row but two Scores.Tournament labels — split = import fix.
- Append new catalog rows at the **end** of insert (id 604+). Never insert in the middle.
- Big bang (catalog insert sorted by event_date → chrono → source_id) is **deferred** until
  all tournaments are disposition-handled.
- After split: patch disposition register only — do NOT run generate-disposition-register.

**Read first:** docs/amiga-import-layer.md, scripts/amiga/tournament_names.py,
scripts/amiga/import_corrections.py, docs/amiga-tournament-structure-policy.md §4,
docs/orchestration/agent-handoffs/amiga-import-split-REVIEW-STARTER-PROMPT.md

**Split vs not-split triage:**
| Category | Action |
|----------|--------|
| Scores label looks like a **second competition** (Cup, Team, …) | Split **candidate** — ask Dagh |
| Milan X-style **KO phase fragments** | NOT split — keep alias + resolve_phase |
| World Cup V KOA Cup | NOT split — merge + phase prefix (WC track) |
| Already two Access catalog rows | NOT this track |

**Initial queue:**
1. Groningen VII (id=48) + Groningen VII Cup (14g) — **SPLIT decided**
2. Gloucester III (id=62) + Gloucester III Team (10g) — Dagh decides
3. Milan X (id=156) — confirm NOT split, close ticket

**Per tournament (one at a time):**
1. Query data/amiga/source/koatd.mdb: catalog rows + Scores counts/phases for parent and label.
2. Post links:
   - http://ratingskickoff.test/amiga/tournament.php?id={id}&view=games
   - http://ratingskickoff.test/amiga/tournament.php?id={id}
3. Summarize evidence (phases, player overlap, cup vs main).
4. Propose merge or split. **Stop for Dagh's one-line decision.**

5. If **merge:** add TOURNAMENT_ALIAS_RATIONALE + row in docs/amiga-import-layer.md; log in
   docs/amiga-tournament-structure-review-queue.md § Import splits; next candidate.

6. If **split:**
   a. Remove parent-merge alias from tournament_names.py
   b. Add IMPORT_CATALOG_SPLITS (or equivalent) in import_corrections.py:
      synthetic source_id (reserved range, e.g. 900_000_001+), is_cup, chrono offset
      (parent chrono + 0.5 for same-day ordering at future big bang)
   c. Hook import_access.py: append synthetic rows after Access catalog, before MySQL INSERT
   d. Reimport + replay locally
   e. Verify: parent id unchanged, parent game count drops, child at max id+1, ids 1–603 stable
   f. Patch disposition_register.json: update parent notes; add child row pending_review
   g. verify-disposition-register
   h. Document in import_corrections + amiga-import-layer.md; log § Import splits

**Hard rules:**
- NEVER insert synthetic catalog in the middle of the list
- NEVER run generate-disposition-register (full overwrite)
- NEVER bulk materialize or promote disposition handlers (except new child → pending_review)
- NEVER change handlers on unrelated ids
- One tournament decision per turn — wait for Dagh

**First message:**
1. Confirm mission and append-only policy
2. Print the split queue table with koatd evidence (catalog vs Scores counts)
3. Start with **Groningen VII** — split already decided: show current 60-game bundle,
   propose implementation plan, ask Dagh to confirm before coding (unless he says "go")
```

---

## Import splits log

_(Agent appends one line per closed ticket.)_

- **2026-06-13** — Groningen VII Cup split from id **48** → synthetic catalog **604** (`pending_review` both); alias removed; `IMPORT_CATALOG_SPLITS` + append hook in import.
- **2026-06-13** — Gloucester III Team split from id **62** → synthetic catalog **605** (`pending_review` both).
- **2026-06-13** — Milan X KO fragments **not split** — alias + `resolve_phase()`; ticket closed. **Import split queue complete** — resume disposition review.
