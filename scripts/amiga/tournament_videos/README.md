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

Re-run when Steve or community uploads new tournament footage, or after forum index edits. Then:

```powershell
python -m scripts.amiga.tournament_videos.apply_review
python -m scripts.amiga.tournament_videos.sync_db_ids
```

## DB anchor sync (after every full L3 reimport)

Numeric ids in `review.csv` / `tournament_videos.json` are **DB caches**, not stable keys. After `import-witness` or `python -m scripts.amiga prove`:

**Policy (Jul 2026):** [`docs/amiga-tournament-videos-game-links-policy.md`](../../../docs/amiga-tournament-videos-game-links-policy.md) — match facts authoritative; sync **remaps** ids; verify **fails loud** on mismatch. **GL-0…GL-6 shipped** (`game_links.py`, sidecar `video_game_links.csv`, `stream_map` mode).

```powershell
python -m scripts.amiga.tournament_videos.sync_db_ids
python -m scripts.amiga.tournament_videos.audit_game_links
python -m scripts.amiga.verify_tournament_videos
```

`prove` runs **sync_db_ids** automatically after L5 replay. Use `--dry-run` on sync to preview changes without writing.

Resolve all match game ids (not only missing):

```powershell
python -m scripts.amiga.tournament_videos.resolve_games --all
python -m scripts.amiga.tournament_videos.sync_db_ids
```

## Manifest build (TV-2)

```powershell
python -m scripts.amiga.tournament_videos.build_manifest
python -m scripts.amiga.tournament_videos.validate_manifest
python -m scripts.amiga.verify_tournament_videos
```

Default includes all mapped rows; `--verified-only` if you want strict CSV gate. Manifest `verified` mirrors CSV (`Y` → true).

## Stream game map (GL-5 sidecar)

Long **`kind=stream`** broadcasts with many linked games use a sidecar file (not one row per game in `review.csv`):

| Path | Role |
|------|------|
| `data/amiga/tournament_videos/video_game_links.csv` | One row per linked game (`link_ordinal`, players, score, stage, optional `start_sec`) |
| `review.csv` → `game_link_mode=stream_map` | Tells sync/verify to load links from sidecar |

**Stream timestamps:** forum indexes use **H:MM** into the broadcast (e.g. `0:13`, `1:26`, `5:36`). Store in sidecar `start_sec` as **`H:MM`** (parsed at build) or as YouTube **seconds** (`total_minutes × 60` — e.g. `1:26` → `5160`). Do not store bare total-minutes as seconds.

Workflow:

1. Set stream row `game_link_mode=stream_map` (and `verified=Y` when curated).
2. Append sidecar rows (see [`amiga-tournament-videos-game-links-policy.md`](../../../docs/amiga-tournament-videos-game-links-policy.md) §10.2).
3. `python -m scripts.amiga.tournament_videos.sync_db_ids` — writes `game_id_guess` from facts; rebuilds manifest with optional `game_start_sec[]`.

Sidecar empty today — machinery only; match rows and dual-leg uploads unchanged.

## Manual additions

Dagh-supplied URLs not from harvest → edit `scripts/amiga/tournament_videos/manual_rows.py`, then:

```powershell
python -m scripts.amiga.tournament_videos.apply_review
python -m scripts.amiga.tournament_videos.sync_db_ids
```

Fix wrong mappings in chat or via future bulk-verify page. Policy: [`docs/amiga-tournament-videos-policy.md`](../../../docs/amiga-tournament-videos-policy.md)

## Drop non-KO2 / off-catalog videos

When a harvested row is not Kick Off 2 tournament material (channel noise, unrelated uploads):

```powershell
python -m scripts.amiga.tournament_videos.drop_video YOUTUBE_ID --reason "Not KO2 related — …"
python -m scripts.amiga.tournament_videos.sync_db_ids
```

- Removes the row from `review.csv`
- Appends audit line to `data/amiga/tournament_videos/dropped.csv`
- Re-harvest skips dropped ids (`harvest.py` denylist)
- Remove id from `amiga_video_orphans_catalog.php` if it was in a curated group