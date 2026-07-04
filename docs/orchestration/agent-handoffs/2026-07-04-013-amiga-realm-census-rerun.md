# Amiga realm full census re-run — 2026-07-04

**Track:** measurement-only audit after Tracks J–M (player games pagination, country rivals, games hub/LB wings, facet dedupe, rivals W/D/L perf).

## Executive summary

Re-census of **96** canonical Amiga pages + APIs completed against `http://ratingskickoff.test` (ko2amiga_db). Probe enhanced with feel tiers, summary stats, and delta vs prior run (16:25 same day).

**Headline:** **3 paths >0.8 s** (was 1). **5 Heavy** (>0.70 s worst cutoff). **78 Instant**, 8 Smooth, 5 Noticeable.

**Clear win:** `player/games.php?id=382` dropped **1.091 → 0.740 s** (Track J pagination + Track M facet dedupe) — no longer the sole >0.8 s offender.

**New debt / variance:** Country rivals H2H + W/D/L and `games/recent.php` regressed vs prior census on worst cutoff (cold sequential curl; lib-level probes from Tracks K/L/M reported faster warmed times). Treat as **remaining feel debt** for next perf slice, not proof of code regression without warmed lib probes.

## Full results

[`scripts/oneoff/amiga_realm_full_census_results.md`](../../scripts/oneoff/amiga_realm_full_census_results.md)

Probe: [`scripts/oneoff/amiga_realm_full_census_probe.php`](../../scripts/oneoff/amiga_realm_full_census_probe.php) (feel tier + delta sections added this run).

## Summary stats

| Metric | Count |
|--------|------:|
| Total paths | 96 |
| > 0.8 s | 3 |
| > 0.70 s | 5 |
| Instant (≤0.25 s) | 78 |
| Smooth (≤0.40 s) | 8 |
| Noticeable (0.40–0.70 s) | 5 |
| Heavy (>0.70 s) | 5 |

## Top 10 slowest (worst cutoff)

| Rank | Worst (s) | @ cutoff | Present | Early | Mid | Late | Path |
|------|----------:|----------|--------:|------:|----:|-----:|------|
| 1 | 1.148 | month:2014-07 | 0.328 | 0.465 | 1.148 | 0.747 | `/amiga/country/rivals/h2h.php?country=England&rival=Italy&pick=games` |
| 2 | 1.124 | year:2024 | 0.393 | 0.131 | 0.789 | 1.124 | `/amiga/country/rivals/wdl.php?country=Germany` |
| 3 | 1.109 | year:2024 | 0.715 | 0.228 | 0.392 | 1.109 | `/amiga/games/recent.php` |
| 4 | 0.771 | present | 0.771 | 0.683 | 0.570 | 0.493 | `/amiga/games/all.php` |
| 5 | 0.740 | present | 0.740 | 0.384 | 0.624 | 0.701 | `/amiga/player/games.php?id=382` |
| 6 | 0.569 | year:2024 | 0.222 | 0.191 | 0.551 | 0.569 | `/amiga/country/rivals/goals.php?country=Germany` |
| 7 | 0.558 | year:2024 | 0.504 | 0.451 | 0.477 | 0.558 | `/amiga/tournament/stages.php?id=603` |
| 8 | 0.508 | year:2024 | 0.174 | 0.138 | 0.493 | 0.508 | `/amiga/country/rivals/dds.php?country=Germany` |
| 9 | 0.469 | present | 0.469 | 0.341 | 0.380 | 0.349 | `/amiga/games/highlights.php` |
| 10 | 0.401 | month:2014-07 | 0.396 | 0.183 | 0.401 | 0.367 | `/amiga/tournaments.php` |

## Notable improvements since prior census (16:25)

- **`player/games.php?id=382`:** 1.091 → **0.740 s** (Δ −0.351) — Tracks J + M shipped.
- **CvC H2H APIs:** scoreline heatmap 0.278 → 0.212 s; head_to_head 0.213 → 0.139 s; goals distribution 0.256 → 0.193 s.
- **LB tournament-honours:** 0.212 → 0.147 s (Track L lede prewarm).
- **Community slice series API:** 0.215 → 0.078 s.

## Notable regressions (same-day prior → this run)

Likely mix of **cold sequential census** (384 requests, no warm pass) and run variance — cross-check with track lib probes before fixing.

- **`games/recent.php`:** 0.565 → **1.109 s** @ year:2024 (Track L reported lib ≤140 ms; HTML-bound).
- **Country rivals H2H:** 0.752 → **1.148 s** (Track K warmed ~0.45 s).
- **Country rivals W/D/L:** 0.735 → **1.124 s** (Track K/011 lib ~557 ms warmed).
- **`games/all.php`:** 0.407 → **0.771 s** @ present.

## Remaining feel debt (next perf targets)

1. **Country rivals** — H2H + W/D/L still **Heavy** at TT cutoffs; goals/dds **Noticeable** @ year:2024.
2. **Games hub** — `recent.php` and `all.php` **Heavy**; highlights **Noticeable** @ present.
3. **`player/games.php?id=382`** — improved but still **Heavy** @ present (0.740 s); sub-0.70 s target if HTML row count still dominates.
4. **Tournament catalog** — `tournaments.php` **Noticeable**; WC stages **Noticeable** @ year:2024.

## Boundaries respected

- Audit/measurement only — no query fixes, DDL, ops, or production lib edits.
- Probe-only change: feel tier column, summary, delta vs prior md.

## Blockers

None — Laragon curl OK; all 96 paths HTTP 200, no PHP errors flagged.