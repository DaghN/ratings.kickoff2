#!/usr/bin/env python3
"""L4 structure overlay — disposition register dispatch (policy §4)."""

from __future__ import annotations

import logging
from dataclasses import dataclass, field
from typing import Any

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import connect_mysql
from scripts.amiga.schema_bundles import apply_schema_structure
from scripts.amiga.tournament_structure.apply import apply_structure_spec_for_tournament
from scripts.amiga.tournament_structure.disposition_register import (
    HANDLER_NO_GAMES,
    HANDLER_PENDING_REVIEW,
    HANDLER_PURE_KNOCKOUT,
    HANDLER_PURE_RR,
    HANDLER_STRUCTURE_SPEC,
    HANDLER_WC_DEFERRED,
    DispositionRegister,
    verify_register,
)
from scripts.amiga.tournament_structure.materialize_legacy import (
    MaterializeResult,
    StructureReviewRequired,
    materialize_legacy_fixtures,
)
from scripts.amiga.tournament_structure.pure_knockout import materialize_pure_knockout

log = logging.getLogger(__name__)

_SKIP_HANDLERS = frozenset({
    HANDLER_PENDING_REVIEW,
    HANDLER_WC_DEFERRED,
    HANDLER_NO_GAMES,
})


@dataclass
class ApplyStructureStats:
    pure_rr: int = 0
    pure_knockout: int = 0
    structure_spec: int = 0
    skipped: int = 0
    skipped_no_spec: int = 0
    games_linked: int = 0
    fixtures_created: int = 0
    failures: list[dict[str, Any]] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "pure_rr": self.pure_rr,
            "pure_knockout": self.pure_knockout,
            "structure_spec": self.structure_spec,
            "skipped": self.skipped,
            "skipped_no_spec": self.skipped_no_spec,
            "games_linked": self.games_linked,
            "fixtures_created": self.fixtures_created,
            "failures": self.failures,
        }


def clear_l4_structure_overlay(conn: pymysql.connections.Connection) -> None:
    """Drop all L4 stages/fixtures; L3 games remain (fixture_id nulled via FK)."""
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        cur.execute("DELETE FROM tournament_stage_players")
        cur.execute("DELETE FROM tournament_entrants")
        cur.execute("DELETE FROM tournament_stages")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()


def _catalog_tournaments(conn: pymysql.connections.Connection) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id, t.name,
                   (SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS game_count
            FROM tournaments t
            WHERE t.source_id IS NOT NULL
            ORDER BY t.id
            """
        )
        return list(cur.fetchall())


def _accumulate_materialize(stats: ApplyStructureStats, result: MaterializeResult) -> None:
    stats.games_linked += max(0, result.games_linked)
    stats.fixtures_created += max(0, result.fixtures_created)


def apply_structure_from_disposition(
    conn: pymysql.connections.Connection,
    *,
    reg: DispositionRegister | None = None,
    tournament_id: int | None = None,
    limit: int | None = None,
    dry_run: bool = False,
    clear_existing: bool = True,
) -> ApplyStructureStats:
    """Dispatch L4 handlers from disposition_register.json."""
    reg = reg or DispositionRegister.load()
    coverage = verify_register(conn, reg)
    if not coverage["ok"]:
        missing = coverage["missing_ids"]
        raise SystemExit(
            f"Disposition register incomplete: {len(missing)} missing id(s), e.g. {missing[:5]}"
        )

    stats = ApplyStructureStats()
    catalog = _catalog_tournaments(conn)
    if tournament_id is not None:
        catalog = [row for row in catalog if int(row["id"]) == tournament_id]
        if not catalog:
            raise SystemExit(f"tournament_id={tournament_id} not in imported catalog")

    if clear_existing and tournament_id is None:
        clear_l4_structure_overlay(conn)

    processed = 0
    for row in catalog:
        tid = int(row["id"])
        game_count = int(row["game_count"])
        disp = reg.get(tid)
        if disp is None:
            raise SystemExit(f"apply-structure: missing disposition row for tournament_id={tid}")

        if disp.handler in _SKIP_HANDLERS or game_count == 0:
            stats.skipped += 1
            continue

        if tournament_id is not None and clear_existing:
            from scripts.amiga.tournament_structure.materialize_legacy import (
                _clear_tournament_structure,
            )

            _clear_tournament_structure(conn, tid)

        try:
            if disp.handler == HANDLER_PURE_RR:
                result = materialize_legacy_fixtures(
                    conn,
                    tid,
                    dry_run=dry_run,
                    replace=True,
                )
                stats.pure_rr += 1
                _accumulate_materialize(stats, result)
            elif disp.handler == HANDLER_PURE_KNOCKOUT:
                result = materialize_pure_knockout(
                    conn,
                    tid,
                    dry_run=dry_run,
                    replace=True,
                )
                stats.pure_knockout += 1
                _accumulate_materialize(stats, result)
            elif disp.handler == HANDLER_STRUCTURE_SPEC:
                from scripts.amiga.tournament_structure.registry import registry_entry_for_catalog

                entry = registry_entry_for_catalog(str(row["name"]))
                if entry is None or entry.status != "active":
                    log.warning(
                        "structure_spec handler for id=%s %r but no active registry spec — skipping",
                        tid,
                        row["name"],
                    )
                    stats.skipped_no_spec += 1
                    continue
                spec_stats = apply_structure_spec_for_tournament(conn, tid, replace=True)
                stats.structure_spec += 1
                stats.games_linked += spec_stats.games_linked
                stats.fixtures_created += spec_stats.fixture_count
            else:
                raise SystemExit(f"apply-structure: unknown handler {disp.handler!r} for id={tid}")
        except StructureReviewRequired as exc:
            log.warning(
                "apply-structure: skipping id=%s %r — %s",
                tid,
                row["name"],
                exc,
            )
            stats.skipped += 1
            continue
        except ValueError as exc:
            raise SystemExit(
                f"apply-structure failed on tournament_id={tid} ({row['name']!r}): {exc}"
            ) from exc

        processed += 1
        if processed % 50 == 0:
            log.info("apply-structure progress: %s tournaments dispatched", processed)
        if limit is not None and processed >= limit:
            break

    if not dry_run:
        conn.commit()
    else:
        conn.rollback()

    return stats


def run_apply_structure(
    *,
    from_disposition: bool,
    recreate_structure: bool = False,
    tournament_id: int | None = None,
    limit: int | None = None,
    dry_run: bool = False,
) -> ApplyStructureStats:
    if not from_disposition:
        raise SystemExit("apply-structure requires --from-disposition")

    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing apply-structure: expected ko2amiga_db, got {cfg.database!r}")

    conn = connect_mysql(cfg)
    try:
        if recreate_structure:
            apply_schema_structure(conn, drop_existing=True)
            clear_l4_structure_overlay(conn)

        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            if int(cur.fetchone()["n"]) == 0:
                raise SystemExit("No L3 games in DB — run import-witness first")

        return apply_structure_from_disposition(
            conn,
            tournament_id=tournament_id,
            limit=limit,
            dry_run=dry_run,
            clear_existing=not recreate_structure or tournament_id is not None,
        )
    finally:
        conn.close()
