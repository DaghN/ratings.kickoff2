# Amiga ground stack — strict layer chain (L0–L5)

**Status:** **Policy locked** (Jun 2026) — strict L0→L5 stack **shipped** (slices 1–11).  
**Authority:** This doc states **engineering intent** for the koatd pipeline. When it conflicts with older archived wording (e.g. “prove skips L1/L2”), **this doc wins**. Strict stack **shipped** slices 1–11 (Jun 2026).  
**Parent:** [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) · [`amiga-ground-layers-implementation-plan.md`](amiga-ground-layers-implementation-plan.md)

---

## 1. What we are building

A **strict inferential stack**: each numbered layer is produced **only** from the layer immediately below it. No layer reads `koatd.mdb` (L0) unless it **is** the L0→L1 step.

```text
L0  koatd.mdb
  → L1  full pristine SQL (all Access tables, mechanical)
  → L2  pruned witness SQL (games + catalog + player identity slice)
  → L3  witness MySQL ground + import_manifest (corrections, merges)
  → L4  structure overlay (disposition, fixtures)
  → L5  product derived (replay, snapshots, standings, …)
```

**Goals:**

1. **Community publishability** — each layer is a valid artefact; consumers can stop at L1, L2, or L3 without our product stack.
2. **Engineering isolation** — prune rules, witness transforms, structure, and derived replay live in **one place each**.
3. **Retire L0** — a promoted canonical L1, L2, or L3 can become the live input later; nobody is forced to hold `koatd.mdb` forever.

---

## 2. Hard rules (non-negotiable)

| # | Rule |
|---|------|
| **S1** | **Strict chain** — L*n* reads **only** L*n−1* output. No `L0 → L3`, no `L1 → L3`, no “peek at `.mdb`” in witness import. |
| **S2** | **Opt in / opt out** — every boundary is a real checkpoint. You may run or publish up to any layer and stop. |
| **S3** | **Promoted entry points** — `import-witness` must accept **L2** (and eventually published L3 SQL) as input, not only fresh L0 drops. |
| **S4** | **L3 role unchanged** — L3 still applies corrections, merges, supplements, manifest audit. Recentring L3 on L2 does **not** change what L3 *means*; only what it *reads*. |
| **S5** | **No duplicate prune logic** — what may enter witness work is defined **once** at L2; L3 must not re-encode table drops. |
| **S6** | **Ratings grid never in L2** — legacy KOA monthly `Rankings` columns (`R0102`…`R1221`, rank order, activity) are derived; they do not appear in L2 witness SQL. |
| **S7** | **Nationality is ground truth** — player nationality survives L2; tournament host country survives L2. A distinct “list of country names” does **not** (see §4). |

---

## 3. What each layer is for

| Layer | Question it answers | Typical consumer |
|-------|---------------------|------------------|
| **L0** | What did KOA ship? | Everyone (source file) |
| **L1** | What is a faithful mechanical copy? | Archivists, KOA diff, parity vs `Tables` / `added_players` |
| **L2** | What raw facts are witness candidates (legacy precomputes removed)? | Pipeline gate; future community “pruned Access” pack |
| **L3** | What do we **claim** as canonical ground after evidence-backed transforms? | Community Pack A (ground) |
| **L4** | How are modules / fixtures modelled? | Organisers; Pack B |
| **L5** | What does ratings.kickoff.com (or a replay) derive? | Product; Pack C |

L3 is **community witness work** (institutional knowledge, corrections). L2 is **not** witness — it is “what we agree to treat as raw input before our claims.”

---

## 4. L2 witness artefact (locked shape)

**L2 SQL (`L2_pruned.sql`) contains:**

| Table / witness name | Source (L1) | Role |
|----------------------|-------------|------|
| `Scores` | `Scores` | Canonical match rows (names on games, not player IDs) |
| `Tournament players` | `Tournament players` | Tournament catalog; **host country** on each event |
| `witness_player_identity` | L1 `Rankings` — **`player` + `country` only** | KOA’s per-player nationality label |

**Explicitly not in L2:**

| L1 table | Reason |
|----------|--------|
| Full `Rankings` | Rating grid + ladder metadata are **derived**; identity columns are **extracted** to `witness_player_identity` |
| `Countries` | Legacy lookup list (21 names) — **re-derive** distinct nationalities from player rows at L3/L5; not ground truth |
| `Tables`, WC `* Tables`, `added_players`, … | Legacy derived — parity via **L1 only** |

**`witness_player_identity` rules:**

- Created at **L2** from L1 `Rankings` rows (`Player` → `player`, `Country` → `country`).
- **Omit for now:** `FirstPlayed` (ladder timeline code), `Address` (empty in current koatd).
- Do **not** name this table `Rankings` in L2 — avoids implying the monthly grid survived.

**Prune manifest** records both drops and extracts, e.g.:

```json
"extracted_from_l1": [{
  "source_table": "Rankings",
  "witness_table": "witness_player_identity",
  "columns": ["player", "country"],
  "reason": "identity_slice; rating_grid_dropped"
}],
"pruned_from_l1": [{
  "table": "Rankings",
  "reason": "legacy_derived_ratings_grid",
  "note": "identity → witness_player_identity"
}]
```

---

## 5. How L3 uses L2 (player & country model)

Access has **no player IDs on `Scores`** — only name strings. Our MySQL model uses surrogate IDs (`amiga_players.id`, FKs on `amiga_games`). That does not change.

| Fact | L2 source | L3 behaviour |
|------|-----------|--------------|
| **Who played** | `Scores` | **Games-first** — distinct names from witness games → `amiga_players` (after in-memory merges). Not from `added_players`. |
| **Player nationality** | `witness_player_identity` | Join by name → `amiga_players.country`. Missing row → empty until L3 correction / community manifest. |
| **Tournament host country** | `Tournament players` | → `tournaments.country` (+ L3 WC venue overrides in `import_corrections.py`) |

Community-specific name merges, typo fixes, and extra witness claims remain **L3 manifest work** — out of scope for L2 structure decisions.

---

## 6. Target `prove` orchestrator

Full sign-off from a fresh L0 drop:

```text
import-pristine     # L0 → L1
import-prune        # L1 → L2
import-witness      # L2 → L3  (not .mdb)
apply-structure     # L3 → L4
replay              # L3 (+ L4) → L5
sync tournament video DB anchors   # review.csv + tournament_videos.json (L0 editorial, not L5)
verify suite        # includes verify-tournament-videos
```

**L5 derived writes:** [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) — `prove` / finalize only; batch `*-rebuild` CLIs retired Jun 2026.

Entry from a **promoted** artefact (future):

```text
import-witness --from-l2 path/to/L2_pruned.sql   # skip L0–L1
# or
import-witness --from-l3 …                        # skip L0–L2
```

Exact CLI flags are implementation detail; **S1–S3** are the contract.

---

## 7. Implementation status

| Track | Status |
|-------|--------|
| L2 `witness_player_identity`; drop `Countries` | **Done** (slice 9) |
| L3 from L2 only; `prove` L1→L5; no `.mdb` on witness path | **Done** (slice 10) |
| L2→L3 boundary verify (`verify-l2-l3`) | **Done** (slice 11) |

`prepare_witness_from_access(mdb)` remains for **legacy audit** only — not used by `prove` or `import-witness`.

---

## 8. Related docs

| Doc | Role |
|-----|------|
| [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) | Layer definitions, G-rules, packs, CLI map |
| [`amiga-ground-layers-implementation-plan.md`](amiga-ground-layers-implementation-plan.md) | Slice schedule + STOP gates |
| [`amiga-import-layer.md`](amiga-import-layer.md) | L3 transforms + manifest |
| [`amiga-schema-discovery.md`](amiga-schema-discovery.md) | L0 Access inventory |
| [`amiga-data-contract.md`](amiga-data-contract.md) | L3–L5 MySQL register |

---

*Locked Jun 2026 — strict L0→L5 chain; L2 `witness_player_identity`; no L0→L3; `Countries` not witness.*
