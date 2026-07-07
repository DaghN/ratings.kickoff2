# Amiga running tournament boundary — policy

**Status:** **Policy locked (Jul 2026, rev. 1)** — two-universe split: **Running** (live workspace + broadcast) vs **Official** (permanent ladder record). **Implementation shipped (RTB-1–RTB-8, Jul 2026).**

**Parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (Lane B) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (L3–L5) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Related:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (fixture = one match) · [`amiga-player-create-policy.md`](amiga-player-create-policy.md) (permanent `amiga_players` at create) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) (Make official writers)

**Inventory / implementation:** [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) · [`amiga-running-tournament-boundary-implementation-plan.md`](amiga-running-tournament-boundary-implementation-plan.md)

---

## 1. Executive summary

Community tournaments in Lane B must **not** write permanent ladder ground or derived truth while they are **in progress**.

| Universe | User-facing | Data meaning |
|----------|-------------|--------------|
| **Running** | Live hub, organizer fixtures, broadcast views | Tournament workspace — scores, table, schedule **inside the event only** |
| **Official** | Historical catalog, player profiles, leaderboards, Activity aggregates | Permanent KOA ladder record — same semantics as import + prove canon |

**Make official** is the **only** boundary crossing: it promotes one running tournament into the official world (L3 ground insert + L5 derive). Until then, nothing from that tournament may appear in public ladder semantics (`amiga_games`, rated career, community headline counts, catalog index rows, etc.).

**Why (Jul 2026):** Early `amiga_games` insert on each score created inconsistent state — searchable players without profiles, orphan rules firing on unrated ground rows, global chronology reserved before commit, and confusion between broadcast and canon.

---

## 2. Vocabulary

| Term | Meaning |
|------|---------|
| **Running tournament** | `tournaments.lifecycle_status = running` (or equivalent draft→started path before official). Lives in the **running universe**. |
| **Official tournament** | Rating-finalized event in the historical canon (`rating_finalized = 1`; lifecycle typically `completed`). Lives in the **official universe**. |
| **Make official** | Organizer verb (Table tab / CLI `finalize-tournament`) that **commits** the tournament package to permanent record. |
| **Running package** | L4 structure + in-tournament results for one `tournament_id` while running — **not** `amiga_games`. |
| **Broadcast** | Read/compute paths for Live hub and organizer UI that read **only** the running universe. No writes to L3/L5. |
| **Promote** | Synonym for Make official — copy running facts into L3, then run existing finalize derive pipeline. |

---

## 3. Two universes (locked)

```text
RUNNING UNIVERSE                          OFFICIAL UNIVERSE
────────────────────────────────          ────────────────────────────────
tournaments (running)                     tournaments (completed + rating_finalized)
tournament_entrants                       amiga_games (+ fixture_id link)
tournament_stages                         amiga_game_ratings
tournament_fixtures                       amiga_tournament_standings
tournament_stage_players                  amiga_tournament_catalog_stats
running result fields on fixtures         amiga_player_current, snapshots, …
  (see §5.1)                              amiga_community_stats, realm, slices, …

Broadcast / organizer reads ──►           Public ladder reads ──►
  running package only                      official tables only
```

**Hard rule:** No row in the official universe may depend on a running tournament **except** shared identity (`amiga_players`, `tournaments` metadata row before promote).

---

## 4. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **RTB1** | **No L3 games while running** | `amiga_games` rows for a live community tournament are created **only** at Make official (batch promote), not on each score entry. |
| **RTB2** | **No L5 while running** | No `amiga_game_ratings`, `amiga_tournament_standings`, `amiga_tournament_catalog_stats`, `amiga_player_current`, community/realm/slice writes until Make official. |
| **RTB3** | **Standings = broadcast compute** | In-running table/bracket display is **computed for display** from the running package (broadcast read path). Not stored in `amiga_tournament_standings` until official. |
| **RTB4** | **Live hub = broadcast surface** | `/amiga/live-tournaments.php` and `/amiga/live-tournament.php` are the public index for running events. Historical `/amiga/tournaments.php` remains **official-only**. |
| **RTB5** | **Make official = whole tournament** | Boundary crossing is per **tournament**, not per match. Partial official is out of scope. |
| **RTB6** | **Promote then derive** | Make official (1) writes L3 ground from running package, (2) runs existing finalize derive (ratings, snapshots, standings, catalog stats, community, …). |
| **RTB7** | **Chronology at promote** | `source_scores_id`, `game_date`, and global ordering are assigned when ground rows are inserted at Make official — not when the secretary types a score. |
| **RTB8** | **Player create stays early** | [`amiga-player-create-policy.md`](amiga-player-create-policy.md) unchanged — `amiga_players` insert at create is ladder-global **identity**, not event results. |
| **RTB9** | **Orphan guard uses official games** | Orphan-delete eligibility counts **official** `amiga_games` only (after RTB1). Running scores do not block orphan cleanup. |
| **RTB10** | **Abandon = delete workspace** | Deleting/abandoning a never-official running tournament removes running package + structure only. No L3/L5 repair if nothing was promoted. |
| **RTB11** | **Import/prove unchanged** | Lane A historical path still materializes fixtures from existing `amiga_games`. RTB applies to **Lane B live ops** only. |
| **RTB12** | **Python/PHP parity** | Record-result and Make official must match between `fixtures.php` and `scripts/amiga/tournament_fixtures.py` / `finalize_tournament.py`. |

---

## 5. Running package — what may exist before Make official

### 5.1 In-tournament results (running scores)

**Locked intent:** Scores live on the **fixture** (L4), not in `amiga_games`.

Implementation will add running result columns on `tournament_fixtures` (or equivalent L4-only table — see inventory §Schema). Minimum fields per played fixture:

| Field | Purpose |
|-------|---------|
| `goals_a`, `goals_b` | Result |
| `extra` | ET / pens note (nullable) |
| `result_recorded_at` | Optional display ordering within tournament (global `game_date` still assigned at promote) |
| `status = played` | Already exists |

Undo/edit before Make official mutates **fixture running fields only**.

### 5.2 Allowlist — may write while running

| Asset | Layer | Notes |
|-------|-------|-------|
| `tournaments` row | L4 meta | create, start, running lifecycle |
| `tournament_entrants`, stages, fixtures, stage_players | L4 | structure + schedule |
| Running result fields | L4 | §5.1 |
| `amiga_players` | L3 identity | player create policy only |
| Organizer session / flash | — | UX |

### 5.3 Denylist — must not exist until Make official

| Asset | Layer |
|-------|-------|
| `amiga_games` (for this tournament) | L3 |
| `amiga_game_ratings` | L5 |
| `amiga_tournament_standings` | L5 |
| `amiga_tournament_catalog_stats` | L5 |
| `amiga_player_event_snapshots`, `amiga_player_current` | L5 |
| `amiga_community_stats`, `amiga_realm_snapshots`, slices, WC stats, … | L5 |
| Public profile / LB / Activity eligibility | read |

---

## 6. Make official — boundary contract

### 6.1 Preconditions

- Tournament is **running** (or equivalent: result entry allowed, not already `rating_finalized`).
- Running package complete enough to promote (policy: all scheduled fixtures played, or product rule documented in organizer UX — implementation detail).
- Entrants valid; no fixture without both players unless void.

### 6.2 Promote step (new — L3 insert)

For each **played** fixture in the running package, insert one `amiga_games` row:

- `player_a_id`, `player_b_id`, `goals_a`, `goals_b`, `extra`, `phase` from fixture/stage
- `tournament_id`, `fixture_id`
- `source_scores_id`, `game_date` — **allocated in promote transaction** (append to global canon chronology)

**Idempotent guard:** refuse Make official if any `amiga_games` already exist for this `tournament_id` (unless explicit repair verb).

### 6.3 Derive step (existing finalize)

Run existing finalize pipeline on the new ground rows — unchanged semantics:

- `amiga_game_ratings` per game
- Event snapshots + `amiga_player_current`
- `amiga_tournament_standings`, `amiga_tournament_catalog_stats`
- Participation, matchups, community, realm, slices, WC hooks as today
- `tournaments.rating_finalized = 1`, lifecycle → completed

Finalize **must not** read running fixture score columns after promote — it reads **`amiga_games`** only (same as today post-promote).

### 6.4 Post-official

- Tournament appears in historical catalog and games hub (rated joins).
- Players with games gain profile / career / aggregate eligibility.
- Running broadcast still may show event until lifecycle cleanup (optional); historical pages are canonical.

---

## 7. Broadcast (running reads)

Broadcast paths **read the running package only** and may **compute** standings/bracket in PHP or JS for display.

| Surface | Today (wrong) | Target |
|---------|---------------|--------|
| Live index | Mostly fixtures ✓ | Unchanged |
| Live detail scores | `LEFT JOIN amiga_games` | Fixture running columns |
| Organizer Results tab | Inserts `amiga_games` | Write fixture running columns |
| Organizer Table tab | Reads `amiga_tournament_standings` | Compute from running package |
| Player links in live view | Profile links | **Deferred** — UI polish; not policy blocker |

**No broadcast write** may insert into official tables (§5.3).

---

## 8. Retired behaviour (current code — to remove)

Documented in [`amiga-data-contract.md`](amiga-data-contract.md) today:

- `record-result` → immediate `INSERT amiga_games` + live `amiga_tournament_standings` rebuild + `amiga_tournament_catalog_stats` refresh

This behaviour is **non-compliant** with RTB1–RTB3 and will be removed when implementation ships.

---

## 9. Out of scope (v1)

| Item | Notes |
|------|-------|
| Pre-debut public profile for running entrants | UI deferred |
| Per-match Make official | Whole tournament only (RTB5) |
| Lane A import/materialize changes | RTB11 |
| Anchored repair / truncate-after-N | Separate live-ops platform work |
| Staging ground pack pull | Unaffected |

---

## 10. Success criteria

1. Secretary can enter all results, see live table on Live hub / organizer — **zero** `amiga_games` rows for that `tournament_id`.
2. Make official creates ground rows + full derive; prove-style verify passes for promoted tournament.
3. New live-created player: searchable before official; **profile and Activity counts unchanged** until Make official gives them rated games.
4. Abandon running tournament: no orphan L3 games; workspace delete sufficient.
5. Python CLI `fixtures record-result` + PHP browser path parity.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | **Rev. 1 locked** — two-universe split; promote at Make official; broadcast vs official tables. |
| 2026-07-07 | **Inventory rev. 2** — audit fold-in (lifecycle gates, verify oracles, CLI readers). |