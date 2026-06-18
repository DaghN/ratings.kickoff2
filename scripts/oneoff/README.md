# One-off scripts

**Policy:** Use **ops simul** for derived history on work/staging ([`docs/coordination/ops-simul-runbook.md`](../../docs/coordination/ops-simul-runbook.md)). Batch REP history: [`docs/archive/replay-register-2026-05.md`](../../docs/archive/replay-register-2026-05.md).

**Steve-facing one-offs** must have a row in [`docs/coordination/one-off-register.md`](../../docs/coordination/one-off-register.md) before asking him to run anything on staging/prod.

---

## Three buckets (do not confuse)

| Bucket | Location | Role |
|--------|----------|------|
| **Registered one-offs** | This folder + `one-off-register.md` OO-* rows | Surgical DB fixes, parity gates wired into verify, catalog patches — things Steve or agents run deliberately |
| **Local toolkit** | This folder, **not** in OO register | Milestone SQL generators, parity oracles, garden-link builders, Amiga curation audits — dev/repair tooling; keep unless grep shows zero refs |
| **Throwaway browser probes** | `scripts/throwaway_*.php` | Schema snapshot / index apply via browser (`?once=…`); **not** in default WinSCP sync — copy to `public_html` manually, delete from server after |

**Not one-offs:** `site/public_html/ops/` (live ladder ops), `scripts/ladder/` (Python replay), `scripts/work_prepare/` (prepare + `ab-post-game`).

---

## Registered one-offs (see register)

| ID | Script | Notes |
|----|--------|-------|
| OO-001 | `scripts/throwaway_drop_playertable_kungfu_columns.php` | Historical throwaway; listed in register only |
| OO-003 | `apply_day_milestone_achieved_at_fix.py` | Day-close milestone surgical fix |
| OO-008 | `verify_activity_wing_parity_work.php` | Wired into `run_verify_ops_sim.php` |

Copy `_template.py` for new Steve one-offs → register as OO-00x.

---

## Local toolkit (inventory)

**Milestone catalog / garden**

- `load_milestone_definitions.py` — full catalog reload from ops seed
- `apply_milestone_catalog_copy_patch.py` — batch copy patches
- `build_milestone_garden_links.py` — regen `data/milestone_garden_links.json` + catalog md
- `milestone_unlock_counts.py` — probe counts → docs/seed

**Milestone rebuild SQL generators** (`gen_milestone_*.py`) — output to `scripts/ladder/sql/archive/batch-2026-05/`; regen only when contract changes.

**Milestone parity oracles** (`milestone_*_parity.py`, `milestone_*_gen_check.py`, `milestone_v0_sanity_check.py` + `milestone_v0_sanity_cli.php`) — local proof vs rebuild SQL; see [`docs/milestones-facilitation.md`](../../docs/milestones-facilitation.md).

**Ops / ladder parity**

- `verify_ratedresults_derived_rows.py` — called from `scripts/work_prepare/ab_post_game.py`
- `verify_opponents_matchup_summary.py` — SCH-019 matchup summary oracle
- `verify_activity_wing_parity_work.php` — also OO-008

**Amiga structure curation**

- `curate_tier_b_non_wc.py`, `audit_auto_ok_cups.py`, `audit_pending_review_merges.py`, `dematerialize_slice6_cup_reviews.py`, `tier_b_non_wc_curation.json`

**Reports / probes**

- `player_name_renames_report.php` — rename inventory (audit-only)
- `h2h_scoreline_levels_probe.php`, `analyze_rr_equal_games.py`, `analyze_tier_c_ratios.py`, `inspect_stoke_158.py` — ad-hoc probes; safe to delete after grep if spent

---

## Workflow (new Steve one-off)

1. Copy `_template.py` → `your_task_name.py`
2. Implement `main(dry_run: bool)` — **no writes** when `dry_run=True`
3. Register row **OO-…** in `one-off-register.md`
4. Local: `python scripts/oneoff/your_task_name.py --dry-run` then without dry-run
5. Staging/prod: send Steve command + dry-run output + before/after counts

## Database config

Same as ladder replay: `site/config/ko2unitydb_config.php` (local) or server `config/ko2unitydb_config*.php`. Use `load_db_config()` from `scripts/ladder/` (see `_template.py`).

Allowlist: `ko2unity_db`, `ko2unity_work`, `ko2unity_baseline`, `kooldb1`, `kooldb2`, legacy `kooldb` (frozen). Forward staging work = **`kooldb1`** — [`docs/coordination/database-copies-2026-06.md`](../../docs/coordination/database-copies-2026-06.md).

## Related

- [`docs/prod-coordination.md`](../../docs/prod-coordination.md)
- [`scripts/ladder/README.md`](../ladder/README.md)
- Throwaway probes: [`docs/LOCAL_DEV.md`](../../docs/LOCAL_DEV.md), [`docs/coordination/one-off-register.md`](../../docs/coordination/one-off-register.md) OO-001
