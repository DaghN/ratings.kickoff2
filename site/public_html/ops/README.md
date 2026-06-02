# `ops/` — server operations (not the public site)

**Audience:** Dagh, Steve, Cursor agents.

**Deploy:** WinSCP-sync with `site/public_html/` → staging `public_html/`.

**Full conventions (canonical):** [`docs/ladder-ops-platform.md`](../../../docs/ladder-ops-platform.md) **§6 Ops layout & conventions** — naming, bootstrap, dispatcher rules, slice boundaries. **Do not duplicate rules here.**

---

## Today

Folder scaffold only: `.htaccess`, `modules/`, `sql/`. **No** `dispatch.php`, `includes/`, or module PHP yet.

---

## Checklist (target layout)

| Path | Role |
|------|------|
| `dispatch.php` | Thin `CMD=` router → modules (**planned**) |
| `includes/ops_bootstrap.php`, `ops_argv.php` | CLI, DB connect, protected DBs (**planned**) |
| `modules/<snake_case>.php` | One primary file per `CMD` (e.g. `process_completed_game.php`) |
| `sql/migrations/` | Mirror of repo `schema/migrations/` before staging sync |
| `sql/rebuild/` | Optional REP SQL mirrors |

**Legacy:** [`../staging-scripts/`](../staging-scripts/) — old runners; migrate in named slices only.

---

## Quick rules

- **New ladder ops code** → `ops/`, not `staging-scripts/`.
- **No business logic** in `dispatch.php`.
- **Work / sim DB:** `ko2unity_work` (+ `ladder-work.ini`); **never** `ko2unity_baseline` / `kooldb2`.
- **Dev DB (`ko2unity_db`):** off-limits for ops unless explicit `allow_dev_db=1` when implemented.
- **Test modules** before shipping `dispatch.php` (see platform doc §6.6).

---

## Local sim (until PHP replay CMD)

```text
python -m scripts.ladder run --target sandbox
```

See [`scripts/ladder/README.md`](../../../scripts/ladder/README.md).

---

## Planned Steve call (not live yet)

```text
php …/ops/dispatch.php   CMD=ProcessCompletedGame   game_id=<id>
```

