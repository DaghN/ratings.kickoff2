# Amiga ground layers — policy (L0–L5)

> **Archive (Jul 2026, DOC-1):** Forward Amiga ground authority → [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md). This doc records the **Access-era L0–L5 pipeline** (shipped Jun 2026). Do not assign daily simul, staging export, or living-ground work from here. Index: [`archive/amiga-access-pipeline-index.md`](archive/amiga-access-pipeline-index.md).

**Status:** **Policy v3** (Jun 2026) — strict stack **locked** and **shipped** (slices 1–11).  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)

**Purpose:** Define the offline Amiga **data pipeline** (L0–L5): what each step contains, how layers depend on each other, what is community-publishable vs ratings.kickoff.com product-only, and how this maps to code/DDL folder names.

**Stack intent (read first):** [`amiga-ground-stack.md`](amiga-ground-stack.md) — strict chain, opt-in/out, `witness_player_identity`, no `L0 → L3`.

---

## 0. Glossary (read first)

| Term | Meaning |
|------|---------|
| **Layer L0–L5** | Numbered **pipeline / epistemic** steps (this policy) |
| **Pack A / B / C** | **Community export** profiles (Ground / +Structure / Product) — not layer numbers |
| **`sql/ground`**, **`structure`**, **`derived`** | **DDL bundle folders** in repo — map to **L3 / L4 / L5** MySQL schema in `ko2amiga_db`, not to L1/L2 dumps |
| **`witness_player_identity`** | L2 witness table — `player` + `country` extracted from L1 `Rankings`; rating grid **not** carried forward |
| **`feature-log` “L0”** | Online ladder *read-time* migration level — **unrelated** to Amiga ground layers |

### Numbering migration (supersedes Apr 2026 draft)

| Old (draft) | **New (v2)** |
|-------------|--------------|
| *(source file)* | **L0** — `koatd.mdb` (artefact; not produced by us) |
| Old L0 pristine | **L1** — full mechanical SQL mirror |
| *(implicit prune)* | **L2** — hard-pruned witness candidate SQL |
| Old L1 witness | **L3** — canonical ground + corrections + light normalisation |
| Old L2 structure | **L4** — tournament structure overlay |
| Old L3 derived | **L5** — product derived + ops (ratings.kickoff.com) |

---

## 1. Why six steps

KOA’s Access file mixes source facts, legacy precomputes, evidence-based corrections, structure, and Elo replay. Six layers separate:

- What KOA actually ships (**L0**)
- A diffable faithful copy (**L1**)
- An explicit decision to drop Access precomputes (**L2**)
- Our researched canonical facts (**L3**)
- Module/fixture structure (**L4**)
- Our optional product layer (**L5**)

**Orchestrator:** `prove` runs the **full strict chain** from L0: **L1 → L2 → L3 → L4 → L5 → verify** (see [`amiga-ground-stack.md`](amiga-ground-stack.md) §6). Shipped slice 10.

---

## 2. Layer definitions

| Layer | Name | What it is | Produced? | Primary audience |
|-------|------|------------|-----------|------------------|
| **L0** | **koatd** | `data/amiga/source/koatd.mdb` — upstream Access file from KOA | No (input) | Everyone |
| **L1** | **Pristine mirror** | **All** Access tables → SQL, mechanical mapping, zero corrections | Yes | Maintainers, KOA diff, parity vs `Tables` / `added_players` |
| **L2** | **Pruned witness** | L1 minus legacy-derived tables; **plus** `witness_player_identity` extract | Yes | Pipeline gate; optional future community pruned pack |
| **L3** | **Witness ground** | Evidence-backed canonical facts in MySQL + `import_manifest.json` | Yes | **Community ground** (Pack A) |
| **L4** | **Structure overlay** | Stages, fixtures, entrants, disposition — RR/KO modules | Yes | Organisers; Pack B |
| **L5** | **Product derived** | Elo, snapshots, matchups, standings rows, catalog stats, ops writers | Yes | ratings.kickoff.com; Pack C |

```text
L0  koatd.mdb
  → L1  full pristine SQL (all Access tables)
  → L2  pruned witness SQL (Scores, Tournament players, witness_player_identity)
  → L3  witness MySQL + import_manifest (corrections, merges, supplements)
  → L4  structure overlay (disposition + materialize / live create)
  → L5  replay / finalize → derived tables
```

**Adjacent (not a layer):** tournament/fixture **creator tooling** (live UI) — writes into **L3/L4** on running events. Community may build their own on **L4**; not part of the numbered stack.

**Dependency rule (strict):** Layer *n* reads **only** layer *n−1* output. **L3** is required for serious use. **L4** optional for consumers but required for fixture UI and fixture-backed standings. **L5** optional (community can run their own ratings). **Any layer may be published or used as a pipeline stop** — see stack doc **S2**. A promoted L1/L2/L3 artefact may replace L0 as entry point later (**S3**).

---

## 3. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **G1** | **Six concerns, six numbers** | Do not ship L5 DDL/data in a community ground pack. Do not conflate L3 witness claims with L4 structure or L5 derived. |
| **G2** | **L0 is source, not output** | L0 is the file on disk. Layers L1–L5 are pipeline products or `ko2amiga_db` contents. |
| **G3** | **L1 = full mirror** | All Access user tables exported mechanically (see [`amiga-schema-discovery.md`](amiga-schema-discovery.md)). No corrections. |
| **G4** | **L2 = hard prune + identity extract** | Drop whole legacy-derived tables (`Tables`, `added_players`, WC `* Tables`, `added_misc`, import-error tables, …). **Exception:** extract L1 `Rankings` → L2 `witness_player_identity` (`player`, `country` only). Full `Rankings` grid (`R*`, rank order, activity) **never** enters L2. Drop L1 `Countries` — not witness (re-derive nationality list from player rows). Prune audit = `prune_manifest.json` (`pruned_from_l1`, `extracted_from_l1`). L1 retains full tables for parity. |
| **G5** | **L3 = historical claims** | Catalog dates, splits, supplements, identity merges, **Tier E finish overrides** — forum/chrono evidence, each row in `import_manifest.json`. Not “modelling taste.” |
| **G6** | **`phase` is witness, not structure authority** | `amiga_games.phase` records **what koatd recorded** (+ narrow routing). Do not patch Access phase strings to express researched structure — that is **L4**. |
| **G7** | **L4 = structure authority** | Module graph in `tournament_stages`, `tournament_fixtures`, `fixture_id` — disposition handlers or live builders. Standings/honours prefer fixture-backed scope; `tournament_phases.py` = legacy fallback. |
| **G8** | **One schema, two provenances for L4** | **Live:** fixtures → results → games. **Legacy:** games → materialize → fixtures. Same tables ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) T9). |
| **G9** | **Players are games-first in L3** | `amiga_players` built by scanning **all witness games** (from L2 `Scores`) after in-memory name merges — **not** imported from `added_players`. **Nationality** enriched from L2 `witness_player_identity` by name join — **not** from L0/L1 `Rankings` at import time. Missing identity row → empty `country` until L3 correction. |
| **G10** | **Epistemic gaps are explicit** | Synthetic `game_date` / within-day order is a **replay convention** (manifest), not an Access historical fact. |
| **G11** | **Sign-off anchor** | Reproducible from **published layer artefacts** at the chosen stop (L2 SQL + prune manifest, or L3 SQL + `import_manifest`, + L4 when present) — **not** from a local `.mdb` path alone. Full L0→L5 sign-off runs the strict chain (stack doc §6). |
| **G12** | **Strict chain — no side doors** | No `L0 → L3`, no `L1 → L3`, no reading `koatd.mdb` inside `import-witness` / `prove` except the L0→L1 step. Prune semantics live **only** in L2. L5 derived writes: [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) (`prove` only; no batch `*-rebuild` CLIs). |

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

### L2 — Pruned witness

| In | Out |
|----|-----|
| L1 `Scores`, `Tournament players` | Witness game + catalog rows (uncorrected) |
| L1 `Rankings` → **`witness_player_identity`** | `player`, `country` only |
| **No** `Tables`, `added_players`, full `Rankings`, `Countries`, WC `* Tables`, `added_misc`, import-error tables |

**Not required standalone publish** for community v1 — but must be a **valid pipeline stop** and future pack candidate.

**Prune manifest (no rating grid data):**

```json
"extracted_from_l1": [{
  "source_table": "Rankings",
  "witness_table": "witness_player_identity",
  "columns": ["player", "country"],
  "reason": "identity_slice; rating_grid_dropped"
}],
"pruned_from_l1": [
  {"table": "Tables", "rows": 4359, "reason": "legacy_derived_standings"},
  {"table": "added_players", "rows": 465, "reason": "legacy_derived_career"},
  {"table": "Rankings", "rows": 465, "reason": "legacy_derived_ratings_grid",
   "note": "identity → witness_player_identity"},
  {"table": "Countries", "rows": 21, "reason": "legacy_lookup_list"}
]
```

### L3 — Witness ground (`ko2amiga_db` core)

| Tables | `tournaments`, `amiga_players`, `amiga_games`, `amiga_tournament_finish_override` |
| Input | **L2 only** (strict stack) |
| Data | Goals, players, `tournament_id`, `source_*`, `phase`/`extra` (G6), synthetic `game_date` (G10) |
| Curated claims | Tier E finish overrides, catalog overrides, supplements — manifest-audited |
| Players | From **L2 games scan** + merges (G9); nationality from **`witness_player_identity`** |
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
| `Scores` | ✓ | ✓ (as-is) | → `amiga_games` |
| `Tournament players` | ✓ | ✓ (as-is) | → `tournaments` (incl. host **country**) |
| `Rankings` (full) | ✓ | **drop** (grid) | — |
| `Rankings.Player` + `Country` | ✓ | → **`witness_player_identity`** | nationality join → `amiga_players.country` |
| `Countries` | ✓ | **drop** | not witness; distinct nationalities re-derived from players |
| `Tables`, WC `* Tables` | ✓ | **drop** | parity via L1 only |
| `added_players` | ✓ | **drop** | parity via L1 only; never player registry |
| `Paste Errors`, `added_misc` | ✓ | **drop** | ignore |

---

## 6. Code / DDL mapping (avoid agent traps)

`ko2amiga_db` is created by **`apply_schema`** = three DDL bundles:

| DDL bundle folder | `apply_schema_*` | Layer |
|-------------------|-------------------|-------|
| `sql/ground/` | `apply_schema_ground()` | **L3** witness tables |
| `sql/structure/` | `apply_schema_structure()` | **L4** structure tables |
| `sql/derived/` | `apply_schema_derived()` | **L5** product tables |

**L1/L2** are separate SQL **dump** artefacts (`import-pristine`, `import-prune`), not these bundle names.

**Agent trap:** `prove` / `import-witness` must use `prepare_witness_from_l2` — not `prepare_witness_from_access(mdb)` (legacy audit only).

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

## 8. CLI map

| Layer | CLI | Verify |
|-------|-----|--------|
| L1 Mirror | `import-pristine` | `verify-pristine` |
| L2 Prune | `import-prune` | `verify-prune` |
| L3 Witness | `import-witness` | `verify-witness`, `verify-l2-l3` |
| L4 Structure | `apply-structure --from-disposition` | `verify-structure` |
| L5 Product | `replay` | `prove` verify suite |
| Orchestrator | `prove` | full chain L1→L5 → verify (includes `verify-l2-l3`) |
| Export packs | `export-pack {mirror\|ground\|structure\|product\|all}` | `verify-export-pack` |
| Staging browser import | `scripts/export_ko2amiga_work.ps1` (forward) · oracle shim `export_ko2amiga_db.ps1` | preview URL in [`amiga-staging-handoff.md`](amiga-staging-handoff.md) |

Community packs live under `data/amiga/exports/packs/`. Pack C archive = `export-pack product`; chunked staging = **`export_ko2amiga_work.ps1`**.

Structure track ([`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md)) **is L4 work**. Per-tournament materialize CLIs remain dev/repair tools.

---

## 9. Related docs

| Doc | Layer |
|-----|-------|
| [`amiga-ground-stack.md`](amiga-ground-stack.md) | **Strict chain intent** (S1–S7, L2 shape) |
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
| L1 vs L2 | `verify-prune` (partition, extracts, no rating columns) |
| L2 vs L3 | `verify-l2-l3` (manifest lineage, re-prepare parity, nationality oracle) + `verify-witness` |
| L3 | `verify-chronology`, `verify-import-manifest`, catalog audits |
| L4 | `verify-disposition-register`, `verify-structure`, `verify-export-pack structure` |
| L5 | `prove` verify suite |
| Full loop (oracle) | `python -m scripts.amiga prove` — L1→L5 on frozen **`ko2amiga_db`** |
| Full loop (forward) | `python -m scripts.amiga simul` — L4/L5 on **`ko2amiga_work`** ([`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md)) |

---

## 11. Out of scope

- Online `kooldb*` ladder
- Replacing `koatd.mdb` as KOA’s format (we may lobby; **L4** is our working spec)
- Big-bang tournament id reorder
- Shipping L5 writers inside Pack A
- Final community witness manifest for every player name alias (L3 content — defer until community input)

---

*Policy v3 Jun 2026: strict L0→L5 chain; L2 `witness_player_identity`; G12 no L0→L3; see [`amiga-ground-stack.md`](amiga-ground-stack.md).*
