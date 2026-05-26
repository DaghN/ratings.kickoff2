# Staging ladder replay (one-shot)

**Goal:** Run Python replay **once** on staging **`kooldb`** (K=32, start 1600, no decay) — reset derived columns, replay ~74k **`ratedresults`**, rebuild **`playertable`** and **`generalstatstable`** row `id=1`.

**Staging DB note:** **`kooldb` on ratings.kickoff2.com does not receive live game writes** from the production game server (May 2026). Staging is updated by **scripts Steve runs** (replay, schema SQL, dumps), not by each new rated match. WinSCP deploys **PHP only**.

**Server separation:** Steve confirmed staging and production are on **entirely different physical servers**. The staging replay wrapper now passes `--target staging`; Python refuses `kooldb` unless that target is explicit. Production replay/cutover would need a separately named, reviewed wrapper.

**Status:** **Done (May 2026).** Local **`ko2unity_db`** validated earlier; **Steve** ran `bash run_staging_ladder_replay.sh` from staging **`public_html/`** on **`kooldb`** — success. **Production live ratings remain C++** (P5 deferred).

**Other docs:** CLI → **`scripts/ladder/README.md`** · scope → **`docs/replay-v1-scope-and-reset.md`** · phases / deferred work → **`docs/ladder-engine-plan.md`**

---

## What was deployed (record)

| Item | Location on server |
|------|------------------|
| Replay package | `public_html/scripts/ladder/` |
| Wrapper | `public_html/run_staging_ladder_replay.sh` |
| DB config | Existing `../config/ko2unitydb_config.php` (not via WinSCP) |

**Command used:** `bash run_staging_ladder_replay.sh` from `public_html/` (~3 min).

---

## Optional cleanup (not done)

After a successful run, you may **delete** from staging `public_html/`:

- `run_staging_ladder_replay.sh`
- `scripts/ladder/` (entire tree)

Not required for the site to work; removes maintenance scripts from the web tree. Re-upload from repo if you need another one-shot recalc.

---

## WinSCP upload (reference — if re-run needed)

SFTP usually shows only **`public_html/`**. Create **`scripts`**, upload **`scripts\ladder\`** → **`public_html/scripts/ladder/`**, upload **`run_staging_ladder_replay.sh`** → **`public_html/`**. Host `ratings.kickoff2.com:5322`.

---

## Steve run reference (archive)

```bash
cd /path/to/public_html
bash run_staging_ladder_replay.sh
```

**Success:** exit 0; log ends with `replay_all complete: … games`, `playertable updated`, `generalstatstable id=1 updated`.

**Preflight log:** replay now prints database identity (`DATABASE()`, `CURRENT_USER()`, `@@hostname`, `@@port`, `VERSION()`) before writes. MariaDB checks should use `COUNT(*)`, not bare `COUNT()`.

**Effect:** recalculated ratings/stats saved to **`kooldb`**; staging site numbers differ from pre-replay — expected.

**Hall of Fame record dates:** After replay with current `scripts/ladder/`, run `python -m scripts.ladder.golden_record_checks` — should pass. Known **wrong** dates from legacy C++ post-game are listed in [`docs/staging-post-game-record-defects.md`](staging-post-game-record-defects.md) for prod deploy regression.

---

## Note — SFTP vs PHP paths

PHP/Python use `DOCUMENT_ROOT/../config/ko2unitydb_config.php`. That folder is often **outside** the SFTP jail; invisible in WinSCP does not mean missing. Optional probe: **`scripts/throwaway_server_paths_probe.php`**.
