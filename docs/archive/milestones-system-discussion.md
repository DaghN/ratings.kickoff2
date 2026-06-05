# Milestone system — discussion paper

**Kick Off 2 ratings site · May 2026**

**Project status:** Phase 1 (idea creation) **complete** — see [`milestones-project.md`](milestones-project.md). This paper is discovery/context; the catalog is pass 1 only, not final.

This note summarises where the ladder site stands on a **milestone / achievement** feature: what already exists in the database, what the site already shows, how it fits the product tone, and whether to call the feature *milestones* or *achievements*.

It is written for humans — product thinking, not implementation handoff.

| Doc | Purpose |
|-----|---------|
| [`milestones-project.md`](milestones-project.md) | **Phase tracker** — start here |
| [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) | Pass 1 brainstorm + curation (**draft**) |
| This file | Discovery, naming, early shape |

---

## The idea (in brief)

A system that celebrates meaningful moments in a player’s ladder career:

1. **Lists** — for key milestones (e.g. Double Digit Merchant), show who achieved them and when; newest achievers at the top.
2. **Leaderboard** — who has unlocked the most milestones.
3. **Profiles** — milestones visible on player pages, with prominence TBD.
4. **Tier bands (plan)** — four career bands with chart-token colors; Key (~15–20) = completeness palette on amber. See [`milestones-product-spec.md`](milestones-product-spec.md). Pass 1 also mentioned “key vs obscure” — superseded by four-band garden (all milestones on-palette).
5. **Milestones hub** — dedicated tab (pass 1); Activity digest/charts remain partial surface today.

The naming question is **resolved for now:** use **Milestones** (see [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) pass 1).

---

## 1. Database — the infrastructure is already there

The site has a stored table **`player_milestones`**. Each row means: *this player hit this milestone for the first time at this time*.

| Column | Meaning |
|--------|---------|
| `player_id` | Which player |
| `milestone_key` | Stable id (e.g. `established_20`) |
| `achieved_at` | UTC date/time of first unlock |
| `value` | Threshold number (20 games, 10 goals, etc.) |

**One row per player per milestone** — you cannot “earn” Established twice.

There is also an index on `(milestone_key, achieved_at)`, which is exactly what you need for “show me everyone who became a Double Digit Merchant, newest first.”

### What is stored today

| Milestone key | Meaning | Players (approx.) |
|---------------|---------|-------------------|
| `established_20` | First time the player reached 20 rated games | 107 |
| `dd_merchant_10` | First time the player scored 10+ goals in one rated game | 44 |

Data has been rebuilt from full game history and checked against player totals. The same setup exists on staging.

### What is *not* in the database yet

- A **catalog** of milestones (display names, descriptions, icons, tier: key vs obscure).
- Anything beyond those two milestone types (e.g. “newbie welcomer” / first rated game).
- A dedicated “milestone count leaderboard” table — but that is easy to compute from existing rows (count milestones per player).

**Bottom line:** the hard part — *remembering when each player first crossed each threshold* — is done. What remains is catalog, UI, copy, and more milestone definitions.

---

## 2. What the website already shows

### Activity page (`activity.php`) — the most mature surface

Already live in the repo:

- A **“Recent milestones”** digest (small cards: latest Double Digit Merchant, busiest day/month, most recent game).
- **Chart groups** for “Established players” and “Double Digit Merchants” (new per year, cumulative totals, rating distributions).
- In the design system, **magenta** is reserved for milestone-related visuals.

So “milestone” is already user-facing language on Activity. Only part of the digest comes from the milestone table today; some cards are general server facts (busiest day, etc.).

### Player profiles — open for rethink

Today’s shipped profile (`player/profile.php`) has celebratory **Moments** (streaks, trophy games) and career stats, but **does not** yet read the milestone table.

**Profile layout is not settled.** The page should be rethought while keeping the site’s core values (truthful data, welcoming tone, stats-first identity). **Where milestones appear on a profile — hero, badges, dedicated block, mixed with other content — is an open design question**, not something this doc prescribes.

Planning notes mention **achievement-style badges** as one possible direction; nothing is locked in.

### Hall of Fame — a different thing

Hall of Fame is **records**: one holder per extreme (longest streak, most goals in a game, …).

Milestones are **shared personal landmarks**: many players can be Established; many can be Double Digit Merchants.

Keeping that distinction clear helps naming and layout — milestones should not feel like a second Hall of Fame.

### Site navigation

Top-level tabs run: **Status → Activity → Games → Leaderboards → Hall of Fame** — life and evidence before pure ranking.

A milestone “corner” fits naturally on **Activity**, optionally **Status** (recent unlocks), and **profiles**. A whole new top-level tab is probably unnecessary unless the catalog grows very large.

---

## 3. Site tone (why the word matters)

These values apply site-wide, including wherever milestones eventually appear:

| Principle | In practice |
|-----------|-------------|
| **Truthful and data-rich** | Numbers and history are authoritative; no gimmicky scoring. |
| **Welcoming on the surface** | Newcomers should feel the site is alive and understandable, not insiders-only. |
| **Inclusive and playful** | Celebrate participation and memorable moments without turning the ladder into a badge farm. |
| **Visual identity** | Dark, precise “neon noir statistics” — not a mobile game or achievement platform cosplay. |

**Community vocabulary already in use:** *Established* (20 rated games), *Double Digit Merchant* (first 10+ goal game), plus DD, clean sheets, etc.

The milestone system is meant to feel **celebratory for veterans and aspirational for newcomers** — tangible titles and dates, not a list of opaque game-ID links. How that maps onto a **rethought profile** is still to be designed.

---

## 4. Your vision vs current gaps

| Idea | Ready? | Notes |
|------|--------|-------|
| Per-milestone achiever list (latest first) | **Mostly** | Data + index exist; need UI/API per milestone |
| Leaderboard: most milestones unlocked | **Easy** | Count rows per player |
| Profile prominence | **Not yet** | Milestone table not wired to profiles; profile layout open for rethink |
| Two tiers (key vs obscure) | **Not yet** | Need catalog + tier metadata |
| Activity presence | **Partial** | Digest + established/DD chart groups exist |
| Live update when someone unlocks | **Contract only** | Post-game rules documented; production game server not fully cut over |

Example of the query the achiever list needs (conceptually):

> All display-visible players who became Double Digit Merchants, ordered by unlock date, newest first.

That is a straightforward, indexed read — not a heavy scan of every game ever played.

---

## 5. Milestone vs achievement — pros and cons

Both words describe “something notable happened.” On this site they land differently.

### Case for **Milestones** (recommended as the main term)

**Pros**

- Already used in the database, Activity copy (“Recent milestones”), and design tokens.
- Sounds like **markers on a career timeline** — fits a stats/ladder site.
- Stays distinct from **Hall of Fame records** (shared landmarks vs single extreme holder).
- Matches existing names: Established, Double Digit Merchant feel like **titles along a path**, not console pop-ups.
- Works for participation story: first game, career thresholds, memorable single-game feats.
- Less “gamification platform” vibe.

**Cons**

- Can feel **too serious** for silly unlocks (“three red cards in one game”).
- Weaker for **collection** UX (“collect them all”, badge grids).
- “Most milestones” leaderboard sounds slightly awkward compared to “most achievements.”

### Case for **Achievements**

**Pros**

- Natural for **obscure/fun** unlocks and badge-style UI.
- **Aspirational** framing is familiar (“achievements to earn”).
- “Most achievements” leaderboard reads cleanly.

**Cons**

- Almost **unused** in current user-facing copy.
- Carries **Steam/Xbox/mobile** baggage — may clash with a truthful stats aesthetic.
- **Blurs with Hall of Fame** — both sound like “things you accomplished.”
- Would fight existing naming (table, APIs, Activity heading all say milestone).

### A practical hybrid

Use **one umbrella word** for the system, **two tiers** with different flavour:

| Tier | Label | Examples |
|------|-------|----------|
| Key | **Milestones** (or keep community names) | Established, Double Digit Merchant, first rated game |
| Obscure | **Feats** or **Marks** | Three red cards in one game, odd streaks |

Calling tier 2 “Achievements” is workable, but **Milestones + Feats** avoids two similar words (“Is DD a milestone or an achievement?”).

If you insist on **one word for everything**, **Milestones** is the stronger fit for this site; obscure ones can be “minor milestones” with playful proper names.

---

## 6. Recommendation

**Use *Milestones* as the primary user-facing term.**

- Keeps continuity with Activity and the database.
- Matches stats-site tone and separates the feature from Hall of Fame records.
- Tier 1 = **Featured milestones** (or just the community names).
- Tier 2 = **Feats** (or similar) if you want playfulness without full “achievement platform” semantics.

**Keep internal names stable** (`player_milestones`, `milestone_key`, `achieved_at`) — no database rename required.

**Suggested first milestone catalog**

| Internal key | Display name | Tier | In DB today? |
|--------------|--------------|------|--------------|
| `established_20` | Established | Key | Yes |
| `dd_merchant_10` | Double Digit Merchant | Key | Yes |
| `first_rated_game` | Newbie welcomer / First rated game | Key | To add |
| (future) | Various oddities | Feats | No |

---

## 7. Sensible build order (when implementing)

1. **Write a short product spec** — catalog, tiers, copy tone, which pages get what.
2. **Milestone catalog** — start as config; move to a DB table if it grows.
3. **APIs** — recent achievers per milestone; optional “most milestones” leaderboard.
4. **UI** — Activity panel with expandable achiever lists; profile integration **TBD** as part of a broader profile rethink.
5. **New keys** — e.g. first rated game, added to rebuild + live post-game rules.
6. **Optional Status strip** — “latest unlocks” for hub freshness.

---

## 8. Summary

| Question | Answer |
|----------|--------|
| Is milestone infrastructure in the DB? | **Yes** — table populated, indexed, rebuild path exists. |
| Is it on the site yet? | **Partly** — Activity digest and established/DD charts; not on profiles or as full achiever lists. |
| Milestone or achievement? | **Milestones** for the system; consider **Feats** for tier 2 obscure unlocks. |
| How does it relate to Hall of Fame? | HoF = records; milestones = shared personal first-times. |
| What’s the main gap? | Catalog, UI surfaces, more milestone types, profile integration. |

---

*Discovery paper — May 2026. Idea phase closed; implementation not started. Hub: [`milestones-project.md`](milestones-project.md).*
