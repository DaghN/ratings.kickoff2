# Amiga tournament structure — non-WC review queue

**Purpose:** Human tournament-by-tournament triage for events **not** in slice 6 bulk (41 auto-OK) and **not** World Cups (23 deferred).  
**Workflow:** [`amiga-tournament-structure-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md)  
**Register:** `scripts/amiga/tournament_structure/tier_b_non_wc_register.py`  
**Curation JSON:** `scripts/oneoff/tier_b_non_wc_curation.json`

---

## What is already done (no per-tournament review needed before bulk)

| Bucket | Count | Status |
|--------|------:|--------|
| Tier A marathons | 503 | Materialized (slice 5) |
| Homburg | 1 | Tier D curated |
| **Slice 6 auto-OK** | **41** | CLI ready; **dry-run 41/41**; apply after GATE E |

---

## Non-WC still to-do (~34 need human judgment)

### A — Manual review (11) — slice **6b**

Labeled phases but distrusted structure. Tier **C** in inventory; materialize **refuses**.

| id | Name | Why flagged |
|----|------|-------------|
| 592 | Athens LXXXV | 66 NULL + 12 labeled |
| 22 | Athens XCI | `League Stage` |
| 591 | Amsterdam I | bronze/silver/clogs parallel tracks |
| 440 | Frankfurt II | Gold/Silver Cup labels |
| 294 | Langenfeld II | Fun Cup |
| 409 | Hamburg V | Fun Cup |
| 477 | Osnabruck II | Fun Cup |
| 496 | Rodenbach I | Fun Cup |
| 352 | Wiesbaden V | Playout Group |
| 406 | Seeshaupt III | Game of Shame |
| 503 | Leicester I | Qualifying Round |

### B — Parser fix first (8) — slice **6a**

| id | Name | Notes |
|----|------|--------|
| 48 | Groningen VII | Playouts |
| 145 | Milan V | Play Outs |
| 152 | Homburg II | Playouts |
| 166 | Milan XII | Finals plural |
| 198 | Milan XVII | Playouts 5-7 |
| 267 | Seeshaupt | Game of Shame + rounds |
| 269 | Cologne I | Place N Final variants |
| 284 | Athens LIII | Places 5-8, playout groups |

### C — NULL-phase tier C (15) — irregular / cups / withdrawals

Not labeled tier B; NULL-phase math failed or audit flag.

| id | Name |
|----|------|
| 17 | Milan XXXIX |
| 29 | Rome |
| 54 | Kristiansand |
| 62 | Gloucester III |
| 64 | Venice |
| 74 | Athens IV Cup |
| 108 | Grimstad II |
| 111 | Kristiansand II Cup |
| 134 | Milan IV |
| 174 | London Marathon |
| 187 | Hertford IV |
| 214 | Milan XVIII |
| 281 | Athens L |
| 323 | Ostersund VI |
| 399 | Koenigswinter VIII |

Plus **416** Duesseldorf V (NULL 3× uneven per-player) — tier C, `STRUCTURE_REVIEW_TOURNAMENT_IDS`.

---

## Deferred (out of scope for this review track)

| Bucket | Count |
|--------|------:|
| World Cups | 23 → WC track (6wc) |

---

## Useful URLs (local)

| View | URL pattern |
|------|-------------|
| **Event stats** | `http://ratingskickoff.test/amiga/tournament.php?id={id}&view=event-stats` |
| Standings | `http://ratingskickoff.test/amiga/tournament.php?id={id}` |
| Games | `http://ratingskickoff.test/amiga/tournament.php?id={id}&view=games` |

**Note:** Structure materialize does not change event-stats much; use **games** (phase column) + **standings/bracket** for structure questions. Event stats = participation rollup.

---

## Decision vocabulary (per tournament)

| Decision | Meaning |
|----------|---------|
| **keep_c** | Stay tier C; StructureSpec later (6b) |
| **parser_fix** | Move to 6a queue; fix phase parser |
| **auto_ok** | Re-curate into slice 6 allow list (rare) |
| **cup** | Confirm cup format; may need manual bracket spec |
| **marathon** | Mis-import; might reclassify to tier A pattern |
| **defer** | Skip for now |

Record outcomes in a running log at bottom of this file or a new `review-decisions.json` as the review chat progresses.

---

## Review log

*(Empty — filled by review chat.)*
