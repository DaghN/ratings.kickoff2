# Milestones — curated tier list (archived snapshot)

**Kick Off 2 ratings site · Phase 2 definition snapshot**

> **Authority for per-key data:** [`milestones-catalog.md`](milestones-catalog.md) (generated). **Start here:** [`milestones-README.md`](milestones-README.md).

**Status:** **Archived tables** (May 2026 consolidation). Tier assignment and copy now live in the seed + generated catalog. This file keeps **win-streak rules** and **discard notes** only.

**Display names & Name Q (1–5):** [`data/milestones_curated_meta.json`](../data/milestones_curated_meta.json). **Runtime catalog:** [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json).

**Related:** [`milestones-product-spec.md`](milestones-product-spec.md) (presentation) · [`milestones-project.md`](milestones-project.md) (phases).

---

## Summary

| Band | Chart token | Count | Role |
|------|-------------|------:|------|
| **Legendary** | `holo` | 20 | Rare feats, long horizons, merchant lore peaks |
| **Accomplished** | `amber` | 21 | Keystones — serious ladder citizenship |
| **Dedicated** | `chrome` | 49 | Mid-ladder grind, variety, leagues volume |
| **Aspirational** | `pitch` | 22 | First steps and broad participation floor |
| **Total in curated set** | — | **112** | — |

Per-key rows and current band counts: **[`milestones-catalog.md`](milestones-catalog.md)**. Refresh probe counts: `python scripts/oneoff/milestone_unlock_counts.py --write-doc --export-seed`.

---

## Tier order (presentation)

Legendary → Accomplished → Dedicated → Aspirational (rarest / highest band first in UI).

---

## Win-streak milestones — rule

These keys use **`playertable.LongestWinningStreak`** (career maximum consecutive wins):

`win_hat_trick` (≥3) · `ten_wins_straight` (≥10) · `rampage` (≥15) · `win_streak_30` (≥30).

`cold_streak` and `win_drought` use the corresponding **longest loss / non-win streak** columns on the same table.

Unlock when the stored career-best run reaches the threshold. Implementation should read the ladder-maintained column (same source as the profile streak display), not a separate replay pass, unless the data contract is extended later.

---

## Per-key tables (retired)

Tier-grouped markdown tables from Phase 2 were removed to avoid drift with the live catalog. Use **[`milestones-catalog.md`](milestones-catalog.md)**.

---

## Out of curated set (discarded for now)

Not in the four bands above. Kept in the ideas catalog as `discard` for reference only.

| Key | Note |
|-----|------|
| `top_ten_sweep` | Unstable snapshot |
| `long_sleep_loud_wakeup` | Cut from legendary |
| `nine_eight_thriller` | Cut |
| `double_digit_handshake` | Merged into `merchant_trade_fair` (10–10 draw) |
| `club_5000` | Superseded by `club_10000` |
| `back_in_the_game` | Cut |
| `league_daily_points_winner` | Duplicate of `moment_of_glory` |
| `nemesis` | Cut |
| `elite_customer` | Cut |
| `podium_month` | Cut |
| `still_here_years_later` | Cut |
| `league_monthly_activity_winner` | Cut (`activity_king` covers monthly activity win) |
| `period_champion` | Cut — redundant vs specific league milestones |
| `six_goal_draw` | Cut — dropped from curated set |

---

*Historical Phase 2 snapshot. Do not hand-edit Unlock / %vet here — use seed export and generated catalog.*
