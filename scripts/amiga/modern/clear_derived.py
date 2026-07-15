"""Clear L5 derived tables on ko2amiga_work (fork of replay.clear_derived)."""

from __future__ import annotations

import logging

import pymysql

log = logging.getLogger(__name__)


def clear_derived(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = cur.fetchone()["n"]
        cur.execute("SELECT COUNT(*) AS n FROM amiga_players")
        players = cur.fetchone()["n"]
    log.info("clear_derived: amiga_games=%s, amiga_players=%s", games, players)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_community_stat_facts")
        cur.execute("DELETE FROM amiga_community_stats_snapshots")
        cur.execute("DELETE FROM amiga_world_cup_stats")
        cur.execute("DELETE FROM amiga_community_stats WHERE id = 1")
        cur.execute("INSERT IGNORE INTO amiga_community_stats (id) VALUES (1)")
        cur.execute("DELETE FROM amiga_generalstats WHERE id = 1")
        cur.execute("INSERT IGNORE INTO amiga_generalstats (id) VALUES (1)")
        cur.execute("DELETE FROM amiga_realm_snapshots")
        cur.execute("DELETE FROM amiga_wc_hof_present")
        cur.execute("DELETE FROM amiga_wc_hof_snapshots")
        cur.execute("DELETE FROM amiga_player_inverse_count_at_event")
        cur.execute("DELETE FROM amiga_player_elo_rank_at_event")
        cur.execute("DELETE FROM amiga_player_matchup_at_event")
        cur.execute("DELETE FROM amiga_player_matchup_summary")
        cur.execute("DELETE FROM amiga_country_slice_at_event")
        cur.execute("DELETE FROM amiga_country_slice_totals")
        cur.execute("DELETE FROM amiga_player_slice_at_event")
        cur.execute("DELETE FROM amiga_player_slice_totals")
        cur.execute("DELETE FROM amiga_player_current")
        cur.execute("DELETE FROM amiga_player_event_snapshots")
        cur.execute("DELETE FROM amiga_tournament_catalog_stats")
        cur.execute("DELETE FROM amiga_tournament_standings")
        cur.execute("DELETE FROM amiga_game_ratings")
        cur.execute(
            """
            UPDATE tournament_stages
            SET frozen_scoring_primitive = NULL,
                frozen_scoring_schema_version = NULL,
                frozen_scoring_win_points = NULL,
                frozen_scoring_draw_points = NULL,
                frozen_scoring_loss_points = NULL
            """
        )
        cur.execute(
            """
            UPDATE tournaments
            SET rating_finalized = 0,
                rating_finalized_at = NULL,
                scoring_frozen_at = NULL,
                frozen_scoring_schema_version = NULL
            """
        )
    conn.commit()