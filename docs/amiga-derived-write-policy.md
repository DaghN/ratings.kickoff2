# Amiga derived write policy

**Status:** **Locked** (Jun 2026)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (G12 strict chain) · **Live ops boundary (staging vs prove):** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md)

---

## Rule

**Derived ladder truth is written only through the holy ops path.**

| Allowed writers | What |
|-----------------|------|
| **`python -m scripts.amiga prove`** | L1→L5 nuclear loop: `replay` → `finalize_tournament` per finalized event |
| **`python -m scripts.amiga replay`** | Same L5 finalize loop (without full L1–L4 reset) |
| **`python -m scripts.amiga finalize-tournament`** | Single-event finalize (same writer as replay slice) |
| **PHP `finalize-tournament`** | Staging/live ops — mirrors Python finalize |
| **Open-tournament ops** | Ground + standings only (`fixtures record-result`, `standings-rebuild --tournament-id` on **unfinalized** events) |

**Wrong derived state → run `prove` again.** Do not patch derived tables with batch rebuild commands.

---

## Verify (read-only oracles)

`prove` ends with verify modules that **recompute in Python and compare** to stored rows. They **do not write**.

Examples: `verify-realm-snapshots` (`build_generalstats_payload`), `verify-community-stats`, `verify-player-matchups`, `verify-event-snapshots`, **`verify-tournament-videos`**.

Oracle **functions** in `scripts/amiga/*.py` exist for verify and unit tests — not as a second write path.

---

## Tournament video manifest sync (not L5 derived)

After L5 replay, **`prove`** runs **`sync_db_ids`** before the verify suite. This refreshes **checked-in** `review.csv` + `tournament_videos.json` DB caches (`tournament_id`, `player_*_id`, `game_ids`) from stable editorial keys — not a new aggregate table. Player **Videos** tabs depend on live id alignment. Policy: [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) §12.

---

## Retired batch derived CLIs (Jun 2026)

Removed from `python -m scripts.amiga` — they bypassed tournament-order finalize:

| Retired command | Was |
|-----------------|-----|
| `generalstats-rebuild` | Wrote `amiga_generalstats` id=1 via full rescan |
| `matchup-rebuild` | Bulk `amiga_player_matchup_summary` |
| `participation-rebuild` | Bulk participation + totals |
| `catalog-stats-rebuild` | Bulk `amiga_tournament_catalog_stats` |
| `performance-rating-rebuild` | Bulk `performance_rating` backfill |
| `rebuild-event-snapshots` | Bulk event snapshots + current |

Historical context: [`archive/retired-amiga-refinalize-2026-06.md`](archive/retired-amiga-refinalize-2026-06.md) (reopen/refinalize + `verify-php-finalize-parity` retired same era).

**Do not add** `community-stats-rebuild` or similar batch writers.

---

## Still available (not batch derived repair)

| Command | Role |
|---------|------|
| `standings-rebuild --tournament-id` | One open tournament’s standings (pre-finalize / structure) |
| `participation-refresh-tournament` | Dev mirror of PHP finalize participation slice |
| `tournament-structure … --rebuild-standings` | L4 structure materialize helper |

These touch **one tournament** or **structure**, not cumulative derived repair across history.

---

## Agent policy

- Sign-off = **`prove` green**.
- Docs must not instruct `*-rebuild` for corrections.
- New derived tables: writers on **finalize** + **verify oracle**; no batch repair CLI.

**Live docs sweep (Jun 2026):** Authority specs + implementation plans updated; `docs/archive/**` left as historical slice context.

*See also:* [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) § Phase 2 (verify hygiene before new grains)
