# Operations quick start — what exists today

**Day-to-day only** — run commands here. Migration tracking: **`docs/UPDATE_DOCS.md`** when you or the agent run **“update docs”**. Registers: **`docs/prod-coordination.md`**.

---

## Status at a glance (May 2026)

| Question | Answer |
|----------|--------|
| **Local replay — one command?** | **Yes** — `scripts\run_local_replay.ps1` (or `python -m scripts.ladder run`) |
| **How to change replay logic?** | Edit **`scripts/ladder/`** — see [Updating replay](#updating-replay) |
| **One-off template?** | **Yes** — `scripts/oneoff/_template.py` + README |
| **Staging package for Steve?** | **Partial** — `run_staging_ladder_replay.sh` + upload `scripts/ladder/`; no single zip; schema SQL separate |
| **Schema migrations folder?** | **Yes** — `schema/migrations/` + `schema/apply_local.ps1` |
| **Coordination registers?** | **Docs only** — track WHAT for prod; not automated |

---

## Local replay (“push button”)

**Once per machine:** Laragon **Start All**, `pip install -r scripts/ladder/requirements.txt`, `site/config/ko2unitydb_config.php` pointing at `ko2unity_db`.

From repo root:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1
```

Dry-run first (no writes):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\run_local_replay.ps1 -DryRun
```

**~3–5 min**, ~74k games. **Recovery:** re-import dump (`data/README.md`) if you need a clean slate.

**Manual equivalent:** `python -m scripts.ladder run` — full options in `scripts/ladder/README.md`.

---

## Updating replay

| Change | Where |
|--------|--------|
| Elo K, start rating | `scripts/ladder/constants.py`, `elo.py` |
| Per-game row fields | `scripts/ladder/engine.py`, `outcome.py` |
| Player career stats | `scripts/ladder/player_state.py`, `finalize_counts.py` |
| Server row `generalstatstable` | `scripts/ladder/generalstats.py` |
| What gets reset | `scripts/ladder/engine.py` (`reset_universe`), `docs/replay-v1-scope-and-reset.md` |
| New column needs backfill | Above + `schema/migrations/` + register in `docs/coordination/replay-register.md` |

After code changes: `run_local_replay.ps1` on local; then staging (below).

---

## Schema (local)

```powershell
powershell -ExecutionPolicy Bypass -File schema\apply_local.ps1
```

Adds indexes etc. from `schema/migrations/*.sql`. Register: `docs/coordination/schema-register.md`.

---

## One-off script

1. Copy `scripts/oneoff/_template.py` → `scripts/oneoff/my_job.py`
2. Register in `docs/coordination/one-off-register.md`
3. `python scripts/oneoff/my_job.py --dry-run` then without `--dry-run`

Prefer replay when the job is “recompute from all games in order.”

---

## Staging — what to give Steve

**Not** a single installer. Repeatable **upload list**:

| Upload to server `public_html/` | From repo |
|----------------------------------|-----------|
| `run_staging_ladder_replay.sh` | repo root |
| `scripts/ladder/` (whole tree) | `scripts/ladder/` |

Steve runs from `public_html/`:

```bash
bash run_staging_ladder_replay.sh
```

**Schema on staging:** send SQL file(s) from `schema/migrations/` — Steve runs on `kooldb` (same as prod name on staging).

**Full checklist:** `docs/STAGING_REPLAY.md` · **Cutover email template:** `docs/coordination/cutover-packet-template.md`

**Remember:** staging DB does **not** get live games — replay is how numbers catch up.

---

## Folder map (real files)

```text
scripts/ladder/          ← replay engine (Python)
scripts/run_local_replay.ps1
scripts/oneoff/          ← one-off template
schema/migrations/       ← SQL for Steve + local apply
run_staging_ladder_replay.sh   ← Steve staging replay wrapper
docs/coordination/       ← registers + cpp-snippets (planning)
docs/prod-coordination.md      ← hub when coordinating prod
docs/OPERATIONS_QUICK_START.md ← this file
```

---

## Still to-do (not built yet)

- **Prod** replay / schema / C++ cutover (registers track; Steve executes)
- **Bundled “staging deploy” script** (WinSCP automate) — optional
- **Filled C++ snippet packs** for PG-001, PG-002 — when you scope those changes
- **Periodic jobs** beyond documenting fade (PER-001)
