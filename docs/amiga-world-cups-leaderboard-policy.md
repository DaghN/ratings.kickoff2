# Amiga World Cups leaderboard — policy

**Status:** **Slice 0 shipped** (Jun 2026) — DDL + replay writers + `wc_*` retired from honours block; `prove` green. **Next:** slice 1 (LB UI + nav).

**Parent:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) · [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md)

**Related:** [`amiga-profile-v0.md`](amiga-profile-v0.md) · [`url-routes.md`](url-routes.md) · [`design-direction.md`](design-direction.md) · online Activity folder pattern (`leaderboards/activity/*`)

---

## 1. Executive summary

Add a **World Cups** leaderboard wing family under **Amiga → Leaderboards** — a dedicated home for **WC-only** career stats (match results, goals, podium honours). World Cups remain ordinary tournaments in the catalog; this wing is a **stored slice** (`slice_key = 'world_cup'`), not a parallel event type.

| Concept | Rule |
|---------|------|
| **V1 product** | Three **foldered sub-wings**: **Honours · Results · Goals** |
| **V1 medals** | Podium only (`tournaments_played`, gold / silver / bronze, podiums) |
| **Stored truth** | Generic slice tables — **all** WC counters and game aggregates live there from day one |
| **Honours LB** | **Extract** WC columns from Tournament honours (all-events table only) |
| **Time travel** | Wired on first ship — read slice timeline at cutoff; no present-only backfill |
| **V2+** | Same infrastructure — e.g. DDs / CSs sub-wings, 4th / 5–8 / silver-cup finishes when finish taxonomy exists |

This is the **first version** of a WC “hub” inside Leaderboards. Infrastructure is built to grow (more columns, more sub-wings) without rethinking storage or TT.

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WC1** | **Sub-wing split** | One top-level LB tab **World Cups**; inner segment tabs on foldered pages (not `?view=`). |
| **WC2** | **Default sub-wing** | `/amiga/leaderboards/world-cups/honours.php` — folder index 302 → honours. |
| **WC3** | **LB tab placement** | Top-level wing tab adjacent to **Tournament honours** (honours family). |
| **WC4** | **V1 medals** | Podium only — same semantics as today’s `wc_gold` / `wc_silver` / `wc_bronze`. |
| **WC5** | **Deferred medals** | 4th, 5–8, silver cup, other finish tiers — after holistic WC `event_finish_position` (or dedicated WC-finish) work ([`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) M4). |
| **WC6** | **Match points** | **3 / 1 / 0** on **every** WC-rated game (group + knockout). Not group-table league points. |
| **WC7** | **Eligibility** | Rows where slice `tournaments_played ≥ 1` (equivalent to ≥1 WC game in practice). |
| **WC8** | **Averages** | `Pts/g`, `GF/g`, `GA/g`, `GD/g` computed in PHP from stored numerators + `games` — not persisted. |
| **WC9** | **Extract honours LB** | Remove WC block from `tournament-honours.php` once World Cups wing ships. Gradual LB reshaping elsewhere is expected. |
| **WC10** | **HoF** | **Deferred** until slice exists and LB proves the surface — then add records (e.g. most WC goals) as a follow-on. |
| **WC11** | **Time travel** | All three sub-wings use `AmigaSnapshotContext` + slice read lib from slice 0 — [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) T4/T15. |
| **WC12** | **No dual home** | **Do not** keep `wc_*` on the snapshot honours block after migration — consolidate in slice tables in the **same** implementation track (no “read medals from old columns while slice grows”). |

---

## 3. What this wing is (and is not)

**Is:**

- Career aggregates over games and participations in catalog **World Cups** only (`amiga_tournament_is_world_cup()` / name `^World Cup\s+\S`).
- A leaderboard lens comparable to filtering player **Games** to World Cups — but **precomputed for all players** on hot paths.
- Extensible infrastructure (`slice_key`, sub-wings) for V2 stats (DDs, CSs, victims, …).

**Is not:**

- A replacement for per-tournament WC pages (`tournament.php`, stages, brackets).
- A new finish-derivation track (4th / 5–8 remain policy-blocked until WC results infrastructure lands).
- A reason to fork URL trees or skip `as=` propagation.

---

## 4. Data architecture — recommended structure

### 4.1 Plain-language model (time travel)

Three layers — same idea as matchup pairs and career honours, but scoped to World Cups:

| Layer | Role | Analogy |
|-------|------|---------|
| **`amiga_player_slice_totals`** | **Present** — one row per player per slice | `amiga_player_matchup_summary` / `amiga_player_current` |
| **`amiga_player_slice_at_event`** | **Timeline** — cumulative WC stats after each tournament a player **participated** in | Checkpoints on the player’s path through the realm |
| **Finalize writer** | Updates in-memory slice state when processing each tournament; persists both tables | Same commit boundary as snapshots |

**Time travel:** At cutoff *T*, for each player take the **latest** `slice_at_event` row whose `(event_date, event_chrono, as_of_tournament_id)` is **on or before** *T* (same chrono tuple as LB snapshots and matchup at-event). That row’s cumulative WC stats are the WC truth at *T*.

**Why not keep `wc_*` on `amiga_player_event_snapshots`?**

- Honours block today embeds WC medals inside the **all-events** snapshot row — workable for TT, but wrong long-term once WC **game** stats (games, goals, W/D/L) join the product.
- Bloating every snapshot with a growing WC block mixes departments and makes V2 (DDs, etc.) harder.
- A **WC department** (slice tables) matches Dagh’s consolidation instinct: website reads **slice at cutoff** for anything WC.

**Why not only `slice_totals` without `slice_at_event`?**

- Present-only totals cannot answer historical leaderboards without rescanning `amiga_games` or replaying memory — forbidden on hot LB paths.

**Why `slice_at_event` rows on participation (sparse), not dense every finalize for every player?**

- Same semantics honours TT already uses via snapshots: each time a player competes, we store a **checkpoint** with **cumulative** totals through that event.
- A kitchen tournament after a WC updates the checkpoint (career rating changes; WC slice columns unchanged unless that event was a WC).
- TT query = latest checkpoint ≤ cutoff — identical pattern to `amiga_lb_honours_rows_at_cutoff()`.

**Present reads:** `amiga_player_slice_totals` where `slice_key = 'world_cup'` — no scan of at-event history.

### 4.2 Table grain

**`amiga_player_slice_totals`**

| Key | `(player_id, slice_key)` |
| **V1 `slice_key`** | `'world_cup'` only |
| **Future** | Same table — e.g. `'kitchen'`, `'milan'` — when product asks |

**`amiga_player_slice_at_event`**

| Key | `(player_id, slice_key, as_of_tournament_id)` |
| **Denorm** | `event_date`, `event_chrono` from `as_of_tournament_id` (cutoff queries) |
| **Write rule** | One row per **participant** at each tournament finalize (carry-forward cumulative slice state; increment only when event ∈ slice) |

### 4.3 Column set — V1 (`slice_key = 'world_cup'`)

**Honours (event finish — podium only in V1)**

| Column | Meaning |
|--------|---------|
| `tournaments_played` | WC events entered (was `wc_played`) |
| `gold` | `event_finish_position = 1` in a WC |
| `silver` | finish = 2 |
| `bronze` | finish = 3 |
| `podiums` | `gold + silver + bronze` |

**Match aggregates (WC games only)**

| Column | Meaning |
|--------|---------|
| `games` | Rated games in WC tournaments |
| `wins` / `draws` / `losses` | Subject perspective |
| `goals_for` / `goals_against` | Sum of goals in WC games |
| `points` | `3 * wins + draws` (stored for cheap sort; verify against W/D/L) |

**HoF rise tracking (moved from honours block)**

| Column | Meaning |
|--------|---------|
| `tournaments_played_last_rise_tournament_id` | Last WC where `tournaments_played` increased |
| `tournaments_played_last_rise_event_date` | Matching date |

V2 may add `dd_wins`, `cs_wins`, … on the same rows without schema rethink.

### 4.4 Honours block consolidation (same track)

**Remove from** `amiga_player_current` and `amiga_player_event_snapshots`:

- `wc_played`, `wc_gold`, `wc_silver`, `wc_bronze`, `wc_podiums`
- `wc_played_last_rise_tournament_id`, `wc_played_last_rise_event_date`

**Keep on honours block (all-events only):**

- `tournaments_played`, `event_gold`, `event_silver`, `event_bronze`, `event_podiums`, …

**Writers:**

- `honours_totals.py` / PHP honours persist — **stop** incrementing `wc_*`.
- New **`slice_totals.py`** (name TBD) + PHP finalize hook — sole writer for WC slice (honours + games).

**Readers to migrate:**

- World Cups LB (new)
- Tournament honours LB — drop WC columns
- Calendar & geo LB — `wc_played` column → join `slice_totals` (`world_cup.tournaments_played`)
- `amiga_generalstats` / HoF **Most World Cups played** → `slice_totals`
- `prove` oracles — re-point WC counts to slice tables + games/participation ground truth

---

## 5. Finalize writer (conceptual)

```text
1. Existing finalize flow through participation + games for tournament E
2. For each participant P:
     a. Load prior world_cup slice from in-memory map (or empty)
     b. If E is World Cup:
          - tournaments_played += 1 if P participated
          - gold/silver/bronze from event_finish_position (podium rules unchanged)
          - games/wins/draws/losses/goals/points += P’s games in E
     c. Write slice_at_event row (player_id, 'world_cup', E) with cumulative totals
     d. Upsert slice_totals for P
3. Persist event snapshots + current (honours block without wc_*)
4. Existing matchup / realm / generalstats steps (generalstats reads slice for WC HoF fields)
```

**Source of game increments:** in-memory games for tournament E (same pool as matchup cumulative) — not a post-hoc `amiga_games` scan on the hot path.

**Replay / `prove`:** full replay must populate slice tables; verify module compares slice totals to oracle from `amiga_games` + WC participation.

---

## 6. Read paths

### 6.1 Leaderboards

| Sub-wing | Path | Primary columns | Default sort |
|----------|------|-----------------|--------------|
| **Honours** | `world-cups/honours.php` | WCs, gold, silver, bronze, podiums | gold ↓ |
| **Results** | `world-cups/results.php` | WCs, games, W, D, L, Pts, Pts/g | Pts ↓ |
| **Goals** | `world-cups/goals.php` | GF, GA, GD, GF/g, GA/g, GD/g | GF ↓ (or GD — implementer choice; document in plan) |

Shared leading columns: Rank, Player, Elo (from snapshot/current at cutoff — same as other LB wings), Country.

**Lib:** `includes/amiga_wc_lb_lib.php` (name TBD) + `includes/amiga_slice_snapshot_lib.php` (present + `amiga_lb_slice_rows_at_cutoff()` mirroring honours TT SQL).

**Nav:** `includes/amiga_wc_lb_nav.php` inner segment bar; top-level entry in `includes/amiga_lb_nav.php` (`world-cups` wing id).

### 6.2 Time travel

- Present: `slice_totals` + `amiga_player_current` for Elo.
- Active `as=`: `slice_at_event` latest row per player ≤ cutoff; Elo from existing snapshot-at-cutoff helper.
- Eligibility at cutoff: `tournaments_played > 0` on the resolved slice row.
- Link propagation: `amiga_url_with_context()` on all sub-wing tabs and player links.

### 6.3 Tournament honours (after extract)

`amiga/leaderboards/tournament-honours.php` — **Events** + event medals + podiums only. Default sort unchanged (Events ↓). WC columns removed.

---

## 7. URL and routing

Folder under leaderboards (no query `view`):

| Route key (register in `k2_amiga_routes.php`) | Path |
|-----------------------------------------------|------|
| `amiga-lb-world-cups` | `/amiga/leaderboards/world-cups/honours.php` (default) |
| `amiga-lb-world-cups-honours` | `/amiga/leaderboards/world-cups/honours.php` |
| `amiga-lb-world-cups-results` | `/amiga/leaderboards/world-cups/results.php` |
| `amiga-lb-world-cups-goals` | `/amiga/leaderboards/world-cups/goals.php` |

Optional: `world-cups/index.php` → 302 honours.

Document in [`url-routes.md`](url-routes.md) when slice 0 ships.

---

## 8. V1 vs V2 scope

### V1 (this track)

- SCH migration: slice tables + drop `wc_*` from snapshots/current
- Python + PHP finalize writers + `prove` verify
- Three sub-wings + extract from tournament honours
- Time travel on all three
- Column help strings + calm-stats table styling per [`design-direction.md`](design-direction.md)

### V2+ (explicitly deferred — same home)

| Topic | Notes |
|-------|--------|
| **Medals 4th / 5–8 / silver cup** | Needs WC finish taxonomy + writer; new slice columns + honours sub-wing columns |
| **DDs & CSs** | New sub-wing; add columns to slice row; mirror career DD LB shape |
| **Victims / culprits / peaks** | Optional further sub-wings |
| **HoF rows** | New records once slice columns exist |
| **Player profile WC strip** | Optional; reads same `slice_totals` |

Adding V2 does **not** require a second storage model — only columns, writers, verify oracles, and PHP sub-wings.

---

## 9. UI notes

- Reuse **segment track** pattern from Opponents / League honours subnav (`k2-chrome-tabs`).
- Medal headers on honours sub-wing: same podium medal markup as tournament honours (`k2_status_league_podium_medal`).
- Anchor column: **Elo** (col 2) per Amiga LB habit; default sort = hero stat per sub-wing (see §6.1).
- Footnote: player count with `tournaments_played ≥ 1` on the slice.

---

## 10. Out of scope (V1)

| Topic | Where |
|-------|--------|
| WC finish ranks below podium | [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) M4 |
| New HoF rows | After LB ship |
| Player-wing WC tab | Optional later |
| Cross-realm / online parity | Amiga-only |
| Live incremental slice on single-game finalize | Tournament finalize boundary only |

---

## 11. Implementation slices (outline only)

| Slice | Deliverable |
|-------|-------------|
| **0** | DDL + backfill from replay + drop `wc_*` from honours block + `prove` verify | **Done** Jun 2026 |
| **1** | Read libs + honours sub-wing + TT + `amiga_lb_nav` tab |
| **2** | Results sub-wing |
| **3** | Goals sub-wing + tournament honours extract |
| **4** | Calendar/geo + generalstats reader migration; export refresh |

Add [`amiga-world-cups-leaderboard-implementation-plan.md`](amiga-world-cups-leaderboard-implementation-plan.md) when slice 0 is scheduled.

---

## 12. Related docs to update at implementation

| Doc | Change |
|-----|--------|
| [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §5.3 | Honours block loses `wc_*`; new §5.x slice tables |
| [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) | Career rollup diagram: WC → slice not honours block |
| [`amiga-event-snapshot-policy.md`](amiga-event-snapshot-policy.md) §4.4 | Remove `wc_*` from honours block list |
| [`amiga-data-contract.md`](amiga-data-contract.md) | Register slice tables |
| [`amiga-profile-v0.md`](amiga-profile-v0.md) | LB wing list |
| [`url-routes.md`](url-routes.md) | Folder routes |

---

## Revision log

| When | What |
|------|------|
| 2026-06 | Policy locked — slice tables, folder sub-wings, consolidate `wc_*` off honours block, V1 podium + results + goals |
