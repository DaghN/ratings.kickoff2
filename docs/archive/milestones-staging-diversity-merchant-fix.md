# Staging wave 2 — `diversity_merchant` rule fix (May 2026)

**Status:** **Done** on staging `kooldb` (May 2026) — verified `diversity_merchant`=**25**, `total`=**6615**, catalog `key`/`amber`.

**When:** After REP-008 wave 1 (full milestones + `giant_slayer` surgical) verified on `kooldb`.

**Bug:** Wave 1 awarded cumulative 10+ goals vs 5 opponents (~68 holders). Correct rule: **per-game DD** (10+ your goals) vs **5 distinct** opponents (~25 holders).

**Does not re-run** full `run_player_milestones_rebuild.php` unless you choose to — surgical SQL only.

---

## Dagh — WinSCP

| Local | Remote (`public_html/`) |
|-------|-------------------------|
| `scripts/ladder/sql/player_milestones_rebuild_diversity_merchant.sql` | `staging-sql/milestones/player_milestones_rebuild_diversity_merchant.sql` |
| `data/milestones_definitions_seed.json` | `staging-data/milestones_definitions_seed.json` |
| `site/public_html/staging-scripts/run_player_milestones_diversity_merchant_fix.php` | `staging-scripts/run_player_milestones_diversity_merchant_fix.php` |

Optional (full tail regen for repo parity; not required on staging if surgical ran):

- `scripts/ladder/sql/player_milestones_rebuild_tail.sql` → `staging-sql/milestones/`

---

## Steve — SSH (from `public_html/`)

```bash
php staging-scripts/run_player_milestones_diversity_merchant_fix.php
php staging-scripts/load_milestone_definitions.php
```

**Expected after fix:**

| Label | Expected |
|-------|----------|
| `diversity_merchant` | **25** |
| `total_rows` | **6615** (was **6658** after wave 1) |
| `giant_slayer` | **31** (unchanged) |
| `definitions` | **110** |

**Verify:**

```bash
mysql -u MYSQL_USER -p kooldb -e "
SET time_zone = '+00:00';
SELECT COUNT(*) AS dm FROM player_milestones WHERE milestone_key = 'diversity_merchant';
SELECT COUNT(*) AS total FROM player_milestones;
SELECT tier_band, chart_token FROM milestone_definitions WHERE milestone_key = 'diversity_merchant';
"
```

**Expected:** `dm` = 25; `total` = 6615; tier = `key` (accomplished), token = `amber`.

---

## Browser smoke (Dagh)

- `player/milestones.php` — former false positives lose chrome **Diversity merchant**; holders show amber tier.
- `leaderboards/milestones.php` — meta totals drop by 1 for ~43 players.

---

## Registers

- [`replay-register.md`](replay-register.md): REP-008b staging **Done**; run log appended.
- [`feature-log.md`](feature-log.md): `diversity_merchant` row updated (L4 staging).
