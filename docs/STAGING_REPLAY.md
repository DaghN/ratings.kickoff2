# Staging ladder replay (one-shot)

**Goal:** Run Python replay **once** on staging **`kooldb`** (K=32, start 1600, no decay) — reset derived columns, replay ~74k **`ratedresults`**, rebuild **`playertable`** and **`generalstatstable`** row `id=1`.

**Status (May 2026):** Local **`ko2unity_db`** validated. **Files uploaded** to staging **`public_html/`**; **Steve** to run `bash run_staging_ladder_replay.sh`. Production stays on C++ after this.

**Other docs:** CLI details → **`scripts/ladder/README.md`** · column scope → **`docs/replay-v1-scope-and-reset.md`** · phases → **`docs/ladder-engine-plan.md`**

---

## Prerequisites (already done)

| Item | Notes |
|------|--------|
| DB | **`kooldb`** (write probe `DATABASE()`) |
| Writable | Write probe passed |
| Not prod | Steve confirmed |
| DB config | Python reads same **`ko2unitydb_config.php`** as PHP (via `public_html/../config/`) |
| No **`ladder.ini`** | Not used on staging |

---

## Dagh — WinSCP upload

SFTP usually shows only **`public_html/`** (no sibling **`config/`** — that is normal; PHP still reads it outside the jail).

**Connect:** `ratings.kickoff2.com:5322`, user `dagh@ratings.kickoff2.com`

**Remote:** inside **`public_html/`**

**From PC** `...\Online and Amiga 500 ELO\`:

1. Create remote folder **`scripts`** if missing (right-click → New → Directory).
2. Upload **`scripts\ladder\`** → **`public_html/scripts/ladder/`**
3. Upload **`run_staging_ladder_replay.sh`** → **`public_html/run_staging_ladder_replay.sh`** (not inside `scripts/`)

**Verify:** remote **`public_html/scripts/ladder/requirements.txt`** exists.

**Do not upload:** `config/`, `ladder.ini`, whole repo, throwaways (unless running paths probe once).

---

## Steve — run once

```bash
cd /path/to/public_html
bash run_staging_ladder_replay.sh
```

(~3 min; installs `pymysql` if needed; uses PHP DB config → **`kooldb`**.)

### What it does

1. Resets derived data on existing rows (does **not** delete players or games).
2. Replays all **`ratedresults`** in date order (Elo K=32, start 1600, no decay).
3. Rebuilds **`playertable`** career stats and **`generalstatstable`** row `id=1`.

On success, changes are **saved to the database**. Staging site numbers **will differ** from before — expected.

### Expected log (success)

```text
INFO reset_universe: ratedresults rows=…
INFO ratedresults cleared: … rows affected
INFO replay_all: … games, … players in memory
INFO ratedresults: 5000 / … games
…
INFO ratedresults replay done; finalizing playertable counts
INFO playertable updated: … players with at least one game
INFO generalstatstable id=1 updated (… fields)
INFO replay_all complete: … games
```

Exit code **0**. On failure: paste last ~30 lines (no passwords).

---

## After run (Dagh)

Spot-check staging: ranked list, one profile, **`server1.php`** / **`server2.php`**. Then update **`docs/ladder-engine-plan.md`** P2 and **`PROJECT_MEMORY.md`**.

**Rollback:** DB restore from backup only if needed.

---

## Note — why `config/` is invisible in WinSCP

PHP uses `DOCUMENT_ROOT/../config/ko2unitydb_config.php`. The file exists on disk next to **`public_html`**; your SFTP account is often chrooted to the web tree only. Python resolves the same path when scripts live under **`public_html/scripts/ladder/`**.

Optional one-shot: **`scripts/throwaway_server_paths_probe.php`** → `public_html/`, open `?once=server-paths-probe-one-shot`, delete file.
