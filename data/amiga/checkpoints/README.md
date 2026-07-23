# Amiga work checkpoints (git milestones)

**Purpose:** Recoverable snapshots of local **`ko2amiga_work`** when forward ground is not yet on staged prod (structure materialize, Tier E overrides, etc.). Not a replacement for staged DR — see [`docs/amiga-staging-authority-policy.md`](../../docs/amiga-staging-authority-policy.md) SS-6.

**Precedent:** Same habit as [`day0/`](day0/) (L3 witness), but **full export tier** (L3–L5 + structure).

## Layout

```text
data/amiga/checkpoints/work-YYYY-MM-DD-<label>/
  manifest.json              checkpoint metadata + restore notes
  ko2amiga_manifest.json     SQL part load order
  ko2amiga_*.sql             export parts (~70 MB full tier)
  companion/                 JSON/CSV snapshots at seal time
```

## Seal a new checkpoint

```powershell
powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label tail
```

Then add a **gitignore allowlist** for that folder (copy the `work-2026-07-18-forum` block in repo `.gitignore`).

## Restore (local)

1. Fresh `ko2amiga_work` (or DROP/CREATE).
2. Import `ko2amiga_*.sql` in `ko2amiga_manifest.json` `parts[]` order.
3. Copy `companion/tournament_videos.json` to `site/public_html/data/amiga/` if video manifest drifted.
4. Restore video editorial from **`companion/video_game_links.csv`** (shared git canon snapshot at seal) — not a stale work fork.
5. `python -m scripts.amiga simul` only if L5 derived looks stale or you imported structure-only.

**Alternative:** Copy parts to `site/public_html/amiga/_import/` and use staging browser import URLs ([`docs/amiga-staging-handoff.md`](../../docs/amiga-staging-handoff.md)) against local Laragon.

## Policy

- **Milestone only** — not every `export_ko2amiga_work.ps1` run.
- **Opt-in per folder** — default `data/amiga/checkpoints/**` is gitignored; un-ignore SQL for sealed checkpoints only.
- **Preferred healthy tip (Jul 2026-23):** `work-2026-07-23-chrono-integer` — dense integer chronos + matchup `event_chrono` parity (mae mismatch **0**). Reference before repair: `work-2026-07-23-pre-chrono-integer` (`companion/chrono_baseline_before.json`). Prior: `work-2026-07-23-inverse-roundtrip`.