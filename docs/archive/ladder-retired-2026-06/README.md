# scripts.ladder — retired replay package (Jun 2026)

Full-memory replay CLI and batch orchestration archived during [obsolete dev scripts retirement](../obsolete-dev-scripts-retirement-policy.md) **slice 4**.

| | Location |
|---|----------|
| **Shared rating library (canonical)** | [`scripts/k2_rating_core/`](../../scripts/k2_rating_core/) |
| **Retired replay code** | This folder — `engine.py`, `milestones.py`, `period_*.py`, … |
| **Retired CLI** | `scripts/ladder/__main__.py` (exit 1 stub at repo) |

`scripts/ladder/__init__.py` in the repo re-exports `k2_rating_core` for transitional imports only.

**Do not restore** `python -m scripts.ladder run` as a fill path.
