"""CLI: drop a non-KO2 / non-catalog video from review.csv into dropped.csv."""

from __future__ import annotations

import argparse
import sys

from scripts.amiga.tournament_videos.dropped import drop_from_review


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Drop a video from tournament review.csv")
    parser.add_argument("youtube_id", help="YouTube video id")
    parser.add_argument(
        "--reason",
        required=True,
        help="Why this is off-catalog (e.g. not KO2 related)",
    )
    args = parser.parse_args(argv)
    try:
        row = drop_from_review(args.youtube_id, reason=args.reason)
    except (ValueError, KeyError, FileNotFoundError) as exc:
        print(str(exc), file=sys.stderr)
        return 1
    title = (row.get("title") or args.youtube_id)[:70]
    print(f"Dropped {args.youtube_id}: {title}")
    print(f"  reason: {args.reason}")
    print("  Next: python -m scripts.amiga.tournament_videos.build_manifest")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())