# Staging ladder replay (one-shot)

**Audience:** Dagh, Steve, Cursor agents.  
**Goal:** Run the Python replay **once** on staging **`kooldb`** so ratings, `playertable`, and `generalstatstable` match replay v1 rules (K=32, start 1600, no decay).  
**Handover:** WinSCP + **one shell command** — no Git for Steve, no manual DB credentials.

**Status (May 2026):** Local replay validated on **`ko2unity_db`**. Staging pending. Production stays on C++ after staging recalc.

---

## What we already know

| Item | Status |
|------|--------|
| Database | **`kooldb`** (`DATABASE()` from write probe) |
| Writable | Yes — Steve helped enable access |
| Not production | Steve confirmed |
| Live games during run | None on staging |
| Replay logic | ~74k games, ~3 min locally |
| DB config for Python | **Automatic** — same file as PHP (see below) |

No need to re-confirm DB name, dry-run, `--limit`, Git pull, or **`ladder.ini`**.

---

## Database connection (no manual step)

Python reads the **same** config as the PHP site:

1. **`config/ko2unitydb_config.php`** — staging layout (sibling of `public_html`)
2. **`site/config/ko2unitydb_config.php`** — repo / Laragon layout
3. **`site/config/ladder.ini`** — only if neither PHP file exists (not needed on staging)

Implementation: `scripts/ladder/config.py`. Whatever **`$database`** is in PHP config is used. Allowlist: **`ko2unity_db`**, **`kooldb`**. Script verifies **`DATABASE()`** after connect.

**If PHP pages and the write probe work, Python connects the same way.** Dagh does not copy host/user/password/database into a second file.

---

## Server layout

Typical staging host:

```text
<project-root>/
  config/
    ko2unitydb_config.php    ← already on server; Python reads this
  public_html/
    …                        ← unchanged for this task
  scripts/
    ladder/                  ← upload entire folder (WinSCP)
  run_staging_ladder_replay.sh   ← optional wrapper at project root
```

**Project root** = parent of **`public_html`** (contains **`config/`** and **`scripts/`**).

PHP uses `DOCUMENT_ROOT/../config/ko2unitydb_config.php` — that path is what Python resolves on the server.

---

## Roles

| Who | Responsibility |
|-----|----------------|
| **Dagh** | WinSCP upload `scripts/ladder/` (+ optional wrapper); optional WhatsApp to Steve |
| **Steve** | Run **one** command from `<project-root>` (~3 min) |
| **Dagh (after)** | Browser spot-check on staging |

Steve does not need Git, Python edits, INI files, or DB troubleshooting.

---

## WinSCP upload

### Why you only see `public_html` today

Normal deploy is **Synchronize** `site/public_html/` → remote **`public_html/`**. That session often opens **inside** `public_html`, so you never see siblings.

On the server, **`config/`** and **`scripts/`** sit **next to** `public_html/`, not inside it:

```text
<project-root>/          ← open WinSCP here (one level above public_html)
  config/                ← ko2unitydb_config.php (already there; hidden from web)
  public_html/           ← what you usually sync
  scripts/ladder/        ← you upload this
  run_staging_ladder_replay.sh
```

**`config/` is not missing** — it is one directory up from `public_html`. In WinSCP: go to **`public_html`**, then **Up** (or `..`) once. You should see **`config`**, **`public_html`**, and (after upload) **`scripts`**.

Host: **`ratings.kickoff2.com`**, port **`5322`**, user **`dagh@ratings.kickoff2.com`** (see **`PROJECT_MEMORY.md`**).

### Local → remote mapping

| Upload from your PC (repo) | To on server (`<project-root>/`) |
|----------------------------|----------------------------------|
| **`scripts\ladder\`** (entire folder) | **`scripts/ladder/`** |
| **`run_staging_ladder_replay.sh`** (repo root) | **`run_staging_ladder_replay.sh`** |

**Do not** put these under `public_html/`. PHP does not need them in the web tree.

| Do not upload | Why |
|---------------|-----|
| **`config/ko2unitydb_config.php`** | Already on server |
| **`site/config/`** | Local Laragon layout only; server uses **`config/`** beside `public_html` |
| **`ladder.ini`** | Not used |
| **`site/public_html/`** | Only for normal site deploy; unrelated to replay upload |

**Cannot use “Synchronize public_html only”** for the ladder package — drag **`scripts/ladder`** and the **`.sh`** file to **`<project-root>`** manually (or a second WinSCP bookmark opened at project root).

After upload, remote should look like:

```text
scripts/ladder/__main__.py
scripts/ladder/engine.py
scripts/ladder/requirements.txt
… (full package)
run_staging_ladder_replay.sh
config/ko2unitydb_config.php   ← already present
public_html/…
```

Do not send credentials or script archives via WhatsApp.

---

## One-time dependency install

If Python packages are missing on the host:

```bash
python3 -m pip install -r scripts/ladder/requirements.txt
```

Optional wrapper script can run this before each replay (idempotent).

---

## The one command

Run from **`<project-root>`** (parent of `public_html`).

**Option A — wrapper (if uploaded):**

```bash
bash run_staging_ladder_replay.sh
```

**Option B — direct:**

```bash
python3 -m pip install -r scripts/ladder/requirements.txt
python3 -m scripts.ladder run
```

Do **not** use `--dry-run`, `--limit`, or `--ini` for the production handover run.

**Duration:** ~3 minutes.

### Success log lines

- `ratedresults replay done`
- `playertable updated: … players with at least one game`
- `generalstatstable id=1 updated (… fields)` (or table created on first run)
- `replay_all complete: … games`

Non-zero exit = failure; capture last error lines.

---

## What the run does

1. **Reset** derived columns on **`ratedresults`** and career stats on **`playertable`** (players and game rows kept).
2. **Replay** all **`ratedresults`** in `Date ASC, id ASC` order.
3. **Rebuild** **`playertable`** (v2 career stats).
4. **Ensure / fill** **`generalstatstable`** row `id=1` (creates table if missing).

Staging numbers **will differ** from pre-replay (no decay, full replay, etc.). That is expected.

---

## WhatsApp to Steve (template)

> Ladder replay is uploaded under [path]. From that folder (same level as `config/` and `public_html`), run once: `bash run_staging_ladder_replay.sh` — about 3 minutes. It uses the same DB config as the PHP site (`kooldb`, not prod). Tell me when it finishes or if you get a Python error.

No passwords, no multi-step checklist, no Git.

---

## After the run (Dagh)

Browser checks on staging:

- Ranked list / known player profile
- **`server1.php`** / **`server2.php`** (require **`generalstatstable`**)
- One game list or stat you already validated locally

When satisfied, mark staging replay done in **`docs/ladder-engine-plan.md`** (P2).

---

## Rollback

Restore from a DB dump/backup only if old derived numbers are needed. Not part of the default path.

---

## Dagh checklist

1. WinSCP: open **`<project-root>`** (parent of `public_html`; see above)
2. Upload `scripts/ladder/` → `scripts/ladder/`
3. Upload `run_staging_ladder_replay.sh` → project root (same folder as `config/`)
3. Confirm staging PHP still loads (proves PHP config exists)
4. Steve runs one command (or Dagh if shell access)
5. Validate staging UI
6. Update ladder plan / memory when done

---

## Out of scope

- Git / GitHub for Steve
- Manual **`ladder.ini`** or copying `$dbhost` / passwords
- Per-game **`generalstatstable`** updates (batch at end only)
- Production C++ changes
- Amiga / offline replay (later phases)

---

## Related docs

| Doc | Purpose |
|-----|---------|
| **`scripts/ladder/README.md`** | CLI usage, defaults |
| **`docs/replay-v1-scope-and-reset.md`** | Column reset scope |
| **`docs/ladder-engine-plan.md`** | Phases P0–P5 |
| **`docs/LOCAL_DEV.md`** | Laragon / local only |
