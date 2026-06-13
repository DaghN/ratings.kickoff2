# Handoff 018 — Slice 6 non-WC tier B curation + register

**Date:** 2026-06-13  
**Track:** Amiga tournament structure  
**Status:** **GATE E passed** — slice 6 bulk applied (Jun 2026 local)

### Apply result

```powershell
python -m scripts.amiga tournament-structure materialize-tier-b-non-wc --apply --rebuild-standings --verify-sample 10
```

| Metric | Value |
|--------|------:|
| Processed | **41** |
| Failed | **0** |
| Standings rebuilt | **41** |
| Verify sample | **12/12 OK** (10 random + pilots 75, 158) |

GATE E anchors: **75** OK · **158** OK · **592** refuse · **48** refuse (6a).

---

## Planning decisions (locked)

1. **World Cups out of slice 6** — all 23 tier-B `World Cup …` catalog events → **WC track** (expands old slice 8). Do not bulk, do not curate in this job.
2. **Slice 6 = non-WC tier B only** — labeled cups, champs, odd labeled events (60 imports).
3. **Curation before bulk** — only **41** events in slice **6** bulk; **8** → separate slice **6a**; **11** manual review (tier C).

---

## Curation summary (60 non-WC tier B)

| Bucket | Count | Action |
|--------|------:|--------|
| **WC deferred** | 23 | WC track later — `DEFERRED_WORLD_CUP_TOURNAMENT_IDS` |
| **Auto OK** | **41** | **Slice 6** bulk only — `NON_WC_TIER_B_AUTO_MATERIALIZE_IDS` |
| **Parser fix** | 8 | **Slice 6a** — separate slice; **not** slice 6; materialize **refuses** until fixed + re-curated |
| **Manual review** | 11 | **Slice 6b** — `NON_WC_STRUCTURE_REVIEW_IDS`; refuse materialize |

Regenerate audit: `python -m scripts.oneoff.curate_tier_b_non_wc` → `scripts/oneoff/tier_b_non_wc_curation.json`

---

## Register (source of truth)

**`scripts/amiga/tournament_structure/tier_b_non_wc_register.py`**

| Symbol | Purpose |
|--------|---------|
| `DEFERRED_WORLD_CUP_TOURNAMENT_IDS` | 23 WCs — never slice 6 |
| `NON_WC_TIER_B_AUTO_MATERIALIZE_IDS` | 41 bulk allow |
| `NON_WC_PARSER_FIX_FIRST_IDS` | 8 — **slice 6a only**; refuse materialize until graduated |
| `NON_WC_STRUCTURE_REVIEW_IDS` | 11 — refuse (unioned with 416 in materialize) |
| `NON_WC_PILOT_TOURNAMENT_IDS` | GATE E pilots: **75**, **158**; negative **592** |
| `is_world_cup_catalog_name()` | PHP parity |
| `is_slice_6_auto_ok()` | per-id allow helper |

**Code wired:** `classify_legacy_tier()` bumps review ids to **tier C**; `materialize_legacy_fixtures()` refuses review ids **and** `NON_WC_PARSER_FIX_FIRST_IDS` (slice 6a queue).

---

## Slice map (non-WC tier B)

| Slice | Count | What |
|-------|------:|------|
| **6** | 41 | Bulk labeled cups/champs — `materialize-tier-b-non-wc` |
| **6a** | 8 | Parser fixes + materialize after re-curation |
| **6b** | 11 | Manual review — no auto materialize |
| **6wc** | 23 | World Cups — WC track |

---

## Manual review (11) — why

| id | Name | Reason |
|----|------|--------|
| 592 | Athens LXXXV | **66 NULL + 12 labeled** — mixed provenance |
| 22 | Athens XCI | `League Stage` singleton scope |
| 591 | Amsterdam I | Parallel bronze/silver/clogs tracks |
| 440 | Frankfurt II | `Gold Cup` / `Silver Cup` event-wide labels |
| 294, 409, 477, 496 | *Fun Cup* events | `Fun Cup` not a real group scope |
| 352 | Wiesbaden V | `Playout Group` |
| 406 | Seeshaupt III | `Game of Shame` |
| 503 | Leicester I | `Qualifying Round` |

→ **Slice 6b** queue (StructureSpec / manual), same spirit as Athens IV (74).

---

## Parser fix first (8)

| id | Name | Notes |
|----|------|--------|
| 48 | Groningen VII | `Playouts`; `Semi Final` vs `Semi Finals` |
| 145 | Milan V | `Play Outs` |
| 152 | Homburg II | `Playouts` |
| 166 | Milan XII | `Finals` plural |
| 198 | Milan XVII | `Playouts 5-7` |
| 267 | Seeshaupt | `Game of Shame` + rounds |
| 269 | Cologne I | `Place N Final` variants |
| 284 | Athens LIII | `Places 5-8`, playout/playoff groups |

**Suggested slice 6a workflow:** parser patches → re-run `curate_tier_b_non_wc` → remove graduated ids from `NON_WC_PARSER_FIX_FIRST_IDS` → add to auto list → materialize per id or small bulk → GATE E′.

---

## Parser-fix queue (8) — slice 6a only

See table above. **materialize refuses** while id ∈ `NON_WC_PARSER_FIX_FIRST_IDS`.

---

## Auto OK (41) — slice 6 bulk only

Gloucester I Cup (**75**), Stoke Cup (**158**), Norwegian Champs (121), Manchester II Cup (189), Copenhagen Cup (171), … — full list in register.

**Pilot dry-run (already OK locally):**

```powershell
python -m scripts.amiga tournament-structure materialize --tournament-id 75 --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id 592
```

Expect: 75 → 9 stages / 23 fixtures; 592 → **FAIL** structure review.

---

## Slice 6 implementation tasks (41 bulk — not 6a)

### Bulk CLI

- [x] `materialize-tier-b-non-wc` mirroring `bulk_tier_a.py`:
  - Allow **only** `NON_WC_TIER_B_AUTO_MATERIALIZE_IDS`
  - Exclude WCs by id + `is_world_cup_catalog_name()`
  - Exclude parser-fix + structure-review ids
  - `--dry-run` / `--apply` (GATE E before apply)
  - `--rebuild-standings`, `--verify-sample N`
- [x] Unit test: WC id refused; 592 refused; **48 refused (6a)**; 75 allowed
- [x] Dry-run: **41/41** OK (Jun 2026 local)

### GATE E (user — slice 6 bulk only)

Spot-check after pilot or small apply:

1. **Gloucester I Cup** (id=**75**) — labeled cup, KO stages  
2. **Stoke Cup** (id=**158**)  
3. **Athens LXXXV** (id=**592**) — tier C / refuse  
4. **Groningen VII** (id=**48**) — slice **6a** / refuse  

Then:

```powershell
python -m scripts.amiga tournament-structure materialize-tier-b-non-wc --apply --rebuild-standings --verify-sample 10
```

---

## Slice 6a implementation tasks (8 — separate slice)

**Do not start until slice 6 bulk is done (or Dagh explicitly reorders).**

- [ ] Extend `tournament_phases.py` (Playouts, Play Outs, Places/Positions, Place N Final, Finals plural, …)
- [ ] Re-run `python -m scripts.oneoff.curate_tier_b_non_wc`; update register (remove graduated ids from `NON_WC_PARSER_FIX_FIRST_IDS`)
- [ ] Materialize graduated ids only; `verify-legacy --check-standings`
- [ ] **GATE E′** — user spot-check (e.g. Groningen VII **48**)

---

## Do not (slice 6)

- Bulk materialize **World Cups** (23 ids) — WC track  
- Materialize **manual review** ids (11) or tier C (74, 416, …)  
- Materialize **parser-fix** ids (8: 48, 145, 152, 166, 198, 267, 269, 284) — **slice 6a**  
- Dematerialize Homburg (137)  
- Run slice 6 `--apply` without GATE E

---

## WC track (deferred — was slice 8)

Rename/expand to **WC slices** when ready: Steve reference, WC parser, ~23 events, GATE D (group tables + brackets). **Not slice 6.**

---

*Next after slice 6: slice 6b manual queue + slice 7 catalog flags; WC track in parallel when Dagh prioritizes.*
