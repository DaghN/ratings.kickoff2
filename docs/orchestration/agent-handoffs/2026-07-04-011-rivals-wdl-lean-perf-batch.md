# Handoff 2026-07-04-011 — Rivals W/D/L lean perf batch

**Status:** Done (query slice) · **Fixture:** Germany W/D/L @ `year:2024` · **Prior:** Track K handoff 008

---

## Shipped

| Change | File |
|--------|------|
| Lean perf games subquery (no wide rated-results projection) | `amiga_country_rivals_perf_lib.php` |
| Nation-pair directed IN filter scoped to rollup rival tokens | same |
| Pass rival token list from `amiga_country_rivals_rows()` before batch | `amiga_country_rivals_load.php` |

## Verification

- `amiga_country_rivals_parity_probe.php` — **PARITY OK**
- `amiga_country_rivals_track_k_probe.php` — Germany W/D/L @ year:2024: rivals_rows+perf **556 ms** (was **~736 ms** lib); perf_batch alone **329 ms**

**Curl** wdl Germany @ year:2024: ~**0.73 s** (marginal vs census 0.74 s; HTML + pair rollup still dominant).

## Still open

- W/D/L page ≤0.45 s — pair matchup window (~307 ms) + table HTML; stored nation-pair perf rollup would be next lever.