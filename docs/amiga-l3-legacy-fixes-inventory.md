# Amiga L2→L3 legacy fixes inventory

**Purpose:** Human-readable inventory of every ad-hoc correction applied to legacy **koatd** data during **L2 → L3 witness import** (`import-witness` / `prepare_witness_from_l2`). These are fixes for wrong, missing, or fragmented Access facts — not systematic pipeline mechanics (pruning, replay, derived writers).

**Authority:** Code in `scripts/amiga/import_corrections.py`, `scripts/amiga/tournament_names.py`, `scripts/amiga/player_names.py`, and `data/amiga/country_registry.json`. Each import writes an audit trail to `data/amiga/exports/import_manifest.json`.

**Related:** [`amiga-import-layer.md`](amiga-import-layer.md) (pipeline + agent policy) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) G5/G9 · [`scripts/amiga/README.md`](../scripts/amiga/README.md)

**Last verified against:** day-0 seal manifest (`data/amiga/day0/manifests/import_manifest.json`, Jul 2026) — 605 tournaments, 27,418 games, 469 canonical players.

---

## What this doc covers

| Included | Excluded |
|----------|----------|
| Tournament name/date/country fixes | L2 table pruning |
| World Cup host city + nation | Calendar-first game ordering / synthetic `game_date` |
| Catalog splits for Scores-only labels | Format-flag inference (`has_league` / `has_cup`) |
| Supplemental games missing from Scores | L4 structure / disposition overlays |
| **Scores row corrections** (regulation / ET / pens) | L5 replay / derived recompute |
| Player name merges (automatic + manual) | Tier E finish overrides (table exists; **0 rows** on day 0) |
| Player nationality overrides | Editing `koatd.mdb` in place |
| Country token alias normalizations | |

---

## 1. Tournament catalog — wrong names or dates

| Tournament | Access value | Canonical import | Why |
|------------|--------------|------------------|-----|
| **World Cup 2015** | Name `World Cup 2015` | **World Cup XV (Dublin)** | Chrono 548 sits between XIV and XVI; Access reference groups already say "World Cup XV Tables"; Scores used a year label instead of Roman numeral |
| **World Cup VIII** | Date `2008-09-08` | **2008-11-09** | Chrono 325 is between Newent XIV (Nov 3) and Helsingborg (Nov 14); real event was 9 Nov 2008 |
| **Wiesbaden IX** | Date `2009-04-07` | **2009-01-25** | Chrono 333 is before Wiesbaden X (Feb 22) and Newent XVI (Feb 13); April date breaks Roman IX-before-X order. Source: [KO Gathering forum](https://ko-gathering.com/forum/viewtopic.php?p=247684#p247684) |

**Note:** Newent XVI (2009-02-13, chrono 334) needs no override — it sits correctly between corrected Wiesbaden IX and Wiesbaden X.

---

## 2. World Cups I–XXIII — host city + real country

Access `[Tournament players]` labels every World Cup with `Country = 'WC'` and a bare Roman name. Import renames each to **`World Cup N (City)`** and sets the real host nation.

| WC | City | Country |
|----|------|---------|
| I | Dartford | England |
| II | Athens | Greece |
| III | Groningen | Netherlands |
| IV | Milan | Italy |
| V | Cologne | Germany |
| VI | Rickmansworth | England |
| VII | Rome | Italy |
| VIII | Athens | Greece |
| IX | Voitsberg | Austria |
| X | Dusseldorf | Germany |
| XI | Birmingham | England |
| XII | Milan | Italy |
| XIII | Voitsberg | Austria |
| XIV | Copenhagen | Denmark |
| XV | Dublin | Ireland |
| XVI | Milan | Italy |
| XVII | Landskrona | Sweden |
| XVIII | Bournemouth | England |
| XIX | Bremen | Germany |
| XX | Athens | Greece |
| XXI | Torremolinos | Spain |
| XXII | Nottingham | England |
| XXIII | Milan | Italy |

Scores rows that still use the bare `World Cup N` string are aliased to the suffixed catalog name via `tournament_names.py`.

---

## 3. Tournament catalog splits (one Access row, two real events)

When Access has one `[Tournament players]` row but the evening had **two real competitions**, import **appends** a synthetic catalog child (reserved `source_id` ≥ 900,000,000). Never inserted in the middle of the catalog.

**Variant A — separate Scores label** (Groningen, Gloucester): second `Scores.Tournament` string routes to the child catalog name via `IMPORT_CATALOG_SPLITS`.

**Variant B — same Scores label, game partition** (Hertford IV): all games share one `Scores.Tournament` string; cup rows route by `source_scores_id` via `SCORE_TOURNAMENT_PARTITION` in `import_corrections.py`.

| New tournament | Parent | What was wrong in Access |
|----------------|--------|--------------------------|
| **Groningen VII Cup** | Groningen VII (id 48) | One catalog row for Groningen VII (2002-07-13); 14 cup games under a separate Scores label (Round 1 / Semi Final / Final). Child appends as id **604**. |
| **Gloucester III Team** | Gloucester III (id 62) | One catalog row for Gloucester III (10 players, 2002-10-12); 10 extra games under `Gloucester III Team` after the 90-game double round-robin (Scores IDs 1411–1420). Child appends as id **605**. |
| **Hertford IV Cup** | Hertford IV (id 187) | One catalog row for Hertford IV (2006-07-21); forum documents 24g 4× RR league + 4g KO cup same evening under one Scores label. Cup games partition by ssid **7579–7582**; league **7555–7578** stays on parent. Child appends as id **606**. Forum: [t=12376](https://ko-gathering.com/forum/viewtopic.php?t=12376). |

---

## 4. Missing games — supplemental Scores

| Tournament | Games added | What was wrong |
|------------|-------------|----------------|
| **Rodenbach II** | **10** | Catalog row exists (2012-08-12, 5 players, chrono 513) but **zero Scores rows**. Complete 5-player round-robin recovered from KO Gathering forum. |

**Players:** Frank F, Horst L, Joerg D, Jan K, Thorsten B — all pairwise results.

**Match results (forum order):**

| Team A | Team B | Score |
|--------|--------|-------|
| Frank F | Horst L | 5–2 |
| Joerg D | Jan K | 1–2 |
| Joerg D | Thorsten B | 0–8 |
| Jan K | Horst L | 1–2 |
| Jan K | Frank F | 2–8 |
| Horst L | Thorsten B | 1–7 |
| Horst L | Joerg D | 6–0 |
| Thorsten B | Frank F | 2–5 |
| Thorsten B | Jan K | 5–1 |
| Frank F | Joerg D | 13–0 |

Synthetic rows use reserved `source_scores_id` ≥ 500,000,000 (`IMPORT_SUPPLEMENT_SCORES_ID_BASE`).

---

## 5. Scores row corrections — wrong or missing ET / pens (Kristiansand)

Access `Scores` sometimes has wrong regulation goals or **NULL `Extra`** when forum evidence records extra time or penalties. Import patches these at L2→L3 (SC-11 structured cols on `amiga_games`) — same values previously hand-patched on `ko2amiga_work`.

| `source_scores_id` | Event | Access | Canonical import | Evidence |
|--------------------|-------|--------|------------------|----------|
| **1189** | Kristiansand semi (Aasmund F vs Glenn L) | `1–1`, Extra NULL (belongs on g1188) | Reg `0–0`; `extra` `(1-0) aet`; `goals_et_a/b` = `1`/`0` (ET period only) | [Forum p=48040](https://ko-gathering.com/forum/viewtopic.php?p=48040#p48040) |
| **1188** | Kristiansand bronze (Oskar B vs Glenn L) | `0–0`, Extra NULL (reg **1–1** was on semi g1189) | Reg **`1–1`**; `extra` `1-1, (0-0, 7-8 on pens)`; `goals_et` `0–0`; `pens_a/b` = `7`/`8` (Glenn wins bronze as player B) | Same forum thread |

**Note:** Other ET/pens games in the catalog keep Access `Extra` witness text; `backfill-match-extensions` derives structured cols at ops time unless a row lands here. Only add `SCORE_CORRECTIONS` when Access is wrong or silent and evidence is human-verified.

### 5.1 Scores row corrections — wrong player assignment (Milan I)

Access `Scores` sometimes assigns the wrong `Team A` / `Team B` while regulation goals are correct. Import patches teams at L2→L3 (`SCORE_CORRECTIONS`); same pattern as Kristiansand goal fixes.

| `source_scores_id` | `amiga_games.id` | Event | Access | Canonical import | Evidence |
|--------------------|------------------|-------|--------|------------------|----------|
| **2421** | 2349 | Milan I Group A Giornata 4 | Gianni T 7–2 **Marco C** | Gianni T 7–2 **Marco M** | [FFZ idd=175](https://web.archive.org/web/20030704044413/http://www.freeforumzone.com/viewmessaggi.aspx?f=3694&idd=175) — forum lists Sandro twice + Marco C twice in Giornata 4 (Gianni absent); 7–2 fits Marco M; Marco C keeps single 0–4 loss on g2357 |
| **2422** | 2350 | Milan I Group A Giornata 5 | **Gianni T** 0–5 Morris C | **Filippo D** 0–5 Morris C | Same FFZ thread — Giornata 5 Filippo 0–5 Morris; Gianni 0–5 implausible; restores Filippo–Morris pairing (7 gp each) |
| **15981** | 15974 | Duesseldorf V (18 Oct 2009) | Frederic B 3–2 **Cornelius H** | Frederic B 3–2 **Volker B** | [Forum t=15624](https://ko-gathering.com/forum/viewtopic.php?t=15624) — last of 18 triple-RR games; score correct, wrong Team B |

**Rationale:** Forum Giornata 4 swapped Gianni↔Sandro in the report; Access/DB already had Gianni right elsewhere but duplicated Gianni–Marco C (g2349 + g2357). Moving g2349 opponent to Marco M yields one game per pair in Group A. Standings points unchanged; L5 rebuild deferred until simul.

**Duesseldorf V:** Access stored the final 3–2 on Frederic–Cornelius instead of Frederic–Volker. Forum lists 18 games = complete 4p triple round-robin; wrong opponent caused 8/9/9/10 per-player counts and tier-C structure audit flag. Finish order unchanged (Oliver · Frederic · Volker · Cornelius); L5 rebuild after work DB patch.

---

## 6. Player identity merges

All player name fixes run in `scripts/amiga/player_names.py` during L2→L3 import. Every merge is logged in `import_manifest.json` → `transforms.name_merges` (also `data/amiga/exports/name_merges.json`).

### 6.1 Automatic rule (spacing / case / abbreviation artefacts)

Access sometimes spells the same player differently. Import groups variants that share an **identity key** after:

1. Trim + collapse whitespace (`Oliver  ST` → `Oliver ST`)
2. Strip trailing period (`Mark B.` → `Mark B`)
3. Case-insensitive match (`ST` vs `St`)

**Canonical spelling** within a group:

- Variant with the **most game rows** wins
- Tie → prefer variant that has a **country** in L2 `witness_player_identity`
- Tie on both → shorter spelling wins

**Manual spelling aliases** (`PLAYER_NAME_ALIASES` in `import_corrections.py`) run *before* grouping and **force** the canonical spelling when they apply.

### 6.2 All merge groups on current corpus (5 total)

| Canonical | Access variants | Games per variant | How decided |
|-----------|-----------------|-------------------|-------------|
| **Oliver St** | `Oliver ST`, `Oliver St` | 1, **808** | **Automatic** — case duplicate on surname; `St` wins on game count |
| **Ian K** | `Ian K`, `Ian Ka` | 251, 21 | **Manual alias** — `Ian Ka` → `Ian K` (extended surname abbreviation) |
| **Jorg D** | `Joerg D`, `Jorg D` | 20, 49 | **Manual alias** — `Joerg` → `Jorg` (umlaut `oe` variant) |
| **Jorg S** | `Joerg S`, `Jorg S` | 16, 23 | **Manual alias** — same umlaut fix |
| **Klaus Le** | `Klaus L`, `Klaus Le` | 22, **287** | **Manual alias** — `Klaus L` → `Klaus Le` (shorter abbreviation) |

**Net effect:** 474 raw player name strings in Scores → **469** canonical players (5 merge groups).

On the current koatd drop, **Oliver ST → Oliver St** is the only purely automatic spacing/case merge. The other four are alias-driven but still collapse duplicate identities the same way.

---

## 7. Player nationality — missing from L2 identity extract

L2 `witness_player_identity` had no country row (or empty). Manual override at L3 (`PLAYER_COUNTRY_OVERRIDES`):

| Player | Country | Reason |
|--------|---------|--------|
| Diego L | Italy | Missing from witness identity |
| Ingvald E | Norway | Missing from witness identity |
| Kjetil D | Norway | Missing from witness identity |
| Oyvind H | Norway | Missing from witness identity |

---

## 8. Country token fixes (Access shorthand → registry official name)

Applied via `country_registry.json` legacy aliases at import (`import_country_registry.py`). Only aliases that actually appear in the corpus fire; the registry defines rules for future hits.

| Entity | Access token | Canonical |
|--------|--------------|-----------|
| Stephen D (player) | `N. Ireland` | **Northern Ireland** |
| Dubai I (tournament) | `UAE` | **United Arab Emirates** |

Registry aliases defined: `N. Ireland` → Northern Ireland; `UAE` → United Arab Emirates.

---

## 9. Scores tournament aliases — fragmented Access labels

These fix Access storing KO stages or side brackets as **separate tournament names** instead of phases under the parent. Games merge into the parent; duplicate catalog rows are **skipped** when they exist (`scores_only_catalog_aliases()` in `tournament_names.py`).

### Milan X (6 Scores fragments → parent `Milan X`)

- Milan X 3rd Place Final
- Milan X Final
- Milan X Quarter Finals
- Milan X Round 1 Group A
- Milan X Round 1 Group B
- Milan X Semi Finals

Each fragment already has a matching `Phase` column. Import infers phase from the fragment name when Access `Phase` is null on the parent label.

### World Cup V KOA Cup → `World Cup V (Cologne)`

Access had a **separate catalog row** for the 2005 KOA Cup consolation bracket. World Cup IV already stored KOA Cup as phased games under the parent; World Cup V wrongly split it out. Scores merge into World Cup V with `KOA Cup - …` phase prefixes; the duplicate catalog row is skipped.

---

## Summary counts (day-0 import manifest)

| Category | Count |
|----------|-------|
| Catalog field overrides (name / date / country) | 49 manifest rows (mostly WC renames + 3 special fixes) |
| Catalog splits | 2 |
| Supplemental games | 10 (1 tournament) |
| Scores row corrections (ET / pens) | 2 (Kristiansand g1188–89) |
| Scores row corrections (wrong player) | 3 (Milan g2421–22; Duesseldorf V g15981) |
| Player country overrides | 4 |
| Player name merges | 5 groups (1 automatic spacing/case, 4 manual spelling aliases) |
| Country token normalizations | 2 |
| Skipped duplicate catalog rows | Milan X KO fragments + World Cup V KOA Cup (when present) |
| Tier E finish overrides | **0** |

---

## Code map

| Fix type | Module |
|----------|--------|
| Manual catalog / player / supplement / score-correction facts | `scripts/amiga/import_corrections.py` |
| Scores tournament string aliases | `scripts/amiga/tournament_names.py` |
| Player identity merges | `scripts/amiga/player_names.py` |
| Country token canonicalization | `scripts/amiga/import_country_registry.py` + `data/amiga/country_registry.json` |
| Orchestration | `scripts/amiga/import_access.py` → `_prepare_witness_core()` |
| Audit output | `scripts/amiga/import_manifest.py` → `data/amiga/exports/import_manifest.json` |

**Agent rule:** New Access quirk → prefer an automatic rule in the right module; use `import_corrections.py` only for one-off documented facts. Never patch derived tables to paper over import issues. See [`amiga-import-layer.md`](amiga-import-layer.md).