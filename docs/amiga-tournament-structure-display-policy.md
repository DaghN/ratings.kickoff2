# Amiga tournament structure display — policy (Jul 2026)

> **Product policy (Jul 2026):** This doc locks the **target end state** for legacy tournament structure imprint and website display. **Not yet fully implemented** — current knockout UI still uses phase-string heuristics; materialize coverage is incomplete (~571/605 tournaments with stages on work DB, Jul 2026).

**Status:** **Policy locked (Jul 2026)** — decided in architecture review (tournament knockout viewer + legacy retirement arc). **Implementation:** deferred — no numbered slice track yet; follow phased rollout in §6.

**Purpose:** Tell agents and implementers **where we are moving** after Access-derived `phase` / `scope_key` display: explicit **structure metadata** on stages, **stage-native** tournament viewer, witness strings retired from layout authority.

**Authority:** This doc owns **structure imprint fields** (round grouping, bracket sections, display ordering) and **tournament viewer read-path rules** for Stages / Knockouts. **Module atoms** (RR scope, KO tie): [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md). **L5 scope / `stage_id`:** [`amiga-standings-scope-policy.md`](amiga-standings-scope-policy.md) S12. **Scoring executor:** [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) SC7–SC10. **Materialize ops:** [`amiga-tournament-structure-manual-materialize-runbook.md`](amiga-tournament-structure-manual-materialize-runbook.md). Table register: [`amiga-data-contract.md`](amiga-data-contract.md).

**Related:** [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) · [`amiga-profile-v0.md`](amiga-profile-v0.md) (tournament detail UX) · [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) (URL shape when adding `stage_id` nav)

**History:** Jul 2026 — product decision: finish catalog materialize (605/605), imprint structure metadata, replace AI-built phase-regex bracket UI with stage-native display. Supersedes ad-hoc bracket bucketing in `amiga_tournament_knockout_phase_rank()` / `amiga_tournament_knockout_phase_bucket()` as **target** (those remain transitional until imprint + new renderer ship).

---

## 1. Executive summary

### Problem

Early tournament viewer work (2025–2026) displayed knockouts by **inferring layout from Access phase strings**:

- Standings `scope_key` embeds witness phase text (`Semi Finals|1-153`).
- Bracket UI regex-buckets phase labels into sections (main / placement finals / placement brackets).
- Exotic labels (`Game of Shame`, `Loser Semi Finals`) mis-file or require ever-growing regex lists.

Parallel work established **canonical modules** in MySQL (`tournament_stages`, `tournament_fixtures`, `fixture_id` on games) and **`stage_id` on L5 standings** (SC-9). The **display layer lagged** — it still reads legacy scope strings instead of structure.

### End state (locked)

```text
WITNESS (retained, not layout authority)
  amiga_games.phase, standings scope_key, fixture phase_label

MODULE LAYER (L4a — materialize target: 605/605)
  tournament_stages.id     one RR scope OR one KO tie
  tournament_stages.name   UI label (editable; may duplicate across ties)
  tournament_fixtures      legs within a tie

STRUCTURE IMPRINT (L4a display metadata — written at materialize/triage)
  round_key, bracket_section, track_key, sequence_no
  on stage config and/or fixtures — read literally by UI

DISPLAY (tournament viewer — new system)
  identity: stage_id (or stable stage_key)
  labels: tournament_stages.name
  grouping: imprinted structure fields only — never parse phase/scope_key for layout
```

**Rule:** *Materialize modules now; imprint structure metadata next; display reads structure, never re-parses Access names.*

---

## 2. Three layers (do not conflate)

| Layer | Question | Storage | Written by | Read by display |
|-------|----------|---------|------------|-----------------|
| **Module** | What is this match / tie? | `tournament_stages`, `tournament_fixtures`, `amiga_games.fixture_id` | Materialize handlers, live builder, `StructureSpec` | `stage_id`, `stage_type`, fixtures |
| **Structure imprint** | Which round / section / track? | `tournament_stages.config_json` (+ optional fixture fields) | Materialize + taxonomy + triage | `round_key`, `bracket_section`, `sequence_no` |
| **Witness** | What did koatd record? | `amiga_games.phase`, L5 `scope_key`, `phase_label` | Import (immutable archaeology) | **Display must not use for layout**; compat redirects only |

**Knockout round ≠ module type.** A “Semi Finals round” groups **several** `knockout` stages (ties). Round grouping lives in **structure imprint**, not in `stage_type` — per [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T3–T4, T16.

---

## 3. Core decisions (locked)

| # | Decision | Rule |
|---|----------|------|
| **D1** | **605 materialize first** | Every catalog tournament gets stages + fixtures (or explicit `pending_review` with documented skip). New display assumes modules exist; legacy scope fallback is **transitional** only. |
| **D2** | **Structure imprint required** | Every materialized KO stage carries display structure metadata before knockout UI v2 ships. RR stages carry ordering + display name; multi-group events carry `group_key` / track as today. |
| **D3** | **`stage_id` canonical for nav** | Tournament Stages / Knockouts links and tie detail use `stage_id` (or `stage_key` slug). **Not** `scope_key`. **Not** `stage.name` alone (names collide — e.g. two “Semi Finals” ties on one event). |
| **D4** | **`name` is display only** | `tournament_stages.name` is UI authority (runbook already). Witness `g.phase` unchanged. |
| **D5** | **No read-time phase regex** | Retire `amiga_tournament_knockout_phase_rank()` / `phase_bucket()` and equivalent JS as **layout authority**. Bracket sections come from imprinted `bracket_section` + `round_key`. |
| **D6** | **Imprint at write time** | Map common Access labels → structure fields **once** at materialize (curated taxonomy). Exotic labels fixed in triage (`config_json`), not new global regex at read time. |
| **D7** | **Three imprint tiers** | See §4 — not 605 hand-written `StructureSpec` files. |
| **D8** | **Scope URL compat** | Legacy `?scope=knockout&scope_key=…` **302** to `stage_id=` when mappable. Same pattern as standings S8 (`overall` → `league`). |
| **D9** | **Witness preserved** | Do not drop `amiga_games.phase`, `scope_key`, or `phase_label` columns. Retire from **compute and layout authority** only (aligned with SC7, SC10, S12). |
| **D10** | **Live parity** | Running-tournament fixture groups (`amiga_live_tournament_fixture_groups()`) are the reference read shape; historical knockout tab converges on the same stage-native model. |
| **D11** | **Promotion graph deferred** | Advancement edges and auto bracket wiring remain out of scope until D18 / structure graph storage is locked. v2 display = **grouped tie columns**, not animated advancement graph. |

---

## 4. Legacy imprint tiers (not 605 bespoke specs)

| Tier | Typical handler | Count (order of magnitude) | Materialize | Structure imprint source |
|------|-----------------|----------------------------|-------------|--------------------------|
| **A — pure RR** | `pure_rr` | ~503 | One `round_robin` stage | Trivial: single league table; `name` often **League** |
| **B — routine cup** | `pure_knockout`, labeled `materialize_legacy` | ~40–80 | One `knockout` stage per tie | **Curated taxonomy** at materialize: witness label → `round_key` + `bracket_section` |
| **C — exotic multi-stage** | `structure_spec`, manual materialize | ~24+ | Per-tie stages and/or round containers | Full **`StructureSpec`** (`FixtureSpec.round_key`, stage `round_keys`) + triage notes |

**Rejected:** one `StructureSpec` Python module per catalog id (Homburg scale × 605). **Accepted:** shared handlers + taxonomy + curated specs for exotics only — disposition register routing unchanged ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) §4).

### Curated taxonomy (tier B)

A **version-controlled mapping table** (JSON or Python module), not runtime regex in PHP:

- `Semi Finals` → `round_key: semi`, `bracket_section: main`
- `Final` → `round_key: final`, `bracket_section: main`
- `3rd Place Final` → `round_key: placement_3rd`, `bracket_section: placement_final`
- Unknown / exotic → `round_key: custom`, `bracket_section: other` + **human triage** before display v2

Taxonomy runs at **materialize or backfill-imprint** time. Review queue documents overrides per tournament ([`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md)).

---

## 5. Structure imprint fields (target)

Stored on **`tournament_stages.config_json`** (v1 preference — avoids DDL until imprint shape stabilizes). Fixture-level `round_key` may mirror stage for generated specs; display **may** read either when consistent.

| Field | Role | Example values |
|-------|------|----------------|
| **`round_key`** | Column / round identity within bracket | `quarter`, `semi`, `final`, `placement_3rd`, `playout`, `last_16` |
| **`bracket_section`** | Top-level UI section | `main`, `placement_final`, `placement_bracket`, `other` |
| **`track_key`** | Parallel cup track (when multi-track) | `main`, `silver`, `koa` — may also use `tournament_stages.track_key` column |
| **`sequence_no`** | Order within tournament (already on stage row) | Used for stable sort when round keys tie |

**Display grouping:** `bracket_section` → section heading; within section, group ties by `round_key`; order ties by `sequence_no` then `stage_id`.

**Do not** re-derive `bracket_section` from `round_key` at read time in v2 — imprint both so triage can override (e.g. `Game of Shame` in `placement_bracket` without pretending it matched a regex).

---

## 6. Phased rollout (locked order)

| Phase | Goal | Exit criterion |
|-------|------|----------------|
| **P1 — Materialize** | 605/605 tournaments have `tournament_stages` (+ fixtures linked) | `COUNT(DISTINCT tournament_id)` from stages = 605; verify green |
| **P2 — Structure imprint** | KO (and multi-track) stages have `config_json` structure fields | Backfill CLI + `verify-structure-imprint` oracle; review queue updated for tier C overrides |
| **P3 — Display v2** | Tournament viewer reads stages + imprint only | Knockouts tab: no `list_scopes(knockout)` for layout; no phase_rank/bucket; `stage_id` URLs |
| **P4 — Compat sunset** | Remove scope-based layout fallbacks | After P3 stable + bookmarks redirected; SC-10 fixture linkage complete |

**Current gap (Jul 2026):** P1 incomplete (~34 tournaments without stages); P2–P3 not started; transitional bracket UI still in `amiga_tournament_bracket.php` + `amiga_tournament_knockout_bracket_data()`.

---

## 7. Tournament viewer rules (target)

Applies to `/amiga/tournament/stages.php` and knockout sub-nav (**Knockouts** label — not “Bracket”).

| Surface | Target read path |
|---------|------------------|
| **League tables** | `tournament_stages` where `stage_type = round_robin`; standings by `stage_id` |
| **Knockout overview** | All `knockout` stages for event; group by `bracket_section` + `round_key` |
| **Tie detail** | Fixtures + games for one `stage_id`; winner from standings executor / fixture resolution |
| **Games tab Phase column** | `COALESCE(stage.name, g.phase)` — already runbook rule |
| **Internal links** | `amiga_url_with_context()` / `k2_amiga_route()` carry `stage_id=`; TT `as=` only on API fetches |

**Fallback (P1 only):** If tournament has **no** stages, legacy `scope_type` + `scope_key` path may remain until materialized — do not extend fallback with new regex.

---

## 8. Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| **Keep phase-regex bracket UI after full materialize** | Structural truth in DB but layout still lies; endless regex debt |
| **605 × `StructureSpec` like Homburg** | Unsustainable; wrong tool for marathons and routine labeled cups |
| **`stage.name` as URL / grouping key** | Collides across ties; name is cosmetic |
| **Read-time name taxonomy in PHP** | Same brittleness as today, moved one layer — imprint must be stored |
| **Drop `scope_key` columns immediately** | Breaks unmateralized events and bookmarks; witness + redirect first |
| **Bracket advancement graph UI in v2** | Requires D18 promotion storage; defer per format vision |

---

## 9. Agent traps

- **Disposition `handler` ≠ display-ready** — materialized tier B may lack imprint until P2 backfill.
- **`scope_key` duplicates per tie** — two standings rows per KO pair; stage list must be **DISTINCT stage_id**, not DISTINCT scope_key.
- **Regex bucket symptoms** — “wrong section” bugs (e.g. Loser Semi Finals under Placement Brackets) mean **missing imprint**, not “fix the regex.”
- **Do not extend `parse_phase()` for display** — parser is executor fallback (SC10), not layout.
- **Materialize display name edits** — `UPDATE tournament_stages.name` is allowed; imprint fields must stay consistent if `round_key` derived from taxonomy.
- **New knockout UI slices** — read this doc + structure policy + standings scope S12 before choosing URL params.

---

## 10. Verification anchors (when implemented)

| Check | Expectation |
|-------|-------------|
| Kristiansand (54) | 8 KO stages; imprint places Game of Shame / Loser Semi Finals in intended sections; two Semi Finals ties same `round_key`, different `stage_id` |
| Pure RR marathon | One stage; Knockouts tab hidden |
| Homburg (structure_spec) | `round_key` from spec fixtures matches display columns |
| Legacy URL | `scope_key=3rd Place Final\|153-347` → 302 to correct `stage_id` |
| Unmaterialized id | Graceful empty or legacy fallback until P1 complete — no new regex |

---

## 11. Out of scope (defer)

- Full promotion / advancement graph storage (**D18**)
- Bracket animation or auto-advance edges UI
- Replacing `scope_type` enum on L5 rows (storage shape may remain for executor)
- Online `kooldb*` ladder tournaments — different realm
- Normalizing all witness phase mislabels in `amiga_games.phase`

---

## Related

- Modules vs structure graph: [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)
- Manual materialize runbook: [`amiga-tournament-structure-manual-materialize-runbook.md`](amiga-tournament-structure-manual-materialize-runbook.md)
- Review / triage log: [`amiga-tournament-structure-review-queue.md`](amiga-tournament-structure-review-queue.md)
- Scoring + `stage_id`: [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md)
- Historical bracket investigation: [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) §3.4, §8
