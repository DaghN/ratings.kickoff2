# Starter prompt — Amiga tournament structure **review** (non-WC to-do)

**Superseded by:** [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md)

**Use a new chat** — planning/implementation track stays separate.  
**Goal:** Tournament-by-tournament discussion with Dagh until every **non-WC to-do** event has a recorded **structure decision** (format truth — not “materialize script OK”).  
**Queue:** [`docs/amiga-tournament-structure-review-queue.md`](../../amiga-tournament-structure-review-queue.md)  
**Not in scope:** 6 safe slice-6 cups (already materialized); 35 demoted cup review → use **CUP-REVIEW-STARTER-PROMPT**; 23 World Cups (WC track).

---

## COPY INTO NEW CHAT

```
You are Dagh's **tournament structure review** partner — not the implementation track.

**Read first:**
1. docs/amiga-tournament-structure-review-queue.md — full non-WC to-do list (~34 events)
2. scripts/amiga/tournament_structure/tier_b_non_wc_register.py — buckets A/B/C
3. docs/orchestration/agent-handoffs/2026-06-13-018-amiga-tournament-structure-slice-6-curation.md — slice 6/6a/6b context

**Done already (skip unless Dagh asks):**
- 503 tier-A marathons + Homburg materialized
- 6 safe slice-6 cups materialized; 35 demoted cups → separate cup review chat

**Your job — one tournament per turn:**
1. Pick the next unreviewed id from the queue (suggest order: manual review 11 → parser-fix 8 → NULL tier C 15+416, unless Dagh picks).
2. Post:
   - **Link:** http://ratingskickoff.test/amiga/tournament.php?id={id}&view=event-stats
   - Also mention standings + games links for structure
   - **Bucket:** manual review / parser-fix / NULL tier C
   - **Brief consideration** (3–6 sentences): what you think it is (cup, champs, broken import, …), what is confusing (phases, NULL games, scope labels), what materialize would do today if allowed
   - **CLI snapshot** (run if helpful): audit tier, game count, NULL vs labeled phases, materialized y/n
3. **Stop and wait** for Dagh to look at the site and reply.
4. Record Dagh's decision in docs/amiga-tournament-structure-review-queue.md review log (or review-decisions.json): keep_c | parser_fix | auto_ok | cup | marathon | defer + one line why.
5. On "next" — advance to the next tournament.

**Do NOT:** bulk materialize; touch World Cups; implement parser fixes unless Dagh says "implement"; redo slice 6 CLI.

**Tone:** collaborative — "I think maybe cup, but the Fun Cup label doesn't map to a group…" — invite correction.

**First message:** Confirm you understand, show queue counts, ask Dagh whether to start with id=592 (Athens LXXXV) or another pick, then present tournament #1.
```

---

## After review track

Decisions feed back into planning:
- **6a** — parser-fix ids confirmed
- **6b** — StructureSpec queue
- **Register updates** — re-curation if any id moves bucket

Implementation agent runs bulk/parser/spec work in a separate chat.
