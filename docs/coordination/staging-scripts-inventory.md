# Phase 0 — `staging-scripts/` inventory (ops migration)

**Status:** Read-only inventory (Jun 2026). **No files moved yet.**  
**Goal:** Empty `site/public_html/staging-scripts/` over time; steady-state ladder work lives in **`ops/`** (WinSCP sync). Legacy **batch rebuilds** are retired or stay **local** (`scripts/`), not copied into `ops/`.

**Canonical ops today:** [`site/public_html/ops/README.md`](../../site/public_html/ops/README.md) · platform [`ladder-ops-platform.md`](../ladder-ops-platform.md) §5–6.

---

## Decision key

| Verdict | Meaning |
|---------|---------|
| **Retire (server)** | Staging/prod should not run this path again; use ops or local toolchain |
| **Local only** | Keep under repo `scripts/` for Dagh; not synced |
| **Superseded** | Capability exists elsewhere (document + delete after grep) |
| **One-off archive** | Historical cutover; do not migrate to `ops/` |
| **Wrap later** | Only if Steve still needs on server before PHP milestones ship |

---

## File inventory

| File | REP / role | Doc references (sample) | Ops replacement | Verdict | Register IDs |
|------|------------|---------------------------|-----------------|--------|--------------|
| `_staging_milestones_bootstrap.php` | Shared DB bootstrap for milestone runners | `milestones-staging-cutover-packet.md` | `ops/includes/ops_bootstrap.php` + work-target guards | **Retire** with last milestone runner | — |
| `_staging_play_streaks_bootstrap.php` | Shared bootstrap for play-streak rebuild | `play-streaks-staging-handoff.md` | `ops/includes/ops_bootstrap.php` | **Retire** with play-streak runner | REP-015 |
| `load_milestone_definitions.php` | REP-014 — seed `milestone_definitions` | `milestones-README.md`, cutover packet | `ops/run_prepare.php seed-catalog` | **Superseded** | SCH-010 / prepare |
| `patch_milestone_catalog_copy.php` | Catalog display copy patches (JSON) | `milestones-README.md`, `milestones-add-one-playbook.md` | `scripts/oneoff/` or rare `ops` one-shot | **One-off archive** — not steady-state ops | OO-* |
| `run_league_awards_rebuild.php` | REP-012 + REP-013 full league awards | `replay-register` REP-012, `schema-register` SCH-009, cutover packet | `ops/run_finalize_league.php rebuild-all` | **Superseded** | REP-012, REP-013, PER-003 |
| `run_player_play_streaks_rebuild.php` | REP-015 — `player_play_streaks` + HoF | `replay-register` REP-015, `play-streaks-staging-handoff.md` | `scripts/rebuild_player_play_streaks.php` (local); post-game P7 on prod | **Local only** for backfill; **not** ops | REP-015 |
| `run_player_milestones_rebuild.php` | REP-008 — full `player_milestones` SQL splice | `milestones-staging-cutover-packet.md`, Steve handoffs | PHP post-game + `ab-post-game` replay; Python `milestones.py` rebuild | **Retire (server)** after PHP milestones prod | REP-008, feature-log |
| `run_player_milestones_diversity_merchant_fix.php` | Surgical fix REP-008b | `milestones-staging-diversity-merchant-fix.md` | Same as full rebuild path | **One-off archive** (staging done May 2026) | feature-log |
| `run_milestone_play_streak_100_unlock.php` | Surgical SQL unlock + reload catalog | `milestones-add-one-playbook.md` | Post-game play-streak facilitators when live | **One-off archive** | feature-log |
| `run_milestone_year_in_heaven_unlock.php` | Surgical unlock + optional full rebuild | `milestones-year-in-heaven-handoff.md` | Post-game / rebuild when live | **One-off archive** | feature-log |

---

## Repo `scripts/` duplicates (not in `staging-scripts/`)

| File | Verdict | Ops / local replacement |
|------|---------|-------------------------|
| `scripts/finalize_league_periods.php` | **Superseded** | `ops/run_finalize_league.php` |
| `scripts/rebuild_player_play_streaks.php` | **Local only** | Same role as staging play-streak runner |
| `scripts/verify_visitor_utc_clock.php` | **Local only** | Pre-deploy checklist |

---

## What stays in `ops/` (synced — do not move out)

| Entry | Role |
|-------|------|
| `run_prepare.php` | Prepare / zero-derived / parity / seed-catalog / seed-lobby |
| `run_process_game.php` | Post-game PHP sim |
| `run_finalize_league.php` | PER-003 + REP-012/013 |
| `run_timeline_sim.php` | Mode C simul |
| `modules/`, `includes/` | Behaviour + post-game phases |

**Not in scope for `ops/`:** Python `scripts/ladder`, `scripts/work_prepare`, PowerShell wrappers.

---

## Reference grep (for migration slices)

Before deleting or moving any row above, run:

```text
rg "staging-scripts/<filename>" docs site scripts data PROJECT_MEMORY.md
```

Update **same PR** as code: handoffs, `replay-register`, `OPERATIONS_QUICK_START.md`, `periodic-register` (PER-003 path).

---

## Suggested migration order (Phase 1+)

1. **Docs only** — **Done (Jun 2026).** PER-003 / REP-012 → `ops/run_finalize_league.php`; `replay-register` marks staging `run_league_awards_rebuild.php` superseded.
2. **Retire** `scripts/finalize_league_periods.php` — **Done:** thin delegate to ops `--target local-dev`.
3. **Superseded** `load_milestone_definitions.php` → comment + delete after Steve confirms `seed-catalog` on staging.
4. **One-off archives** — Leave in place until cutover packets closed; then delete milestone unlock/fix runners (no `ops` home).
5. **`run_player_milestones_rebuild.php`** — Last large piece; retire when PHP post-game milestones on staging/prod (feature-log).
6. **`dispatch.php`** — Optional consolidation of `run_*.php` (separate slice).

---

## Registers to touch when executing slices

| Register | When |
|----------|------|
| [`replay-register.md`](replay-register.md) | REP-012/015 path changes |
| [`periodic-register.md`](periodic-register.md) | PER-003 → `ops/run_finalize_league.php` |
| [`feature-log.md`](feature-log.md) | Milestones / play-streaks staging path |
| [`one-off-register.md`](one-off-register.md) | Archived one-offs |
| [`schema-register.md`](schema-register.md) | Only if staging-sql vs `ops/sql/migrations` mirror changes |

---

*Generated as Phase 0 hygiene; update rows when a slice completes (add **Done** date + PR).*
