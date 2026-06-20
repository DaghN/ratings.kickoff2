# Amiga ground layers — policy (L0–L3)

**Status:** Locked direction (Jun 2026). Implementation not started — see [`amiga-ground-layers-implementation-plan.md`](amiga-ground-layers-implementation-plan.md).  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)

**Purpose:** Define four **modular** data layers for the offline Amiga realm, what each may contain, how they depend on each other, and what is community-publishable vs ratings.kickoff.com product-only.

---

## 1. Why four layers

Today `python -m scripts.amiga prove` collapses archival input, evidence-based corrections, structure backfill, and derived ratings into one nuclear path and one fat staging export. That worked for shipping the website but blocks:

- A **neutral ground pack** the community can adopt without Elo/snapshots
- A **pristine baseline** to diff new `koatd.mdb` drops
- Clear separation between **what happened** (scores), **how koatd labelled it** (`phase`), **how the event was organised** (fixtures), and **our derived product**

**Target:** separate **scripts**, **DDL bundles**, and **export profiles** per layer. `prove` becomes an orchestrator over layers 1→2→3, not a monolith.

---

## 2. Layer definitions

| Layer | Name | What it is | Primary audience |
|-------|------|------------|------------------|
| **L0** | **Pristine** | Mechanical export of `koatd.mdb` → SQL/rows **as-read** (no corrections, no supplements, no identity merges) | Archivists, diff vs KOA drops |
| **L1** | **Witness ground** | Evidence-backed **facts**: catalog, players, game scores, routing — plus `import_manifest.json` for every change | Community canonical facts |
| **L2** | **Structure overlay** | Stages, fixtures, entrants, lifecycle — **RR / KO modules**; live UI and historical backfill share this model | Organisers, fixture UI, honours that need modules |
| **L3** | **Derived product** | Ratings, snapshots, matchups-at-event, standings rows, catalog stats, generalstats — rebuildable from L1 (+ L2 when fixture-backed) | ratings.kickoff.com |

```text
koatd.mdb
    → L0  pristine SQL (optional artifact; diff only)
    → L1  witness ground + manifest
    → L2  structure overlay (disposition + materialize / live create)
    → L3  replay / finalize → derived tables
```

**Dependency rule:** L1 is required for any serious use. L2 is optional for consumers but **required** for fixture UI and for structural truth when `phase` is wrong or NULL. L3 is optional (community can build their own ratings).

---

## 3. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **G1** | **Four layers, four concerns** | Do not ship L3 DDL/data in a “community ground” pack. Do not conflate L1 corrections with L2 structure or L3 derived. |
| **G2** | **L1 = historical claims** | Catalog dates, splits, supplements, merges, identities — forum/chrono evidence, each row in `import_manifest.json`. Same epistemic spirit as today’s `import_corrections.py`; not “Dagh modelling taste.” |
| **G3** | **`phase` is witness, not structure authority** | `amiga_games.phase` (and `extra`) record **what koatd recorded** (+ narrow import routing: Milan X fragment inference, KOA track prefix). **Do not** patch erroneous Access phase strings to express researched structure. |
| **G4** | **L2 = structure authority** | Researched module graph (RR tables, KO ties, groups, tracks) lives in `tournament_stages`, `tournament_fixtures`, `fixture_id` — via disposition handlers (`pure_rr`, `pure_knockout`, `structure_spec`) or live builders. Standings/honours **prefer** fixture-backed scope when `fixture_id` is set; `tournament_phases.py` is **legacy fallback** only. |
| **G5** | **One schema, two provenances for L2** | **Live:** schedule fixtures → enter results → games. **Legacy:** games first → materialize assigns fixtures/stages. Same tables; different creation order ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T9). |
| **G6** | **Community fixture UI ⇒ standardise L2** | If the community wants tournament/fixture tooling, the interchange format must include L2 — not legacy phase strings as the long-term API. Historical backfill is **the same engine in reverse**, not a separate product. |
| **G7** | **Epistemic gaps are explicit** | Synthetic `game_date` / within-day order is a **replay convention**, not L1 historical fact. Document in manifest policy; do not pretend Access recorded play order. |
| **G8** | **`koatd.mdb` remains input, not chain anchor** | Sign-off must be reproducible from **published L1 SQL + manifest** (and L2 overlay when present), not from a local MDB path alone. MDB drives L0/L1 builds, not daily ops. |

---

## 4. What belongs in each layer

### L0 — Pristine

| In | Out |
|----|-----|
| Raw `[Tournament players]`, `Scores`, player names as in Access | No `import_corrections`, no supplements, no `player_names` merges, no catalog splits |
| Mechanical column mapping only | May still assign insert order / raw Access dates (no synthetic second counter) — document if so |

**Publish:** optional; mainly for maintainers and KOA diff.

### L1 — Witness ground

| Tables (minimum) | `tournaments`, `amiga_players`, `amiga_games` |
| Fields | Goals, players, `tournament_id`, `source_*` provenance, `phase`/`extra` per G3, synthetic `game_date` per G7 |
| Audit | `import_manifest.json` — catalog overrides, splits, supplements, name merges, routing aliases |
| Live append | New events add rows here (and L2 when fixture-backed); `source_id IS NULL` = generated catalog |

**Publish:** **yes** — primary community pack (“canonical offline ground v1”).

### L2 — Structure overlay

| Tables | `tournament_format_templates`, `tournament_entrants`, `tournament_stages`, `tournament_stage_players`, `tournament_fixtures`; `amiga_games.fixture_id`; `tournaments` lifecycle / `format_*` columns |
| Authority | `disposition_register.json` → handler per tournament; `StructureSpec` for curated events |
| Not in L2 | Per-game Elo, career snapshots, H2H summaries |

**Publish:** **yes** — second community pack or combined “ground + structure” manifest with disposition version.

### L3 — Derived product

| Tables | `amiga_game_ratings`, `amiga_player_event_snapshots`, `amiga_player_current`, `amiga_player_matchup_*`, `amiga_tournament_standings`, `amiga_tournament_catalog_stats`, `amiga_generalstats`, `amiga_tournament_finish_override`, … |
| Writers | `replay`, `finalize_tournament`, PHP live finalize |

**Publish:** ratings.kickoff.com staging export only unless community explicitly wants derived rebuild recipes.

---

## 5. Community packaging (target)

| Pack | Layers | Use |
|------|--------|-----|
| **A — Witness** | L1 + manifest | Facts only; roll your own UI/ratings |
| **B — Structure** | L1 + L2 + manifests | Fixture UI, brackets, module-aware standings |
| **C — Product** | L1 + L2 + L3 | Full ratings.kickoff.com parity |

Pack A must not require Pack C tables to exist.

---

## 6. Current repo vs target (honest)

| Topic | Today | Target |
|-------|-------|--------|
| L0 script | Does not exist | `import-pristine` / `export-pristine` |
| L1 | `import_access.py` (+ corrections modules) | Same logic, isolated DDL bundle, dedicated export |
| L2 at import | Schema in `apply_schema`; data: thin (`StructureSpec` hook); bulk materialize post-import | `apply-structure` after L1; disposition dispatch in pipeline |
| L3 | `replay` / `prove` | Unchanged; DDL split out of L1 recreate |
| Staging export | Single fat dump | Profiles A / B / C |
| `prove` | Nuclear monolith | `L1 → L2 → L3 → verify` orchestrator |

Existing structure track ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md), disposition register, `materialize_legacy`, `fixtures.php`) **is L2 work** — this policy names it and positions it in the modular pipeline.

---

## 7. Relationship to other docs

| Doc | Relation |
|-----|----------|
| [`amiga-import-layer.md`](amiga-import-layer.md) | **L1** transform registry and manifest; will shrink to L1-only vocabulary |
| [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) | **L2** module taxonomy and legacy backfill rules |
| [`amiga-tournament-format-vision.md`](amiga-tournament-format-vision.md) | Background; L2 supersedes “phase as format” long-term |
| [`amiga-data-contract.md`](amiga-data-contract.md) | L3 post-game/replay rules; table register split by layer in implementation |
| [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) | **L3** only |

---

## 8. Verification (target)

| Layer | Gate |
|-------|------|
| L0 vs L1 | Row-level diff script; manifest explains every delta |
| L1 | `verify-import-manifest`, `verify-chronology`, catalog audits |
| L2 | `verify-disposition-register`, structure verify CLIs, fixture linkage counts |
| L3 | Existing `prove` verify suite |

---

## 9. Out of scope (this track)

- Online `kooldb*` ladder
- Replacing `koatd.mdb` edits by KOA (we still **lobby** for a better canonical interchange; L2 is our working spec)
- Big-bang tournament id reorder (deferred; append-only splits remain)
- Moving L3 writers into community pack

---

*Policy locked Jun 2026 from architecture review: witness vs structure split, community fixture UI, modular exports.*
