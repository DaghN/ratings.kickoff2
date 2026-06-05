# Amiga staging — deploy & refresh

**Status:** **Live** on `ratings.kickoff2.com` (Jun 2026) — rating, profile, games, cross-realm search.

---

## Layout (same as online site)

| Piece | Path on server |
|--------|----------------|
| Web root | `public_html/` (WinSCP sync from `site/public_html/`) |
| Amiga DB config | `config/ko2amiga_config.local.php` — **sibling of `public_html`**, not inside it |
| Config router (git) | `config/ko2amiga_config.php` |
| Amiga PHP include | `include __DIR__ . '/../../config/ko2amiga_config.php';` in `public_html/amiga/*.php` |
| Database | **`ko2amiga_db`** (separate from online `kooldb*`) |
| SQL dump (gitignored) | `public_html/amiga/_import/ko2amiga_db.sql` |

Online `kooldb*` is untouched. Credentials mirror staging config1 user/password; only `$database` differs.

---

## Live URLs

- https://ratings.kickoff2.com/amiga/rating.php
- https://ratings.kickoff2.com/amiga/profile.php?id=1
- https://ratings.kickoff2.com/amiga/games.php?id=1

---

## Dagh — code or data refresh

1. **Code:** WinSCP sync **`site/public_html/`** → staging **`public_html/`** (usual button).
2. **Data (only when Amiga DB changed locally):**

```powershell
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

Then sync again so `amiga/_import/ko2amiga_db.sql` reaches the server. Ping Steve to re-import.

`setup_ko2amiga_db.ps1` runs export automatically at the end of a full local rebuild.

---

## Steve — one-time setup (done)

1. Create MySQL database **`ko2amiga_db`**.
2. Import `public_html/amiga/_import/ko2amiga_db.sql`.
3. Copy `config/ko2amiga_config.local.php.example` → `config/ko2amiga_config.local.php` (same folder as online `ko2unitydb_config.local.php`).

**Do not** put Amiga config under `public_html/amiga/` — pages load `../../config/ko2amiga_config.php` only.

---

## WhatsApp — data refresh only

```
Amiga data refresh on staging.

I synced public_html including a new dump:
  public_html/amiga/_import/ko2amiga_db.sql
Please re-import into ko2amiga_db (usual Heidi/mysql way).

Pages to spot-check:
  /amiga/rating.php
  /amiga/profile.php?id=1
  /amiga/games.php?id=1
```
