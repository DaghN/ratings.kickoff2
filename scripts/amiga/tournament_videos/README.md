# Tournament video harvest (TV-1)

Builds `data/amiga/tournament_videos/review.csv` from YouTube channels, the WC finals playlist, and the ko-gathering forum index (t=15358).

## Requirements

- Python 3 + repo deps (`pymysql` — same as other `scripts/amiga/` tools)
- **yt-dlp** on PATH
- Local **`ko2amiga_db`** config: `site/config/ko2amiga_config.local.php`
- Network access to YouTube + ko-gathering.com

## Run (repo root)

```powershell
cd "C:\Users\daghn\Desktop\Online and Amiga 500 ELO"
python -m scripts.amiga.tournament_videos.harvest
```

Optional flags:

- `--probe-wmv` — HEAD-check forum WMV mirror URLs (slow)
- `--skip-youtube` / `--skip-forum` — partial re-run (YouTube needs prior `raw/*.json` only when skip-youtube if you extend loader; forum-only useful for parser dev)
- `-v` — verbose logging

## SSL workaround (Windows)

If yt-dlp fails with certificate errors, the harvester passes **`--no-check-certificate`** automatically. Update yt-dlp or Windows root certs if you prefer stricter TLS.

Fallback: export flat playlists manually into `data/amiga/tournament_videos/raw/{source}.json` (array of `{youtube_id, title, duration_sec, source, ...}`) and extend the loader — not wired by default.

## Outputs

| Path | Role |
|------|------|
| `data/amiga/tournament_videos/review.csv` | Human review queue (one row per `youtube_id`) |
| `data/amiga/tournament_videos/raw/*.json` | Per-source yt-dlp dumps (gitignored) |

## Re-harvest cadence

Re-run when Steve or community uploads new tournament footage, or after forum index edits. **TV-2** (`build_manifest.py`) reads Dagh-verified CSV rows only — do not skip CSV review.

## Human review (TV-2 gate)

Edit `review.csv`: set `verified=Y`, fix `guessed_tournament_id`, `relation` / `relation_group`, Greek Champs 2011, Milan I 2003, Online WC 2024 (`kind=excluded`).

Policy: [`docs/amiga-tournament-videos-policy.md`](../../../docs/amiga-tournament-videos-policy.md)