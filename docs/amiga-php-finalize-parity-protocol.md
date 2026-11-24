# Amiga PHP Finish ↔ simul-oracle parity protocol

**Status:** Since 2026-11-22 the calculation is checked with one command, **§0**. §1–§10 are the July 2026 hand protocol (probe tournament #608), kept because it also covers promote, which the command does not.

**Audience:** Dagh, agents re-running or extending PHP live Finish confidence.

**Related:** [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (RTB12) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) Lane B · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) · starter `orchestration/agent-handoffs/amiga-php-finalize-parity-STARTER-PROMPT.md` · checkpoints [`../data/amiga/checkpoints/README.md`](../data/amiga/checkpoints/README.md)

---

## 0. The command: `python -m scripts.amiga php-finish-check`

The website's Finish (PHP) and simul (Python) calculate the same things with separate code. **Python is the reference.** The command copies the results and structure from `ko2amiga_work` into the throwaway database `ko2amiga_finish_check`, lets the PHP in `site/public_html` calculate every tournament there, oldest first, compares every stored value in every table with what simul stored, and drops the database. About 20 minutes. Local only: no live site, nothing else changed.

### When

After a change to **how something is calculated**, on either side, before the PHP is uploaded: a new statistic or record, a rule (ties, placings, ratings), a speed or safety change to Finish or simul, or a change to the online files Finish shares (`ops/includes/post_game_constants.php`, `post_game_elo.php`, `post_game_outcome.php`, `post_game_player_db.php`, `post_game_player_state.php`).

1. Same change on the other side. 2. `simul`. 3. `php-finish-check` must say `RESULT: SAME`. 4. Upload with Dagh's OK; tell Dagh the result.

**Not needed** for display, text, charts, videos, corrected results without a rule change, or as a routine. The rule reaches agents through `.cursor/rules/amiga-calculation-check.mdc` and its Claude mirror.

### After a tournament is finished on the website

A different check: pull live into `ko2amiga_work`, side-pull a copy, run simul, and compare the two (`scripts/oneoff/compare_work_vs_staging_cmp.py`). It tests what the website really stored, including promote and the clicking. Do it after the first few. By 2026-11-22 there had been none: Nottingham III and Oslo were entered on the PC.

### What it does not cover

The promote step (fixtures to official games), the browser steps, an interrupted or repeated Finish, deleting or inserting tournaments.

### Reading the result

| Word | Meaning |
|------|---------|
| `SAME` | No value differs (exit 0) |
| `DIFFERENT` | Exit 1. Tables, columns, samples and the earliest tournament with a difference; start there |
| `PHP STOPPED` | Exit 1. PHP raised an error at the named tournament |
| rounding | One step in the last stored decimal; ratings up to 0.00005; sums of ratings 0.00000001 of their size. Passes |
| known | `LeastGoalsScoredVictims`, `LeastGoalsConcededCulprits`: shown on no Amiga page, retirement deferred ([`amiga-player-inverse-count-timeline-policy.md`](amiga-player-inverse-count-timeline-policy.md) §6). Passes |

A mistake made in both programs passes this check; the recount is for that ([`amiga-recount-policy.md`](amiga-recount-policy.md)).

It refuses to start when `ko2amiga_work` is not one complete Python rebuild, or when a Python calculation file changed after that rebuild (run simul first). Options: `--limit N` (first N tournaments), `--site-root PATH` (another `public_html`, for example an older version), `--keep-db`, `--allow-old-python`. Report: `data/amiga/modern/php-finish-check-last.json`. Code: `scripts/amiga/modern/php_finish_check.py`, `php_finish_replay.php`; tests `scripts/amiga/test_php_finish_check.py`.

A new derived table is compared without being listed anywhere. A new **ground** table must be added to `GROUND_TABLES`; until then the check fails.

Finish on the website saves row by row; the command saves once per tournament, which is about three times faster and does not touch the calculation.

### Results

| Date | PHP | Python | Result |
|------|-----|--------|--------|
| 2026-11-22 | `e7d2f66a` (live that day) | live data of that morning | Test before the command existed: only rounding and the two known counts |
| 2026-11-22 | working copy at `de0b7082`, with the extra-time rule, the World Cup podium and the held online fix | simul of 15:58 UTC | **SAME**, 608 tournaments, 8,820,661 values, 19.4 min |
| 2026-11-24 | working copy at `471b56e9` with the tie rule for count records (the holder keeps an equalled record) | simul of that afternoon | **SAME**, 608 tournaments, 8,984,460 values, 17.8 min |
| 2026-11-22 | `e7d2f66a`, before that day's two rule changes (a one-sided change on purpose) | simul of 15:58 UTC, with both | **DIFFERENT**, as it should be: 33 positions in stage tables (extra-time rule) and 1 value in `amiga_world_cup_stats` (World Cup XII bronze), nothing else. Exit 1 |

Planted faults it caught: K factor 32.001 instead of 32; one clean-sheet condition changed in one of three branches; an error in one tournament.

---

## 1. Why this test exists

Staging Finish / Make official runs **PHP** (`amiga_promote_running_tournament` → `amiga_finalize_tournament`). Green **`simul` / `prove`** only proves **Python** writers.

Assumed “PHP mirrors Python” was never continuously proven. Recent staging failures were schema/shape mismatches (dead columns; leaked `as_of_tournament_id` into `amiga_player_slice_totals`). This protocol asks: **after promote + full Finish derive, does PHP write the same ladder truth as a trusted Python replay?**

**Not resurrected:** retired `verify-php-finalize-parity` / reopen-refinalize (`archive/retired-amiga-refinalize-2026-06.md`).

---

## 2. Locked method (no one-tournament rewind)

### Forbidden

Clearing or “rewinding” only tournament **T**’s derived rows on a DB that already absorbed **T** into career `current`, matchup summary, slice totals, realm, community, etc. That unwind is not accurate and must not be the compare protocol.

### Locked fork protocol (final form)

Shared mid-state = **N + T_running** (probe tournament exists with fixtures played; **no** `amiga_games` for T yet; `rating_finalized = 0`).

| Fork | Steps | Role |
|------|--------|------|
| **Oracle** | Restore mid-state → Python **promote** → **`python -m scripts.amiga simul`** (full chronological L5 replay through T) | Trusted truth |
| **Under test** | Restore same mid-state → **PHP promote + finalize** (same path staging Finish uses) | Candidate |

Then fingerprint-diff derived tables. Restore living **`ko2amiga_work`** to pre-experiment base **N** afterward.

### Rejected alternatives

| Approach | Why rejected |
|----------|----------------|
| Fix “broken live Python single finalize” as the main oracle | Live ops authority for writers under test is PHP; oracle is full **`simul`** |
| Start from **N + T_promoted** so PHP skips promote | Misses promote bugs (chrono assignment was the loud first gap) |
| Finalize Py then PHP on the same living `ko2amiga_work` without restore | Pollutes work; not two independent forks |

### Hygiene

| DB | Role |
|----|------|
| Staged `ko2amiga_db` | Community prod — **never** run forks here; **never** export polluted work |
| Local `ko2amiga_work` | Seal checkpoint **before** kitchen; restore to base **N** after |
| Throwaway DBs | `ko2amiga_parity_php`, `ko2amiga_parity_simul` (or re-import dumps) |

---

## 3. Probe used (Jul 2026)

**Option B — WC-stamped kitchen** (exercises WC slices + WC HoF + community host country).

| Field | Value |
|-------|--------|
| Tournament | **#608** `World Cup Parity Probe I` |
| Stamp | `is_world_cup = 1`, host **Iceland**, `event_date` 2026-07-16 |
| Shape | 8-player kitchen round-robin; **28** fixtures played |
| Mid-state | `amiga_games` for T = **0**; `lifecycle_status = running`; `rating_finalized = 0` |
| Newcomer | **#470 Inga H** (Iceland) — unused host/nationality before probe |
| Veterans | 382, 14, 149, 417, 441, 134, 418 |

Meta: [`../data/amiga/parity/nt-running-meta.json`](../data/amiga/parity/nt-running-meta.json).

---

## 4. Artifacts (on disk)

| Artifact | Path |
|----------|------|
| Base **N** checkpoint | `data/amiga/checkpoints/work-2026-07-16-php-parity-base/` |
| Mid-state dump **N+T_running** | `data/amiga/parity/nt-running-2026-07-16/ko2amiga_work.sql` (~72 MB) |
| Renamed mid-state for PHP DB | `data/amiga/parity/nt-running-2026-07-16/ko2amiga_parity_php.sql` |
| Simul oracle dump | `data/amiga/parity/nt-simul-oracle-2026-07-16/ko2amiga_work.sql` |
| Fingerprint report | `data/amiga/parity/fingerprint-608-simul-vs-php.json` |
| Probe meta | `data/amiga/parity/nt-running-meta.json` |

Throwaway DBs may still exist locally (`ko2amiga_parity_php`, `ko2amiga_parity_simul`, `ko2amiga_parity_py`) — recreate from dumps if dropped.

---

## 5. Helper scripts (one-offs)

Under `scripts/oneoff/` (parity probe only — not product CLIs):

| Script | Purpose |
|--------|---------|
| `amiga_php_parity_finalize_php.php` | Promote + `amiga_finalize_tournament` on `ko2amiga_parity_php` |
| `amiga_php_parity_finalize_py.py` | Promote + Python finalize on a parity DB (early fork; not the signed-off oracle) |
| `amiga_php_parity_fingerprint_simul.py` | Diff `ko2amiga_parity_simul` vs `ko2amiga_parity_php` |
| `amiga_php_parity_fingerprint.py` | Earlier Py-single-finalize vs PHP fingerprint |
| `amiga_parity_promote_work.py` / seed helpers | Kitchen seed / promote on work (legacy CLIs refuse `ko2amiga_work`) |

PHP CLI path on Dagh’s machine: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.

---

## 6. Re-run (short)

Assuming mid-state + oracle dumps still exist:

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
$mysql = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe"
$phpExe = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

# 1) Fresh PHP fork from N+T_running
& $mysql -u root -e "DROP DATABASE IF EXISTS ko2amiga_parity_php;"
Get-Content -LiteralPath "data\amiga\parity\nt-running-2026-07-16\ko2amiga_parity_php.sql" -Raw -Encoding UTF8 | & $mysql -u root

# 2) PHP promote + finalize
& $phpExe scripts\oneoff\amiga_php_parity_finalize_php.php

# 3) Ensure oracle DB exists (import nt-simul-oracle dump renamed to ko2amiga_parity_simul if needed)

# 4) Fingerprint
$env:PYTHONPATH = (Get-Location).Path
python scripts\oneoff\amiga_php_parity_fingerprint_simul.py
```

To rebuild the **oracle** from mid-state (slow — full simul):

1. Import mid-state into a throwaway DB (or temporarily into work with checkpoint sealed).
2. Python promote for T=608.
3. `python -m scripts.amiga simul` (full replay).
4. mysqldump that DB → `nt-simul-oracle-…`.
5. Restore living work to base **N** (checkpoint), not leave the probe tournament on work.

**Do not** `export_ko2amiga_work.ps1` → staging until work is clean of the probe.

---

## 7. Surfaces compared

Fingerprint covers (among others):

- `amiga_games` + `amiga_game_ratings` for T
- `amiga_tournament_standings`
- `amiga_player_event_snapshots` / `amiga_player_current` (participants)
- `amiga_player_matchup_at_event`
- Player + country slice at-event / totals
- `amiga_realm_snapshots`, community stats + facts + snapshots
- `amiga_world_cup_stats`, `amiga_wc_hof_{snapshots,present}`
- `amiga_tournament_catalog_stats`, inverse-count changelog, `amiga_generalstats`

Treat as **soft** (not content failure):

| Soft gap | Notes |
|----------|--------|
| Rating float ~**1e-6** | PHP vs Python double formatting / path |
| `finalized_at`, `last_finalized_at` | Wall clock of each run |
| `lifecycle_status` `completed` vs `running` | Full browser Finish sets `completed`; PHP one-shot finalize may leave `running` (RTB-9 browser path is separate) |

---

## 8. Bugs found and fixed (this probe)

| Rank | Gap | Fix |
|------|-----|-----|
| 1 | PHP promote assigned `chrono=1` vs Python `599` on a new event_date | `amiga_promote_next_tournament_chrono()` — same-day bump else global max+1 ([`amiga_promote_running_tournament.php`](../site/public_html/amiga/ops/includes/amiga_promote_running_tournament.php)) |
| 2 | Python inline promote left stale `tour` chrono NULL | Reload tournament row after promote in `finalize_tournament.py` |
| 3 | Community under-count (TournamentsFinalized 607 vs 608; Iceland host facts missing) | Community tournament-host SQL: `(rating_finalized = 1 OR id = as_of)` so limbo-safe late `rating_finalized` still counts T ([`amiga_community_realm_scan_lib.php`](../site/public_html/amiga/ops/includes/amiga_community_realm_scan_lib.php) + Python `community_stat_facts.py`) |

**Why community needed OR id:** PHP sets `rating_finalized = 1` **only after** community/realm writers succeed (no silent Finish rewind / limbo safety). Community scan previously required `rating_finalized = 1`, so T was invisible during its own finalize. Games scan already included T via cutoff; tournament-host metrics did not.

---

## 9. Sign-off result (Jul 17)

After chrono + community fixes, re-run from **N+T_running**:

| Verdict | Surfaces |
|---------|----------|
| **Content match** | Game ratings, standings, matchups, WC player + country slices, community headline + facts, catalog, inverse changelog, generalstats, WC HoF present |
| **Soft only** | ~1e-6 float on some snapshot/current ratings; `finalized_at` / `last_finalized_at`; lifecycle complete vs one-shot running |
| **Hygiene** | Living `ko2amiga_work` restored to base **N** (607 tournaments; no #608) |

**Staging deploy reminder:** WinSCP sync at least:

- `amiga/ops/includes/amiga_promote_running_tournament.php`
- `amiga/ops/includes/amiga_community_realm_scan_lib.php`

---

## 10. What this does *not* prove

- Browser-only RTB-9 “complete” lifecycle step (one-shot PHP script may stop at `rating_finalized=1` with `lifecycle_status=running`).
- Every future writer change — re-run when Finish surface changes materially.
- Post-simul `verify-event-snapshots` failures on a polluted/replayed work DB (seen once after oracle simul; separate from Finish content parity).
- Staging DR / secretary UX — practice track remains [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md).

---

## Changelog

| Date | Change |
|------|--------|
| 2026-11-22 | §0: one command replaces the hand comparison for the calculation; run on all 608 tournaments. |
| 2026-07-17 | Protocol doc — locked simul-oracle vs PHP Finish; #608 sign-off; chrono + community limbo fixes recorded. |