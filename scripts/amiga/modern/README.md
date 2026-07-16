# scripts/amiga/modern/ — forward compartment (CODE-1 / MG11)

**Authority:** [`docs/amiga-modern-ground-platform.md`](../../../docs/amiga-modern-ground-platform.md) §5.1 MG11.

Living ground tooling for **`ko2amiga_work`**. Legacy nuclear prove (`prove.py`, `import_access.py`, `scripts/amiga/replay.py`) is **frozen** on **`ko2amiga_db`**.

---

## Daily commands

| CLI | Module |
|-----|--------|
| `python -m scripts.amiga seed-work` | `seed_work.py` |
| `python -m scripts.amiga simul` | `simul.py` (video on by default) |
| `python -m scripts.amiga parity` | `parity.py` |
| `python -m scripts.amiga align-video-work` | `video_catalog.py` |
| `python -m scripts.amiga promote-video-deploy` | `video_catalog.py` |
| `python -m scripts.amiga snapshot-video-promote` | `work_safety.py` |
| `python -m scripts.amiga write-ground-fingerprint` | `work_safety.py` |

**Safety:** `seed-work` / `simul --recreate-schema` refuse when living ground exists unless `--i-mean-destroy-work` + `--confirm-destroy=destroy-ko2amiga-work`. Video sidecar editorial = shared git only at align time.

Staging export: `scripts/export_ko2amiga_work.ps1` (outside this package).

---

## MG11 import rules

**Forbidden** in `modern/` (audit enforces):

- `scripts.amiga.prove`
- `scripts.amiga.import_access` / `import_witness` / `import_pristine` / `import_prune`
- `scripts.amiga.replay` (legacy — use `modern.replay`)

**Allowed shared helpers** (read-only or thin wrappers):

- `verify_*` oracles, `schema_bundles`, `config`, `k2_rating_core`
- `apply_structure.apply_structure_from_disposition` via `modern/apply_structure.py` (work DB connect only)
- `tournament_videos.*` only through `video_catalog.work_video_paths()` path overrides
- `matchup_cumulative`, `realm_incremental`, `tournament_structure`, `tournament_format`

Need new behaviour from legacy prove path → **copy into `modern/`**, rename, adapt DB connect — never edit frozen legacy modules.

---

## Audit

```powershell
python scripts/audit_amiga_modern_compartment.py
```

Run before shipping slices that touch `modern/`.