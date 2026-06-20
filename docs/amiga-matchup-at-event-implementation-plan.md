# Amiga cumulative matchup at event — implementation plan

**Status:** Complete (slices 0–6, Jun 2026).  
**Policy:** [`amiga-matchup-at-event-policy.md`](amiga-matchup-at-event-policy.md)

**Goal:** Finalize-only pairwise + network truth; remove replay tail batches (`commit_heavy` network, `matchup-rebuild`, `catalog-stats-rebuild`, `generalstats` rebuild).

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Policy + this plan | Dagh OK |
| **1** | DDL `026_matchup_at_event.sql` + schema bundle | `apply_schema` |
| **2** | `matchup_cumulative.py` — in-memory pairs, apply game, network derive | unit smoke |
| **3** | Wire `finalize_tournament.py` — persist at-event + summary + network + peaks | one tournament smoke |
| **4** | Strip replay/refinalize tail batches; `clear_derived` | full replay |
| **5** | `verify_player_matchups.py` + network-on-snapshot checks | 0 errors |
| **6** | export/import README; UPDATE_DOCS | `prove` green |

---

## Slice 1 — DDL

- `amiga_player_matchup_at_event` PK `(player_id, opponent_id, as_of_tournament_id)`
- Extend `amiga_player_matchup_summary` with `dd_wins`, `dd_losses`, `cs_wins`, `cs_losses` (fresh bundle + migration in 026)

---

## Slice 2 — Cumulative engine

- `MatchupCumulative` dict: `player_id → opponent_id → PairTotals`
- `apply_game(game)` updates both directed sides
- `network_counts_for_player(player_id) → dict` for PlayerState fields

---

## Slice 3 — Finalize wire

- Shared `matchups` dict across replay (like `players`)
- After games in tournament: derive network → peaks → snapshots → matchup persist
- Remove `defer_heavy_derived` flag

---

## Slice 4 — Replay cleanup

Remove from `replay.py` / `refinalize.py`:

- `commit_heavy_player_derived`
- `rebuild_all_matchup_summary`
- `rebuild_all_catalog_stats`
- `rebuild_generalstats` (HoF later)

Keep `matchup-rebuild` CLI as **repair oracle** only (document as non-sign-off).

---

## Slice 5 — Verify

- Summary parity (existing)
- At-event vs games rollup spot checks
- Network columns on latest snapshot vs pair-count

---

## Slice 6 — Docs + export

- `export_ko2amiga_db.ps1` include at-event table
- MEMORY + feature-log + player-universe §5.4 cross-link
