#!/usr/bin/env python3
"""List amiga_games needing human SC-11 extension review (ET and/or pens)."""

from __future__ import annotations

import argparse

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.extension_review_handoff import (
    format_forum_context_lines,
    game_forum_context,
)
from scripts.amiga.match_extensions import witness_indicates_extension
from scripts.amiga.match_extensions_verified import extension_review_status, load_verified_game_ids

DEFAULT_TOURNAMENT_GAMES_URL = (
    "http://ratingskickoff.test/amiga/tournament/games.php?id={tournament_id}"
)


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def tournament_games_url(tournament_id: int, *, base_url: str = DEFAULT_TOURNAMENT_GAMES_URL) -> str:
    return base_url.format(tournament_id=int(tournament_id))


def _phase_label(phase: object) -> str:
    if phase is None or str(phase).strip() == "":
        return "(no phase label)"
    return str(phase).strip()


def _witness_label(extra: object) -> str:
    if extra is None or str(extra).strip() == "":
        return "(empty)"
    return str(extra)


def list_extension_review_candidates(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
    include_verified: bool = False,
) -> list[dict]:
    verified = load_verified_game_ids()
    sql = """
        SELECT g.id, g.tournament_id, g.source_scores_id, t.name AS tournament_name, g.phase,
               pa.name AS player_a, pb.name AS player_b,
               g.goals_a, g.goals_b, g.goals_et_a, g.goals_et_b, g.pens_a, g.pens_b, g.extra
        FROM amiga_games g
        JOIN tournaments t ON t.id = g.tournament_id
        JOIN amiga_players pa ON pa.id = g.player_a_id
        JOIN amiga_players pb ON pb.id = g.player_b_id
        WHERE g.extra IS NOT NULL AND TRIM(g.extra) <> ''
    """
    params: list[int] = []
    if tournament_id is not None:
        sql += " AND g.tournament_id = %s"
        params.append(int(tournament_id))
    sql += " ORDER BY g.tournament_id, g.id"

    with conn.cursor() as cur:
        cur.execute(sql, params or None)
        rows = list(cur.fetchall())

    out: list[dict] = []
    for row in rows:
        game_id = int(row["id"])
        extra = row.get("extra")
        if not witness_indicates_extension(extra):
            continue
        status = extension_review_status(extra, game_id)
        if status == "not_applicable":
            continue
        if status == "verified" and not include_verified:
            continue
        row["review_status"] = status
        row["verified"] = game_id in verified
        out.append(row)
    return out


def format_extension_review_handoff(
    row: dict,
    *,
    base_url: str = DEFAULT_TOURNAMENT_GAMES_URL,
) -> str:
    tid = int(row["tournament_id"])
    gid = int(row["id"])
    url = tournament_games_url(tid, base_url=base_url)
    phase = _phase_label(row.get("phase"))
    players = f"{row['player_a']} vs {row['player_b']}"
    witness = _witness_label(row.get("extra"))
    forum_ctx = game_forum_context(tid, gid)

    lines = [
        f"{row['review_status']:10} g{gid} t{tid} {row['tournament_name']!r}",
        f"  games: {url}",
        f"  game_id: {gid}",
        f"  source_scores_id: {row.get('source_scores_id')}",
        f"  players: {players}",
        f"  phase: {phase}",
        f"  access_witness_extra: {witness!r}",
        (
            f"  structured_cols: reg={row['goals_a']}-{row['goals_b']} "
            f"et={row['goals_et_a']}-{row['goals_et_b']} "
            f"pens={row['pens_a']}-{row['pens_b']}"
        ),
    ]
    lines.extend(format_forum_context_lines(forum_ctx))
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="List games needing human SC-11 extension review (ET and/or pens)"
    )
    parser.add_argument("--tournament-id", type=int, default=None)
    parser.add_argument("--include-verified", action="store_true")
    parser.add_argument(
        "--games-url",
        default=DEFAULT_TOURNAMENT_GAMES_URL,
        help="Tournament games URL template with {tournament_id}",
    )
    args = parser.parse_args(argv)

    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        rows = list_extension_review_candidates(
            conn,
            tournament_id=args.tournament_id,
            include_verified=args.include_verified,
        )
    finally:
        conn.close()

    if not rows:
        print("list-extension-review: no candidates")
        return 0

    for i, row in enumerate(rows):
        if i:
            print()
        print(format_extension_review_handoff(row, base_url=args.games_url))
    print()
    print(f"list-extension-review: {len(rows)} game(s)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())