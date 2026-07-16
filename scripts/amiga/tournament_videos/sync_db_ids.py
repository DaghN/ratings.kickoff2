"""Sync review.csv DB-cache columns from live ko2amiga_db, then rebuild manifest."""

from __future__ import annotations

import argparse
import sys

from scripts.amiga.tournament_videos.build_manifest import run as build_manifest
from scripts.amiga.tournament_videos.manifest_db import (
    DbSnapshot,
    connect_db,
    load_review_rows,
    sync_review_csv_from_db,
    validate_catalog,
    write_review_csv,
)


def run(*, write: bool = True, resolve_matches: bool = True, rebuild: bool = True) -> int:
    from scripts.amiga.modern.work_safety import refuse_legacy_video_deploy_on_work

    refuse_legacy_video_deploy_on_work(cli_name="sync_db_ids")
    rows = load_review_rows()
    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()

    changes, escalations = sync_review_csv_from_db(rows, snap, resolve_matches=resolve_matches)

    if write:
        write_review_csv(rows)
        print(f"Wrote {len(rows)} rows to review.csv")
    if changes:
        print(f"  changes: {len(changes)}")
        for line in changes[:30]:
            print(f"    {line}")
        if len(changes) > 30:
            print(f"    ... and {len(changes) - 30} more")
    else:
        print("  changes: none")

    if escalations:
        print(f"  unresolved match rows: {len(escalations)}")
        for line in escalations[:20]:
            print(f"    {line}")
        if len(escalations) > 20:
            print(f"    ... and {len(escalations) - 20} more")

    if rebuild and write:
        build_manifest()

    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()
    errors, total = validate_catalog(snap, csv_rows=rows)
    if total:
        for e in errors:
            print(f"ERROR: {e}", file=sys.stderr)
        print(f"validate after sync: {total} error(s)", file=sys.stderr)
        return 1

    print("OK: review.csv + manifest aligned with DB")
    return 0


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(
        description="Sync tournament video catalog DB anchors (player/game/tournament ids)"
    )
    p.add_argument(
        "--dry-run",
        action="store_true",
        help="Report changes without writing CSV or manifest",
    )
    p.add_argument(
        "--no-resolve",
        action="store_true",
        help="Only refresh ids from names/games; skip full match re-resolution",
    )
    p.add_argument(
        "--no-rebuild",
        action="store_true",
        help="Skip build_manifest after CSV sync",
    )
    args = p.parse_args(argv)
    return run(
        write=not args.dry_run,
        resolve_matches=not args.no_resolve,
        rebuild=not args.no_rebuild,
    )


if __name__ == "__main__":
    raise SystemExit(main())