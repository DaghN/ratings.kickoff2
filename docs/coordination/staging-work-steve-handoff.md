# Staging work DB — Steve handoff (ops self-contained)

**Narrative (five phases: Broadcast, Prepare, Simul, Live, Bug fixing):** [`staging-work-steve-brief.md`](staging-work-steve-brief.md)

**Goal:** Refresh `kooldb1` from `kooldb2`, apply SCH migrations, seed milestone catalog, then full simul. Site reads **`kooldb1`** during the run.

**Parity check:** Compare at game **~74800** against frozen legacy dev DB (human-verified truth), not a separate small replay.

---

## 1. WinSCP sync

Sync local **`site/public_html/`** → server **`public_html/`** (includes all of `ops/`: migrations, `data/milestones_definitions_seed.json`, `dispatch.php`, etc.).

Do **not** rely on repo-root `data/` or sibling `site/config/` on the server.

---

## 2. One-time server config

From `public_html/ops/config/work-targets.ini.example` → **`public_html/ops/config/work-targets.ini`** (gitignored; create on server only).

`[staging-work]` MySQL **host / user / password** = same as `config/ko2unitydb_config1.php`. Only database names differ:

| Key | Typical value |
|-----|----------------|
| `work_database` | `kooldb1` |
| `baseline_database` | `kooldb2` |

Smoke:

```bash
cd public_html
php ops/run_prepare.php migrate-work --target staging-work --dry-run
```

(Or any prepare subcommand that connects — expect connect OK, no writes if dry-run supported.)

---

## 3. Prepare (refresh + migrate + seed catalog)

```bash
cd public_html
php ops/run_prepare.php prepare --target staging-work
```

Includes: dump baseline → work, `migrate-work`, `seed-catalog` (112 rows from `ops/data/milestones_definitions_seed.json`), zero-derived GST, lobby seed.

**Requires on host:** `mysqldump` and `mysql` CLI (Laragon paths on Windows; `/usr/bin` on Linux). Zero-derived GST DDL ships in `ops/sql/generalstatstable.sql` (synced with `public_html`).

---

## 4. Full simul (prod-shaped)

**Canonical:** [`ops-simul-runbook.md`](ops-simul-runbook.md). After prepare, one command replays games **and** runs the same “end of UTC day” work as live (league medals, daily milestones, etc.). Lobby milestone **`entered_arena`** is already set during prepare — not part of this replay.

```bash
php ops/run_ops_sim.php run --target staging-work
php ops/run_ops_sim.php run --target staging-work --until-game-id 74800
```

Expect a long run (hours for full history). For ladder-only dev (no league honours), `replay-to` still exists but is **not** a complete simul.

---

## 5. Live-shaped post-game (optional, after simul trust)

Thin router (same post-game pipeline as simul):

```bash
php ops/dispatch.php CMD=ProcessCompletedGame game_id=N target=staging-work
```

Nightly: [`steve-nightly-ops.md`](steve-nightly-ops.md). Simul: §4 + [`ops-simul-runbook.md`](ops-simul-runbook.md). Detail: [`ops-dispatch.md`](ops-dispatch.md).

---

## 6. User / Dagh

- Set canonical site PHP config to **`kooldb1`** for the staging test window; tell Steve when done (do not ask Steve to pick config files).
- Parity SQL / spot checks at **~74800** vs frozen dev export.

---

## Reference

| Doc | Topic |
|-----|--------|
| [`ops/README.md`](../../site/public_html/ops/README.md) | Ops layout, WinSCP |
| [`work-db-prepare.md`](../work-db-prepare.md) | Prepare phases |
| [`ops-dispatch.md`](ops-dispatch.md) | Dispatcher |
| [`ops-schema-migrations.md`](ops-schema-migrations.md) | SCH under `ops/sql/migrations/` |
