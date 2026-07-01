"""Prove oracle: tournament video manifest matches live ko2amiga_db anchors."""

from __future__ import annotations

import sys

from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    connect_db,
    validate_catalog,
)


def main() -> int:
    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()

    errors, total = validate_catalog(snap)
    if total:
        for e in errors:
            print(f"FAIL: {e}", file=sys.stderr)
        print(f"verify-tournament-videos: {total} error(s)", file=sys.stderr)
        return 1

    print("verify-tournament-videos OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())