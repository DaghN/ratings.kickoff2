# Amiga staging authority and repair shop — policy (Jul 2026)

**Status:** **Intent locked** — roles and sync loop agreed Jul 2026. **Pull automation:** not shipped (PoC = manual mysqldump / reverse WinSCP). **Admin UI / lock tiers / discard vs delete:** described, not decided.

**Audience:** Dagh, Cursor agents, future staging admins.

**Parent:** [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) (living ground, simul) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (Lane B/C, ALO8 bidirectional flow)

**Related:** [`amiga-staging-handoff.md`](amiga-staging-handoff.md) (export/import URLs, destructive import warning) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) (running vs official) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Supersedes (vocabulary):** “merge import” as the primary staging sync story in [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §9.5 — replaced by **pull → repair → push** below.

---

## 1. Executive summary

Amiga **community ground** is moving from “local laptop is canon, staging is a demo mirror” to **staged prod + local repair shop** — the same habit as online ladder after cutover (prod on server; work DB for writer repair).

| Realm | Prod (authority) | Repair shop |
|-------|------------------|-------------|
| **Online** | Server `kooldb*` | Local `ko2unity_work` / `kooldb1` + ops simul |
| **Amiga** | Staging **`ko2amiga_db`** on `ratings.kickoff2.com` | Local **`ko2amiga_work`** + **`simul`** |

**Local is not a second competing version of staged.** It is where we **pull** staged ground, **repair** it (schema, writers, anchored fixes, PHP/website work against Laragon), and **push** it back. There is no standing “diff product” between local and staged — only *“has work been refreshed from staged before we pushed?”*

---

## 2. Roles

### 2.1 Staged `ko2amiga_db` — prod

- **Authority** for community tournaments, running events, finalized ground, and (Lane C) live media rows on server disk.
- Organizers and secretaries act here via PHP `amiga/ops/` and public pages.
- What exists on staging **is** the Amiga realm for the community era.

### 2.2 Local `ko2amiga_work` — repair clone

- **Not** prod. A working copy used to run **`python -m scripts.amiga simul`**, apply DDL bundles, prove writer fixes, and build **`export_ko2amiga_work.ps1`** output.
- **Steady-state habit:** refresh from staged (**pull**) before a push that must preserve community ground.
- **Website/code** truth remains **git** (`site/public_html/`); ground truth for community events remains **staged** until pulled.

### 2.3 Local frozen `ko2amiga_db` — oracle only

- P-1 parity baseline, legacy **`prove`** archaeology — **lab equipment**, not prod and not a second staging.
- Do not confuse with staging’s database name (same name, different machine).

### 2.4 Day 0 L3 seal (`data/amiga/day0/`)

- Git-tracked **historical witness** bootstrap — not a backup of staged prod.

---

## 3. Sync loop (intent)

```text
                    ┌─────────────────────────────┐
                    │  STAGED ko2amiga_db (prod)   │
                    └──────────────┬──────────────┘
                                   │ pull (required before push
                                   │  when staging has moved)
                                   ▼
                    ┌─────────────────────────────┐
                    │  LOCAL ko2amiga_work         │
                    │  repair · simul · schema     │
                    └──────────────┬──────────────┘
                                   │ push
                                   ▼
        export_ko2amiga_work.ps1 → WinSCP → browser import (full replace)
```

| Step | Verb | Notes |
|------|------|-------|
| **Pull** | Staging → work | Brings community ground onto the repair shop. **PoC:** reverse WinSCP + mysqldump import into `ko2amiga_work`. Automation = follow-on slice. |
| **Repair** | On work | `simul`, DDL in bundles, anchored repair per [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §7, local PHP/UI dev. |
| **Push** | Work → staging | Existing [`amiga-staging-handoff.md`](amiga-staging-handoff.md) path. Staging import **replaces** the DB from export parts — safe when work already contains the staged ground you intend to keep. |

**Typical trigger for push:** schema or writer work signed off with **simul green** on work **after** pull when staging had community events.

**Push without pull** is allowed only when explicit and rare: empty/greenfield staging, disaster recovery from a known-good work export, or Dagh accepts wiping staging-only ground (document the exception).

---

## 4. Locked intent (SS rules)

| ID | Rule |
|----|------|
| **SS-1** | **Staging is authority** for community Amiga ground while the community era is live. |
| **SS-2** | **Local work is the repair shop** for schema, simul, and export — not a parallel canon. |
| **SS-3** | **Push = full export + full staging replace** (today’s browser import). No merge-import as the primary sync mechanism. |
| **SS-4** | **Pull before push** when staging may have changed (new tournaments, running events, secretary edits). |
| **SS-5** | **No diff/merge product** — reconcile by refreshing work from staged, then repairing, then pushing. |
| **SS-6** | **Backup** = capture known-good **staged** state (export parts + manifest, or periodic SQL dump) — not “work is always truth.” |
| **SS-7** | **Deletion / lock policy** informs what pull and push must preserve — see §6 (open). |

---

## 5. What we are not building (first)

| Approach | Why not primary |
|----------|-----------------|
| Row-level **merge import** on staging | Does not solve DDL; obscures derived replay semantics. |
| Standing **local vs staged diff** UI | Local is not a second branch — it is a repair clone. |
| **Prove** / full Access reimport for staging mistakes | Oracle archaeology; use anchored repair on staged or pull → repair → push. |

**Ground packs** (per-tournament pull) — **shelved with live-ops L6 (Jul 2026)** until further notice; vocabulary kept. **Full pull / full backup pack** is the default before schema pushes and for staging safety restore.

---

## 6. Permissions and lifecycle

**Locked intent (Jul 2026):** [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md).

| Layer | Password | Intent |
|-------|----------|--------|
| **Organizer** | `$organizer_password` (admin also OK where wired) | Running scores, entrants, void never-official, **Make official** (+ finish confirm). **No** delete of finalized tip events. |
| **Admin** | `$admin_password` | Import/export, tip **delete** (+ Case A/B repair when shipped), backup restore surfaces. |

**Rejected for v1** (do not reopen without new policy): lock/unlock delete matrix; per-tournament delete password; demotion-first; soft-discard-as-only-path for official tip events.

**Still open at implement time:** exact admin UI placement; retention N for rolling seals; Case C mid-history delete.

---

## 7. Backup

**Staging tip safety (v1 intent):** full backup pack **after** Finish / admin delete — [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md). Restore = Apply import (or equivalent) from a prior seal.

**Also:**

| Mechanism | Role |
|-----------|------|
| **Export parts + `ko2amiga_manifest.json`** after a verified push | Same artifacts as staging import; last push is a practical restore point today |
| **Work checkpoints (git)** | Milestone seals of local **`ko2amiga_work`** — `data/amiga/checkpoints/…` |
| **Periodic mysqldump** of staging | Optional DR; not automated yet |
| **`data/amiga/day0/`** | Bootstrap witness only — not staged prod backup |

Agents: do not treat local work as the backup of staged unless a pull just happened and simul is green.

---

## 8. Pull staged → local (PULL-1a shipped)

**One command:**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\pull_ko2amiga_from_staging.ps1 -Force
```

Agents: run the above when Dagh says **pull staged Amiga** (non-interactive). Flow: staging `run_export_ko2amiga.php` generate (JSON; HTML fallback on older builds) → download → rewrite DB name → **DROP/CREATE** `ko2amiga_work` → mysql import → `staging-sync-last.json`. **`simul` not default** — `-Simul` only when sign-off needs it.

**Verified Jul 2026** on staging — full automated pull green (605 / 469 / 27,418).

**Manual / browser** (same dump):

| Step | URL |
|------|-----|
| **Preview** (no dump) | https://ratings.kickoff2.com/amiga/run_export_ko2amiga.php?once=ko2amiga-export-one-shot |
| **Generate dump** | Open preview → **Generate dump** (admin password POST form / session) |
| **Download dump** | Open preview → **Download dump** (same session) |

Passwords in `amiga/_ops/amiga_ops_password.local.php` (gitignored): **`$admin_password`** (import/export) and **`$organizer_password`** (fixtures; admin also accepted). Prefer the POST password form — do not put `pwd=` in the address bar. Pull script POSTs the admin password in the request body. Writes **`public_html/amiga/_export/ko2amiga_staging_pull.sql`** (+ manifest JSON; **overwrite** each generate). Direct HTTP to `_export/*.sql` is blocked (`.htaccess`); use export page **Download dump**, pull script, or WinSCP.

**Manual fallback** (if pull script unavailable):

1. WinSCP sync `run_export_ko2amiga.php` + `includes/amiga_staging_export_lib.php`.
2. Open **Generate** URL; wait for OK.
3. **Download** or WinSCP `ko2amiga_staging_pull.sql`.
4. Import into local **`ko2amiga_work`**.
5. **`python -m scripts.amiga simul`** — only when sign-off needs it (`-Simul` on pull script).
6. Repair if needed; then **push** per [`amiga-staging-handoff.md`](amiga-staging-handoff.md).

Follow-on: **SYNC-1** — export gate from `staging-sync-last.json` before push.

---

## 9. Agent habits

1. **Staged prod, local repair** — do not assign “local is ahead of staging” features without pull/push vocabulary.
2. **Pull from staged** — Dagh says “pull staged Amiga” (or similar) → **run** `pull_ko2amiga_from_staging.ps1 -Force`; not simul unless asked.
3. **Export to staged** — run `export_ko2amiga_work.ps1` only when work reflects the repair you intend; script regenerates + audits `staging_export_tables.json` against `schema_bundles` before dump (blocks incomplete push). Remind Dagh if staging had community activity since last pull.
4. **Destructive import** — staging browser import still **wipes** unstaged ground; see handoff doc every time.
5. **Forward authority** — [`amiga-modern-ground-platform.md`](amiga-modern-ground-platform.md) §0 for simul/work; this doc for **staging sync roles**.

---

## 10. Related slices (not started)

| ID | Work |
|----|------|
| **PULL-1a** | **Shipped + verified Jul 2026** — `pull_ko2amiga_from_staging.ps1` + `staging-sync-last.json` |
| **PULL-1b** | **Shipped + verified Jul 2026** — `run_export_ko2amiga.php` (export-v4; JSON + download) |
| **CHECKPOINT-1** | **Shipped Jul 2026** — `seal_amiga_work_checkpoint.ps1` + `data/amiga/checkpoints/` (milestone git seals) |
| **SYNC-1** | `staging-sync-last.json` + export gate |
| **ADMIN-1** | Staged admin delete + after-action backup seals — intent locked [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md); implement with Track L **L5** |
| **PACK-1** | Per-tournament ground pack export (live-ops L6) — **shelved** with L6 until further notice |
| **BACKUP-1** | Staging after-Finish / after-delete full pack seals + rolling/reserve — same intent doc as ADMIN-1 |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | **Permissions + backup intent** — §6/§7 aligned with [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md); lock/unlock + per-tournament delete password rejected for v1; ADMIN-1/BACKUP-1; L6/PACK-1 shelved. |
| 2026-07-11 | **CHECKPOINT-1** — `seal_amiga_work_checkpoint.ps1` + `data/amiga/checkpoints/`; first seal `work-2026-07-11-tail`; §7 work git checkpoint row. |
| 2026-07-10 | **Export table registry** — `staging_export_tables.py` + JSON manifest; push/pull read same list; `export_ko2amiga_work.ps1` audit preflight (incl. `tournament_stage_scoring_steps`). |
| 2026-07-08 | **Agent pull ritual** — `kool-workspace.mdc` + `AGENTS.md`: trigger phrases → `pull_ko2amiga_from_staging.ps1 -Force`. |
| 2026-07-08 | **PULL-1 verified** — full staging → `ko2amiga_work` pull green; export-v4 mysqldump stderr fix; simul opt-in on pull script. |
| 2026-07-08 | **PULL-1b** — `run_export_ko2amiga.php` + `_export/` staging pull dump (preview/generate/download). |
| 2026-07-08 | Initial policy — staged prod, local repair shop, pull → repair → push; SS-1–SS-7; permissions open. |