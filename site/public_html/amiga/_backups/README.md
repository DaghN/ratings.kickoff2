# Amiga staging backup seals (L5)

Dated full DB packs (Apply-import family: `ko2amiga_manifest.json` + `ko2amiga_*.sql` parts) sealed **after** tip-changing actions (Make official) or admin **Backup now**.

| Path | Role |
|------|------|
| `seal-*/seal.json` | Metadata (reason, reserve, bytes, parts) |
| `seal-*/ko2amiga_manifest.json` | Part list for Apply import (slice 2 restore) |
| `seal-*/ko2amiga_*.sql` | Chunked SQL parts |

**Admin UI:** `/amiga/run_backup_ko2amiga.php?once=ko2amiga-backup-one-shot`

**Restore (slice 2):** Restore… → copies seal into `amiga/_import/` → **Apply import** replaces live `ko2amiga_db` (same multi-part engine as push import — BA4 full replace).

**Retention:** rolling last N non-reserve seals; every 5th (or manual reserve checkbox) is **reserve** — not deletable via PHP (BA6). WinSCP remains the outer key.

SQL payloads are gitignored; this README + `.htaccess` are tracked.