# Amiga player create — policy

**Status:** **Policy locked (Jul 2026, rev. 2.1)** — storage model **rev. 2**: permanent `amiga_players` at create + orphan cleanup on abandoned tournaments (replaces provisional-until-finalize). Orphan sweep skips players still on another tournament (§6.3.1). Implementation not started.

**Parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (Lane B) · [`amiga-data-contract.md`](amiga-data-contract.md) (ground truth layers)

**Related:** [`amiga-country-registry-policy.md`](amiga-country-registry-policy.md) (nationality on create) · [`amiga-import-layer.md`](amiga-import-layer.md) (historical import merges — separate from live create) · [`scripts/amiga/player_names.py`](../scripts/amiga/player_names.py) (reference algorithms today) · [`scripts/amiga/player_registry.py`](../scripts/amiga/player_registry.py) (today's CLI — already inserts `amiga_players`; aligns with rev. 2 storage)

**Implementation plan:** [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md) — slices **PC-1+** not started.

---

## 1. Executive summary

KOA Amiga player identity on the ladder is a **two-token display name**: a **first name** plus a **short second token** that disambiguates the player among everyone on the ladder (conventionally derived from the surname, but not verified as legal ID).

**Live create (organizer)** must:

1. Accept a **full name** from the operator and **auto-compute** the single allowed canonical name — **no manual pick** of abbreviation.
2. Require a **registry country** (same rules as league host country create).
3. Attach the newcomer to the **tournament being built** in one step (**Create player** adds to the draft roster like **Add player**).
4. Insert into **`amiga_players` immediately** (same table as the historical corpus) — the name is **ladder-global** and reusable in a later tournament if the first draft is abandoned.
5. **Orphan hygiene:** live-created players with **no games ever** are removed when an **abandoned never-finalized** tournament is deleted — **only if** they are not still entrants elsewhere (§6.3). Also removable via organizer delete before any games.
6. Allow **delete player** in organizer when the row is still deletable (no rename/edit — delete and create again).

Historical Access import keeps its own merge/alias rules (`player_names.py` at L3). Live create does **not** reuse import merge logic and does **not** append to `PLAYER_NAME_ALIASES` automatically.

---

## 2. Scope

### 2.1 In scope (v1)

| Area | Rule |
|------|------|
| **Naming convention** | KOA two-token display names; strict suggestion algorithm |
| **Organizer UX** | Create player during league create; country required; auto-suggested name only |
| **Permanent roster at create** | New row in `amiga_players` when organizer confirms create |
| **Orphan cleanup** | Delete live-created zero-game players when parent tournament deleted — **never** if still entrant on another tournament |
| **Organizer delete** | Remove deletable live-created player from draft / DB when no games |
| **Auth** | Same password gate as [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) league create |

### 2.2 Out of scope (v1)

| Item | Notes |
|------|-------|
| **Public self-registration** | Organizer-only |
| **Rename / edit** after create | Delete + create again |
| **Manual canonical override** | Operator cannot type `Mark Be` when algorithm says `Mark Ben` |
| **Mononyms / single-token names** | Refused — convention requires two tokens |
| **Import-time merge changes** | Still `import_corrections.py` + L3 manifest |
| **Tournament delete + orphan cleanup** | With guarded delete verb — removes eligible live-created zero-game players |
| **Online realm (`kooldb`)** | Amiga-only |

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **PC1** | **Convention over choice** | Operator enters **full name**; system assigns **one** canonical display name. No “pick a nice abbreviation” UI. |
| **PC2** | **Two tokens** | Stored display name = exactly **two** whitespace-separated tokens after normalization. Single-token names are **rejected**. |
| **PC3** | **Minimum unique suffix** | Second token = **shortest** prefix of the surname portion that yields a unique name among **reserved identities** (see §5.3). Expand one letter at a time (`Dagh N` → `Dagh Ni` → …). |
| **PC4** | **First name token** | First token of the operator''s full-name input (after normalization). Additional middle names are **not** separate tokens in the display name — they inform which surname token to abbreviate (see §4). |
| **PC5** | **Country required** | Nationality must be a **choosable** registry `official_name` ([`amiga-country-registry-policy.md`](amiga-country-registry-policy.md)). No new countryless players. |
| **PC6** | **Create = tournament context** | Creating a player only makes sense when building a tournament roster. **Create player** auto-adds to the current create-league draft (same outcome as **Add player** for roster membership). |
| **PC7** | **Permanent roster at create** | Confirming **Create player** inserts one row into **`amiga_players`** immediately. The name is reserved ladder-wide (supports re-use in tournament Y after abandoning X). |
| **PC8** | **Orphan hygiene** | A **live-created** player with **zero `amiga_games` rows** may be deleted when: (a) organizer deletes them on the create flow, or (b) an **abandoned never-finalized** generated tournament is deleted **and** the player is **not** an entrant on any **other** tournament (§6.3). Players who have played any game are **never** auto-deleted. Deleting tournament **X** must **not** remove **N** if **N** is still on running (or draft) tournament **Y**. |
| **PC9** | **Delete, don't edit** | Organizer may **delete** a deletable live-created player; no edit-name path. |
| **PC10** | **Same auth as league create** | No extra role beyond organizer password. |
| **PC11** | **Identity normalization** | Trim, collapse internal whitespace, strip trailing `.` before checks ([`normalize_display_name()`](../scripts/amiga/player_names.py)). |
| **PC12** | **Case-insensitive collision** | `identity_key()` (casefold) prevents near-duplicates; stored spelling uses normalized casing from the suggestion algorithm. |

---

## 4. KOA display name convention

### 4.1 What KOA expects

From long-standing KOA forum practice (example locked in [`prompt-008-koa-player-naming-foundation.md`](archive/orchestration/prompt-008-koa-player-naming-foundation.md)):

> If the new guy''s name is Mark Bentley, he should be entered as **Mark Be** when **Mark B** already exists.

The ladder name is **not** a legal identity document. It is:

- Token 1: **given / first name** (how the player is known on the ladder).
- Token 2: **disambiguator** — ideally mnemonic for the surname, lengthened only as needed so the pair is unique.

There is **no** requirement to prove surname spelling at create time.

### 4.2 Parsing full-name input

Given normalized full-name input from the operator:

1. Split on whitespace into tokens.
2. If fewer than **two** tokens → **reject** (PC2).
3. **First token** → first name for the display name.
4. **Last token** → surname basis for abbreviation (middle names are ignored for token count but the **last** token anchors the suffix — e.g. `Jean Pierre Dupont` → first `Jean`, abbreviate from `Dupont`).

*Future refinement:* if real cases need “surname = last two tokens”, that is a **policy amendment**, not operator choice.

### 4.3 Normalization (all paths)

| Step | Rule |
|------|------|
| Trim | Leading/trailing whitespace removed |
| Collapse | Internal runs of whitespace → single space |
| Trailing period | Strip one trailing `.` (Access artefact) |
| Storage | `amiga_players.name` is `varchar(50)` **`utf8mb4_bin` unique** — exact bytes matter for display |

Import-time merges (spacing/case duplicates in Access) remain documented in [`amiga-import-layer.md`](amiga-import-layer.md). Live create uses the same **normalization helpers** but **does not** auto-merge two distinct live-created players.

### 4.4 Practice edge case — abandon tournament X, start tournament Y

**Scenario:** Organizer creates tournament **X**, creates newcomer **N** on the draft roster, then abandons X and starts **Y** instead.

| Storage model | Where N lives when building Y |
|---------------|-------------------------------|
| ~~Provisional-until-finalize (rev. 1)~~ | Awkward — N is scoped to X; re-use in Y needs promotion rules, draft carry, or duplicate-name failure. |
| **Permanent at create (rev. 2 — locked)** | **N is already in `amiga_players`.** Add **N** to Y via normal **Find player** search (same as any corpus player). If X is deleted with orphan cleanup, **N survives** because **N** is still an entrant on **Y** — even with zero games on both tournaments. |

**Tradeoff (accepted):** N may appear in site player search with **zero games** until Y finishes or orphan cleanup runs. Prefer this over provisional FK complexity.

---

## 5. Suggestion algorithm (strict)

### 5.1 Operator input vs system output

| Field | Who sets it |
|-------|-------------|
| Full name (text) | Operator |
| Country (`<select>`) | Operator (registry) |
| **Canonical display name** | **System only** — show read-only preview before confirm |

There is **no** dropdown of alternate abbreviations and **no** free-text canonical field.

### 5.2 Candidate generation

Let `F` = first token, `S` = surname basis (last token). Candidates are:

```text
F + " " + S[0:1]
F + " " + S[0:2]
…
F + " " + S[0:len(S)]
```

(Implementation today: [`koa_abbreviation_candidates()`](../scripts/amiga/player_names.py) — policy may extend max length beyond historical “≤3 char” heuristic when uniqueness requires it.)

### 5.3 Uniqueness scope

The **first** candidate whose `identity_key()` is not already **reserved** wins.

**Reserved** identities include every **`amiga_players.name`** (import corpus + all live-created rows). Names are ladder-global once assigned.

If all candidates are exhausted → **refuse create** with a clear error (operator must contact admin / choose a different first name — policy does not invent alternate schemes).

### 5.4 Refusal cases (no override)

| Input | Result |
|-------|--------|
| Empty / whitespace | Reject |
| Single token (`Madonna`) | Reject — PC2 |
| All abbreviation candidates taken | Reject — report exhaustion |
| Country missing or not choosable registry token | Reject — PC5 |

### 5.5 Relation to today''s code

[`suggest_koa_display_name()`](../scripts/amiga/player_names.py) and [`is_canonical_style_name()`](../scripts/amiga/player_names.py) (second token ≤3) are **reference implementations** from the Jun 2026 CLI slice. Live ops implementation must:

- Keep **minimum-prefix** uniqueness (PC3).
- **Drop** the “≤3 chars = canonical” shortcut as a hard product cap when longer prefixes are required.
- **`taken`** keys = all `amiga_players` names (same as rev. 2 storage).

---

## 6. Storage, orphans, and tournament lifecycle

### 6.1 Why not provisional-until-finalize (rev. 1 retired)

Rev. 1 stored newcomers outside `amiga_players` until finalize. That avoids zero-game search clutter but breaks the common **abandon X → build Y** flow (§4.4): newcomer **N** was tied to X, not visible as a normal roster citizen when composing Y.

Rev. 2 **inserts at create** and relies on **orphan cleanup** instead of a second promotion step.

### 6.2 Locked lifecycle (rev. 2)

```text
Create player (organizer)
  → INSERT amiga_players (live-created provenance flag)
  → add to create-league draft / tournament_entrants when league is saved
Play / finalize
  → normal ops path; player accumulates games → no longer orphan-eligible
Abandon / delete never-finalized tournament X
  → orphan sweep: delete eligible live-created zero-game players who were on X
     AND have no entrant row on any other tournament (Y blocks delete)
Switch to another tournament
  → ADD existing player via search (same id, same canonical name)
```

### 6.3 Orphan eligibility (cleanup)

A player row is **orphan-deletable** when **all** of:

| # | Condition |
|---|-----------|
| 1 | Row was **live-created** (not import corpus — provenance column or equivalent) |
| 2 | **Zero** rows in `amiga_games` as player A or B |
| 3 | **Not** an entrant on any **other** tournament — any surviving `tournament_entrants` (or equivalent) row on a tournament **≠** the one being deleted/cleaned. Includes **draft**, **running**, and **finalized** parents. Zero games on Y does **not** waive this guard. |
| 4 | Trigger = organizer **delete player** on create flow, **or** **delete/cleanup abandoned tournament** verb |

**Never** orphan-delete import corpus players or anyone who has ever played a rated game.

#### 6.3.1 Delete tournament X while N is on Y

**Scenario:** **N** created on draft **X**, then added to **Y** (search). Organizer deletes abandoned **X**.

| Check | Result |
|-------|--------|
| **N** live-created, zero games | Passes conditions 1–2 |
| **N** still entrant on **Y** | **Fails condition 3 → do not delete** |
| **N** only ever on **X** | Passes condition 3 → eligible when **X** is deleted |

Tournament-delete orphan sweep is **per player**, not “delete everyone who was on X.” Implementation must count entrant links **excluding** the tournament being removed.

### 6.4 Public surfacing tradeoff

Live-created players **do** appear in organizer player search immediately (may show **0 games** on profile until they play). Countries hub / leaderboards typically exclude zero-game players already via stored stats — verify in implementation plan. Full “hide until first game” filtering site-wide is **out of scope** for v1 unless audit shows clutter.

### 6.5 Abandoned create draft (league never submitted)

If organizer **Create player** on the compose form but **never submits** the league, the row exists with **no tournament**. Treat as orphan: organizer **delete** on the draft chip, or a future sweep. Same zero-game rule.

---

## 7. Organizer UX (browser)

Location: [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php) **Create league** flow (same auth as today).

| Control | Behaviour |
|---------|-----------|
| **Find player** (existing) | Autocomplete → **Add player** to draft chips |
| **Create player** (new) | Full name + country → show **system-suggested** canonical preview → confirm → add to draft chips |
| Roster | Both paths append to the same selected-player list for league create |

After league is created, entrant registration for **existing** players stays on the **Players** tab (unchanged). **Creating** new players mid-tournament may be a later slice; v1 minimum is **create league** path.

**No** separate “create player without tournament” screen.

---

## 8. Country on create

- Use the same registry UX as league **Country** ([`amiga-country-registry-policy.md`](amiga-country-registry-policy.md) §8.2): used countries first, **More countries…** for full choosable set.
- Server: `k2_amiga_country_validate_token()` on POST.
- Store registry **`official_name`** on `amiga_players.country` at insert.

Rationale: corpus hygiene — manual overrides (`PLAYER_COUNTRY_OVERRIDES`) fixed historical gaps; live create must not reopen empty nationality.

---

## 9. Historical import (unchanged)

L3 import continues to:

- Build `amiga_players` from witness games + [`build_canonical_name_map()`](../scripts/amiga/player_names.py).
- Apply manual spelling aliases in [`PLAYER_NAME_ALIASES`](../scripts/amiga/import_corrections.py).
- Log merges in `import_manifest.json` → `transforms.name_merges`.

Live-create rows **append** to `amiga_players`; they do not rewrite import manifest or merge groups.

---

## 10. CLI today vs this policy

| Command | Today | After live-ops slice |
|---------|-------|----------------------|
| `players suggest-name` | Reference algorithm | Same rules as §5 |
| `players create` | Inserts `amiga_players` | Same; add live-created provenance + country validate |
| `fixtures onboard-newcomer` | Creates player + entrant | Same storage; browser parity |

---

## 11. Verification (implementation)

Future `verify-*` / ops checks should include:

1. Every live-created player has provenance metadata.
2. No live-created player with `game_count > 0` is orphan-deleted.
3. Names satisfy PC2–PC3 and country ∈ registry.
4. Orphan cleanup only runs on allowed triggers (§6.3); tournament delete never removes players still on another entrant list.

---

## 12. Phase 2 backlog

| Item | Notes |
|------|-------|
| Create player on **open** tournament (not only create-league draft) | Same rules |
| Tournament **delete** + orphan cleanup | With guarded delete verb — §6.3 |
| **Rename** permanent players | Admin-only; separate policy |
| Site-wide hide until first game | Only if §6.4 clutter audit fails |

---

## 13. Changelog

| Date | Note |
|------|------|
| 2026-07-07 | Policy locked — Dagh decisions: auto-suggest only, minimum unique suffix, country required, create+roster in tournament context, delete-not-edit, organizer auth. |
| 2026-07-07 | **Rev. 2** — Retire provisional-until-finalize; **permanent `amiga_players` at create** + **orphan cleanup** on abandoned never-finalized tournaments (§4.4 X→Y edge case). |
| 2026-07-07 | **Rev. 2.1** — Orphan guard: tournament **X** delete must **not** remove **N** if **N** is still entrant on **Y** (§6.3.1); condition 3 = any other tournament, not “with at least one game”. |
