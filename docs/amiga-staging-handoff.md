# Amiga staging — deploy & refresh

**Status:** **Live** on `ratings.kickoff2.com` (Jun 2026) — rating, profile, games, cross-realm search.

**Agents — remind Dagh:** When local `ko2amiga_db` should match staging (any import path, not only Access file changes): export → WinSCP sync → browser import. Script: `public_html/amiga/run_import_ko2amiga.php` (build tag in page header, e.g. `a2-2026-06-06-b4`). Password **`coffee`** — add `&pwd=coffee` to the URL, or enter it on the form when the `once` link is valid without `pwd`. **Preview:** `/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee` · **Apply:** `&apply=1&part=1` (17 short parts auto-continue; avoids gateway timeout). Staging base: `https://ratings.kickoff2.com` · local: `http://ratingskickoff.test`. Import payload: `public_html/amiga/_import/ko2amiga_manifest.json` + `ko2amiga_*.sql` part files (gitignored; WinSCP). Full dump `ko2amiga_db.sql` optional (Heidi fallback).

**Agents — when Dagh says “export to staged” (or similar):** **run** `scripts\export_ko2amiga_db.ps1` yourself (unless he clearly needs a full Access rebuild first → `setup_ko2amiga_db.ps1`), then reply that the dump is **ready for WinSCP sync and staging import** — include preview/apply URLs above. Do not hand-wave “run export locally”; execute it.

---

## Layout (same as online site)

| Piece | Path on server |
|--------|----------------|
| Web root | `public_html/` (WinSCP sync from `site/public_html/`) |
| Amiga DB config | `config/ko2amiga_config.local.php` — **sibling of `public_html`**, not inside it |
| Config router (git) | `config/ko2amiga_config.php` |
| Amiga PHP include | `include __DIR__ . '/../../config/ko2amiga_config.php';` in `public_html/amiga/*.php` |
| Database | **`ko2amiga_db`** (separate from online `kooldb*`) |
| Import payload (gitignored) | `public_html/amiga/_import/ko2amiga_manifest.json` + `ko2amiga_01_schema.sql` … `ko2amiga_16_stats.sql` (+ optional full `ko2amiga_db.sql`) |

Online `kooldb*` is untouched. Credentials mirror staging config1 user/password; only `$database` differs.

---

## Live URLs

- https://ratings.kickoff2.com/amiga/rating.php
- https://ratings.kickoff2.com/amiga/tournaments.php
- https://ratings.kickoff2.com/amiga/tournament.php?id=372 (London XXIII — adjust id after import)
- https://ratings.kickoff2.com/amiga/profile.php?id=1
- https://ratings.kickoff2.com/amiga/games.php?id=1

---

## Dagh — code or data refresh

1. **Code:** WinSCP sync **`site/public_html/`** → staging **`public_html/`** (usual button).
2. **Data** — whenever local **`ko2amiga_db`** is the state you want on staging (Access import, replay-only, manual SQL, etc.):

```powershell
# Full rebuild from Access (import + Elo + export):
powershell -ExecutionPolicy Bypass -File scripts\setup_ko2amiga_db.ps1

# Or export only if ko2amiga_db is already correct:
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

WinSCP sync `public_html/` (must include `amiga/run_import_ko2amiga.php` + `amiga/_import/ko2amiga_manifest.json` + all `ko2amiga_*.sql` part files from export), then open a preview URL (or apply URL — password form if `pwd` is omitted):

| Step | URL |
|------|-----|
| **Preview** (no DB changes) | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee |
| **Apply import** | https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee&apply=1&part=1 |

Preview must show **`parts: 16`** and the importer build tag. Apply runs part 1 (schema) through part 16 (stats); each part auto-continues (~2s). Expect **473 players**, **27,408 games** after part 16.

Password is **`coffee`** (`&pwd=coffee` in URL, or type it on the prompt page).

Local dry-run (same paths, `ratingskickoff.test`): preview URL above with local host.

Spot-check: `/amiga/rating.php`, `/amiga/profile.php?id=1`, `/amiga/games.php?id=1`.

**Verified Jun 2026:** A2 schema + multi-part browser import on staging (`ratings.kickoff2.com`).

**Fallback:** Steve Heidi/mysql import of `ko2amiga_db.sql` — only if browser import fails.

---

## Steve — one-time setup (done)

1. Create MySQL database **`ko2amiga_db`**.
2. Import `public_html/amiga/_import/ko2amiga_db.sql`.
3. Copy `config/ko2amiga_config.local.php.example` → `config/ko2amiga_config.local.php` (same folder as online `ko2unitydb_config.local.php`).

**Do not** put Amiga config under `public_html/amiga/` — pages load `../../config/ko2amiga_config.php` only.

---

## WhatsApp — only if browser import fails

```
Amiga staging import failed in browser.

Import files synced: public_html/amiga/_import/ko2amiga_manifest.json + ko2amiga_*.sql
Failed on part: [N/16]
Error: [paste from run_import_ko2amiga.php apply page]

Please re-import into ko2amiga_db (Heidi/mysql) or check PHP limits.
```
