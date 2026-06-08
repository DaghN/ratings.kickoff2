# Amiga schema discovery (Phase A0)

**Status:** Phase **A0** inventory complete · Phase **A1** import + Elo replay shipped (Jun 2026).  
**Source:** `data/amiga/source/koatd.mdb` (Microsoft Access, Jet `.mdb`, ~5.6 MB).  
**Machine output:** `data/amiga/exports/schema_inventory.json` (regenerate with `python scripts/amiga/discover_access_schema.py`).

---

## Executive summary

`koatd.mdb` is a **tournament-centric offline ladder database** for the Amiga 500 KO2 scene. The canonical game history is **`Scores`** (27,408 finished matches). There is **no per-game datetime** — chronology is inferred from **`Tournament players.Date`**, **`Chrono`**, and global **`Scores.ID`**.

Player identity is **name-based** (`Team A` / `Team B`, 477 distinct names). Precomputed ladder state lives in **`Rankings`** (monthly rating grid) and **`added_players`** (rich career aggregates). **`Tables`** and **23× `World Cup * Tables`** (+ a few other named cup tables) store **per-tournament group standings**, not individual match rows.

This maps cleanly to our planned Amiga MySQL layout: **`Scores` → ground-truth games**, **`Tournament players` → tournaments catalog**, everything else either **imported as Amiga-only metadata** or **rebuilt by replay** (Elo, W/D/L, leaderboards).

---

## Inventory at a glance

| Table | Rows | Role |
|-------|-----:|------|
| **`Scores`** | 27,408 | **Canonical match results** |
| **`Tables`** | 4,359 | Player standings per tournament (G/W/D/L, points) |
| **`Tournament players`** | 604 | **Tournament catalog** (name, date, country, chrono order) |
| **`Rankings`** | 465 | Players + monthly rating snapshots (`RMMYY` columns) |
| **`added_players`** | 465 | Derived career stats (medals, rank points, extremes, …) |
| **`World Cup I` … `XXIII Tables`** | 47–91 each | Per–World Cup group/bracket standings |
| **`Milan Tables`**, **`Norw Champs Tables`**, etc. | 8–16 | Other named event standings |
| **`Countries`** | 21 | Country name list |
| **`added_misc`** | 1 | Wide scratch row (`s00`…`s45`) — ignore for v1 |
| **`Paste Errors`**, **`Scores$_ImportErrors`** | 2–10 | Import artefacts — ignore |

**Totals:** 38 user tables · 27,408 games · 477 players · 604 dated tournaments (2001-11-03 → 2025-11-01).

---

## Core table: `Scores` (games)

| Column | Type | Notes |
|--------|------|--------|
| `ID` | COUNTER | Surrogate PK; **not contiguous** (max 28,369, **961 gaps** — deleted rows) |
| `Team A` | VARCHAR(50) | Home-side player name |
| `Team B` | VARCHAR(50) | Away-side player name |
| `A` | SMALLINT | Goals for Team A |
| `B` | SMALLINT | Goals for Team B |
| `Tournament` | VARCHAR(50) | FK-ish string → `Tournament players.Tournament` |
| `Phase` | VARCHAR(50) | Bracket phase label; **NULL on 16,786 rows (~61%)** |
| `Extra` | VARCHAR(50) | Rare metadata; mostly NULL |

**Quality:** no NULL goals; **2,694 draws** (`A = B`).

**Tournament linkage:** 27,327 rows join to `Tournament players` on `Tournament` name; **81 rows** use tournament names absent from the catalog (mostly Milan X sub-events split into separate pseudo-tournaments — see § Data quirks).

**Phase examples (when set):** `Round 1 - Group A`, `Quarter Finals`, `Semi Finals`, `Final`, `Silver Cup - Group G`, etc.

### Proposed mapping → online vocabulary (`ratedresults`)

| Access | MySQL (Amiga realm) | Notes |
|--------|---------------------|--------|
| `ID` | `source_id` or preserve as `id` | Keep original for traceability |
| `Team A` / `Team B` | `NameA` / `NameB` + `idA` / `idB` | Resolve names → `playertable` at import |
| `A` / `B` | `GoalsA` / `GoalsB` | Direct |
| *(none)* | `Date` | **Synthetic** — see § Chronology |
| `Tournament` | FK → `tournaments` table | Don't leave as free text in v2 |
| `Phase` | `phase` on game or tournament stage | Amiga-only column |
| `Extra` | nullable metadata | Low priority |

Derived Elo columns (`RatingA`, `NewRatingA`, …) are **not stored** in Access — compute via replay, same as online.

---

## Core table: `Tournament players` (events)

| Column | Type | Notes |
|--------|------|--------|
| `ID` | COUNTER | Event id |
| `Tournament` | VARCHAR(50) | Display name (unique in practice) |
| `Players` | SMALLINT | Entrant count |
| `Chrono` | REAL | Global chronological sort key (1.0 = World Cup I, …) |
| `Date` | DATETIME | Event date (day precision) |
| `Cup?` | BIT | World Cup / major cup flag |
| `Country` | VARCHAR(50) | Host country label (`WC` for all World Cups in Access — import maps to real nations; otherwise `England`, `Spain`, …) |
| `EqualTeams` | BIT | Format flag |

**604 events** from World Cups, city kitchens, national opens, etc.

### Proposed MySQL table: `tournaments`

Import this catalog as first-class ground truth. Website can list events, filter World Cups, show date/location.

---

## `Tables` (per-player tournament standings)

| Column | Meaning |
|--------|---------|
| `K` | Row id |
| `P` | Position in tournament |
| `Player` | Name |
| `G`, `W`, `D`, `L` | Games / wins / draws / losses |
| `GS`, `GC` | Goals scored / conceded |
| `Pts` | Standing points |
| `Tournament` | Event name |
| `Chrono` | Copy of event chrono |

**4,359 rows** — summary standings **derived from results**, not a second game log. For v1 import: **optional** (nice for tournament pages); ladder replay does not need this table.

---

## Named cup tables (`World Cup * Tables`, …)

Each is an **11–12 column group table** for one event, e.g. `World Cup I Tables`:

`K`, `P`, `Player`, `G`, `W`, `D`, `L`, `GS`, `GC`, `Pts`, `Group` (sometimes `Round`).

These are **presentation snapshots** for specific World Cups / Milan / Norwegian Champs. **Do not import 30+ duplicate table schemas** into MySQL — normalize into one `tournament_standings` table with `(tournament_id, group_name, …)` or derive from `Scores` when needed.

---

## Player tables

### `Rankings` (465 rows, 38 logical + 240 rating columns)

| Column | Notes |
|--------|--------|
| `R` | Rank order |
| `Player` | Name |
| `Country` | Text |
| `FirstPlayed` | Encoded period `RMMYY` (e.g. `R0503` = May 2003) |
| `Activity` | Activity score (not game count) |
| `Address` | Free text |
| `R0102` … `R1221` | **Monthly rating snapshots** — `R{month}{2-digit-year}`; `-1` = no rating |

The grid runs **Jan 2002 (`R0102`) through Dec 2021 (`R1221`)** (column naming confirmed on samples). This is the **legacy Amiga ladder rating history** — useful for **parity checks** against our Elo replay, not necessarily as source of truth.

### `added_players` (465 rows, 38 named + many `opponent*` array columns)

Key fields for website v1:

| Field | Example / range |
|-------|-----------------|
| `name`, `country` | Identity |
| `won`, `drawn`, `lost`, `gfor`, `gagainst` | Career totals |
| `rankpos`, `rankpoints` | Ladder position; points **−1 … 3664** (not Elo-scale) |
| `goldmedals`, `silvermedals`, `bronzemedals` | WC medals |
| `biggestwin`, `biggestdefeat` | Extremes |
| `lasttournament`, `activityrating` | Recency / activity |

Many trailing columns (`easy1`, `opponentno`, …) are **precomputed fun stats** — treat as **reference**, rebuild what we need from `Scores` for consistency.

**Player count alignment:** 477 names in `Scores`, 465 in `Rankings` / `added_players` — **~12 players appear only in games** (create-from-results at import).

---

## Chronology (critical for Elo replay)

Access stores **no game timestamp**. **Import sort key** (see [`amiga-data-contract.md`](amiga-data-contract.md) § Chronology):

1. **`Tournament players.Date`** ASC (calendar-first)
2. **`Tournament players.Chrono`** ASC (same-day tie-break)
3. **`Scores.ID`** ASC within the same tournament

**Synthetic `Date` for each game row:**

- Parent tournament `Date` at UTC midnight + running seconds **per calendar day** (continuous across tournaments on the same day).

**Read path:** `ORDER BY game_date ASC, id ASC` — order is materialized at import; replay/API/ops read it back.

Global `Scores.ID` alone is **mostly** chronological but **not sufficient** (tournaments overlap; IDs span 20+ years).

> **Supersedes Phase A1:** chrono-first import order and per-tournament midnight reset — see [`amiga-chronology-fix-plan.md`](amiga-chronology-fix-plan.md).

---

## Data quirks (fix at import)

| Issue | Detail | Mitigation |
|-------|--------|------------|
| Tournament name mismatches | 8 `Scores` tournament strings not in `Tournament players` (Milan X sub-stages) | Alias map or merge into parent event |
| `Rodenbach II` | In catalog, zero `Scores` rows | **10 supplemental games** at import from KO Gathering forum (complete 5-player round-robin, 2012-08-12); see `import_corrections.py` |
| Missing `Scores.ID`s | 961 gaps | Ignore; use present rows only |
| NULL `Phase` | 61% of games | Expected for informal/local events; store NULL |
| `rankpoints` scale | Not online Elo | **Recompute** Elo rating via shared Elo engine; optionally compare to `Rankings` grid |

---

## Recommended MySQL layout (Amiga DB — draft)

Separate database (e.g. `ko2amiga_db`), ground vs derived split:

**Ground truth (import from Access)**

| Table | Source |
|-------|--------|
| `tournaments` | `Tournament players` |
| `ratedresults` | `Scores` (+ synthetic `Date`, resolved player ids) |
| `playertable` | Seed names from `Scores`; optional seed country from `Rankings` |
| `tournament_name_aliases` | Manual fixes for Milan X etc. |

**Derived (replay / ops — same vocabulary as online)**

| Table | Notes |
|-------|--------|
| `ratedresults` Elo columns | PHP/Python replay |
| `playertable` career cols | Replay |
| `generalstatstable` | Batch rebuild |
| Period / milestones / leagues | **Subset later** — not v1 |

**Do not import verbatim:** 30+ per-cup Access tables, `added_misc`, import error tables, `Rankings` monthly grid (unless parity tooling needs it — store in `exports/` only).

---

## Phase A1 decisions (locked Jun 2026)

| Topic | Decision |
|-------|----------|
| **Rating authority** | Full Elo replay from `Scores` only — never show legacy Access `Rankings` |
| **Synthetic dates** | ~~Tournament `event_date` + 1 second per game within event~~ → **superseded:** calendar-day continuous counter; reads `game_date ASC, id ASC` |
| **Tournament sub-events** | Milan X fragments merged to parent catalog row; `phase` column holds stage |
| **Starting rating / K** | 1600 / K=32 (online sandbox constants) |
| **Player names** | Faithful display; merge spacing/case duplicates at import (`player_names.py` → 473 players) |
| **Website v1** | Leaderboard + profile v0 shipped; game list + tournament index deferred |

**Implemented:** `scripts/amiga/` (`import_access.py`, `python -m scripts.amiga run`), `ko2amiga_db`, `/amiga/rating.php`, profile, games. **Staging live** — [`amiga-staging-handoff.md`](amiga-staging-handoff.md).

**Next (Track B):** schema split (A2) per [`amiga-data-contract.md`](amiga-data-contract.md), then tournament standings engine + reference parity.

---

## Commands

```powershell
# Regenerate machine inventory
python scripts/amiga/discover_access_schema.py

# Output
#   data/amiga/exports/schema_inventory.json
```

See also: [`scripts/amiga/README.md`](../scripts/amiga/README.md), [`data/amiga/README.md`](../data/amiga/README.md).
