# Amiga realm — source data (gitignored contents)

Microsoft Access snapshot for the offline / Amiga 500 ladder (~27k games). **Not in Git** — same rules as [`../dumps/`](../dumps/).

---

## Where to put the file

Copy your Access database into:

```text
data/amiga/source/
```

Accepted formats:

| Extension | Engine |
|-----------|--------|
| `.mdb` | Jet (older Access) |
| `.accdb` | ACE (Access 2007+) |

Use the original filename if you like, or a stable name such as `amiga-ladder.mdb` / `amiga-ladder.accdb`. Only one canonical snapshot is needed for discovery; note the date in the table below when you replace it.

---

## Optional exports

Import and discovery write JSON extracts here (gitignored):

```text
data/amiga/exports/
  import_manifest.json   # canonical audit — every import
  name_merges.json       # legacy slice; also embedded in manifest
```

See [`docs/amiga-import-layer.md`](../../docs/amiga-import-layer.md) and example [`docs/amiga-import-manifest.example.json`](../../docs/amiga-import-manifest.example.json).

---

## Snapshot log (fill in when you add the file)

| Field | Value |
|-------|--------|
| **Filename** | `koatd.mdb` |
| **Format** | `.mdb` (Jet) |
| **Size** | ~5.6 MB |
| **Games (`Scores`)** | 27,408 in Access · **27,418** in `ko2amiga_db` after import (+10 supplemental Rodenbach II games; see `import_corrections.py`) |
| **Players (distinct in `Scores`)** | 477 raw → **473** after import merges |
| **Tournaments (`Tournament players`)** | 604 (Nov 2001 – Nov 2025) |
| **File modified** | 2026-05-16 |
| **Notes** | Phase A0 discovery + **A1 import/replay** — see [`docs/amiga-schema-discovery.md`](../docs/amiga-schema-discovery.md), [`scripts/amiga/README.md`](../../scripts/amiga/README.md) |

---

## Build local `ko2amiga_db`

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1
```

Name merges logged to `exports/name_merges.json` (gitignored).

## Push to staging

Whenever local **`ko2amiga_db`** is the state you want (any build path):

1. `scripts\export_ko2amiga_db.ps1` → `site/public_html/amiga/_import/ko2amiga_db.sql`
2. WinSCP sync `public_html/`
3. **Preview:** https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee
4. **Apply:** same URL with `&apply=1`

Agents: remind Dagh of those URLs — [`docs/amiga-staging-handoff.md`](../docs/amiga-staging-handoff.md).
