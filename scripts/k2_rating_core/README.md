# k2_rating_core — shared rating library

**Status:** **Canonical** (Jun 2026) — extracted from retired `scripts.ladder` replay CLI during obsolete dev scripts retirement slice 4.

**Consumers:**

| Consumer | Use |
|----------|-----|
| **`scripts.amiga` holy path** | `apply_game_row`, `PlayerState`, `START_RATING`, `config` |
| **PHP `ops/includes/post_game_*.php`** | Behaviour mirror (comments cite this package) |
| **`scripts/oneoff/`** | Milestone generators / parity probes (`connect`, `load_db_config`) |

**Not a database fill path.** Online = `run_ops_sim.php`. Amiga = `python -m scripts.amiga prove`.

Retired replay orchestration: [`docs/archive/ladder-retired-2026-06/`](../docs/archive/ladder-retired-2026-06/).
