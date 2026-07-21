# Amiga running tournament boundary — policy

> **Live ops policy (Jul 2026):** Lane B running vs official — **active** on staging. Local writer regression = **`simul`** on **`ko2amiga_work`**; oracle **`prove`** was ship path only ([`amiga-running-tournament-boundary-implementation-plan.md`](amiga-running-tournament-boundary-implementation-plan.md)).

**Status:** **Policy locked (Jul 2026, rev. 2)** — two-universe split: **Running** vs **Official**. RTB-1–RTB-9 shipped Jul 2026 (organizer **Finish and make official** = promote + finalize + lifecycle `completed`).

**Parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (Lane B) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (L3–L5) · [`amiga-data-contract.md`](amiga-data-contract.md) · **Scoring contract:** [`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) (RTB14)

**Related:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (fixture = one match) · [`amiga-player-create-policy.md`](amiga-player-create-policy.md) (permanent `amiga_players` at create) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md) (Make official writers) · [`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md) (PHP Finish ↔ simul-oracle fingerprint)

**Inventory / implementation:** [`amiga-running-tournament-boundary-inventory.md`](amiga-running-tournament-boundary-inventory.md) · [`amiga-running-tournament-boundary-implementation-plan.md`](amiga-running-tournament-boundary-implementation-plan.md)

---

## 1. Executive summary

Community tournaments in Lane B must **not** write permanent ladder ground or derived truth while they are **in progress**.

| Universe | User-facing | Data meaning |
|----------|-------------|--------------|
| **Running** | Live hub, organizer fixtures, broadcast views | Tournament workspace — scores, table, schedule **inside the event only** |
| **Official** | Historical catalog, player profiles, leaderboards, Activity aggregates | Permanent KOA ladder record — same semantics as import + prove canon |

The **organizer finish action** (**Finish and make official** in the browser) is the **only** boundary crossing: it promotes one running tournament into the official world (L3 ground insert + L5 derive + lifecycle `completed`). Until then, nothing from that tournament may appear in public ladder semantics (`amiga_games`, rated career, community headline counts, catalog index rows, etc.).

**Why (Jul 2026):** Early `amiga_games` insert on each score created inconsistent state — searchable players without profiles, orphan rules firing on unrated ground rows, global chronology reserved before commit, and confusion between broadcast and canon.

---

## 2. Vocabulary

Use the right term per layer — do not put **finalize** on the primary organizer button.

### 2.1 Layered terms (locked rev. 2)

| Layer | Term | Meaning |
|-------|------|---------|
| **Organizer UI** | **Finish and make official** | Primary button label on the Table tab (Table tab / `view=table`). One human action to end the league and commit it permanently. Optional helper (same screen): *Commits all results to ratings and tournament history. Leaves Live; joins the historical catalog.* Narrow screens may use **Finish & make official**. |
| **Policy / product** | **Organizer finish action** | Plain-language name for that atomic commit in specs and handoffs. |
| **Ops / code** | **finalize**, **promote**, `rating_finalized` | Implementation only. CLI: `python -m scripts.amiga finalize-tournament` (or fixtures path that calls the same pipeline). PHP: `amiga_promote_running_tournament()` then `amiga_finalize_tournament()`. Logs, prove, and module filenames keep **finalize**. |

### 2.2 Domain terms (unchanged)

| Term | Meaning |
|------|---------|
| **Running tournament** | `tournaments.lifecycle_status = running` (after Start tournament). Lives in the **running universe**. |
| **Official tournament** | `rating_finalized = 1` **and** `lifecycle_status IN ('completed', 'archived')`. Lives in the **official universe** and on the historical catalog. |
| **Running package** | L4 structure + in-tournament results for one `tournament_id` while running — **not** `amiga_games`. |
| **Broadcast** | Read/compute paths for Live hub and organizer UI that read **only** the running universe. No writes to L3/L5. |
| **Promote** | L3 insert step inside the organizer finish action — copy played fixtures into `amiga_games`. Not organizer-facing copy. |

### 2.3 Retired organizer terms (rev. 2)

| Term | Fate |
|------|------|
| **Mark complete** | **Retired** from the happy path (Setup tab button). Was lifecycle-only (`running` → `completed`) with no rating commit. Repair / Advanced tab / CLI only. |
| **Make official** (standalone button) | **Retired** as a separate half-step. Folded into **Finish and make official**, which also sets lifecycle `completed`. Policy prose may still say “make official” as the *effect* (entering the official universe). |
| **Finalize** | **Not** primary UI copy. Ops/code/contract name for the derive pipeline step. |
| **Reprocess** | **Retired** from UI (`reprocess_tournament_derived` is an internal POST action name only). |

**Do not** use **Submit** on the finish button — there is no approval queue; the action is immediate and whole-tournament.

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
| **RTB12** | **Python/PHP parity** | Record-result and organizer finish must match between `fixtures.php` and `scripts/amiga/tournament_fixtures.py` / `finalize_tournament.py`. **Finish derive:** [`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md) (simul-oracle fingerprint; #608 signed off Jul 2026). |
| **RTB13** | **One organizer finish action** | Browser happy path = single **Finish and make official** control. On success, atomically: **promote** → **finalize** derive → `rating_finalized = 1` → `lifecycle_status = completed` + `completed_at`. No separate **Mark complete** step. |
| **RTB14** | **Scoring contract parity** | Broadcast (fixtures) and official (`amiga_games`) use the **same stage scoring contracts + standings executor** ([`amiga-format-scoring-contract-policy.md`](amiga-format-scoring-contract-policy.md) SC14). Broadcast **does not persist L5**; live hub league + KO in scope. |

---

## 5. Running package — what may exist before Make official

### 5.1 In-tournament results (running scores)

**Locked intent:** Scores live on the **fixture** (L4), not in `amiga_games`.

Implementation will add running result columns on `tournament_fixtures` (or equivalent L4-only table — see inventory §Schema). Minimum fields per played fixture:

| Field | Purpose |
|-------|---------|
| `goals_a`, `goals_b` | Result |
| `extra` | ET / pens witness (nullable) |
| `goals_et_a`, `goals_et_b`, `pens_a`, `pens_b` | Structured match extensions when parseable from `extra` (SC-11; dual-written on `record-result`) |
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

## 6. Organizer finish — boundary contract

**Organizer finish action** = **Finish and make official** (UI) = promote + finalize + lifecycle complete (ops). This section is the locked contract for RTB-9 implementation.

### 6.1 Organizer UX (browser)

| Element | Rule |
|---------|------|
| **Placement** | Table tab (`view=table`) — primary workspace when the secretary is ready to commit (all results **or** early finish). Results tab may link here; no second finish control on Setup. |
| **Button** | **Finish and make official** (or **Finish & make official** when width-constrained). |
| **Helper** | One line under the button: commits to ratings and tournament history; leaves Live hub; joins historical catalog. |
| **Finish confirm (Planned)** | Before commit, secretary reviews/edits finishing order → Tier E — [`amiga-organizer-finish-confirm-policy.md`](amiga-organizer-finish-confirm-policy.md). |
| **Retired on Setup** | **Mark complete** button and “finish lifecycle only” happy path removed. Setup keeps **Start tournament** and **Void tournament** only. |
| **Advanced / CLI** | Raw `lifecycle_status` transitions and `finalize-tournament` remain for operators; not the secretary happy path. |

### 6.2 Preconditions (gate)

All must pass; otherwise refuse with an actionable message (no partial *commit* of ratings — either full finish or refuse):

- Generated tournament (`source_id IS NULL`, fixture-backed builder) — imported Access rows refuse.
- `lifecycle_status = running` (not `draft`, `ready`, `completed`, `void`, …).
- `rating_finalized = 0` and zero existing `amiga_games` for this `tournament_id` (idempotent promote guard).
- **At least one played fixture** with scores.
- Entrants valid; no fixture without both players unless fixture status is `void`.

**Partial finish (Jul 2026):** Remaining **scheduled** fixtures are **auto-voided** as part of Finish (player left early, night cut short). Only **played** fixtures become official `amiga_games`. Do **not** require every fixture to be played before the button appears.

### 6.3 Promote step (L3 insert)

For each **played** fixture in the running package, insert one `amiga_games` row:

- `player_a_id`, `player_b_id`, `goals_a`, `goals_b`, `extra`, `goals_et_a`, `goals_et_b`, `pens_a`, `pens_b`, `phase` from fixture/stage
- `tournament_id`, `fixture_id`
- `source_scores_id`, `game_date` — **allocated in promote transaction** (append to global canon chronology)

**Idempotent guard:** refuse promote if any `amiga_games` already exist for this `tournament_id` (unless explicit repair verb).

### 6.4 Derive step (existing finalize) + lifecycle complete

Run existing finalize pipeline on the new ground rows — unchanged derive semantics:

- `amiga_game_ratings` per game
- Event snapshots + `amiga_player_current`
- `amiga_tournament_standings`, `amiga_tournament_catalog_stats`
- Participation, matchups, community, realm, slices, WC hooks as today
- `tournaments.rating_finalized = 1`, `rating_finalized_at` set

Then, **in the same successful browser action** (same transaction as finalize commit, or immediately after with rollback if finalize fails):

- `lifecycle_status = completed`
- `completed_at` set if null (UTC, same habit as **Mark complete** today)

Finalize **must not** read running fixture score columns after promote — it reads **`amiga_games`** only (same as today post-promote).

**Second click:** if already `rating_finalized`, flash a calm “already official” message; do not error. If official but lifecycle still `running` (pre-RTB-9 limbo), repair via CLI/Advanced only — see §6.6.

### 6.5 Postconditions (after successful finish)

| Surface | State |
|---------|--------|
| **Ratings / profiles / Activity** | Official — games and derived truth from finalize pipeline. |
| **Historical catalog** | Visible — `lifecycle_status = completed` satisfies `AMIGA_TOURNAMENT_PUBLIC_LIFECYCLE_STATUSES`. |
| **Live hub** | **Gone** — index requires `lifecycle_status = running`. |
| **Organizer result entry** | Refused — lifecycle no longer `running`. |
| **Setup status badge** | **Finished** (friendly label for `completed`). |

### 6.6 Repair (not happy path)

| Situation | Action |
|-----------|--------|
| `rating_finalized = 1` but `lifecycle_status = running` (RTB-1–8 limbo) | CLI `fixtures set-lifecycle-status` → `completed`, or Advanced tab — one-off hygiene after RTB-9 ships. |
| Finish refused / limbo | Honest message; **do not** silent-rewind on Finish. Advanced **Reset incomplete finish** is **limbo-only** (`lifecycle` still `running` + half-official). It is **not** available after successful `completed` Finish — do not tell secretaries to Reset a green event. Prefer pull→repair or a future post-official correction verb. |
| Void never-official test league | **Abandon league (void)** on Advanced — separate from finish; no promote. |

---

## 7. Broadcast (running reads)

Broadcast paths **read the running package only** and may **compute** standings/bracket in PHP or JS for display. **Target (SC14):** same relational stage scoring contracts + executor as official path — no separate “live scoring profile.”

| Surface | Today (wrong) | Target |
|---------|---------------|--------|
| Live index | Mostly fixtures ✓ | Unchanged |
| Live detail scores | `LEFT JOIN amiga_games` | Fixture running columns |
| Organizer Results tab | Inserts `amiga_games` | Write fixture running columns |
| Organizer Table tab | Reads `amiga_tournament_standings` | Compute from running package |
| Player links in live view | Profile links | **Deferred** — UI polish; not policy blocker |

**No broadcast write** may insert into official tables (§5.3).

---

## 8. Retired behaviour

**Pre-RTB (removed RTB-1–RTB-8):**

- `record-result` → immediate `INSERT amiga_games` + live `amiga_tournament_standings` rebuild + `amiga_tournament_catalog_stats` refresh

**Pre-RTB-9 organizer UX (to remove):**

- Separate **Make official** (Table) + **Mark complete** (Setup) as two secretary steps
- Finish derive without lifecycle `completed` (limbo: official ratings but still on Live hub)

**Repair-only (kept):** Advanced lifecycle dropdown; CLI `set-lifecycle-status`; **Void tournament** for abort without promote.

---

## 9. Out of scope (v1)

| Item | Notes |
|------|-------|
| Pre-debut public profile for running entrants | UI deferred |
| Per-match Make official | Whole tournament only (RTB5) |
| Lane A import/materialize changes | RTB11 |
| Anchored repair / truncate-after-N | Separate live-ops platform work — admin delete + backup intent [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) |
| Staging per-tournament ground pack (L6) | **Shelved** — full backup pack is the safety path |

---

## 10. Success criteria

1. Secretary can enter all results, see live table on Live hub / organizer — **zero** `amiga_games` rows for that `tournament_id`.
2. **Finish and make official** creates ground rows + full derive + lifecycle `completed` in one action; prove-style verify passes; event leaves Live and appears on historical catalog.
3. New live-created player: searchable before finish; **profile and Activity counts unchanged** until organizer finish gives them rated games.
4. Abandon running tournament: no orphan L3 games; workspace delete sufficient.
5. Python CLI `fixtures record-result` + PHP browser path parity.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-16 | **No silent Finish rewind** — `rating_finalized` only after full derive succeeds; limbo → Advanced explicit reset (narrow). Partial finish + SCH-043 strip retained. |
| 2026-07-17 | **Community scan limbo parity** — PHP (and Python) tournament-host community scan uses `(rating_finalized = 1 OR id = as_of)` so Finish can keep limbo-safe late `rating_finalized` without under-counting the tournament being finalized. Simul-oracle #608 re-fingerprint: community headline/facts match. |
| 2026-07-16 | **Promote chrono parity** — PHP `amiga_promote_next_tournament_chrono` matches Python `next_tournament_chrono` (same-day bump, else global append). Fork fingerprint: was PHP `chrono=1` vs Py `599` on new event_date. |
| 2026-07-16 | **Partial finish** — Finish and make official with unplayed matches: auto-void remaining scheduled; button visible with ≥1 played (Dagh kitchen feedback). |
| 2026-07-08 | **RTB-9 shipped** — browser **Finish and make official** atomically promote + finalize + lifecycle `completed`; Setup **Mark complete** retired. |
| 2026-07-08 | **Rev. 2 locked** — one organizer **Finish and make official** action (promote + finalize + lifecycle `completed`); layered vocabulary §2; **Mark complete** retired from happy path; RTB13. |
| 2026-07-22 | Out of scope: L6 ground pack **shelved**; anchored repair → backup/admin-delete intent doc. |
| 2026-07-07 | **Implementation shipped (RTB-1–RTB-8)** — running vs official boundary; fixture running columns; promote at Make official. |
| 2026-07-07 | **Rev. 1 locked** — two-universe split; promote at Make official; broadcast vs official tables. |
| 2026-07-07 | **Inventory rev. 2** — audit fold-in (lifecycle gates, verify oracles, CLI readers). |