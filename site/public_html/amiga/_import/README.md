# Amiga DB dump for staging import

**`ko2amiga_db.sql`** — refreshed by `scripts/export_ko2amiga_db.ps1` before WinSCP sync. Gitignored; WinSCP still carries it with `public_html/`.

**Import on staging (verified Jun 2026):** sibling script `../run_import_ko2amiga.php` — preview `/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`, apply with `&apply=1`. Password **`coffee`** in URL or on the form if `pwd` is omitted. Full loop: [`docs/amiga-staging-handoff.md`](../../../../docs/amiga-staging-handoff.md).

This folder is not web-accessible (`.htaccess`); the import PHP reads the file from disk.
