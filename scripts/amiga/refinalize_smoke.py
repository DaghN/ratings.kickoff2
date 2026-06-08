"""Smoke test: goal correction + refinalize-from on a small finalized tournament."""

from __future__ import annotations

import argparse
import logging
import sys

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import verify_tournament_finalize
from scripts.amiga.refinalize import refinalize_from
from scripts.amiga.replay import _connect, tournament_ids_for_replay

log = logging.getLogger(__name__)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Refinalize smoke test on one tournament")
    parser.add_argument(
        "--tournament-id",
        type=int,
        default=None,
        help="Finalized tournament to tweak (default: last in catalog order — refinalizes one event only)",
    )
    parser.add_argument("--game-id", type=int, default=None, help="Game to tweak (default: first in T)")
    parser.add_argument("--dry-run", action="store_true", help="Only print planned steps")
    args = parser.parse_args(argv)

    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(message)s")
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        if args.tournament_id is not None:
            tid = args.tournament_id
        else:
            all_ids, _ = tournament_ids_for_replay(conn, limit_games=None)
            if not all_ids:
                log.error("no tournaments with games in catalog order")
                return 1
            tid = all_ids[-1]
            log.info("smoke: using last tournament_id=%s (single-event cascade)", tid)
        with conn.cursor() as cur:
            cur.execute(
                "SELECT rating_finalized FROM tournaments WHERE id = %s LIMIT 1",
                (tid,),
            )
            row = cur.fetchone()
            if row is None or int(row["rating_finalized"]) != 1:
                log.error("tournament_id=%s must be rating_finalized for smoke test", tid)
                return 1

            if args.game_id is not None:
                game_id = args.game_id
            else:
                cur.execute(
                    "SELECT id FROM amiga_games WHERE tournament_id = %s ORDER BY id ASC LIMIT 1",
                    (tid,),
                )
                grow = cur.fetchone()
                if grow is None:
                    log.error("tournament_id=%s has no games", tid)
                    return 1
                game_id = int(grow["id"])

            cur.execute(
                "SELECT goals_a, goals_b FROM amiga_games WHERE id = %s LIMIT 1",
                (game_id,),
            )
            game = cur.fetchone()
            if game is None:
                log.error("game_id=%s not found", game_id)
                return 1

        ga = int(game["goals_a"])
        gb = int(game["goals_b"])
        new_ga = ga + 1
        new_gb = gb
        log.info(
            "smoke: tournament_id=%s game_id=%s goals %s-%s -> %s-%s",
            tid,
            game_id,
            ga,
            gb,
            new_ga,
            gb,
        )
        if args.dry_run:
            return 0

        with conn.cursor() as cur:
            cur.execute(
                "UPDATE amiga_games SET goals_a = %s WHERE id = %s",
                (new_ga, game_id),
            )
        conn.commit()

        result = refinalize_from(conn, tid, dry_run=False)
        log.info("refinalize_from: %s", result)

        errors = verify_tournament_finalize(conn, tid)
        if errors:
            log.error("verify failed: %s", "; ".join(errors))
            return 1
        log.info("verify_tournament_finalize OK for tournament_id=%s", tid)

        with conn.cursor() as cur:
            cur.execute(
                "UPDATE amiga_games SET goals_a = %s WHERE id = %s",
                (ga, game_id),
            )
        conn.commit()
        refinalize_from(conn, tid, dry_run=False)
        log.info("restored goals_a=%s for game_id=%s and refinalized", ga, game_id)
        return 0
    finally:
        conn.close()


if __name__ == "__main__":
    sys.exit(main())
