"""TV-1 harvest CLI: union YouTube feeds + forum index -> review.csv."""

from __future__ import annotations

import argparse
import csv
import logging
import sys

from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, RAW_DIR, REVIEW_CSV
from scripts.amiga.tournament_videos.enrich import (
    build_review_row,
    load_player_index,
    load_wc_catalog,
    merge_youtube_rows,
)
from scripts.amiga.tournament_videos.forum_parse import load_forum
from scripts.amiga.tournament_videos.wmv_probe import probe_url
from scripts.amiga.tournament_videos.youtube_harvest import YtVideo, harvest_all

log = logging.getLogger(__name__)


def _synthetic_from_forum(youtube_id: str, forum) -> YtVideo:
    title = f"{forum.player_a} - {forum.player_b} ({forum.score})"
    if forum.event_label:
        title = f"{forum.event_label}: {title}"
    return YtVideo(
        youtube_id=youtube_id,
        title=title,
        duration_sec=None,
        source="forum_index",
        source_channel=None,
        source_playlist=None,
    )


def run_harvest(*, skip_youtube: bool = False, skip_forum: bool = False, probe_wmv: bool = False) -> int:
    REVIEW_CSV.parent.mkdir(parents=True, exist_ok=True)
    RAW_DIR.mkdir(parents=True, exist_ok=True)

    yt_rows: list[YtVideo] = []
    if not skip_youtube:
        yt_rows = harvest_all()
    grouped = merge_youtube_rows(yt_rows)

    forum_by_yt = {}
    if not skip_forum:
        _, forum_by_yt = load_forum()

    playlist_ids = {v.youtube_id for v in yt_rows if v.source == "wc_finals_playlist"}

    all_ids = set(grouped.keys()) | set(forum_by_yt.keys())
    wc_by_year = load_wc_catalog()
    players = load_player_index()

    out_rows: list[dict[str, object]] = []
    for youtube_id in sorted(all_ids):
        variants = list(grouped.get(youtube_id, []))
        forum = forum_by_yt.get(youtube_id)
        if not variants and forum:
            variants = [_synthetic_from_forum(youtube_id, forum)]
        row = build_review_row(
            youtube_id,
            variants,
            forum=forum,
            wc_by_year=wc_by_year,
            players=players,
            playlist_ids=playlist_ids,
        )
        if probe_wmv and row.get("external_url"):
            url = str(row["external_url"])
            if not probe_url(url):
                note = str(row.get("notes") or "")
                row["notes"] = (note + "; WMV probe failed").strip("; ")
        out_rows.append(row)

    with REVIEW_CSV.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(out_rows)

    ids = [r["youtube_id"] for r in out_rows]
    dupes = len(ids) - len(set(ids))
    print(f"Wrote {len(out_rows)} rows -> {REVIEW_CSV}")
    if dupes:
        print(f"ERROR: {dupes} duplicate youtube_id rows", file=sys.stderr)
        return 1

    checks = ["-OD-f0t92VQ", "tEb--soimgs", "wTqyB6iHKjU", "gmVFrhEr_IQ"]
    missing = [c for c in checks if c not in set(ids)]
    if missing:
        print(f"WARNING: expected IDs missing: {', '.join(missing)}", file=sys.stderr)
    else:
        print("Known verification IDs present.")

    rg2010 = [r for r in out_rows if r.get("relation_group") and "2010" in str(r.get("relation_group"))]
    dual = len([r for r in out_rows if r.get("relation_group")])
    print(f"Rows with relation_group hints: {dual}")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Harvest tournament videos into review.csv (TV-1)")
    parser.add_argument("--skip-youtube", action="store_true", help="Skip yt-dlp harvest (use raw/ dumps)")
    parser.add_argument("--skip-forum", action="store_true", help="Skip forum fetch")
    parser.add_argument("--probe-wmv", action="store_true", help="HEAD-probe forum WMV mirrors (slow)")
    parser.add_argument("-v", "--verbose", action="store_true")
    args = parser.parse_args(argv)
    logging.basicConfig(level=logging.DEBUG if args.verbose else logging.INFO, format="%(levelname)s %(message)s")
    return run_harvest(skip_youtube=args.skip_youtube, skip_forum=args.skip_forum, probe_wmv=args.probe_wmv)


if __name__ == "__main__":
    raise SystemExit(main())