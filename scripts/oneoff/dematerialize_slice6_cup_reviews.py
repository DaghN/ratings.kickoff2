"""Dematerialize slice-6 cups demoted to manual review (Jun 2026 audit)."""

from __future__ import annotations

import sys

from scripts.amiga.tournament_structure.materialize_legacy import (
    _connect,
    dematerialize_legacy_fixtures,
)
from scripts.amiga.tournament_structure.tier_b_non_wc_register import (
    NON_WC_SLICE6_CUP_REVIEW_IDS,
)


def main(argv: list[str] | None = None) -> int:
    dry_run = "--dry-run" in (argv or sys.argv[1:])
    conn = _connect()
    ok = 0
    skipped = 0
    failed: list[tuple[int, str]] = []
    try:
        for tid in sorted(NON_WC_SLICE6_CUP_REVIEW_IDS):
            try:
                result = dematerialize_legacy_fixtures(conn, tid, dry_run=dry_run)
                print(
                    f"OK id={tid} {result.tournament_name!r}: "
                    f"removed {abs(result.stages_created)} stage(s), "
                    f"unlinked {abs(result.games_linked)} game(s)"
                )
                ok += 1
            except ValueError as exc:
                msg = str(exc)
                if "no legacy structure" in msg:
                    print(f"SKIP id={tid}: already dematerialized")
                    skipped += 1
                else:
                    failed.append((tid, msg))
                    print(f"FAIL id={tid}: {msg}", file=sys.stderr)
        if dry_run:
            conn.rollback()
            print("DRY RUN: rolled back")
        else:
            conn.commit()
    finally:
        conn.close()

    print(f"Done: {ok} dematerialized, {skipped} skipped, {len(failed)} failed")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
