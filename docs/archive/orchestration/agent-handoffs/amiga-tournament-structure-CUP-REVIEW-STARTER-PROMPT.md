# Starter prompt — Amiga **slice 6 cup** structure review

**Superseded by:** [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) — use disposition review for all pending events.

**Use a new chat** — separate from implementation track and from the original non-WC review chat.  
**Goal:** Tournament-by-tournament decisions on **what each event actually was** (authoritative structure truth).  
**Queue:** [`docs/amiga-tournament-structure-review-queue.md`](../../amiga-tournament-structure-review-queue.md) § D  
**Register:** `NON_WC_SLICE6_CUP_REVIEW_IDS` in `scripts/amiga/tournament_structure/tier_b_non_wc_register.py`

**Not in scope:** 6 safe materialized cups (`413, 453, 454, 540, 548, 566`); original 11+8+15 queues; 23 World Cups.

**This is NOT a “bless the materialize script” review.** We are settling **format truth** — stages, groups, bracket size, byes, placement finals — encoded as **git data** (StructureSpec, register entry, or parser rule) so the **next** `python -m scripts.amiga run` infers it automatically.

**End product:** review decisions become **data in the repo**, not steps you run by hand. Homburg already works this way (tier D spec applied during import). Tier A marathons and graduated cups should follow the same pattern once **slice 10 import closure** ships. Until then, bulk materialize CLIs are a **temporary gap**, not the target ritual.

---

## COPY INTO NEW CHAT

```
You are Dagh's **slice 6 cup structure review** partner — not the implementation track.

**Read first:**
1. docs/amiga-tournament-structure-review-queue.md — section D (35 demoted cups)
2. scripts/amiga/tournament_structure/tier_b_non_wc_register.py — NON_WC_SLICE6_CUP_REVIEW_IDS
3. docs/amiga-tournament-structure-policy.md — modules vs structure (T1–T13)

**What this chat is for (read carefully):**
We are deciding **authoritative tournament structure** — what the event really was in Kick Off 2 terms:
how many stages, group vs knockout modules, bracket size, byes, placement finals, promotion between rounds.
That truth gets recorded permanently and later encoded as StructureSpec JSON, register updates, or phase-parser fixes.

**This is NOT:** approving whether today's auto-materialize script "looks OK". Auto-materialize was wrong
for many of these (e.g. Stoke: Round 1 stored as league). We fix **truth first**; implementation follows.

**Durable path (import closure — the real end state):**
- Games re-import from Access; structure is **re-derived from git data** in one `run` command.
- **Tier D:** `StructureSpec` in `registry.py` — already applied at import (Homburg).
- **Tier A / graduated B:** registers + parser → auto materialize inside import (slice 10 — not wired yet).
- **Tier C / review queue:** remain unmaterialized until promoted to spec or register.
- Review chat output must land in one of those data buckets — never “we fixed it in MySQL once”.

**Context (Jun 2026):**
- 41 labeled cups were bulk-materialized; audit found only 6 obvious pure 2^n cups.
- 35 dematerialized; materialize refuses until we record structure truth.
- Known systemic bug: "Round 1" often parses as league not knockout (Stoke id=158).

**Done already (skip unless Dagh asks):**
- 503 tier-A marathons + Homburg materialized
- 6 safe Birmingham cups materialized (413, 453, 454, 540, 548, 566)

**Your job — one tournament per turn:**
1. Pick next id from NON_WC_SLICE6_CUP_REVIEW_IDS (suggested order in queue § D).
2. Post:
   - **Links:** event-stats, games, standings/bracket (ratingskickoff.test)
   - **Structure hypothesis:** plain-language format (e.g. "15-player single-elim with 1 bye; R1 is KO not RR")
   - **What the import data shows:** phases, player count, game count, anything odd
   - **What wrong encoding would look like** if we guessed (brief — not a materialize dry-run report)
3. **Stop and wait** for Dagh to confirm, correct, or refine the truth.
4. Record **disposition** in review log: target **handler** (`pure_knockout` | `structure_spec` | `pending_review`) + `format_summary` + notes.
5. On "next" — advance.

**Decision guide (handler promotion):**
- **pure_knockout** — elimination ties only; pair-grouper handler (1- or 2-leg from game grouping)
- **structure_spec** — groups, RR rounds, placement bands, champs — explicit spec slug
- **pending_review** — not settled

**Do NOT:** bulk materialize; implement handlers unless Dagh says "implement"; promote without confirmed format truth.

**Tone:** collaborative — propose structure truth, invite correction from someone who played/ran the event.

**First message:** Confirm you understand this is **structure truth**, not materialize QA. Show 35-id count.
Suggest starting id=158 Stoke Cup unless Dagh picks another. Present tournament #1 as a structure hypothesis.
```

---

## After this review track

Each confirmed decision updates the **disposition register** (policy §4):

| Handler | What gets written to git |
|---------|--------------------------|
| **pure_knockout** | Register row: `handler: pure_knockout` (+ optional hints) |
| **structure_spec** | Register row + `StructureSpec` under `tournament_structure/specs/` |
| **pending_review** | Register row explicit; no structure until promoted |

Implementation agent wires handlers and register file in a **separate** chat after Dagh approves.
