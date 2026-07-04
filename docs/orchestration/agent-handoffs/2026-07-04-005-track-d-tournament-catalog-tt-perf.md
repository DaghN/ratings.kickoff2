# Handoff 2026-07-04-005 — Track D tournament entity + catalog TT perf

**Status:** Done · **Fixtures:** tournament **589** (ordinary), **603** (WC) · **Method:** [`docs/amiga-tt-query-optimization-playbook.md`](../../amiga-tt-query-optimization-playbook.md)

---

## Shipped

| Change | File(s) | Pattern |
|--------|---------|---------|
| At-cutoff tournament catalog request cache (single SELECT; count from cache) | `amiga_tournament_lib.php` | D |
| Scoped request caches: load, scopes, standings, game count, winner, games rows, bracket, participation | `amiga_tournament_lib.php`, `amiga_player_tournament_lib.php` | D |
| Batch `step_player_facet_counts` — one `GROUP BY` over eligible tournament ids | `amiga_tournament_step_catalog.php` | narrow read |
| Step catalog / row-by-id caches keyed by cutoff tuple | `amiga_tournament_step_catalog.php` | D |
| Lazy WC bracket — only when `$isStagesContentView && $hasBracket` | `amiga_tournament_page.php` | E |

## Parity

- `scripts/oneoff/amiga_tournament_index_parity_probe.php` — PASS ×6 cutoffs
- `scripts/oneoff/amiga_tournament_player_facet_parity_probe.php` — PASS ×4 cutoffs ×3 filter bags

## Oracle + census

`scripts/oneoff/amiga_tournament_tt_probe.php` — lib bootstrap + curl on 5 Track D URLs ×3 cutoffs.

### Curl after (worst per URL)

| URL | Worst | Notes |
|-----|-------|-------|
| event-stats?id=589 | 0.176 s | lib ~51 ms |
| games?id=589 | 0.287 s | lib ~16 ms |
| standings?id=589 | 0.373 s | lib ~15 ms |
| stages?id=603 | 0.781 s | bracket-heavy; lib ~170 ms cold |
| tournaments.php | 0.456 s | 600-row catalog @ year:2024 |

All ≤0.8 s success bar; no PHP errors in body.