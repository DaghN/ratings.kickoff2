# Operations standards (prepare v2 and future ops CLI)

**Audience:** Dagh, Cursor agents.

Prepare platform v2 (`scripts/work_prepare/`) sets the pattern for later `ops/dispatch.php` verbs.

---

## Vocabulary

| Verb | Meaning |
|------|---------|
| **refresh-work** | Clone baseline → work (restores prod-derived in core tables) |
| **migrate-work** | Apply `site/public_html/ops/sql/migrations/` on work only |
| **zero-derived** | §4 day-zero: core ladder + truncate aggregate tables |
| **prepare** | Orchestrator: full (refresh → migrate → zero-derived) or `--zero-only` |
| **parity** | Read-only checks after prepare |

Do not call refresh “reset work DB.”

---

## Targets

Profiles in `site/config/work-targets.ini` (optional; defaults in code):

| Profile | Work DB | Baseline |
|---------|---------|----------|
| `local-work` | `ko2unity_work` | `ko2unity_baseline` |
| `staging-work` | `kooldb1` | `kooldb2` |

**Guards:** Never mutate `ko2unity_db`, `ko2unity_baseline`, or `kooldb2` via prepare verbs.

---

## CLI conventions

- `--dry-run` — log only, no writes.
- `--target <profile>` — required for non-default DBs.
- Log `DATABASE()`, row counts, and profile name at start of mutating verbs.
- Mutating verbs exit non-zero on failure; `prepare` runs **parity** after success.

---

## Implementation layout

```text
scripts/work_prepare/
  __main__.py      # CLI dispatch
  targets.py       # profiles + ini
  guards.py        # refuse dev/baseline
  refresh.py       # mysqldump pipe (--no-create-db)
  migrate.py       # schema/apply_local.ps1
  zero_derived.py  # reset_universe + §4.5 truncates
  prepare.py       # orchestration
  parity.py        # post-prepare checks
```

PowerShell wrappers (`prepare_local_work_db.ps1`, `refresh_local_work_db.ps1`) are thin delegates.

---

## Retiring legacy scripts

Deprecate when parity checklist vs old manual path is green and one operational smoke (e.g. `ladder run --target sandbox`) succeeded. See [`work-db-prepare.md`](work-db-prepare.md) § Parity.
