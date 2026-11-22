"""Compare the website's Finish calculation (PHP) with simul (Python) over every tournament.

``python -m scripts.amiga php-finish-check``: copies the results from ko2amiga_work into a throwaway
database, lets the PHP in ``site/public_html`` calculate all tournaments there, compares every stored value
with what simul stored in ko2amiga_work, and drops the throwaway database. Local only; changes nothing else.

When to run it and what it does not cover: docs/amiga-php-finalize-parity-protocol.md.
"""

from __future__ import annotations

import ast
import json
import logging
import os
import shutil
import subprocess
import time
from collections import Counter
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Iterable, Sequence

import pymysql

from scripts.amiga.modern.clear_derived import clear_derived
from scripts.amiga.modern.constants import WORK_DB
from scripts.amiga.modern.db_config import load_work_db_config
from scripts.amiga.modern.work_db import connect_server, connect_work
from scripts.amiga.verify_php_community_parity import _find_php

log = logging.getLogger(__name__)

_REPO = Path(__file__).resolve().parents[3]
_SITE_ROOT = _REPO / "site" / "public_html"
_PHP_DRIVER = Path(__file__).resolve().parent / "php_finish_replay.php"
_OUT_DIR = _REPO / "data" / "amiga" / "modern"
_REPORT_JSON = _OUT_DIR / "php-finish-check-last.json"
_PHP_LOG = _OUT_DIR / "php-finish-check.log"
_PHP_REPORT = _OUT_DIR / "php-finish-check-php.json"

# Also hard-coded in php_finish_replay.php, which refuses any other database.
CHECK_DB = "ko2amiga_finish_check"

# Results and structure: copied as they are. Every other table is expected to be filled by the calculation,
# so a new derived table is compared without being listed here, and a new ground table fails loudly.
GROUND_TABLES: tuple[str, ...] = (
    "tournament_format_templates",
    "tournaments",
    "amiga_players",
    "amiga_games",
    "amiga_tournament_finish_override",
    "tournament_entrants",
    "tournament_stages",
    "tournament_stage_players",
    "tournament_stage_scoring_steps",
    "tournament_fixtures",
)

# When something was calculated, not what.
VOLATILE_COLUMNS: frozenset[str] = frozenset(
    {"finalized_at", "last_finalized_at", "rating_finalized_at", "scoring_frozen_at", "created_at", "updated_at"}
)

# Known difference, found 2026-11-22: shared with the online code, shown on no Amiga page, retirement deferred
# (docs/amiga-player-inverse-count-timeline-policy.md §6). Counted, but does not fail the check.
KNOWN_DIFFERENT_COLUMNS: frozenset[str] = frozenset({"LeastGoalsScoredVictims", "LeastGoalsConcededCulprits"})

# One rebuild takes about 15 minutes. A longer spread means the stored values come from more than one run.
_MAX_REBUILD_SPREAD = timedelta(hours=3)

_NOT_CALCULATION = (
    "scripts/amiga/import_",
    "scripts/amiga/verify_",
    "scripts/amiga/apply_",
    "scripts/amiga/promote_",
    "scripts/amiga/tournament_structure/",
    "scripts/amiga/tournament_fixtures",
    "scripts/amiga/tournament_builder",
    "scripts/amiga/player_registry",
    "scripts/amiga/schema_bundles",
)

# PHP reads ratings back from the database (6 decimals), Python keeps them in memory, so ratings and what is
# subtracted from them can be some hundred-thousandths of a point apart. Sums of ratings grow with the games.
_RATING_WORDS = ("rating", "adjustment")
_RATING_ROUNDING = 5e-5
_RELATIVE_ROUNDING = 1e-8
_SAMPLES_PER_COLUMN = 3


@dataclass(frozen=True)
class Column:
    name: str
    data_type: str
    scale: int | None = None


@dataclass
class TableResult:
    table: str
    rows_php: int = 0
    rows_python: int = 0
    values: int = 0
    rounding: Counter = field(default_factory=Counter)
    known: Counter = field(default_factory=Counter)
    different: Counter = field(default_factory=Counter)
    only_php: list[tuple] = field(default_factory=list)
    only_python: list[tuple] = field(default_factory=list)
    samples: dict[str, list[tuple]] = field(default_factory=dict)
    different_keys: set[tuple] = field(default_factory=set)
    max_rounding: float = 0.0

    @property
    def failures(self) -> int:
        return sum(self.different.values()) + len(self.only_php) + len(self.only_python)


def classify(column: Column, php_value: Any, python_value: Any) -> str:
    """'same', 'rounding' or 'different' for two stored values of one column."""
    if php_value == python_value:
        return "same"
    if php_value is None or python_value is None:
        return "different"
    if column.data_type not in {"decimal", "double", "float"}:
        return "different"
    a, b = float(php_value), float(python_value)
    allowed = _RELATIVE_ROUNDING * max(abs(a), abs(b))
    if column.data_type == "decimal" and column.scale is not None and column.scale >= 2:
        allowed = max(allowed, 1.01 * 10.0 ** -column.scale)  # one step in the last stored decimal
        if any(word in column.name.lower() for word in _RATING_WORDS):
            allowed = max(allowed, _RATING_ROUNDING)
    return "rounding" if abs(a - b) <= allowed else "different"


def compare_rows(
    table: str,
    columns: Sequence[Column],
    key_columns: Sequence[str],
    php_rows: Iterable[Sequence[Any]],
    python_rows: Iterable[Sequence[Any]],
) -> TableResult:
    """Compare two row sets, matched on their key. Rows are sequences in ``columns`` order."""
    names = [c.name for c in columns]
    key_index = [names.index(k) for k in key_columns]
    compared = [(i, c) for i, c in enumerate(columns) if c.name not in VOLATILE_COLUMNS and c.name not in key_columns]
    php = {tuple(r[i] for i in key_index): r for r in php_rows}
    python = {tuple(r[i] for i in key_index): r for r in python_rows}

    result = TableResult(table, rows_php=len(php), rows_python=len(python))
    result.only_php = [k for k in php if k not in python]
    result.only_python = [k for k in python if k not in php]
    result.different_keys.update(result.only_php, result.only_python)
    for key, php_row in php.items():
        python_row = python.get(key)
        if python_row is None:
            continue
        result.values += len(compared)
        for i, column in compared:
            kind = classify(column, php_row[i], python_row[i])
            if kind == "same":
                continue
            if kind == "rounding":
                result.rounding[column.name] += 1
                result.max_rounding = max(result.max_rounding, abs(float(php_row[i]) - float(python_row[i])))
            elif column.name in KNOWN_DIFFERENT_COLUMNS:
                result.known[column.name] += 1
            else:
                result.different[column.name] += 1
                result.different_keys.add(key)
                samples = result.samples.setdefault(column.name, [])
                if len(samples) < _SAMPLES_PER_COLUMN:
                    samples.append((key, php_row[i], python_row[i]))
    return result


def calculation_files() -> list[Path]:
    """Python files simul's calculation uses (imports of modern/replay.py, without import/structure tooling)."""
    seen: set[str] = set()
    todo = ["scripts/amiga/modern/replay.py"]
    while todo:
        rel = todo.pop()
        if rel in seen or rel.startswith(_NOT_CALCULATION):
            continue
        seen.add(rel)
        tree = ast.parse((_REPO / rel).read_text(encoding="utf-8-sig"))
        for node in ast.walk(tree):
            if isinstance(node, ast.Import):
                modules = [a.name for a in node.names]
            elif isinstance(node, ast.ImportFrom) and node.module:
                modules = [node.module, *(f"{node.module}.{a.name}" for a in node.names)]
            else:
                continue
            for module in modules:
                if not module.startswith("scripts."):
                    continue
                base = module.replace(".", "/")
                for candidate in (f"{base}.py", f"{base}/__init__.py"):
                    if (_REPO / candidate).is_file():
                        todo.append(candidate)
    return [_REPO / rel for rel in sorted(seen)]


def _preflight(conn: pymysql.connections.Connection, *, allow_old_python: bool) -> dict[str, Any]:
    """Refuse when ko2amiga_work does not hold one complete Python rebuild of the current code."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n FROM tournaments t
            WHERE COALESCE(t.rating_finalized, 0) = 0
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            """
        )
        unfinished = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        games = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_game_ratings")
        ratings = int(cur.fetchone()["n"])
        cur.execute("SELECT MIN(rating_finalized_at) AS a, MAX(rating_finalized_at) AS b FROM tournaments")
        row = cur.fetchone()
    if unfinished or games != ratings:
        raise SystemExit(
            f"{WORK_DB} is not a complete rebuild ({unfinished} tournaments not finalized, {games} games, "
            f"{ratings} ratings). Is simul running? Otherwise run: python -m scripts.amiga simul"
        )
    first, last = row["a"], row["b"]
    if last - first > _MAX_REBUILD_SPREAD:
        raise SystemExit(
            f"{WORK_DB} holds values from more than one run ({first} to {last} UTC), so some may come from the "
            "website's Finish. Run: python -m scripts.amiga simul"
        )
    rebuilt_at = first.replace(tzinfo=timezone.utc)
    newer = [
        p.relative_to(_REPO).as_posix()
        for p in calculation_files()
        if datetime.fromtimestamp(p.stat().st_mtime, timezone.utc) > rebuilt_at
    ]
    if newer:
        message = f"Python calculation files changed after the rebuild of {first} UTC: {', '.join(newer)}"
        if not allow_old_python:
            raise SystemExit(f"{message}\nRun: python -m scripts.amiga simul (or pass --allow-old-python)")
        log.warning("%s", message)
    return {"rebuilt_utc": first.isoformat(), "games": games, "python_files_newer": newer}


def _build_check_database(server: pymysql.connections.Connection) -> None:
    with server.cursor() as cur:
        cur.execute(
            "SELECT DEFAULT_CHARACTER_SET_NAME AS cs, DEFAULT_COLLATION_NAME AS co "
            "FROM information_schema.schemata WHERE schema_name = %s",
            (WORK_DB,),
        )
        row = cur.fetchone()
        cur.execute(f"DROP DATABASE IF EXISTS `{CHECK_DB}`")
        cur.execute(f"CREATE DATABASE `{CHECK_DB}` CHARACTER SET {row['cs']} COLLATE {row['co']}")
        # READ COMMITTED: INSERT ... SELECT then takes no locks on ko2amiga_work.
        cur.execute("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED")
        cur.execute("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'")
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        tables = _tables(cur, WORK_DB)
        missing = [t for t in GROUND_TABLES if t not in tables]
        if missing:
            raise SystemExit(f"{WORK_DB} lacks expected tables: {', '.join(missing)}")
        cur.execute(f"USE `{CHECK_DB}`")
        for table in tables:
            cur.execute(f"SHOW CREATE TABLE `{WORK_DB}`.`{table}`")
            cur.execute(cur.fetchone()["Create Table"])
        for table in GROUND_TABLES:
            cur.execute(f"INSERT INTO `{CHECK_DB}`.`{table}` SELECT * FROM `{WORK_DB}`.`{table}`")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    server.commit()
    clear_derived(server, dry_run=False)


def _drop_check_database(server: pymysql.connections.Connection) -> None:
    with server.cursor() as cur:
        cur.execute(f"DROP DATABASE IF EXISTS `{CHECK_DB}`")


def _run_php(site_root: Path, limit: int | None) -> dict[str, Any]:
    php = _find_php()
    php_exe = str(php) if php else shutil.which("php")
    if not php_exe:
        raise SystemExit("PHP command line not found (Laragon, or php on PATH)")
    cfg = load_work_db_config()
    env = {
        **os.environ,
        "FINISH_CHECK_HOST": str(cfg.host),
        "FINISH_CHECK_USER": str(cfg.user),
        "FINISH_CHECK_PASSWORD": str(cfg.password or ""),
        "FINISH_CHECK_PORT": str(cfg.port),
    }
    _OUT_DIR.mkdir(parents=True, exist_ok=True)
    # Not in the system temp folder: a 20-minute run once found its folder there gone at the end.
    _PHP_REPORT.unlink(missing_ok=True)
    command = [
        php_exe,
        "-d",
        "memory_limit=2G",
        str(_PHP_DRIVER),
        site_root.as_posix(),
        _PHP_REPORT.as_posix(),
        str(limit or 0),
    ]
    with _PHP_LOG.open("wb") as out:
        proc = subprocess.run(command, env=env, stdout=out, check=False)
    if not _PHP_REPORT.is_file():
        raise SystemExit(f"PHP stopped without a report (exit {proc.returncode}); see {_PHP_LOG}")
    report = json.loads(_PHP_REPORT.read_text(encoding="utf-8"))
    _PHP_REPORT.unlink()
    return report


def _tables(cur: Any, database: str) -> list[str]:
    cur.execute(
        "SELECT TABLE_NAME AS t FROM information_schema.tables "
        "WHERE table_schema = %s AND table_type = 'BASE TABLE' ORDER BY TABLE_NAME",
        (database,),
    )
    return [str(r["t"]) for r in cur.fetchall()]


def _table_shape(cur: Any, table: str) -> tuple[list[Column], list[str]]:
    """Columns to read and the key to match rows on. A surrogate auto id of a derived table is left out."""
    cur.execute(
        "SELECT COLUMN_NAME AS n, DATA_TYPE AS d, NUMERIC_SCALE AS s, EXTRA AS e FROM information_schema.columns "
        "WHERE table_schema = %s AND table_name = %s ORDER BY ORDINAL_POSITION",
        (WORK_DB, table),
    )
    rows = cur.fetchall()
    surrogate = {r["n"] for r in rows if "auto_increment" in (r["e"] or "")} if table not in GROUND_TABLES else set()
    cur.execute(
        "SELECT INDEX_NAME AS i, COLUMN_NAME AS c FROM information_schema.statistics "
        "WHERE table_schema = %s AND table_name = %s AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX",
        (WORK_DB, table),
    )
    indexes: dict[str, list[str]] = {}
    for r in cur.fetchall():
        indexes.setdefault(str(r["i"]), []).append(str(r["c"]))
    candidates = [indexes["PRIMARY"]] if "PRIMARY" in indexes else []
    candidates += [cols for name, cols in indexes.items() if name != "PRIMARY"]
    key = next((cols for cols in candidates if not surrogate & set(cols)), None)
    if key is None:
        raise SystemExit(f"{table}: no unique key to match rows on")
    columns = [
        Column(str(r["n"]), str(r["d"]), None if r["s"] is None else int(r["s"])) for r in rows if r["n"] not in surrogate
    ]
    return columns, key


def _tournament_key(key: Sequence[str]) -> str | None:
    return next((c for c in ("tournament_id", "as_of_tournament_id") if c in key), None)


def _game_tournaments(cur: Any) -> dict[int, int]:
    cur.execute(f"SELECT id, tournament_id FROM `{WORK_DB}`.amiga_games")
    return {int(r["id"]): int(r["tournament_id"]) for r in cur.fetchall()}


def compare_databases(conn: pymysql.connections.Connection, done: set[int] | None) -> list[TableResult]:
    """Compare CHECK_DB (PHP) with WORK_DB (Python). ``done``: only rows of these tournaments (partial run)."""
    results: list[TableResult] = []
    with conn.cursor() as cur:
        game_tournament = _game_tournaments(cur) if done is not None else {}
        for table in _tables(cur, WORK_DB):
            columns, key = _table_shape(cur, table)
            tkey = _tournament_key(key)
            per_game = key == ["game_id"]
            if done is not None and tkey is None and not per_game:
                continue
            names = [c.name for c in columns]
            select = ", ".join(f"`{n}`" for n in names)
            sets: list[list[tuple]] = []
            for database in (CHECK_DB, WORK_DB):
                cur.execute(f"SELECT {select} FROM `{database}`.`{table}`")
                rows = [tuple(r[n] for n in names) for r in cur.fetchall()]
                if done is not None:
                    i = names.index(tkey or "game_id")
                    rows = [r for r in rows if (game_tournament.get(int(r[i])) if per_game else int(r[i])) in done]
                sets.append(rows)
            results.append(compare_rows(table, columns, key, sets[0], sets[1]))
    return results


def _first_different_tournament(conn: pymysql.connections.Connection, results: list[TableResult]) -> str | None:
    """Earliest tournament with a difference: where to start looking."""
    with conn.cursor() as cur:
        game_tournament = _game_tournaments(cur)
        ids: set[int] = set()
        for result in results:
            if not result.different_keys:
                continue
            _, key = _table_shape(cur, result.table)
            tkey = _tournament_key(key)
            if tkey is not None:
                ids.update(int(k[key.index(tkey)]) for k in result.different_keys)
            elif key == ["game_id"]:
                ids.update(game_tournament[int(k[0])] for k in result.different_keys if int(k[0]) in game_tournament)
        if not ids:
            return None
        marks = ", ".join(["%s"] * len(ids))
        cur.execute(
            f"SELECT id, name, event_date FROM `{WORK_DB}`.tournaments WHERE id IN ({marks}) "
            "ORDER BY event_date, chrono, id LIMIT 1",
            tuple(ids),
        )
        row = cur.fetchone()
    return f"{row['name']} (id {row['id']}, {row['event_date']})" if row else None


def _print_results(results: list[TableResult], first: str | None, php: dict[str, Any], *, partial: bool) -> None:
    print(f"\n{'table':42}{'values':>12}{'rounding':>10}{'known':>8}{'different':>11}")
    for r in results:
        print(
            f"{r.table:42}{r.values:>12,}{sum(r.rounding.values()):>10,}"
            f"{sum(r.known.values()):>8,}{r.failures:>11,}"
        )
        for name, count in r.different.most_common(8):
            print(f"      {name}: {count:,}")
            for key, a, b in r.samples[name]:
                print(f"          {key}  PHP {a!s}  Python {b!s}")
        for label, keys in (("only in PHP", r.only_php), ("only in Python", r.only_python)):
            if keys:
                print(f"      rows {label}: {len(keys):,}, e.g. {keys[:3]}")
    values = sum(r.values for r in results)
    failures = sum(r.failures for r in results)
    print(
        f"\n{len(php['tournaments_done'])} of {php['tournaments_total']} tournaments calculated by PHP in "
        f"{php['seconds'] / 60:.1f} min. {values:,} values compared: {failures:,} different, "
        f"{sum(sum(r.rounding.values()) for r in results):,} differ by rounding only "
        f"(largest {max((r.max_rounding for r in results), default=0):g}), "
        f"{sum(sum(r.known.values()) for r in results):,} known."
    )
    if php["php_messages"]:
        print("PHP warnings (the calculation went on):")
        for message, count in list(php["php_messages"].items())[:10]:
            print(f"      {count:,} x {message}")
    if first:
        print(f"Earliest tournament with a difference: {first}")
    if php["error"]:
        verdict = "PHP STOPPED"
    else:
        verdict = "SAME" if failures == 0 else "DIFFERENT"
    print(f"RESULT: {verdict}" + (" (partial run)" if partial else ""))


def run_php_finish_check(
    *,
    limit: int | None = None,
    site_root: Path | None = None,
    keep_database: bool = False,
    allow_old_python: bool = False,
) -> int:
    root = (site_root or _SITE_ROOT).resolve()
    started = datetime.now(timezone.utc)
    t0 = time.monotonic()

    work = connect_work()
    try:
        facts = _preflight(work, allow_old_python=allow_old_python)
    finally:
        work.close()

    server = connect_server()
    server.autocommit(True)
    with server.cursor() as cur:
        cur.execute("SELECT GET_LOCK(%s, 0) AS got", (CHECK_DB,))
        if int(cur.fetchone()["got"] or 0) != 1:
            server.close()
            raise SystemExit("Another php-finish-check is running")
    try:
        print(f"Copying results from {WORK_DB} (Python rebuild of {facts['rebuilt_utc']} UTC) to {CHECK_DB}")
        _build_check_database(server)
        print("Calculating with the PHP in " + str(root) + (f", first {limit} tournaments" if limit else ", about 20 minutes"))
        php = _run_php(root, limit)
        if php["error"]:
            print(f"PHP stopped at tournament {php['error']['tournament_id']} {php['error']['name']}: "
                  f"{php['error']['message']} ({php['error']['at']})")
        work = connect_work()
        try:
            after = _preflight(work, allow_old_python=True)
        finally:
            work.close()
        if after["rebuilt_utc"] != facts["rebuilt_utc"]:
            raise SystemExit(f"{WORK_DB} was rebuilt while PHP was calculating; run php-finish-check again")
        partial = bool(limit) or bool(php["error"])
        done = set(php["tournaments_done"]) if partial else None
        results = compare_databases(server, done)
        first = _first_different_tournament(server, results)
        _print_results(results, first, php, partial=partial)
        failures = sum(r.failures for r in results)
        ok = failures == 0 and not php["error"]
        report = {
            "ok": ok,
            "partial": partial,
            "started_utc": started.isoformat(),
            "minutes": round((time.monotonic() - t0) / 60, 1),
            "site_root": root.as_posix(),
            "python_rebuilt_utc": facts["rebuilt_utc"],
            "python_files_newer_than_rebuild": facts["python_files_newer"],
            "php": php,
            "values_compared": sum(r.values for r in results),
            "different": failures,
            "earliest_different_tournament": first,
            "tables": {
                r.table: {
                    "rows_php": r.rows_php,
                    "rows_python": r.rows_python,
                    "values": r.values,
                    "rounding": dict(r.rounding),
                    "known": dict(r.known),
                    "different": dict(r.different),
                    "only_php": len(r.only_php),
                    "only_python": len(r.only_python),
                }
                for r in results
            },
        }
        _REPORT_JSON.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print(f"Report: {_REPORT_JSON.relative_to(_REPO).as_posix()}")
        return 0 if ok else 1
    finally:
        if keep_database:
            print(f"Kept {CHECK_DB}")
        else:
            _drop_check_database(server)
        server.close()
