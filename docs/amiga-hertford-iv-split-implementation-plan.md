# Hertford IV split — implementation plan

**Status:** Shipped (Jul 2026) on `ko2amiga_work`. Staging export pending Dagh.
**DB:** `ko2amiga_work` only until staging export.  
**Starter prompt:** [`docs/orchestration/agent-handoffs/amiga-hertford-iv-split-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-hertford-iv-split-STARTER-PROMPT.md)

**Authority:** Forum [t=12376](https://ko-gathering.com/forum/viewtopic.php?t=12376) · [`amiga-tournament-structure-manual-materialize-runbook.md`](amiga-tournament-structure-manual-materialize-runbook.md) · [`amiga-import-layer.md`](amiga-import-layer.md) § catalog splits · precedent: Gloucester III / **605**, Groningen VII / **604**, Hertford V / **192** (separate Access row — different pattern).

---

## Problem

Access has **one** `[Tournament players]` row and **one** Scores label for **Hertford IV**, but the forum documents **two competitions** the same evening (2006-07-21):

| Competition | Games | Format |
|-------------|------:|--------|
| **League** | 24 | 4× round-robin |
| **Cup** | 4 | Random-draw semis + 3rd + final |

All **28** games currently sit on catalog id **187** (`has_league=1`, `has_cup=0`). Combined standings pollute league points (Wayne/Darren). **Do not** `materialize --force` as a single 28-game league.

**Work DB today (pre-surgery):** id **187** — 28 games, **0** stages, **0** fixture links, all `phase` NULL. Not yet materialized.

---

## Target end state

| Id | Name | Games | Format | Flags |
|----|------|------:|--------|-------|
| **187** (existing) | Hertford IV | 24 | 4× RR league | `has_league=1`, `has_cup=0` |
| **606** (new, append) | Hertford IV Cup | 4 | 4p KO | `has_league=0`, `has_cup=1` |

### Game partition (`source_scores_id` = Access Scores.ID)

| Stay on **187** | Move to **606** |
|-----------------|-----------------|
| **7555–7578** (24 league games; `amiga_games` **7548–7571**) | **7579, 7580, 7581, 7582** (`amiga_games` **7572–7575**) |

### Cup bracket (forum-confirmed scores match DB)

| ssid | Result |
|------|--------|
| 7579 | Wayne L 2–3 Darren G (semi) |
| 7580 | Haydn H 5–1 Mark B (semi) |
| 7581 | Wayne L 4–2 Mark B (3rd place) |
| 7582 | Haydn H 2–1 Darren G (final) |

### Expected finishes (3-1-0 league / KO honours)

| Event | Finish |
|-------|--------|
| **187 league** | 1 Haydn H · 2 Wayne L · 3 Mark B · 4 Darren G (25 / 18 / 16 / 12 pts) |
| **606 cup** | 1 Haydn H · 2 Darren G · 3 Wayne L · 4 Mark B |

Wayne won the **league and cup double** per forum recap.

---

## In scope

- Work DB ground surgery (insert **606**, reparent 4 cup games, metadata)
- Full **simul** on work (L5 replay; **not** `--apply-structure`)
- Structure materialize **187** (league) + **606** (pure knockout)
- L2→L3 import: `IMPORT_CATALOG_SPLITS` + **new** same-label game partition hook
- Registers + human inventory (`disposition_register.json`, `amiga-l3-legacy-fixes-inventory.md`, `amiga-import-layer.md`, review queue, tests)
- `UPDATE_DOCS` Part A on ship

## Out of scope

- Staging export / WinSCP (unless Dagh asks in same session)
- Score corrections (forum scores already match DB)
- Re-materializing unrelated tail tournaments
- Big-bang catalog re-order

---

## Simul safety (materialize on other events)

Plain `python -m scripts.amiga simul` **does not** wipe L4 structure when `tournament_fixtures` is non-empty (~18k fixtures on work). It clears **L5 derived** (ratings, standings, snapshots) and resets `rating_finalized`. **Avoid** `--apply-structure` and `--recreate-schema`.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Pre-flight SQL + transactional ground surgery | Counts: 187→24g, 606→4g; ssid ranges correct |
| **1** | `python -m scripts.amiga simul` | League pts 25/18/16/12; both events `rating_finalized=1` |
| **2** | Materialize 187 + 606 + post-materialize ritual | Stages/fixtures/links; browser games + standings |
| **3** | Import corrections + unit tests | `test_import_corrections.py` green; partition routes ssid 7579–7582 |
| **4** | Docs + registers + `UPDATE_DOCS` Part A | `verify-disposition-register`; inventory §3 row |

**One slice per session** unless Dagh says “do it all.” No git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>" unless asked.

---

## Slice 0 — Work DB ground surgery

**Goal:** Correct L3 ground partition before derived replay.

### Pre-flight

```sql
SELECT id, name, source_id, event_date, chrono, has_league, has_cup, player_count,
       lifecycle_status, rating_finalized, country, equal_teams
FROM tournaments WHERE id = 187;

SELECT tournament_id, COUNT(*) games, MIN(source_scores_id) min_ss, MAX(source_scores_id) max_ss
FROM amiga_games WHERE tournament_id = 187 GROUP BY tournament_id;

SELECT id, source_scores_id, phase, goals_a, goals_b
FROM amiga_games WHERE source_scores_id IN (7579, 7580, 7581, 7582);

SELECT COUNT(*) FROM tournaments WHERE id = 606 OR name = 'Hertford IV Cup';
```

Expect: 28 games on 187; 606 absent.

### Surgery (single transaction)

**1. Insert child 606** (append; mirror **605** / **192** family):

| Column | Value |
|--------|-------|
| `id` | **606** (explicit; next after 605) |
| `name` | `Hertford IV Cup` |
| `source_id` | `900000003` |
| `event_date` | `2006-07-21` |
| `chrono` | `154.5` (parent `154` + `0.5`) |
| `country` | `England` |
| `player_count` | `4` |
| `equal_teams` | `1` |
| `has_league` | `0` |
| `has_cup` | `1` |
| `lifecycle_status` | `completed` |
| `rating_finalized` | `0` |

Copy any other safe defaults from parent 187 where columns exist and matter.

**2. Reparent cup games:**

```sql
UPDATE amiga_games
SET tournament_id = 606
WHERE tournament_id = 187
  AND source_scores_id IN (7579, 7580, 7581, 7582);
```

**3. Optional witness polish** on cup games:

```sql
UPDATE amiga_games SET phase = 'Semi Finals'      WHERE source_scores_id = 7579;
UPDATE amiga_games SET phase = 'Semi Finals'      WHERE source_scores_id = 7580;
UPDATE amiga_games SET phase = '3rd Place Final'  WHERE source_scores_id = 7581;
UPDATE amiga_games SET phase = 'Final'            WHERE source_scores_id = 7582;
```

**4. Parent metadata:**

```sql
UPDATE tournaments
SET has_league = 1, has_cup = 0, rating_finalized = 0, rating_finalized_at = NULL
WHERE id = 187;
```

### Post-surgery asserts

```sql
SELECT id, name, (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) games
FROM tournaments t WHERE id IN (187, 606);

SELECT tournament_id, source_scores_id FROM amiga_games
WHERE source_scores_id BETWEEN 7555 AND 7582 ORDER BY source_scores_id;
```

**Do not:** change `amiga_games.id`, change scores, insert 606 in the middle of the catalog.

---

## Slice 1 — Simul (L5 only)

```powershell
cd <repo>
python -m scripts.amiga simul
```

**Flags to avoid:** `--apply-structure`, `--recreate-schema`

### Verify

```sql
SELECT player_id, points, position
FROM amiga_tournament_standings
WHERE tournament_id = 187 AND scope_type = 'league'
ORDER BY position;

SELECT id, rating_finalized FROM tournaments WHERE id IN (187, 606);
```

Spot-check career goal totals unchanged in kind (Type B partition only — no score edits).

---

## Slice 2 — Structure materialize

Follow [`amiga-tournament-structure-manual-materialize-runbook.md`](amiga-tournament-structure-manual-materialize-runbook.md).

### 187 — league

```powershell
python -m scripts.amiga tournament-structure materialize --tournament-id 187 --dry-run
python -m scripts.amiga tournament-structure materialize --tournament-id 187 --replace
```

Rename stage `overall` → **League** if needed:

```sql
UPDATE tournament_stages SET name = 'League'
WHERE tournament_id = 187 AND stage_key = 'overall';
```

Expect: 1 stage, 24 fixtures, 24 `fixture_id` links.

### 606 — cup

```powershell
python -m scripts.amiga tournament-structure materialize-pure-knockout --tournament-id 606 --dry-run
python -m scripts.amiga tournament-structure materialize-pure-knockout --tournament-id 606 --replace
```

Expect: 4 fixtures linked; KO `phase_label` NULL on fixtures if honours scope needs it (Milan XII pattern).

### Post-materialize ritual (both ids)

```powershell
python -m scripts.amiga backfill-standings-stage-id --tournament-id 187
python -m scripts.amiga backfill-standings-stage-id --tournament-id 606
python -m scripts.amiga verify-standings-stage-id --tournament-id 187
python -m scripts.amiga verify-standings-stage-id --tournament-id 606
python -m scripts.amiga refresh-event-finish-snapshots --tournament-id 187
python -m scripts.amiga refresh-event-finish-snapshots --tournament-id 606
```

Browser: `http://ratingskickoff.test/amiga/tournament/games.php?id=187` and `…id=606`, standings tabs.

Promote disposition before materialize if `pending_review` blocks tooling expectations.

---

## Slice 3 — L2→L3 import corrections

Hertford is a **new split variant**: same Scores label; partition by `source_scores_id`. Gloucester/Groningen used a **second Scores label**.

### D1. `IMPORT_CATALOG_SPLITS` (`import_corrections.py`)

```python
CatalogSplit(
    name="Hertford IV Cup",
    parent_name="Hertford IV",
    source_id=900_000_003,
    chrono_offset=0.5,
    is_cup=True,
    player_count=4,
)
```

Add `CATALOG_SPLIT_RATIONALE["Hertford IV Cup"]` — forum double, same Access label, ssid partition.

### D2. New game-level partition map

Add e.g. `SCORE_TOURNAMENT_PARTITION` in `import_corrections.py`:

```python
"Hertford IV": {
    7579: "Hertford IV Cup",
    7580: "Hertford IV Cup",
    7581: "Hertford IV Cup",
    7582: "Hertford IV Cup",
},
```

Expose resolver helper; wire in `import_access.py` game loop (~line 507): after `resolve_tournament_name`, if parent matches and `source_scores_id` in map → route to child catalog name for `tournament_id`.

`apply_catalog_split_format_overrides` should force parent league-only / child cup-only.

### D3. Tests (`test_import_corrections.py`)

- Split appends child with `source_id` 900_000_003 and chrono 154.5
- Partition: 7579–7582 → child; 7555–7578 → parent
- Format flags: parent `has_cup=0`, child `has_league=0`

### D4. Manifest

`catalog_splits` entry via `catalog_splits_manifest()` (import_manifest.json on next import run).

**No `SCORE_CORRECTIONS` needed.**

---

## Slice 4 — Registers and docs

| Artifact | Change |
|----------|--------|
| `disposition_register.json` | **187** → `pure_rr` + split note; add **606** → `pure_knockout` |
| `docs/amiga-l3-legacy-fixes-inventory.md` §3 | New row — **same-label game partition** (distinct from Gloucester separate label) |
| `docs/amiga-import-layer.md` | Catalog splits table + short § game-level partition |
| `docs/amiga-tournament-structure-review-queue.md` | 187 deferred → shipped log line |
| `PROJECT_MEMORY.md` | Session one-liner |
| `docs/UPDATE_DOCS.md` Part A | On ship |

Fix wrong ssids in current disposition note (`7567`, `7563` → `7580`, `7581`).

```powershell
python -m scripts.amiga tournament-structure verify-disposition-register
python -m scripts.amiga tournament-structure audit-review-register
```

---

## Risks

| Risk | Mitigation |
|------|------------|
| Accidental `simul --apply-structure` | Plain `simul` only |
| Re-import merges cup back onto 187 | Partition hook mandatory |
| `materialize` refuses 187 | Promote disposition; id not in tier-B frozensets |
| NULL-phase 4× RR edge case | Forum sign-off + `--force` only if dry-run refuses |
| Wrong game ids in register/docs | Always key on `source_scores_id`, not `amiga_games.id` |

---

## Environment

| Tool | Path |
|------|------|
| MySQL | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_work` |
| PHP | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Simul | `python -m scripts.amiga simul` |
| Local site | `http://ratingskickoff.test/amiga/…` |

---

## Closure checklist

- [x] 187: 24 league games only; league finish 1–4 correct
- [x] 606: 4 cup games; cup finish 1–4 correct
- [x] Both materialized with stages + fixture links
- [x] Import partition + tests green
- [x] Inventory §3 + import-layer + disposition updated
- [x] `UPDATE_DOCS` Part A recorded