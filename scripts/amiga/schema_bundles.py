"""Amiga DDL bundles — L3 witness / L4 structure / L5 product schema.

Folder names: sql/ground, sql/structure, sql/derived (not pipeline L1/L2 dumps).
Policy: docs/amiga-ground-layers-policy.md §6.
"""

from __future__ import annotations

from pathlib import Path

import pymysql

_SQL_ROOT = Path(__file__).resolve().parent / "sql"

GROUND_SQL: tuple[Path, ...] = (
    _SQL_ROOT / "ground" / "001_core.sql",
    _SQL_ROOT / "ground" / "002_tournament_finish_override.sql",
)

STRUCTURE_SQL: tuple[Path, ...] = (
    _SQL_ROOT / "structure" / "005_tournament_formats.sql",
    _SQL_ROOT / "structure" / "006_tournament_fixtures.sql",
    _SQL_ROOT / "structure" / "007_tournament_entrants.sql",
    _SQL_ROOT / "structure" / "008_tournament_lifecycle.sql",
    _SQL_ROOT / "structure" / "009_tournament_finalize_markers.sql",
)

DERIVED_SQL: tuple[Path, ...] = (
    _SQL_ROOT / "derived" / "001_game_ratings.sql",
    _SQL_ROOT / "derived" / "002_tournament_standings.sql",
    _SQL_ROOT / "derived" / "004_tournament_catalog_stats.sql",
    _SQL_ROOT / "derived" / "012_player_matchup_summary.sql",
    _SQL_ROOT / "derived" / "013_generalstats.sql",
    _SQL_ROOT / "derived" / "024_player_snapshots.sql",
    _SQL_ROOT / "derived" / "026_matchup_at_event.sql",
    _SQL_ROOT / "derived" / "027_realm_snapshots.sql",
    _SQL_ROOT / "derived" / "028_hof_tournament_geo.sql",
    _SQL_ROOT / "derived" / "029_hof_record_rise_dates.sql",
    _SQL_ROOT / "derived" / "030_career_rise_dates.sql",
    _SQL_ROOT / "derived" / "031_matchup_summary_opponents_ext.sql",
    _SQL_ROOT / "derived" / "032_elo_rank.sql",
    _SQL_ROOT / "derived" / "033_player_slice.sql",
    _SQL_ROOT / "derived" / "034_community_stats.sql",
    _SQL_ROOT / "derived" / "035_drop_realm_aggregate_columns.sql",
    _SQL_ROOT / "derived" / "036_community_stats_v2.sql",
    _SQL_ROOT / "derived" / "037_world_cup_stats.sql",
    _SQL_ROOT / "derived" / "038_world_cup_stats_blowout_intl.sql",
    _SQL_ROOT / "derived" / "039_player_slice_v2.sql",
)

# Legacy flat paths (archaeology / one-off scripts — not apply_schema).
LEGACY_SQL_TRACK_B = _SQL_ROOT / "002_tournament_standings.sql"
LEGACY_SQL_KNOCKOUT = _SQL_ROOT / "003_knockout_scope.sql"

_DERIVED_DROP_ORDER = (
    "amiga_world_cup_stats",
    "amiga_community_stat_facts",
    "amiga_community_stats_snapshots",
    "amiga_community_stats",
    "amiga_player_slice_at_event",
    "amiga_player_slice_totals",
    "amiga_generalstats",
    "amiga_realm_snapshots",
    "amiga_player_elo_rank_at_event",
    "amiga_player_matchup_at_event",
    "amiga_player_matchup_summary",
    "amiga_player_current",
    "amiga_player_event_snapshots",
    "amiga_tournament_catalog_stats",
    "amiga_tournament_standings",
    "amiga_game_ratings",
)

_STRUCTURE_DROP_ORDER = (
    "tournament_fixtures",
    "tournament_stage_players",
    "tournament_stages",
    "tournament_entrants",
    "tournament_format_templates",
)

_GROUND_DROP_ORDER = (
    "amiga_tournament_finish_override",
    "amiga_games",
    "amiga_players",
    "tournaments",
    "ratedresults",
    "playertable",
)

_AMIGA_TABLES_DROP_ORDER = _DERIVED_DROP_ORDER + _STRUCTURE_DROP_ORDER + _GROUND_DROP_ORDER


def _split_sql(sql: str) -> list[str]:
    sql = "\n".join(ln for ln in sql.splitlines() if ln.strip() and not ln.strip().startswith("--"))
    parts: list[str] = []
    for chunk in sql.split(";"):
        lines = [ln for ln in chunk.splitlines() if ln.strip()]
        if lines:
            parts.append("\n".join(lines))
    return parts


def _is_idempotent_alter_error(exc: pymysql.err.OperationalError) -> bool:
    if not exc.args:
        return False
    code = int(exc.args[0])
    if code in (1060, 1061, 1826):
        return True
    return code == 1005 and "Duplicate" in str(exc)


_WC_HONOURS_DROP_COLUMNS: tuple[str, ...] = (
    "wc_played",
    "wc_gold",
    "wc_silver",
    "wc_bronze",
    "wc_podiums",
    "wc_played_last_rise_tournament_id",
    "wc_played_last_rise_event_date",
)

_WC_HONOURS_DROP_TABLES: tuple[str, ...] = (
    "amiga_player_event_snapshots",
    "amiga_player_current",
)


def _ensure_slice_at_event_rise_columns(conn: pymysql.connections.Connection) -> None:
    """Add rise columns to slice_at_event when upgrading from slice-0 draft DDL."""
    rise_cols = (
        ("tournaments_played_last_rise_tournament_id", "int(11) DEFAULT NULL"),
        ("tournaments_played_last_rise_event_date", "date DEFAULT NULL"),
    )
    with conn.cursor() as cur:
        for col, col_type in rise_cols:
            cur.execute(
                """
                SELECT 1
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'amiga_player_slice_at_event'
                  AND COLUMN_NAME = %s
                LIMIT 1
                """,
                (col,),
            )
            if cur.fetchone():
                continue
            cur.execute(
                f"ALTER TABLE `amiga_player_slice_at_event` ADD COLUMN `{col}` {col_type}"
            )
    conn.commit()


def _drop_wc_honours_columns_if_present(conn: pymysql.connections.Connection) -> None:
    """Retire wc_* from honours block — idempotent on fresh 024 DDL (no wc_*)."""
    with conn.cursor() as cur:
        for table in _WC_HONOURS_DROP_TABLES:
            placeholders = ", ".join(["%s"] * len(_WC_HONOURS_DROP_COLUMNS))
            cur.execute(
                f"""
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = %s
                  AND COLUMN_NAME IN ({placeholders})
                """,
                (table, *_WC_HONOURS_DROP_COLUMNS),
            )
            present = {row["COLUMN_NAME"] for row in cur.fetchall()}
            for col in _WC_HONOURS_DROP_COLUMNS:
                if col not in present:
                    continue
                cur.execute(f"ALTER TABLE `{table}` DROP COLUMN `{col}`")
    conn.commit()


def _drop_tables(conn: pymysql.connections.Connection, tables: tuple[str, ...]) -> None:
    if not tables:
        return
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        for table in tables:
            cur.execute(f"DROP TABLE IF EXISTS `{table}`")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")


def _apply_sql_files(conn: pymysql.connections.Connection, sql_paths: tuple[Path, ...]) -> None:
    for sql_path in sql_paths:
        sql = sql_path.read_text(encoding="utf-8")
        with conn.cursor() as cur:
            for stmt in _split_sql(sql):
                if stmt.strip().upper().startswith("ALTER TABLE"):
                    try:
                        cur.execute(stmt)
                    except pymysql.err.OperationalError as exc:
                        if not _is_idempotent_alter_error(exc):
                            raise
                else:
                    cur.execute(stmt)
    conn.commit()


def apply_schema_ground(
    conn: pymysql.connections.Connection,
    *,
    drop_existing: bool = False,
) -> None:
    """L1 witness tables only."""
    if drop_existing:
        _drop_tables(conn, _GROUND_DROP_ORDER)
    _apply_sql_files(conn, GROUND_SQL)


def apply_schema_structure(
    conn: pymysql.connections.Connection,
    *,
    drop_existing: bool = False,
) -> None:
    """L2 structure overlay (requires ground)."""
    if drop_existing:
        _drop_tables(conn, _STRUCTURE_DROP_ORDER)
    _apply_sql_files(conn, STRUCTURE_SQL)


def apply_schema_derived(
    conn: pymysql.connections.Connection,
    *,
    drop_existing: bool = False,
) -> None:
    """L3 derived tables (requires ground; some FKs need structure)."""
    if drop_existing:
        _drop_tables(conn, _DERIVED_DROP_ORDER)
    _apply_sql_files(conn, DERIVED_SQL)
    _ensure_slice_at_event_rise_columns(conn)
    _drop_wc_honours_columns_if_present(conn)


def apply_schema(conn: pymysql.connections.Connection, *, drop_existing: bool = False) -> None:
    """Full fresh-install DDL: ground → structure → derived."""
    if drop_existing:
        _drop_tables(conn, _AMIGA_TABLES_DROP_ORDER)
    apply_schema_ground(conn)
    apply_schema_structure(conn)
    apply_schema_derived(conn)
