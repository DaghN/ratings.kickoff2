# Amiga organizer finish confirm — policy (Jul 2026)

**Status:** **In progress** — FO1–FO10 locked; **slice 0** inventory done; Phase A implementation continues on plan slices 1+. Docs-first track (policy → plan → slices).

**Purpose:** Before **Finish and make official** commits ratings and history, the secretary can **see, edit, and confirm** the tournament finishing order. That order becomes stored Tier E ground (`amiga_tournament_finish_override`) and drives `event_finish_position`, Winner, and medals — including cases where automatic derive is empty or wrong (e.g. kitchen stamped World Cup with round-robin only).

**Audience:** Dagh, Cursor agents, future organisers.

**Parents:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) (tiers A–E) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (Make official boundary) · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md) (serial L1 feedback) · [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) SC13 (finish ≠ rollup) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (structure modules — future finish *mode*)

**Plan / prompt:** [`amiga-organizer-finish-confirm-implementation-plan.md`](amiga-organizer-finish-confirm-implementation-plan.md) · [`orchestration/agent-handoffs/amiga-organizer-finish-confirm-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-organizer-finish-confirm-STARTER-PROMPT.md)

**Motivation (Jul 2026):** Staging #609 “World Cup Kitchen getting wild” finished with ratings but Finish `—`, Winner `—`, no gold — because `is_world_cup` selected Tier D (KO podium) while the kitchen structure was pure league. Heuristic fallbacks (WC + no cup → league table) are **temporary**, not the product end state.

---

## 1. Locked decisions

| Id | Decision |
|----|----------|
| **FO1** | **Confirm before commit** — Make official happy path includes an explicit **finishing order** step (review / edit / confirm) **before** promote+finalize commits. Not buried only in Advanced. |
| **FO2** | **Authority = Tier E ground** — Confirmed order is written to `amiga_tournament_finish_override` (full ladder preferred: one row per entrant, positions `1..N`, no gaps/duplicates). Derive A–D may **prefill**; confirmed Tier E **wins** at finalize (existing honours Tier E rules). |
| **FO3** | **Manual-first when derive is empty or wrong** — If A–D yield no/partial finishes, secretary can still enter a full finishing list. Empty derive must **not** silently ship with all `NULL` finishes when the secretary intends a winner. |
| **FO4** | **`is_world_cup` ≠ finish algorithm** — World Cup is a **catalog / slice / counter** flag. Finish algorithm comes from **structure + finish mode** (later) and/or **confirmed ladder**. Do not grow WC-named heuristics as the primary fix. |
| **FO5** | **Placement** — Table tab, same workspace as Finish (modal, second panel, or gated sub-step immediately before the commit button). One mental place: “table → confirm order → make official.” |
| **FO6** | **Prefill from current derive** — Show proposed order from tiers A–D (plus any existing overrides). Labels: ordinal + player; for WC stamp, show Gold/Silver/Bronze labels when positions are 1–3 (display only). |
| **FO7** | **No silent re-Finish of completed events** — Successful `completed` + `rating_finalized` events are out of scope for this UI. Do not revive incomplete-reset as a happy path for “fix finishes after the fact.” Post-official correction = separate Lane B repair track. |
| **FO8** | **Structure-declared finish mode is Phase B** — Templates / StructureSpec may later declare finish mode (pure league, league+cup, KO podium, WC-style KO podium, …). Phase A ships confirm UI on today’s kitchen + flags; Phase B wires mode → default prefill without secretary inventing rules. |
| **FO9** | **Temporary kitchen WC fallback** — Code that maps `is_world_cup` + `has_league` + `!has_cup` → Tier C league finish may remain until Phase A ships; **retire or demote** once confirm UI is the secretary path. Do not extend that heuristic family. |
| **FO10** | **Partial finish unchanged** — Unplayed fixtures still auto-void at Finish ([RTB §6.2](amiga-running-tournament-boundary-policy.md)). Confirm order applies to **entrants** (or played participants — lock in plan if needed); do not block Finish solely because some fixtures voided. |

---

## 2. Product model

```text
Structure / results
        │
        ▼
  Derive A–D (proposal only)
        │
        ▼
  Secretary confirm / edit  ──►  amiga_tournament_finish_override (Tier E)
        │
        ▼
  Finish and make official (promote + finalize)
        │
        ▼
  event_finish_position / is_winner / medals / Winner chrome
```

| Layer | Role |
|-------|------|
| **A–D** | Automatic proposal from standings / KO labels |
| **Tier E** | Human-confirmed (or curated) finish ladder — ground truth for that tournament |
| **`is_world_cup`** | Membership for WC surfaces and `wc_*` counters once finish exists |
| **Finish mode (Phase B)** | Which A–D recipe prefills; never silently replaces confirmed Tier E |

---

## 3. UX sketch (Phase A — not pixel-locked)

1. Secretary on **Table** with ≥1 played result.
2. Chooses **Finish and make official** (or **Confirm finishing order…** then Finish).
3. Sees ordered list of players (prefilled when possible); can reorder / set positions / clear to blank then assign.
4. **Confirm** writes/updates Tier E rows for this `tournament_id`.
5. Proceeds to existing promote + finalize (reads Tier E via existing derive).

**Copy habit:** Plain language — “Who finished where?” — not “Tier E” / “override table.”

---

## 4. Rejected alternatives

| Rejected | Why |
|----------|-----|
| More WC-only hardcoded finish rules as the durable fix | Conflates catalog flag with format; fails on kitchens and odd shapes |
| Ship empty finishes when derive is empty | Breaks Winner / medals / catalog; #609 class |
| Use Advanced **Reset incomplete finish** to “fix” completed events | Reset is **limbo-only** (`lifecycle = running`); completed success is not limbo |
| Silent Finish rewind on second click | Already rejected (drift / limbo) |
| Require perfect StructureSpec before any medals | Blocks L1 secretary drills; override exists for a reason |
| Per-match Make official | Out of scope (RTB5) |

---

## 5. Out of scope (this track)

| Topic | Notes |
|-------|-------|
| Cup / KO browser templates (Track L **L3**) | Separate; finish confirm helps kitchens first |
| Structure imprint P2–P3 / WC materialize | Track C / structure tracks |
| Post-official anchored repair UI | Lane B verbs — after Phase A if needed for #609-class already completed |
| Changing historical Access WC Tier D medal rules | Canon stays; confirm UI is for live Lane B |
| Lane C media | Deferred |

---

## 6. Relation to existing Tier E ops

Offline / CLI Tier E (full ladder or sparse band) and `refresh-event-finish-snapshots` remain for **canon repair**. Organizer confirm is the **live** writer of the same ground table for community tournaments — same semantics as honours rules § Tier E (default **full ladder or none** for secretary path; sparse band stays expert/canon-only unless a later slice names it).

---

## 7. Changelog

| Date | Change |
|------|--------|
| 2026-07-17 | **In progress** — slices 0–1 done (inventory + write helper); FO1–FO10 unchanged. |
| 2026-07-17 | **In progress** — slice 0 inventory + locks (plan §3a); FO1–FO10 unchanged. |
| 2026-07-17 | **Planned** — FO1–FO10 locked from L1 #609 / finish-pipeline advice; docs-first before implementation. |