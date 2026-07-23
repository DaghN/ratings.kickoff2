"""Integer tournament chrono repair and before/after comparison."""

from __future__ import annotations

import json
import logging
from pathlib import Path

import pymysql

from scripts.amiga.config import load_amiga_db_config, require_amiga_ground_database

log = logging.getLogger(__name__)
_REPO = Path(__file__).resolve().parents[3]
_DEFAULT_BASELINE = (
    _REPO
    / "data"
    / "amiga"
    / "checkpoints"
    / "work-2026-07-23-pre-chrono-integer"
    / "companion"
    / "chrono_baseline_before.json"
)


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    require_amiga_ground_database(cfg, operation="chrono-integer")
    return pymysql.connect(
        host=cfg.host,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )


def load_ladder_ordered_tournaments(conn: pymysql.connections.Connection) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, name, event_date, chrono
            FROM tournaments
            WHERE event_date IS NOT NULL AND chrono IS NOT NULL
            ORDER BY event_date ASC, chrono ASC, id ASC
            """
        )
        return list(cur.fetchall())


def renumber_chronos_integers(*, apply: bool = False, dry_run: bool = False) -> dict[str, object]:
    conn = _connect()
    try:
        rows = load_ladder_ordered_tournaments(conn)
        mapping: list[dict[str, object]] = []
        for rank, row in enumerate(rows, start=1):
            old = float(row["chrono"])
            mapping.append(
                {
                    "id": int(row["id"]),
                    "name": row["name"],
                    "event_date": str(row["event_date"]),
                    "old_chrono": old,
                    "new_chrono": float(rank),
                    "changed": abs(old - rank) > 0.0001,
                }
            )
        changed = [m for m in mapping if m["changed"]]
        if dry_run or not apply:
            log.info(
                "renumber-chronos-integers %s: %s tournaments, %s would change",
                "dry-run" if dry_run or not apply else "preview",
                len(mapping),
                len(changed),
            )
            return {
                "applied": False,
                "tournament_count": len(mapping),
                "changed_count": len(changed),
                "mapping": mapping,
            }
        conn.begin()
        try:
            with conn.cursor() as cur:
                for item in mapping:
                    if not item["changed"]:
                        continue
                    cur.execute(
                        "UPDATE tournaments SET chrono = %s WHERE id = %s",
                        (item["new_chrono"], item["id"]),
                    )
            conn.commit()
        except Exception:
            conn.rollback()
            raise
        log.info(
            "renumber-chronos-integers applied: %s tournaments, %s updated",
            len(mapping),
            len(changed),
        )
        return {
            "applied": True,
            "tournament_count": len(mapping),
            "changed_count": len(changed),
            "mapping": mapping,
        }
    finally:
        conn.close()


def audit_chrono_integers(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    rows = load_ladder_ordered_tournaments(conn)
    for rank, row in enumerate(rows, start=1):
        chrono = float(row["chrono"])
        if abs(chrono - round(chrono)) > 0.0001:
            errors.append(f"tournament_id={row['id']} chrono={chrono} is not integer")
        if int(round(chrono)) != rank:
            errors.append(
                f"tournament_id={row['id']} chrono={chrono} expected dense rank {rank}"
            )
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_matchup_at_event m
            INNER JOIN tournaments t ON t.id = m.as_of_tournament_id
            WHERE ABS(m.event_chrono - t.chrono) > 0.001
            """
        )
        n = int(cur.fetchone()["n"])
        if n:
            errors.append(f"matchup event_chrono mismatch rows={n}")
    return errors


def chrono_integer_compare(
    baseline_path: Path | None = None,
    *,
    write_report: Path | None = None,
) -> dict[str, object]:
    baseline_path = baseline_path or _DEFAULT_BASELINE
    if not baseline_path.is_file():
        raise SystemExit(f"Baseline not found: {baseline_path}")
    before = json.loads(baseline_path.read_text(encoding="utf-8"))
    before_by_id = {int(r["id"]): r for r in before.get("tournaments", [])}

    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, name, event_date, chrono
                FROM tournaments
                ORDER BY event_date ASC, chrono ASC, id ASC
                """
            )
            after_rows = cur.fetchall()
            cur.execute(
                """
                SELECT COUNT(*) AS n
                FROM amiga_player_matchup_at_event m
                JOIN tournaments t ON t.id = m.as_of_tournament_id
                WHERE ABS(m.event_chrono - t.chrono) > 0.001
                """
            )
            mae_mismatch = int(cur.fetchone()["n"])
            cur.execute(
                "SELECT COUNT(*) AS n FROM tournaments WHERE chrono <> FLOOR(chrono)"
            )
            frac_count = int(cur.fetchone()["n"])

        changes = []
        for row in after_rows:
            tid = int(row["id"])
            old = before_by_id.get(tid)
            if old is None:
                continue
            old_c = float(old["chrono"])
            new_c = float(row["chrono"])
            if abs(old_c - new_c) > 0.0001:
                changes.append(
                    {
                        "id": tid,
                        "name": row["name"],
                        "event_date": str(row["event_date"]),
                        "old_chrono": old_c,
                        "new_chrono": new_c,
                        "was_fractional": old_c != int(old_c),
                    }
                )

        report = {
            "baseline_path": str(baseline_path),
            "before_mae_row_mismatch": before.get("mae_row_mismatch_count"),
            "after_mae_row_mismatch": mae_mismatch,
            "before_fractional_count": before.get("fractional_tournament_count"),
            "after_fractional_count": frac_count,
            "chrono_mapping_changes": len(changes),
            "changes": changes,
            "audit_errors": audit_chrono_integers(conn),
        }
        if write_report is not None:
            write_report.parent.mkdir(parents=True, exist_ok=True)
            write_report.write_text(json.dumps(report, indent=2), encoding="utf-8")
        return report
    finally:
        conn.close()