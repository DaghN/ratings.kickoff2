"""Dry-run audit: editorial match facts vs cached game_ids (GL-1).

@see docs/amiga-tournament-videos-game-links-policy.md §9 GL-1
"""

from __future__ import annotations

import argparse
import sys

from scripts.amiga.tournament_videos.game_links import audit_catalog
from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    connect_db,
    load_manifest_videos,
    load_review_rows,
)


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(
        description="Audit tournament video game links (facts vs cached ids, scores)"
    )
    p.add_argument(
        "--errors-only",
        action="store_true",
        help="Print only error-severity issues (default: errors + summary)",
    )
    args = p.parse_args(argv)

    rows = load_review_rows()
    manifest = load_manifest_videos()
    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()

    issues = audit_catalog(snap, rows, manifest)
    errors = [i for i in issues if i.severity == "error"]
    warns = [i for i in issues if i.severity == "warn"]

    for issue in errors:
        print(f"ERROR {issue.youtube_id} [{issue.code}] {issue.message}")
    if not args.errors_only:
        for issue in warns:
            print(f"WARN  {issue.youtube_id} [{issue.code}] {issue.message}")

    print(f"audit_game_links: {len(errors)} error(s), {len(warns)} warn(s)")
    return 1 if errors else 0


if __name__ == "__main__":
    raise SystemExit(main())