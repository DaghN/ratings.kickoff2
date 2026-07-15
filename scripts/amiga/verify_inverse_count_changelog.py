"""Verify inverse-count changelog vs pointer oracle (present + TT sample)."""

from __future__ import annotations

import sys

from scripts.amiga.inverse_count_changelog import INVERSE_COUNT_METRICS
from scripts.amiga.modern.work_db import connect_work

# metric -> pointer column on snapshots/current
_PTR = {
    "mgs_culprits": "MostGoalsScoredVictimID",
    "bw_culprits": "BiggestWinVictimID",
    "mgc_victims": "MostGoalsConcededCulpritID",
    "bl_victims": "BiggestLossCulpritID",
}
_CUR_COL = {m: col for m, _attr, col in INVERSE_COUNT_METRICS}


def _present_mismatches(conn) -> list[str]:
    errors: list[str] = []
    with conn.cursor() as cur:
        for metric, _attr, col in INVERSE_COUNT_METRICS:
            ptr = _PTR[metric]
            cur.execute(
                f"""
                SELECT c.player_id,
                       COALESCE(c.`{col}`, 0) AS stored_count,
                       COALESCE(inv.n, 0) AS oracle_count
                FROM amiga_player_current c
                LEFT JOIN (
                    SELECT `{ptr}` AS hero_id, COUNT(*) AS n
                    FROM amiga_player_current
                    WHERE `{ptr}` IS NOT NULL AND `{ptr}` > 0
                    GROUP BY `{ptr}`
                ) inv ON inv.hero_id = c.player_id
                WHERE COALESCE(c.`{col}`, 0) <> COALESCE(inv.n, 0)
                """
            )
            for row in cur.fetchall():
                errors.append(
                    f"present {metric} player={row['player_id']} "
                    f"stored={row['stored_count']} oracle={row['oracle_count']}"
                )
    return errors


def _tt_case_nazim(conn) -> list[str]:
    """Athens I (tid=27): Nazim 327 MGC should be 2 via changelog, sparse snap still 3."""
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT value_after FROM amiga_player_inverse_count_at_event
            WHERE player_id = 327 AND tournament_id = 27 AND metric = 'mgc_victims'
            """
        )
        row = cur.fetchone()
        if row is None:
            errors.append("TT Nazim: missing changelog row (327, 27, mgc_victims)")
        elif int(row["value_after"]) != 2:
            errors.append(f"TT Nazim: changelog value_after={row['value_after']} expected 2")

        cur.execute(
            """
            SELECT value_after FROM (
                SELECT value_after,
                    ROW_NUMBER() OVER (
                        ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC
                    ) AS rn
                FROM amiga_player_inverse_count_at_event
                WHERE player_id = 327 AND metric = 'mgc_victims'
                  AND (event_date, event_chrono, tournament_id) <= (
                      (SELECT event_date FROM tournaments WHERE id = 27),
                      (SELECT chrono FROM tournaments WHERE id = 27),
                      27
                  )
            ) x WHERE rn = 1
            """
        )
        latest = cur.fetchone()
        if latest is None or int(latest["value_after"]) != 2:
            errors.append(f"TT Nazim: latest<=27 = {latest}")
    return errors


def _changelog_row_count(conn) -> int:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_inverse_count_at_event")
        return int(cur.fetchone()["n"])


def verify_inverse_count_changelog(conn) -> list[str]:
    errors: list[str] = []
    n = _changelog_row_count(conn)
    if n < 1000:
        errors.append(f"changelog row count suspiciously low: {n}")
    errors.extend(_present_mismatches(conn))
    errors.extend(_tt_case_nazim(conn))
    return errors


def main(argv: list[str] | None = None) -> int:
    del argv
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
        errors = verify_inverse_count_changelog(conn)
        n = _changelog_row_count(conn)
        if errors:
            print(f"FAIL: {len(errors)} verify-inverse-count-changelog issue(s):", file=sys.stderr)
            for err in errors[:40]:
                print(f"  {err}", file=sys.stderr)
            if len(errors) > 40:
                print(f"  ... and {len(errors) - 40} more", file=sys.stderr)
            return 1
        print(f"verify-inverse-count-changelog OK (rows={n})")
        return 0
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())