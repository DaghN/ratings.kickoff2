"""Validate tournament_videos.json against live DB anchors (TV-2)."""

from __future__ import annotations

import sys

from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    connect_db,
    load_manifest_videos,
    validate_catalog,
)


def run() -> int:
    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()

    videos = load_manifest_videos()
    errors, total = validate_catalog(snap, manifest_videos=videos)
    if total:
        for e in errors:
            print(f"ERROR: {e}", file=sys.stderr)
        return 1

    groups = sum(1 for v in videos if v.get("relation_group"))
    print(f"OK: {len(videos)} videos, {groups} relation groups, DB anchors verified")
    return 0


if __name__ == "__main__":
    raise SystemExit(run())
