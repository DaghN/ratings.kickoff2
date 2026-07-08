# Amiga derived write policy

**Status:** **Locked** (Jun 2026); **forward sign-off** updated Jul 2026 (PROMOTE-1 / DOC-1).  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (living ground / simul) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (G12 strict chain — **archived** Access era) · **Live ops boundary (staging vs prove):** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · **Drill-first implementation:** [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md)

---

## Forward vs oracle (Jul 2026)

| Path | DB | Sign-off verb | When |
|------|-----|---------------|------|
| **`python -m scripts.amiga simul`** | **`ko2amiga_work`** | **simul green** | Daily dev, DDL, derived refresh, staging export source |
| **`python -m scripts.amiga prove`** | **`ko2amiga_db`** (frozen oracle) | prove green | Access archaeology, P-1 parity baseline only — **not** routine staging |

**Wrong derived state on work** → **`simul`** again (clear derived + replay + verify). **Not** nuclear `import-witness` or full prove.

---

## Rule

**Derived ladder truth is written only through the holy ops path.**

| Allowed writers | What |
|-----------------|------|
| **`python -m scripts.amiga simul`** | **Forward default** — L5 replay + finalize on **`ko2amiga_work`** (derived clear first); video align by default |
| **`python -m scripts.amiga prove`** | **Oracle only** — L1→L5 nuclear loop on frozen **`ko2amiga_db`** |
| **`python -m scripts.amiga replay`** | Legacy L5 on **`ko2amiga_db`** (without full L1–L4 reset) |
| **`python -m scripts.amiga finalize-tournament`** | Single-event finalize (same writer as replay slice) |
| **PHP `finalize-tournament`** | Staging/live ops — mirrors Python finalize |
| **Open-tournament ops** | **Running package only** — `fixtures record-result` / browser Results tab write fixture columns; **no** L5 until Make official ([`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md)) |

**Wrong derived state on `ko2amiga_work`** → run **`simul`** again. **Wrong oracle regression** → run **`prove`** on frozen `ko2amiga_db`. Do not patch derived tables with batch rebuild commands.

---

## Verify (read-only oracles)

`prove` ends with verify modules that **recompute in Python and compare** to stored rows. They **do not write**.

Examples: `verify-realm-snapshots` (`build_generalstats_payload`), `verify-community-stats`, `verify-player-matchups`, `verify-event-snapshots`, **`verify-tournament-videos`**, **`verify-running-tournament-boundary`** (RTB Jul 2026).

Oracle **functions** in `scripts/amiga/*.py` exist for verify and unit tests — not as a second write path.

---

## Tournament video manifest sync (not L5 derived)

After L5 replay, **`simul`** on work runs **`align-video-work`** (V-1) before verify; legacy **`prove`** runs **`sync_db_ids`** on oracle. Both refresh **checked-in** editorial caches (`review.csv` + manifest `game_ids`) from stable keys — not a new aggregate table.

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

- **Forward sign-off** = **`simul` green** on **`ko2amiga_work`**.
- **Oracle / archaeology** = **`prove` green** on frozen **`ko2amiga_db`** (not daily staging).
- Docs must not instruct `*-rebuild` for corrections.
- New derived tables: writers on **finalize** + **verify oracle**; no batch repair CLI.

**Live docs sweep (Jun 2026):** Authority specs + implementation plans updated; `docs/archive/**` left as historical slice context.

*See also:* [`amiga-community-stats-implementation-plan.md`](amiga-community-stats-implementation-plan.md) § Phase 2 (verify hygiene before new grains)
