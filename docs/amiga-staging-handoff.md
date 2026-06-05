# Amiga staging — Dagh checklist (two steps)

## 1. WinSCP

Sync **`site/public_html/`** → staging **`public_html/`** (your usual button).

That uploads:

- `amiga/rating.php` — leaderboard
- `amiga/profile.php` — player profile v0
- `amiga/_import/ko2amiga_db.sql` — database dump (gitignored; export before sync)
- `amiga/ko2amiga_config.php` + `.local.php.example` (Steve copies example → `.local.php`)

**Before sync** (only when you changed Amiga data locally):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
```

`setup_ko2amiga_db.ps1` runs export automatically at the end.

## 2. WhatsApp Steve

Use the message in this doc — update nothing unless the SQL path changed.

---

## WhatsApp text (copy-paste)

```
Amiga offline ladder — first staging drop.

I synced public_html. Two jobs your side:

1) Create empty MySQL DB: ko2amiga_db (name flexible if you prefer).

2) Import the dump:
   public_html/amiga/_import/ko2amiga_db.sql
   into that database (Heidi / mysql — usual way).

3) Copy public_html/amiga/ko2amiga_config.local.php.example
   → ko2amiga_config.local.php
   Same DB user/password as staging config1; $database = ko2amiga_db.

Then open:
  https://ratings.kickoff2.com/amiga/rating.php
  https://ratings.kickoff2.com/amiga/profile.php?id=1

Amiga Elo leaderboard (~27k games, 473 players after name merges). Legacy Access ratings are not shown.

Online kooldb* untouched.
```
