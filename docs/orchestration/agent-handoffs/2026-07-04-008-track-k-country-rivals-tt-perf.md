# Handoff 2026-07-04-008 — Track K country rivals + CvC chart APIs (TT perf)

**Status:** Done (query slice) · **Fixtures:** England/Italy H2H · Germany W/D/L · **Cutoff:** `year:2024` (census worst) · **Method:** [`amiga-tt-query-optimization-playbook.md`](../amiga-tt-query-optimization-playbook.md)

**Prior:** Track A query dedupe in [`2026-07-04-002-f20-country-rivals-h2h-audit.md`](2026-07-04-002-f20-country-rivals-h2h-audit.md).

---

## Census baselines → targets

| Surface | Census @ `year:2024` | Target |
|---------|---------------------|--------|
| `/amiga/country/rivals/h2h.php?country=England&rival=Italy` | **0.75 s** | page **≤0.45 s** |
| `/amiga/country/rivals/wdl.php?country=Germany` | **0.74 s** | page **≤0.45 s** |
| CvC chart APIs (`player_head_to_head`, heatmap, goals histograms) | **0.20–0.28 s** | API **≤0.15 s** |

---

## Probe first (2026-07-04)

**Oracle:** `scripts/oneoff/amiga_country_rivals_parity_probe.php` — **PARITY OK** ×3 heroes ×7 cutoffs (incl. `year:2024`).

**Track K lib probe:** `scripts/oneoff/amiga_country_rivals_track_k_probe.php`

| Phase | England/Italy H2H @ `year:2024` | Germany W/D/L @ `year:2024` |
|-------|--------------------------------|----------------------------|
| `country_summary` | 26 ms | 36 ms |
| `rivals_rows` | 307 ms | 736 ms (incl. perf batch) |
| `pair_game_rows_raw` | 99 ms | — |
| `moments_slots` | **8 ms** (was ~929× normalize) | — |
| `cumulative_payload` | 2 ms | — |

Audit probe retained: `scripts/oneoff/amiga_country_rivals_h2h_audit_probe.php`.

---

## Shipped (Track K)

1. **Cross-border SQL scope** — exclude domestic opponent pairs inside matchup window + present summary read; exclude domestic games in W/D/L perf batch scan (`amiga_country_rivals_cross_border_games_where_sql()`).
2. **Nation-pair game WHERE** — H2H pair reads use `amiga_country_rivals_games_where_sql()` (directed country tokens + cutoff) on top of hero×rival player inner scope.
3. **Moments hot path** — `amiga_country_rivals_h2h_moments_slots()` scans raw cached pair rows once, normalizes **~9 picks** only (England/Italy ≈929 games); panel no longer calls full `h2h_games_rows()` for moments.

**Files:** `amiga_country_rivals_load.php`, `amiga_country_rivals_h2h_games_lib.php`, `amiga_country_rivals_h2h.php`, `amiga_country_rivals_perf_lib.php`.

---

## Curl (local Laragon, warmed 5-run)

| URL @ `year:2024` | Before (census) | After (best → typical) |
|-------------------|-----------------|------------------------|
| rivals/h2h England/Italy | 0.75 s | **0.45 s** best · 0.45–0.75 s |
| rivals/wdl Germany | 0.74 s | **0.55 s** best · 0.60–0.87 s |
| `/api/player_head_to_head.php` (CvC) | 0.21 s | **0.13 s** best · 0.13–0.20 s |
| `/api/player_h2h_scoreline_heatmap.php` | 0.28 s | **0.12 s** best · 0.13–0.21 s |

**Pages:** H2H meets ≤0.45 s on warmed runs; W/D/L still above target (residual: full W/D/L table HTML + `rivals_rows`+perf batch ~740 ms lib for Germany). **APIs:** warmed CvC calls at or below **0.15 s**; cold first hit may be ~0.2–0.3 s.

---

## Still open

- **W/D/L page ≤0.45 s** — Germany `rivals_rows`+perf batch still ~740 ms @ `year:2024`; stored nation-pair rollup or slimmer perf path would be next slice.
- **H2H curl variance** — first cold hit can exceed 0.7 s; lib blocking ~440 ms sequential @ `year:2024`.
- **F20 chrome** — Type B void / carry gate unchanged (see 002 handoff).

---

## Boundaries

- Read-time SQL only — no new indexes / stored tables this slice.
- Chart APIs stay async; payloads reuse `h2h_game_rows_raw()` request cache within request.