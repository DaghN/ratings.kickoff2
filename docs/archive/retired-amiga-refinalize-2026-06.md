# Retired: Amiga reopen / refinalize ops (Jun 2026)

**Status:** Removed from repo. **Do not resurrect.**

## What was removed

- CLI verbs: `reopen-tournament`, `refinalize-from`, `refinalize-smoke`
- PHP verbs: `reopen-tournament`, `refinalize-from`
- Modules: `scripts/amiga/refinalize.py`, `refinalize_smoke.py`, `site/public_html/amiga/ops/modules/refinalize_tournament.php`
- Prove step: `verify-php-finalize-parity` (mutated DB via reopen+finalize)
- Batch derived repair CLIs (Jun 2026): `generalstats-rebuild`, `matchup-rebuild`, `participation-rebuild`, `catalog-stats-rebuild`, `performance-rating-rebuild`, `rebuild-event-snapshots` — see [`amiga-derived-write-policy.md`](../amiga-derived-write-policy.md)
- Finalize warm-through guard (only existed to paper over bare finalize after reopen)

## Why

Reopen + single-tournament finalize could corrupt cumulative career stats when roster rows were missing from `amiga_player_current` (T24 bug, Jun 2026). Warm-through was a band-aid; refinalize added ops surface without beating full `prove` (~5 min).

## Repair path now

```powershell
python -m scripts.amiga prove
```

Ground-truth edits on finalized events, standings corrections that affect ratings, or any derived drift → full holy loop only.

**Deferred (separate slice):** ~~ops bootstrap from latest prior snapshot~~ **Done Jun 2026** — `load_player_states_before_tournament` / `amiga_ops_load_prior_snapshot_rows_before_tournament`.

## Historical references

Implementation plans and handoffs may still mention refinalize — treat as archive context only.
