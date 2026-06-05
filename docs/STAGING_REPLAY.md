# Staging ladder replay — archived

**May 2026 historical record only.** Do not use as the current staging or cutover runbook.

| Today | Where |
|-------|--------|
| **Work DB / cutover prep** | [`coordination/cutover-readiness.md`](coordination/cutover-readiness.md) — ops simul on **`kooldb1`** / local **`ko2unity_work`** |
| **Steve live cutover** | [`site/public_html/ops/docs/post-dagh-live-story.md`](../site/public_html/ops/docs/post-dagh-live-story.md) |
| **Local Elo replay** | [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) — `scripts/run_local_replay.ps1` on **`ko2unity_db`** |
| **Full May 2026 one-shot record** | [`archive/STAGING_REPLAY-2026-05.md`](archive/STAGING_REPLAY-2026-05.md) |

**Legacy wrapper (historical):** `run_staging_ladder_replay.sh` at repo root — Python ladder on frozen **`kooldb`**; deprecated for forward work.
