# Add one milestone after v0 (playbook)

**First use:** `play_streak_100` — **100 days of bliss** (May 2026). Use this checklist whenever the catalog grows after the 110-key rebuild shipped.

---

## Catalog total (112, not hard-coded)

Garden intro and profile hero **`{n}/{catalog}`** use `k2_milestone_catalog_total($con)` → `COUNT(*)` from `milestone_definitions`. After adding a key, reload catalog (local: `python scripts/oneoff/load_milestone_definitions.py` or `php ops/run_prepare.php seed-catalog --target local-dev`) so the count updates. **Editing the seed JSON alone does not change the site.** No PHP constant to bump for display (fallback only if the table is missing).

---

## Garden copy — is it in the DB?

**Yes.** The milestone garden reads **`milestone_definitions.rule_short`** (and `display_name`, tier, token) via `k2_milestone_garden_by_tier()` → card `rule_short` in `player_milestones_helpers.php`. It is **not** hard-coded in PHP.

Optional long copy: `description` column (usually NULL).

**Bulk copy pass (display_name / rule_short only):** edit `data/milestone_catalog_copy_patches.json`, then:

- Local: `python scripts/oneoff/apply_milestone_catalog_copy_patch.py` (updates seed + DB; no TRUNCATE)
- Staging/work: sync seed + `php ops/run_prepare.php seed-catalog --target staging-work` — see § Staging copy patch below

Full `load_milestone_definitions.py` still safe after seed edits (re-imports entire catalog from seed).

---

## Staging / work copy patch (after local apply script)

1. Run `python scripts/oneoff/apply_milestone_catalog_copy_patch.py` locally (updates `ops/data/milestones_definitions_seed.json`).
2. WinSCP-sync `site/public_html/ops/data/milestones_definitions_seed.json`.
3. From `public_html`:

```bash
php ops/run_prepare.php seed-catalog --target staging-work
```

Expect spot-check: `play_streak_100` → **100 days of bliss**.

---

## Checklist (repo)

| Step | What |
|------|------|
| 1 | **Catalog** — Add object to `ops/data/milestones_definitions_seed.json` (`milestone_key`, `display_name`, `tier_band`, `chart_token`, `rule_short`, …). Bump `milestone_count`. |
| 2 | **Garden order** — Add `milestone_key` to `site/public_html/includes/player_milestones_garden_order.php` in the right tier list. **Within a tier, list runs common → rare** (more holders first, fewer holders later). Regenerate probe: `python scripts/oneoff/milestone_unlock_counts.py --write-doc --export-seed` and read `unlock_veterans`. **0** holders → last in Legendary. **Do not** blindly append every new key after the previous add-one unless probe count is truly lowest (e.g. `year_in_heaven` = **5** holders sits with other 5s like `monthly_regular`, not after `club_10000` at 1). |
| 3 | **Unlock SQL** — Generator → `docs/archive/batch-rebuild-sql-2026-05/` (archived repair only). **Proof on work:** ops simul, not batch splice on prod. |
| 4 | **Full rebuild splice** — **Retired** — update archived SQL generators only; staging/work happy path: ops simul + post-game, not batch splice. |
| 5 | **Post-game** — Document in `docs/website-data-contract.md` § `player_milestones`; implement PHP reference (and later C++). `play_streak_100`: `k2_play_streak_maybe_unlock_milestone_100()` when day streak hits 100. |
| 6 | **Parity** — `milestone_definitions` count = N. `COUNT(DISTINCT milestone_key)` in `player_milestones` may be **N−1** if no player has unlocked yet (ultra-rare). |
| 7 | **Sanity** — `python scripts/oneoff/milestone_v0_sanity_check.py` (update expected N if needed). Spot-check garden for a player with/without unlock. |
| 8 | **Proof on work DB** — After PHP post-game change: ops simul + verify on **`ko2unity_work`** (or staging **`kooldb1`**). See [`cutover-readiness.md`](cutover-readiness.md). |
| 9 | **Local dev UI (optional)** — Catalog reload on **`ko2unity_db`** only if you need garden preview without full simul (step below). |

---

## Work DB verify (preferred)

After implementing PHP unlock logic:

```powershell
php site/public_html/ops/run_prepare.php seed-catalog --target local-work
php site/public_html/ops/run_ops_sim.php run --target local-work
php site/public_html/ops/run_verify_ops_sim.php --target local-work
```

Spot-check garden + `SELECT COUNT(DISTINCT milestone_key) FROM player_milestones`.

---

## Local dev verify (`ko2unity_db` repair only)

If you only need catalog + splice preview on the **dev** DB (not cutover proof):

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python scripts/oneoff/load_milestone_definitions.py
# Apply the new splice SQL (example: year_in_heaven)
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root ko2unity_db -e "source C:/Users/daghn/Desktop/Online and Amiga 500 ELO/docs/archive/batch-rebuild-sql-2026-05/player_milestones_rebuild_year_in_heaven.sql"
```

Hard-refresh (`Ctrl+F5`). **Alternative:** re-import dev dump or use work DB simul — not retired batch PS1.

---

## Local commands (regen only)

```powershell
# Regenerate unlock SQL (examples)
python scripts/oneoff/gen_milestone_play_streak_100_sql.py
python scripts/oneoff/gen_milestone_year_in_heaven_sql.py

# Reload catalog only (still required after seed edit)
python scripts/oneoff/load_milestone_definitions.py

# Full milestones on work: ops simul (not retired batch PS1)
```

---

## Staging / work (forward)

| Step | Command |
|------|---------|
| WinSCP | Sync `public_html/ops/` + PHP includes (garden order, helpers) |
| Steve / local work | `php ops/run_prepare.php seed-catalog --target staging-work` |
| Proof | `php ops/run_ops_sim.php run` + `run_verify_ops_sim.php` on **`kooldb1`** |

Historical May 2026 `kooldb` batch path: [`../archive/milestones-staging-cutover-packet.md`](../archive/milestones-staging-cutover-packet.md) — **not** forward cutover.

---

## `year_in_heaven` reference (May 2026)

| Field | Value |
|-------|--------|
| `milestone_key` | `year_in_heaven` |
| `display_name` | **Year in Heaven** |
| `rule_short` | **Rated game in every UTC week of a calendar year** |
| Rule | All **52** Monday slots for calendar year **Y** (profile Played weeks grid); first cross only |
| Unlock game | `MIN(ratedresults.id)` on the **week Monday that completes** 52/52 — not a later game that week |
| Depends | `player_period_games` week rows; post-game after week upsert |
| Handoff | [`milestones-year-in-heaven-handoff.md`](milestones-year-in-heaven-handoff.md) |

---

## `play_streak_100` reference

| Field | Value |
|-------|--------|
| `milestone_key` | `play_streak_100` |
| `display_name` | **100 days** |
| `rule_short` | **100 consecutive UTC days with a rated game** |
| Rule | First cross of **100** consecutive UTC days with ≥1 rated game; unlock on the **game that extends** the day streak to 100 (`player_play_streaks.php` when `$newLen === 100`) |
| Depends | `player_period_games` (day rows); play streaks proven on **`kooldb1`** after ops simul |

---

## Prod

Steve: same as work proof — migrate → seed → zero → simul → verify → live `dispatch.php`. See [`cutover-readiness.md`](cutover-readiness.md).
