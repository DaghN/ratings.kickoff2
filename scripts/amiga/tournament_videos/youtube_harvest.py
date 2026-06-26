"""YouTube flat-playlist harvest via yt-dlp."""

from __future__ import annotations

import json
import logging
import shutil
import subprocess
from dataclasses import dataclass
from pathlib import Path

from scripts.amiga.tournament_videos.constants import RAW_DIR, YOUTUBE_SOURCES

log = logging.getLogger(__name__)


@dataclass(frozen=True)
class YtVideo:
    youtube_id: str
    title: str
    duration_sec: int | None
    source: str
    source_channel: str | None
    source_playlist: str | None


def _yt_dlp_bin() -> str:
    path = shutil.which("yt-dlp")
    if not path:
        raise SystemExit("yt-dlp not found on PATH — install yt-dlp to harvest YouTube sources.")
    return path


def harvest_source(source: dict[str, str | None], *, raw_dir: Path = RAW_DIR) -> list[YtVideo]:
    url = str(source["url"])
    ytdlp = _yt_dlp_bin()
    cmd = [
        ytdlp,
        "--no-check-certificate",
        "--flat-playlist",
        "--print",
        "%(id)s\t%(duration)s\t%(title)s",
        url,
    ]
    log.info("Harvesting %s", url)
    proc = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if proc.returncode != 0:
        raise SystemExit(f"yt-dlp failed for {url}:\n{proc.stderr}")

    rows: list[YtVideo] = []
    for line in proc.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        parts = line.split("\t", 2)
        if len(parts) < 3:
            continue
        vid, dur_raw, title = parts
        dur: int | None
        try:
            dur = int(float(dur_raw)) if dur_raw not in ("", "NA", "None") else None
        except ValueError:
            dur = None
        rows.append(
            YtVideo(
                youtube_id=vid,
                title=title,
                duration_sec=dur,
                source=str(source["source"]),
                source_channel=source.get("source_channel"),
                source_playlist=source.get("source_playlist"),
            )
        )

    raw_dir.mkdir(parents=True, exist_ok=True)
    slug = str(source["source"])
    dump = raw_dir / f"{slug}.json"
    dump.write_text(
        json.dumps([row.__dict__ for row in rows], indent=2, ensure_ascii=False),
        encoding="utf-8",
    )
    log.info("  %s -> %d videos (%s)", slug, len(rows), dump.name)
    return rows


def harvest_all(*, raw_dir: Path = RAW_DIR) -> list[YtVideo]:
    out: list[YtVideo] = []
    for source in YOUTUBE_SOURCES:
        out.extend(harvest_source(source, raw_dir=raw_dir))
    return out