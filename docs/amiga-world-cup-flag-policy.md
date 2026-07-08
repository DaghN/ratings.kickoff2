# Amiga World Cup flag (`is_world_cup`) — policy

> **Product policy (Jul 2026):** Rules below remain authoritative for product behaviour. **Writer/sign-off at ship** = oracle **`prove`** on frozen **`ko2amiga_db`**; **forward** = **`simul`** on **`ko2amiga_work`**. [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0.

**Status:** **Shipped (Jul 2026)** — local `ko2amiga_db` + `prove` green.  
**Parent:** [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (L3 witness) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-data-contract.md`](amiga-data-contract.md)  
**Related:** [`amiga-world-cup-flag-implementation-plan.md`](amiga-world-cup-flag-implementation-plan.md) · [`amiga-world-cups-hub-policy.md`](amiga-world-cups-hub-policy.md) · [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md)

---

## 1. Executive summary

World Cup **catalog membership** is today inferred everywhere by tournament **name regex** (`^World Cup\s+\S`). That works but scatters the same rule across PHP SQL, Python writers, and filters.

This policy adds a stored **`is_world_cup`** flag:

- On **`tournaments`** (L3 ground witness)
- **Copied** onto participation / event-snapshot grain (same data-flow habit as `has_league` / `has_cup`)
- **Derived mechanically** on historical import (no overrides, no manifest section)
- **Set by manual checkbox** on live ops create, with **bidirectional name↔flag validation**

**Primary goal:** cleaner read paths and writers — not query indexes or performance tuning.

---

## 2. Scope

### 2.1 In scope

| Area | Rule |
|------|------|
| **DDL** | `tournaments.is_world_cup` in L3 ground bundle; snapshot/participation copy in L5 DDL |
| **L3 import** | Set flag **last** in catalog persist, after merge/split/corrections |
| **Prove verify** | Every imported tournament: `is_world_cup` equals regex applied to canonical `name` |
| **Read path** | `amiga_tournament_is_world_cup()` and SQL filters read stored flag |
| **Live ops create** | Checkbox on organizer create; independent of `format_template_id` |
| **Live ops validate** | Checkbox ⟺ name regex at create (see **WC14**) |
| **Writers** | WC slice, honours WC increments, `amiga_world_cup_stats`, community facts — gate on stored flag |

### 2.2 Out of scope (v1)

| Item | Notes |
|------|-------|
| **Index on `is_world_cup`** | Deferred — not a v1 goal |
| **`import_manifest` section** | Pure derivation; no override table |
| **Repurposing `has_cup` / `is_cup`** | Unchanged semantics ([`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) M12) |
| **Tying flag to `world_cup_class` template** | Template and flag are **separate** product knobs |
| **Ad-hoc SQL backfill** | Forbidden — repair = full L3 re-import + L5 `prove` |

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WC1** | **One meaning** | `is_world_cup` = member of the **World Cup product canon** (yearly WC events). Not format shape (`has_league` / `has_cup`). Not Access `is_cup`. |
| **WC2** | **L3 ground column** | `tournaments.is_world_cup TINYINT(1) NOT NULL DEFAULT 0` — witness metadata, same table as `is_cup` and format flags. |
| **WC3** | **Denormalize on snapshots** | Copy `is_world_cup` onto participation / `amiga_player_event_snapshots` grain alongside `has_league` / `has_cup` so TT, slice, and honours paths do not special-case WC as “name-only”. |
| **WC4** | **Detection rule (import)** | Canonical `tournaments.name` matches **`^World Cup\s+\S`** (case-insensitive) — same as today’s `amiga_tournament_is_world_cup_by_name()` / `is_world_cup_tournament()`. |
| **WC5** | **Import timing** | Set `is_world_cup` **after** all catalog transforms (name merge, `WORLD_CUP_VENUES`, splits, `import_corrections`) — last convenience field on persist. |
| **WC6** | **No overrides** | No per-tournament exceptions; no `import_manifest` transforms section. If regex + canonical names are wrong, fix corrections — not the flag. |
| **WC7** | **Repair contract** | Rule or column change → **`import-witness` + `replay` + `prove`** on `ko2amiga_db`. No manual `UPDATE tournaments SET …`. |
| **WC8** | **Read authority** | Hot paths use **`is_world_cup` column** (or denormalized copy). Name regex remains **import + validation** only — retire from SQL `REGEXP` and duplicate PHP/Python read checks over time. |
| **WC9** | **No index (v1)** | Do not add `KEY (is_world_cup)` unless a future `EXPLAIN` audit proves need. |
| **WC10** | **Live ops: separate from template** | `format_template_id` (e.g. `kitchen_marathon`, `world_cup_class`) does **not** imply `is_world_cup`. Organizer uses an explicit **checkbox**. |
| **WC11** | **Live ops: bidirectional create rule** | At tournament **create**, `is_world_cup = 1` **if and only if** name satisfies **WC4**. Cannot submit a World Cup–shaped name without the box checked; cannot check the box without a matching name. |
| **WC12** | **Live ops: post-create edits** | v1: validate **WC11** on any save that changes `name` or `is_world_cup` while tournament is still ops-editable (`draft` / `registration` / `ready` / `running`). After finalize, catalog row is historical — flag frozen like other ground fields on imported events. |
| **WC13** | **Supersedes read-path regex policies** | Replaces name-regex-as-detection in **WCH13**, **V2P12**, honours **M1** wording, WC stats plan “detection” lines, and similar — **stored flag** is the read contract; regex is derivation/validation only. |
| **WC14** | **Community ground** | Column is part of **L3 witness** on `tournaments`. It exists when L3 is published — not a separate artefact or pre-L3 export. |

---

## 4. Relationship to existing columns

| Column | Relationship to `is_world_cup` |
|--------|--------------------------------|
| `is_cup` | Access import artifact — **orthogonal** (M12). |
| `has_cup` | Knockout/cup **phase shape** — a WC may be `has_league=1` and `has_cup=1` after structure materialization. |
| `has_league` | League **phase shape** — independent of WC membership. |
| `format_template_id` | UI/builder template — **does not set** `is_world_cup` (WC10). |

**Deferred-structure World Cups** (23 tier-B IDs in structure register) remain **`is_world_cup=1`** when canonical name matches — structure deferral does not affect catalog membership.

---

## 5. Import pipeline (L3)

Order within `persist_witness_to_mysql` / equivalent:

1. Existing catalog transforms (merges, `WORLD_CUP_VENUES`, splits, corrections).
2. Insert/update `tournaments` row with all other ground fields.
3. Set `is_world_cup` from **WC4** applied to final `name` (or compute in INSERT values in the same statement).

**Verify gate (`prove`):** for every tournament with `source_id IS NOT NULL` (imported), assert `is_world_cup == is_world_cup_tournament(name)`.

---

## 6. Live ops (browser / CLI create)

| Surface | Rule |
|---------|------|
| **Organizer create** (`fixtures.php` or future create flow) | Show **“World Cup event”** checkbox (default **off** for kitchen marathon and generic creates). |
| **Validation** | **WC11** on POST — reject with clear error if name and checkbox disagree. |
| **Template `world_cup_class`** | May pre-fill **suggested** name pattern in UI copy only — does not auto-set flag without checkbox + valid name. |
| **CLI builders** | Must pass explicit `is_world_cup` (0/1) and satisfy **WC11** when name is known at create. |

---

## 7. Writers and read surfaces (non-exhaustive)

After ship, gate on **`is_world_cup`** (tournament row or denormalized snapshot copy), not name regex:

- WC player slice totals / at-event (`slice_totals.py`, PHP slice libs)
- `amiga_world_cup_stats` finalize writer
- WC-filtered player games / realm games (`event=world-cup`)
- Tournament index filter `world-cup` / `not-world-cup`
- Honours WC increments (until fully on slice tables)
- Community stat facts WC columns
- `amiga_tournament_is_world_cup($row)` → `(int)($row['is_world_cup'] ?? 0) === 1` with column present in SELECTs

**Helper retention:** `amiga_tournament_is_world_cup_by_name()` stays for import, prove, and live create validation — not for routine reads.

---

## 8. DDL placement (implementation hint)

| Location | Change |
|----------|--------|
| `sql/ground/` | `ALTER tournaments` add `is_world_cup` after format flags (or adjacent witness metadata) |
| `sql/derived/` (snapshots / participation) | Add `is_world_cup` next to `has_league` / `has_cup` |
| Holy ops only | Per [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) G12 — no manual staging ALTER |

---

## 9. Migration / coordination

| Trigger | Register |
|---------|----------|
| New L3 column on `tournaments` | Part B when DDL ships — [`docs/UPDATE_DOCS.md`](UPDATE_DOCS.md) |
| Snapshot column | Same DDL slice |
| Read-path migration | Implementation plan slices; no Steve / online ladder coordination |

---

## 10. Revision history

| When | Summary |
|------|---------|
| **Jul 2026** | Policy locked — stored `is_world_cup`, import derivation, snapshot denorm, live checkbox ⟺ name, no index, no manifest overrides. |
| **Jul 2026** | **Shipped** — DDL `structure/010` + `derived/047`; import persist; `verify-is-world-cup` in prove; read paths + organizer create checkbox. |