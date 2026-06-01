# Milestones — product plan (tier bands & presentation)

**Kick Off 2 ratings site · May 2026**

**Status:** **Plan** — direction agreed for Phase 2 definition; **not locked**. Catalog keys, exact Key count (~15–20), league edge cases, and pixel UI remain TBD. Implementation has not started beyond two live DB keys.

**Related:** [`milestones-project.md`](milestones-project.md) (phases) · [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) (pass 1 brainstorm) · [`milestones-system-discussion.md`](milestones-system-discussion.md) (discovery)

---

## 1. Purpose

Celebrate a player’s ladder career with stored first-unlock times (`player_milestones`). This plan defines **how milestones are grouped, colored, and shown** — not the final list of every `milestone_key`.

Surfaces in scope (when built):

- **Milestones hub tab** — tier sections + “garden” cards
- **Profile** — count + presence TBD in layout pass
- **Meta-leaderboard** — most milestones (tie-break rules below)
- **Milestone story** — chronological unlock feed with tier colors
- **Achiever lists** — for Key milestones (newest first); same set as amber tier (see §3)

Activity digest / charts may remain a partial surface; they are not the primary home for this system.

---

## 2. Four tier bands (every milestone exactly one)

All milestones in the catalog assign to **one** band. There is **no** separate “obscure” or off-palette lane — humor, league, and grind milestones all light up on the garden when unlocked.

Bands follow **career position and difficulty**, not loot rarity (contrast Diablo / Path of Exile).

| Band | Working label | Chart token | Hex (canonical) | Meaning |
|------|----------------|-------------|-----------------|--------|
| 1 | **Aspirational** | `pitch` | `#9ccc65` | Early server life — unlocks as you **encounter** types of play and first-time events. Many land quickly once you are active; you are “on the way,” not yet a grinder. |
| 2 | **Dedicated** *(was Veteran)* | `chrome` | `#64b5f6` | Post-beginner — milestones typical once you have moved past newbie rank into **mid-level grinding** (volume, variety, sustained play). |
| 3 | **Accomplished** *(Keystones; was Key)* | `amber` | `#ffb74d` | **Completeness palette** — not “hardest,” but **what makes you a fully accomplished player** on this server. **~18 locked** (May 2026) in theme doc. Realistic to **collect all** with effort and longevity; not lottery-tier. |
| 4 | **Legendary** | `holo` | `#bf80f8` (`--k2-ms-holo`; site holo `#b388ff`) | Genuinely very difficult or rare — e.g. winning a **yearly** league, **10k** rated games, 10–10 draw. **~17 locked** (May 2026). Few players, long horizons. |

### Label note: Aspirational

English *aspire* = intend to become something. The tier name is slightly **tongue-in-cheek / solemn**: “I have plans to amount to something” while still on green milestones. Optional section subtitle in UI if needed for clarity (e.g. “First steps on the server”) — not required to rename the band.

### Color vs Activity charts

Chart semantics in [`design-direction.md`](design-direction.md) are **suggestive** and may change. Milestone tier colors on the hub, profile, and story **do not** need to match Activity chart ink meanings. Users can learn milestone tiers in context without global confusion.

---

## 3. Key milestones = amber tier (one concept)

**Do not** split “Key for achiever lists” vs “Key for completeness.” They are the **same** milestones:

- Amber band on the garden
- Public **achiever lists** (newest first) where prominence is warranted
- Examples of the **profile** of a Key milestone: `dd_merchant_10` (first 10+ goal game), `established_20`, league-defining firsts, etc.

Phase 2 work: **select ~15–20** from the `want` pool in [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) by fit, not by two different checklists.

---

## 4. Milestones hub — garden IA (plan)

**Primary structure:** page split into **four sections** in band order (Aspirational → Veteran → Key → Legendary). **Thematic grouping** (leagues vs goals vs social) is **deferred** in favour of tier-first layout; pass 2 catalog curation still tags topic in metadata for copy/search later if needed.

**Card behaviour:**

| State | Presentation |
|-------|----------------|
| **Locked** | Dim panel (~40–50% opacity or equivalent); **title + short requirement** remain visible (silhouette / readable — not empty slots). |
| **Unlocked** | Full tier color; **soft glow** using tier token; optional `achieved_at` on hover or secondary line. |

Goal: the page reads as a **garden you light up** over a career.

---

## 5. Milestone story (plan)

Chronological account of unlocks: **time**, **display name**, **tier color** (dot, stripe, or accent).

Expected shape for a long-term player: **many green early**, then **blue**, then **amber** filling in; **holo** spikes rare at any stage (e.g. early yearly win is valid — story shows real path, not idealized gradient).

Data source: `player_milestones.achieved_at` + catalog tier + display name.

---

## 6. Meta-leaderboard (plan)

**Default sort:**

1. **Total** milestones unlocked (descending)
2. Tie-break, in order (mimics league boards: common periods first, rare last):
   - Count of **Aspirational** (`pitch`) unlocks
   - Count of **Veteran** (`chrome`) unlocks
   - Count of **Key** (`amber`) unlocks
   - Count of **Legendary** (`holo`) unlocks

**TBD:** separate “Key completion %” (amber unlocked / amber defined) — interesting but not required for v1.

---

## 7. Data & catalog (unchanged baseline; plan hooks)

| Item | Plan |
|------|------|
| `player_milestones` | One row per player per `milestone_key`; `achieved_at` UTC |
| Unlock event link + context | [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md) · catalog [`milestones-catalog.md`](milestones-catalog.md) + `data/milestone_garden_links.json` |
| `milestone_definitions` (Phase 3) | SCH-011; **112** rows from [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json) |
| `player_milestones` unlock rows | **22 key types** after rebuild wave 1 (2 game + 20 league); ~88 TODO — [`milestones-facilitation.md`](milestones-facilitation.md) |

---

## 8. Open / not decided (explicit)

| Topic | State |
|-------|--------|
| Final milestone list | **112 keys** — [`milestones-catalog.md`](milestones-catalog.md) (generated) |
| Exact Key (~15–20) keys | **20 accomplished band** — locked in curated list |
| League rules (podium vs winner, win totals) | **Locked** — [`leagues-rules-spec.md`](leagues-rules-spec.md) |
| Profile layout for milestones | Integrate when profile rethought |
| Key completion % leaderboard | TBD |
| Hub tab route | **`milestones.php`** stub (May 2026); full hub WIP — [`milestones-hub-ia.md`](milestones-hub-ia.md) |

**Shipped v0 URLs (May 2026, local):** hub stub `milestones.php` · garden `individual_milestones.php?id={player}` · meta-leaderboard `ranked10.php` · profile hero milestones on all player tabs (`player_hero.php`) · trial achiever list on `server2.php` (Double Digit Merchant only; migrates when hub ready).

---

## 9. Phase 3 entry (implementation)

1. **`milestone_definitions`** — SCH-011 + seed load (done locally May 2026).
2. **Rebuild waves** — see [`milestones-facilitation.md`](milestones-facilitation.md); league wave done.
3. **Hub wireframe** — four tier sections + garden states (Phase 4).

---

*Plan recorded May 2026. Phase 2 definition complete; Phase 3 data contract in progress.*
