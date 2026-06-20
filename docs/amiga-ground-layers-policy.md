# Amiga ground layers — policy (L0–L5)

**Status:** **Implemented** (Jun 2026) — slices 1–8 complete. DDL bundles, modular `prove`, export packs shipped. See [`amiga-ground-layers-implementation-plan.md`](amiga-ground-layers-implementation-plan.md).  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)

**Purpose:** Define the offline Amiga **data pipeline** (L0–L5): what each step contains, how layers depend on each other, what is community-publishable vs ratings.kickoff.com product-only, and how this maps to code/DDL folder names.

---

## 0. Glossary (read first)

| Term | Meaning |
|------|---------|
| **Layer L0–L5** | Numbered **pipeline / epistemic** steps (this policy) |
| **Pack A / B / C** | **Community export** profiles (Ground / +Structure / Product) — not layer numbers |
| **`sql/ground`**, **`structure`**, **`derived`** | **DDL bundle folders** in repo — map to **L3 / L4 / L5** MySQL schema in `ko2amiga_db`, not to L1/L2 dumps |
| **`feature-log` “L0”** | Online ladder *read-time* migration level — **unrelated** to Amiga ground layers |

### Numbering migration (supersedes Apr 2026 draft)

| Old (draft) | **New (v2)** |
|-------------|--------------|
| *(source file)* | **L0** — `koatd.mdb` (artefact; not produced by us) |
| Old L0 pristine | **L1** — full mechanical SQL mirror |
| *(implicit prune)* | **L2** — hard-pruned witness candidate tables |
| Old L1 witness | **L3** — canonical ground + corrections + light normalisation |
| Old L2 structure | **L4** — tournament structure overlay |
| Old L3 derived | **L5** — product derived + ops (ratings.kickoff.com) |

---

## 1. Why six steps

Today `python -m scripts.amiga prove` collapses KOA’s Access file, legacy-derived tables, evidence-based corrections, structure backfill, and Elo replay into one nuclear path. That shipped the website but obscures:

- What KOA actually ships (**L0**)
- A diffable faithful copy (**L1**)
- An explicit decision to drop Access precomputes (**L2**)
- Our researched canonical facts (**L3**)
- Module/fixture structure (**L4**)
- Our optional product layer (**L5**)

**Target (shipped Jun 2026):** separate **scripts**, **export profiles**, and **DDL bundles** per concern. `prove` orchestrates **L3 → L4 → L5 → verify**.

---

## 2. Layer definitions

| Layer | Name | What it is | Produced? | Primary audience |
|-------|------|------------|-----------|------------------|
| **L0** | **koatd** | `data/amiga/source/koatd.mdb` — upstream Access file from KOA | No (input) | Everyone |
| **L1** | **Pristine mirror** | **All** Access tables → SQL, mechanical mapping, zero corrections | Yes | Maintainers, KOA diff, parity vs `Tables` / `added_players` |
| **L2** | **Pruned** | L1 **minus** legacy-derived relations (hard drop — **no sidecar**, no tagged duplicates) | Yes | Pipeline step; audit via prune manifest only |
| **L3** | **Witness ground** | Evidence-backed canonical facts in MySQL + `import_manifest.json` | Yes | **Community ground** (Pack A) |
| **L4** | **Structure overlay** | Stages, fixtures, entrants, disposition — RR/KO modules | Yes | Organisers; Pack B |
| **L5** | **Product derived** | Elo, snapshots, matchups, standings rows, catalog stats, ops writers | Yes | ratings.kickoff.com; Pack C |

```text
L0  koatd.mdb
  → L1  full pristine SQL (all Access tables)
  → L2  pruned SQL (witness candidates only; manifest lists what was dropped)
  → L3  witness MySQL + import_manifest (corrections, merges, supplements)
  → L4  structure overlay (disposition + materialize / live create)
  → L5  replay / finalize → derived tables
```

**Adjacent (not a layer):** tournament/fixture **creator tooling** (live UI) — writes into **L3/L4** on running events. Community may build their own on **L4**; not part of the numbered stack.

**Dependency rule:** **L3** is required for serious use. **L4** optional for consumers but required for fixture UI and fixture-backed standings. **L5** optional (community can run their own ratings). **L1** retained for parity; **L2** is an internal gate before witness work.

---

## 3. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **G1** | **Six concerns, six numbers** | Do not ship L5 DDL/data in a community ground pack. Do not conflate L3 witness claims with L4 structure or L5 derived. |
| **G2** | **L0 is source, not output** | L0 is the file on disk. Layers L1–L5 are pipeline products or `ko2amiga_db` contents. |
| **G3** | **L1 = full mirror** | All Access user tables exported mechanically (see [`amiga-schema-discovery.md`](amiga-schema-discovery.md)). No corrections. |
| **G4** | **L2 = hard prune only** | Drop whole legacy-derived tables (`Tables`, `added_players`, `Rankings` grid, WC `* Tables`, …). **No reference sidecar** in L2 — L1 remains for parity. Prune audit = **manifest JSON only** (`pruned_from_l1`: table, rows, reason). |
| **G5** | **L3 = historical claims** | Catalog dates, splits, supplements, identity merges, **Tier E finish overrides** — forum/chrono evidence, each row in `import_manifest.json`. Not “modelling taste.” |
| **G6** | **`phase` is witness, not structure authority** | `amiga_games.phase` records **what koatd recorded** (+ narrow routing). Do not patch Access phase strings to express researched structure — that is **L4**. |
| **G7** | **L4 = structure authority** | Module graph in `tournament_stages`, `tournament_fixtures`, `fixture_id` — disposition handlers or live builders. Standings/honours prefer fixture-backed scope; `tournament_phases.py` = legacy fallback. |
| **G8** | **One schema, two provenances for L4** | **Live:** fixtures → results → games. **Legacy:** games → materialize → fixtures. Same tables ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T9). |
| **G9** | **Players are games-first in L3** | `amiga_players` built by scanning **all witness games** after in-memory name merges — **not** imported from `added_players`. Country may be read from L1 `Rankings` at import time without storing the grid in L3. |
| **G10** | **Epistemic gaps are explicit** | Synthetic `game_date` / within-day order is a **replay convention** (manifest), not an Access historical fact. |
| **G11** | **Sign-off anchor** | Reproducible from **published L3 SQL + manifest** (+ L4 when present), not from a local `.mdb` path alone. |

---

## 4. What belongs in each layer

### L0 — koatd (source)

| In | Notes |
|----|--------|
| `data/amiga/source/koatd.mdb` | Read-only input; KOA drops |

Not exported by us. Not versioned in git (local/staging).

### L1 — Pristine mirror

| In | Out |
|----|-----|
| All Access tables → SQL | No `import_corrections`, supplements, merges, splits |
| Mechanical column mapping | Raw names on games; Access dates as-is |

**Publish:** optional **Mirror pack** for maintainers / archivists.

**Use:** diff vs new KOA drops; `standings_parity` / honours archaeology without re-reading `.mdb`.

### L2 — Pruned

| In | Out |
|----|-----|
| L1 subset | **No** `Tables`, `added_players`, `Rankings`, WC `* Tables`, `added_misc`, import-error tables |
| Witness-candidate core | `Scores`, `Tournament players`, `Countries` (if needed), mechanical player name strings on games |

**Not published standalone** for community — intermediate step. **L1 still holds** dropped tables.

**Prune manifest (no data):**

```json
"pruned_from_l1": [
  {"table": "Tables", "rows": 4359, "reason": "legacy_derived_standings"},
  {"table": "added_players", "rows": 465, "reason": "legacy_derived_career"}
]
```

### L3 — Witness ground (`ko2amiga_db` core)

| Tables | `tournaments`, `amiga_players`, `amiga_games`, `amiga_tournament_finish_override` |
| Data | Goals, players, `tournament_id`, `source_*`, `phase`/`extra` (G6), synthetic `game_date` (G10) |
| Curated claims | `amiga_tournament_finish_override` (Tier E), catalog overrides, supplements — manifest-audited |
| Players | From **games scan** + merges (G9); not from `added_players` |
| Live | New events append here (+ L4 when fixture-backed) |

**Publish:** **Pack A — Ground** (L3 + manifest).

### L4 — Structure overlay

| Tables | `tournament_format_templates`, `tournament_entrants`, `tournament_stages`, `tournament_stage_players`, `tournament_fixtures`; `amiga_games.fixture_id`; tournament lifecycle / `format_*` |
| Authority | `disposition_register.json`, `StructureSpec`, live `fixtures.php` |
| Not L4 | Per-game Elo, career snapshots, H2H summaries |

**Publish:** **Pack B** = L3 + L4 + manifests.

### L5 — Product derived

| Tables | `amiga_game_ratings`, `amiga_player_event_snapshots`, `amiga_player_current`, `amiga_player_matchup_*`, `amiga_tournament_standings`, `amiga_tournament_catalog_stats`, `amiga_generalstats`, … |
| Writers | `replay`, `finalize_tournament`, PHP live finalize |
| Rebuild | Always from L3 (+ L4 when fixture-backed) |

**Publish:** **Pack C** = L3 + L4 + L5. ratings.kickoff.com staging default.

---

## 5. Access tables — L1 vs L2 vs L3

| Access table | L1 | L2 | L3 usage |
|--------------|----|----|----------|
| `Scores` | ✓ | ✓ | → `amiga_games` |
| `Tournament players` | ✓ | ✓ | → `tournaments` |
| `Countries` | ✓ | optional | reference at import |
| `Rankings` | ✓ | **drop** | read `Country` only at L3 import (from L1 dump or `.mdb`) |
| `Tables`, WC `* Tables` | ✓ | **drop** | parity via L1 only |
| `added_players` | ✓ | **drop** | parity via L1 only; never player registry |
| `Paste Errors`, `added_misc` | ✓ | **drop** | ignore |

---

## 6. Code / DDL mapping (avoid agent traps)

`ko2amiga_db` today is created by **`apply_schema`** = three DDL bundles (slice 1 shipped):

| DDL bundle folder | `apply_schema_*` | Layer |
|-------------------|-------------------|-------|
| `sql/ground/` | `apply_schema_ground()` | **L3** witness tables |
| `sql/structure/` | `apply_schema_structure()` | **L4** structure tables |
| `sql/derived/` | `apply_schema_derived()` | **L5** product tables |

**L1/L2** are separate SQL **dump** artefacts (`import-pristine`, `import-prune` — shipped Jun 2026), not these bundle names.

---

## 7. Community packaging

| Pack | Layers | Use |
|------|--------|-----|
| **Mirror** *(optional)* | L1 | Full Access mirror for archivists |
| **A — Ground** | L3 + manifest | Neutral canonical facts |
| **B — Structure** | L3 + L4 + manifests | Fixture UI, module-aware standings |
| **C — Product** | L3 + L4 + L5 | ratings.kickoff.com parity |

Pack A must not require Pack C tables.

---

## 8. Shipped CLI map (Jun 2026)

| Layer | CLI | Verify |
|-------|-----|--------|
| L1 Mirror | `import-pristine` | `verify-pristine` |
| L2 Prune | `import-prune` | `verify-prune` |
| L3 Witness | `import-witness` | `verify-witness` |
| L4 Structure | `apply-structure --from-disposition` | `verify-structure` |
| L5 Product | `replay` (via `prove` / `run`) | `prove` verify suite |
| Orchestrator | `prove` | — |
| Export packs | `export-pack {mirror\|ground\|structure\|product\|all}` | `verify-export-pack` |
| Staging browser import | `scripts/export_ko2amiga_db.ps1` | preview URL in [`amiga-staging-handoff.md`](amiga-staging-handoff.md) |

Community packs live under `data/amiga/exports/packs/`. Pack C archive = `export-pack product`; chunked staging = `export_ko2amiga_db.ps1`.

Structure track ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)) **is L4 work**. Per-tournament materialize CLIs remain dev/repair tools.

---

## 9. Related docs

| Doc | Layer |
|-----|-------|
| [`amiga-schema-discovery.md`](amiga-schema-discovery.md) | L0 inventory |
| [`amiga-import-layer.md`](amiga-import-layer.md) | **L3** transforms + manifest |
| [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) | **L4** |
| [`amiga-data-contract.md`](amiga-data-contract.md) | L3–L5 table register + replay rules |
| [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) | **L5** |

---

## 10. Verification

| Layer | Gate |
|-------|------|
| L0 vs L1 | `verify-pristine` |
| L1 vs L2 | `verify-prune` |
| L2 vs L3 | `verify-import-manifest`, `verify-witness` |
| L3 | `verify-chronology`, catalog audits |
| L4 | `verify-disposition-register`, `verify-structure`, `verify-export-pack structure` |
| L5 | `prove` verify suite |
| Full loop | `python -m scripts.amiga prove` |

---

## 11. Out of scope

- Online `kooldb*` ladder
- Replacing `koatd.mdb` as KOA’s format (we may lobby; **L4** is our working spec)
- Big-bang tournament id reorder
- Shipping L5 writers inside Pack A

---

*Policy v2 locked Jun 2026: L0=koatd, L1 mirror, L2 hard prune, L3 witness, L4 structure, L5 product; no L2 sidecar.*
