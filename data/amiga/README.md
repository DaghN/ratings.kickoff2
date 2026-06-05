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

Discovery scripts may write CSV/JSON extracts here (also gitignored):

```text
data/amiga/exports/
```

---

## Snapshot log (fill in when you add the file)

| Field | Value |
|-------|--------|
| **Filename** | `koatd.mdb` |
| **Format** | `.mdb` (Jet) |
| **Size** | ~5.6 MB |
| **Games (`Scores`)** | 27,408 |
| **Players (distinct in `Scores`)** | 477 raw → **473** after import merges |
| **Tournaments (`Tournament players`)** | 604 (Nov 2001 – Nov 2025) |
| **File modified** | 2026-05-16 |
| **Notes** | Phase A0 discovery + **A1 import/replay** — see [`docs/amiga-schema-discovery.md`](../docs/amiga-schema-discovery.md), [`scripts/amiga/README.md`](../../scripts/amiga/README.md) |

---

## Build local `ko2amiga_db`

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1
```

Name merges logged to `exports/name_merges.json` (gitignored). Staging dump: `site/public_html/amiga/_import/ko2amiga_db.sql` — see [`docs/amiga-staging-handoff.md`](../docs/amiga-staging-handoff.md).
