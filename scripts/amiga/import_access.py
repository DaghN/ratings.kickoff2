#!/usr/bin/env python3
"""Import ground truth from koatd.mdb into ko2amiga_db.

FROZEN (Jul 2026, CODE-1): Access L3 witness import — oracle path only.
Forward ground: day 0 seal + ko2amiga_work — do not extend (MG11).
"""

from __future__ import annotations

import json
import logging
from dataclasses import dataclass
from datetime import date, datetime, timedelta
from pathlib import Path
from types import SimpleNamespace

import pymysql
import pyodbc
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_corrections import (
    IMPORT_SUPPLEMENT_SCORES_ID_BASE,
    SUPPLEMENTAL_SCORES,
    apply_catalog_corrections,
    apply_catalog_splits,
    apply_player_country_corrections,
    catalog_splits_manifest,
    supplemental_scores_manifest,
)
from scripts.amiga.import_country_registry import (
    apply_country_registry_to_prepared,
    registry_manifest_metadata,
)
from scripts.amiga.import_manifest import (
    build_manifest,
    default_manifest_path,
    source_metadata,
    write_manifest,
)
from scripts.amiga.player_names import (
    build_canonical_name_map,
    canonical_country,
    identity_key,
    normalize_display_name,
)
from scripts.amiga.tournament_names import (
    resolve_phase,
    resolve_tournament_name,
    scores_only_catalog_aliases,
)
from scripts.amiga.tournament_format import (
    LEGACY_TEMPLATE_SLUG,
    audit_tournament_format_flags,
    infer_legacy_tournament_formats,
    seed_format_templates,
)
from scripts.amiga.tournament_honours import is_world_cup_tournament
from scripts.amiga.tournament_structure.apply import (
    ApplyContext,
    apply_structure_spec,
    structure_specs_manifest,
)
log = logging.getLogger(__name__)

from scripts.amiga.schema_bundles import (
    LEGACY_SQL_KNOCKOUT,
    LEGACY_SQL_TRACK_B,
    _AMIGA_TABLES_DROP_ORDER,
    _split_sql,
    apply_schema,
    apply_schema_derived,
    apply_schema_ground,
    apply_schema_structure,
)


_REPO = Path(__file__).resolve().parents[2]
_DEFAULT_MDB = _REPO / "data" / "amiga" / "source" / "koatd.mdb"
_DEFAULT_L2_DIR = _REPO / "data" / "amiga" / "exports" / "pruned"

# Back-compat aliases for legacy one-off scripts.
_SQL_TRACK_B = LEGACY_SQL_TRACK_B
_SQL_KNOCKOUT = LEGACY_SQL_KNOCKOUT


@dataclass(frozen=True)
class AccessScore:
    source_id: int
    team_a: str
    team_b: str
    goals_a: int
    goals_b: int
    raw_tournament: str
    phase: str | None
    extra: str | None


def connect_access(mdb: Path) -> pyodbc.Connection:
    conn_str = f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={mdb.resolve()};"
    return pyodbc.connect(conn_str)


def connect_mysql(cfg) -> pymysql.connections.Connection:
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def truncate_ground_truth(conn: pymysql.connections.Connection) -> None:
    """Clear L3 ground + L4 structure rows and L5 derived (full import reload)."""
    truncate_l3_structure_data(conn)
    truncate_derived_data(conn)


def _truncate_tables(conn: pymysql.connections.Connection, tables: tuple[str, ...]) -> None:
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        for table in tables:
            cur.execute(f"TRUNCATE TABLE `{table}`")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()


def truncate_l3_structure_data(conn: pymysql.connections.Connection) -> None:
    """Clear witness ground + structure overlay data (L3/L4 rows)."""
    _truncate_tables(
        conn,
        (
            "amiga_tournament_finish_override",
            "amiga_games",
            "tournament_fixtures",
            "tournament_stage_players",
            "tournament_stages",
            "tournament_entrants",
            "amiga_players",
            "tournaments",
            "tournament_format_templates",
        ),
    )


_DERIVED_TRUNCATE_ORDER = (
    "amiga_generalstats",
    "amiga_player_matchup_at_event",
    "amiga_player_matchup_summary",
    "amiga_player_current",
    "amiga_player_event_snapshots",
    "amiga_tournament_catalog_stats",
    "amiga_tournament_standings",
    "amiga_game_ratings",
)


def truncate_derived_data(conn: pymysql.connections.Connection) -> None:
    """Clear L5 derived tables when present."""
    with conn.cursor() as cur:
        cur.execute("SELECT DATABASE()")
        db = cur.fetchone()["DATABASE()"]
        existing: list[str] = []
        for table in _DERIVED_TRUNCATE_ORDER:
            cur.execute(
                """
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = %s AND table_name = %s
                """,
                (db, table),
            )
            if cur.fetchone():
                existing.append(table)
    if not existing:
        return
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        for table in existing:
            cur.execute(f"TRUNCATE TABLE `{table}`")
        if "amiga_generalstats" in existing:
            cur.execute("INSERT IGNORE INTO amiga_generalstats (id) VALUES (1)")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()


def prepare_l3_schema(
    conn: pymysql.connections.Connection,
    *,
    recreate_ground: bool,
) -> None:
    """L3 witness path: ground + structure DDL only (no L5 derived bundle)."""
    if recreate_ground:
        apply_schema_ground(conn, drop_existing=True)
        apply_schema_structure(conn, drop_existing=True)
        truncate_derived_data(conn)
    else:
        truncate_l3_structure_data(conn)


@dataclass
class WitnessPrepared:
    source: dict[str, object]
    tournaments: list[dict]
    scores_sorted: list[AccessScore]
    tour_by_name: dict[str, dict]
    names: set[str]
    raw_player_names: set[str]
    merge_log: list[dict[str, object]]
    raw_to_canonical: dict[str, str]
    countries: dict[str, str]
    catalog_overrides: list[dict[str, str]]
    player_country_overrides: list[dict[str, str]]
    country_token_normalizations: list[dict[str, str]]
    catalog_splits: list[dict[str, str | int | float]]
    score_supplements: list[dict[str, object]]
    skipped_catalog: list[str]
    format_by_name: dict


def _prepare_witness_core(
    *,
    source: dict[str, object],
    tournaments: list[dict],
    scores: list[AccessScore],
    countries: dict[str, str],
) -> WitnessPrepared:
    """L3 transforms on witness inputs (L2 SQL or legacy Access load)."""
    catalog_overrides = apply_catalog_corrections(tournaments)
    skip_catalog = scores_only_catalog_aliases()
    skipped_catalog = sorted(t["name"] for t in tournaments if t["name"] in skip_catalog)
    tournaments = [t for t in tournaments if t["name"] not in skip_catalog]
    if skipped_catalog:
        log.info(
            "Skipped %s Access catalog row(s) merged via tournament_names aliases: %s",
            len(skipped_catalog),
            skipped_catalog,
        )
    if catalog_overrides:
        log.info("Applied %s catalog override(s) from import_corrections.py", len(catalog_overrides))
        for entry in catalog_overrides:
            log.info(
                "  → %s.%s: %s → %s",
                entry["tournament"],
                entry["field"],
                entry["access"],
                entry["canonical"],
            )
    catalog_splits = apply_catalog_splits(tournaments)
    if catalog_splits:
        log.info("Appended %s synthetic catalog split(s) from import_corrections.py", len(catalog_splits))
        for entry in catalog_splits:
            log.info("  → %s (parent %s, source_id %s)", entry["tournament"], entry["parent"], entry["source_id"])
    scores = merge_supplemental_scores(scores)
    score_supplements = supplemental_scores_manifest()
    if score_supplements:
        log.info(
            "Appended %s supplemental game(s) from import_corrections.py (%s tournament(s))",
            len(SUPPLEMENTAL_SCORES),
            len(score_supplements),
        )
        for entry in score_supplements:
            log.info("  → %s: +%s games", entry["tournament"], entry["games_added"])
    raw_player_names: set[str] = set()
    for s in scores:
        raw_player_names.add(s.team_a)
        raw_player_names.add(s.team_b)
    raw_to_canonical, merge_log = build_canonical_name_map(scores, countries=countries)
    apply_name_map(scores, raw_to_canonical)
    if merge_log:
        log.info("Merged %s player identity groups (spacing/case duplicates)", len(merge_log))
        for entry in merge_log:
            log.info("  → %s <= %s", entry["canonical"], entry["variants"])
        merge_path = _REPO / "data" / "amiga" / "exports" / "name_merges.json"
        merge_path.parent.mkdir(parents=True, exist_ok=True)
        merge_path.write_text(json.dumps(merge_log, indent=2) + "\n", encoding="utf-8")

    tour_by_name = {t["name"]: t for t in tournaments}
    format_by_name = infer_legacy_tournament_formats(tournaments, scores)

    def sort_key(s: AccessScore) -> tuple:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = tour_by_name.get(parent, {})
        chrono = meta.get("chrono")
        ev = meta.get("event_date")
        ev_date = ev.date() if isinstance(ev, datetime) else (ev or date(1970, 1, 1))
        chrono_val = chrono if chrono is not None else 999999.0
        return (ev_date, chrono_val, s.source_id)

    scores_sorted = sorted(scores, key=sort_key)
    names = {s.team_a for s in scores_sorted} | {s.team_b for s in scores_sorted}

    player_country_overrides = apply_player_country_corrections(countries)
    if player_country_overrides:
        log.info(
            "Applied %s player country override(s) from import_corrections.py",
            len(player_country_overrides),
        )
        for entry in player_country_overrides:
            log.info(
                "  → %s.%s: %r → %s",
                entry["player"],
                entry["field"],
                entry["access"],
                entry["canonical"],
            )

    country_token_normalizations = apply_country_registry_to_prepared(
        SimpleNamespace(countries=countries, tournaments=tournaments)
    )
    if country_token_normalizations:
        log.info(
            "Applied %s country token normalization(s) from country_registry.json",
            len(country_token_normalizations),
        )
        for entry in country_token_normalizations:
            log.info(
                "  → %s %s.%s: %s → %s",
                entry["entity"],
                entry["name"],
                entry["field"],
                entry["access"],
                entry["canonical"],
            )

    return WitnessPrepared(
        source=source,
        tournaments=tournaments,
        scores_sorted=scores_sorted,
        tour_by_name=tour_by_name,
        names=names,
        raw_player_names=raw_player_names,
        merge_log=merge_log,
        raw_to_canonical=raw_to_canonical,
        countries=countries,
        catalog_overrides=catalog_overrides,
        player_country_overrides=player_country_overrides,
        country_token_normalizations=country_token_normalizations,
        catalog_splits=catalog_splits_manifest(),
        score_supplements=score_supplements,
        skipped_catalog=skipped_catalog,
        format_by_name=format_by_name,
    )


def prepare_witness_from_l2(l2_dir: Path) -> WitnessPrepared:
    """L3 in-memory witness load from L2 pruned SQL (strict stack)."""
    from scripts.amiga.import_l2_witness import load_l2_witness_inputs

    source, tournaments, scores, countries = load_l2_witness_inputs(l2_dir)
    return _prepare_witness_core(
        source=source,
        tournaments=tournaments,
        scores=scores,
        countries=countries,
    )


def prepare_witness_from_access(mdb: Path) -> WitnessPrepared:
    """Legacy Access load — audit/dev only; not used by prove (G12)."""
    acc = connect_access(mdb)
    acc_cur = acc.cursor()
    tournaments = load_access_tournaments(acc_cur)
    scores = load_access_scores(acc_cur)
    countries = load_country_by_player(acc_cur)
    acc.close()
    return _prepare_witness_core(
        source=source_metadata(mdb),
        tournaments=tournaments,
        scores=scores,
        countries=countries,
    )


def persist_witness_to_mysql(
    mysql: pymysql.connections.Connection,
    prepared: WitnessPrepared,
    *,
    apply_structure: bool,
) -> dict[str, int]:
    """Write L3 witness rows (+ optional L4 structure spec hook)."""
    template_ids = seed_format_templates(mysql)
    legacy_template_id = template_ids[LEGACY_TEMPLATE_SLUG]

    with mysql.cursor() as cur:
        for t in prepared.tournaments:
            inferred_format = prepared.format_by_name[t["name"]]
            cur.execute(
                """
                INSERT INTO tournaments
                  (source_id, name, chrono, event_date, is_cup, country, equal_teams, player_count,
                   format_template_id, has_league, has_cup, is_world_cup, lifecycle_status, completed_at)
                VALUES (%(source_id)s, %(name)s, %(chrono)s, %(event_date)s, %(is_cup)s,
                        %(country)s, %(equal_teams)s, %(player_count)s,
                        %(format_template_id)s, %(has_league)s, %(has_cup)s, %(is_world_cup)s,
                        'completed', %(completed_at)s)
                """,
                {
                    **t,
                    "event_date": t["event_date"].date() if isinstance(t["event_date"], datetime) else t["event_date"],
                    "format_template_id": legacy_template_id,
                    "has_league": inferred_format.has_league,
                    "has_cup": inferred_format.has_cup,
                    "is_world_cup": 1 if is_world_cup_tournament(str(t["name"])) else 0,
                    "completed_at": (
                        datetime.combine(
                            t["event_date"].date() if isinstance(t["event_date"], datetime) else t["event_date"],
                            datetime.min.time(),
                        )
                        if t.get("event_date") is not None
                        else None
                    ),
                },
            )

    with mysql.cursor() as cur:
        cur.execute("SELECT id, name FROM tournaments")
        tour_id_by_name = {row["name"]: int(row["id"]) for row in cur.fetchall()}

    missing_parents: set[str] = set()
    for s in prepared.scores_sorted:
        parent = resolve_tournament_name(s.raw_tournament)
        if parent and parent not in tour_id_by_name:
            missing_parents.add(parent)
    if missing_parents:
        raise SystemExit(f"Tournament catalog missing parents after alias map: {sorted(missing_parents)}")

    variants_by_key: dict[str, list[str]] = {}
    for raw, canonical in prepared.raw_to_canonical.items():
        variants_by_key.setdefault(identity_key(canonical), []).append(raw)

    with mysql.cursor() as cur:
        for name in sorted(prepared.names):
            variants = variants_by_key.get(identity_key(name), [name])
            country = canonical_country(name, variants, prepared.countries)
            cur.execute(
                "INSERT INTO amiga_players (name, country) VALUES (%s, %s)",
                (name, country),
            )

    with mysql.cursor() as cur:
        cur.execute("SELECT id, name FROM amiga_players")
        player_id = {row["name"]: int(row["id"]) for row in cur.fetchall()}

    per_day_seq: dict[date, int] = {}
    game_rows: list[dict] = []
    for s in prepared.scores_sorted:
        parent = resolve_tournament_name(s.raw_tournament)
        meta = prepared.tour_by_name[parent]
        ev = meta["event_date"]
        base_day = ev.date() if isinstance(ev, datetime) else ev
        if base_day is None:
            base_day = date(1970, 1, 1)
        seq = per_day_seq.get(base_day, 0)
        per_day_seq[base_day] = seq + 1
        game_dt = datetime.combine(base_day, datetime.min.time()) + timedelta(seconds=seq)
        phase = resolve_phase(s.raw_tournament, s.phase)
        game_rows.append(
            {
                "source_scores_id": s.source_id,
                "game_date": game_dt.strftime("%Y-%m-%d %H:%M:%S"),
                "player_a_id": player_id[s.team_a],
                "player_b_id": player_id[s.team_b],
                "tournament_id": tour_id_by_name[parent],
                "phase": phase,
                "goals_a": s.goals_a,
                "goals_b": s.goals_b,
                "extra": s.extra,
            }
        )

    structure_result = None
    if apply_structure:
        structure_result = apply_structure_spec(
            mysql,
            ApplyContext(
                tour_id_by_name=tour_id_by_name,
                player_id=player_id,
                tour_by_name=prepared.tour_by_name,
                scores=prepared.scores_sorted,
            ),
            game_rows,
        )

    with mysql.cursor() as cur:
        cur.executemany(
            """
            INSERT INTO amiga_games
              (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id,
               phase, goals_a, goals_b, extra)
            VALUES
              (%(source_scores_id)s, %(game_date)s, %(player_a_id)s, %(player_b_id)s,
               %(tournament_id)s, %(fixture_id)s, %(phase)s, %(goals_a)s, %(goals_b)s, %(extra)s)
            """,
            [
                {
                    **row,
                    "fixture_id": row.get("fixture_id"),
                }
                for row in game_rows
            ],
        )

    format_audit_failures = audit_tournament_format_flags(mysql)
    if format_audit_failures:
        raise SystemExit(f"Tournaments with games but neither format flag set: {format_audit_failures}")

    mysql.commit()

    format_bucket_counts = {
        "league_only": 0,
        "cup_only": 0,
        "league_and_cup": 0,
        "neither": 0,
    }
    for inferred in prepared.format_by_name.values():
        if inferred.has_league and inferred.has_cup:
            format_bucket_counts["league_and_cup"] += 1
        elif inferred.has_league:
            format_bucket_counts["league_only"] += 1
        elif inferred.has_cup:
            format_bucket_counts["cup_only"] += 1
        else:
            format_bucket_counts["neither"] += 1

    stats = {
        "tournaments": len(prepared.tournaments),
        "games": len(game_rows),
        "players_raw": len(prepared.raw_player_names),
        "players_canonical": len(prepared.names),
        "name_merge_groups": len(prepared.merge_log),
        **{f"format_{key}": value for key, value in format_bucket_counts.items()},
    }
    manifest = build_manifest(
        source=prepared.source,
        stats=stats,
        name_merges=prepared.merge_log,
        catalog_overrides=prepared.catalog_overrides,
        player_country_overrides=prepared.player_country_overrides,
        country_token_normalizations=prepared.country_token_normalizations,
        country_registry=registry_manifest_metadata(),
        catalog_splits=prepared.catalog_splits,
        score_supplements=prepared.score_supplements,
        structure_specs=structure_specs_manifest(structure_result) if structure_result else [],
    )
    manifest_path = default_manifest_path(_REPO)
    write_manifest(manifest_path, manifest)
    log.info("Wrote import manifest: %s", manifest_path)
    return stats


def import_witness(*, l2_dir: Path = _DEFAULT_L2_DIR, recreate_ground: bool) -> dict[str, int]:
    """L3 witness import â€” corrections + ground rows; no L4 disposition; L5 empty."""
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing import: expected database ko2amiga_db, got {cfg.database!r}")

    prepared = prepare_witness_from_l2(l2_dir)
    mysql = connect_mysql(cfg)
    prepare_l3_schema(mysql, recreate_ground=recreate_ground)
    stats = persist_witness_to_mysql(mysql, prepared, apply_structure=False)
    mysql.close()
    log.warning(
        "L3 witness import complete â€” derived tables empty. "
        "Run replay (or apply-structure then replay) before serving the website."
    )
    return stats


def import_witness_nuclear(*, l2_dir: Path = _DEFAULT_L2_DIR) -> dict[str, int]:
    """L3 witness with full L3+L4+L5 schema recreate (prove / run nuclear path)."""
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing import: expected database ko2amiga_db, got {cfg.database!r}")

    prepared = prepare_witness_from_l2(l2_dir)
    mysql = connect_mysql(cfg)
    apply_schema(mysql, drop_existing=True)
    truncate_ground_truth(mysql)
    stats = persist_witness_to_mysql(mysql, prepared, apply_structure=False)
    mysql.close()
    return stats


def import_witness_reload(*, l2_dir: Path = _DEFAULT_L2_DIR) -> dict[str, int]:
    """Incremental L3 witness reload — schema unchanged; clears L3/L4/L5 rows."""
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing import: expected database ko2amiga_db, got {cfg.database!r}")

    prepared = prepare_witness_from_l2(l2_dir)
    mysql = connect_mysql(cfg)
    truncate_ground_truth(mysql)
    stats = persist_witness_to_mysql(mysql, prepared, apply_structure=False)
    mysql.close()
    return stats


def load_access_tournaments(cur: pyodbc.Cursor) -> list[dict]:
    cur.execute("SELECT * FROM [Tournament players]")
    cols = [d[0] for d in cur.description]
    idx = {name: cols.index(name) for name in cols}
    rows: list[dict] = []
    for row in cur.fetchall():
        rows.append(
            {
                "source_id": int(row[idx["ID"]]),
                "name": str(row[idx["Tournament"]]).strip(),
                "chrono": float(row[idx["Chrono"]]) if row[idx["Chrono"]] is not None else None,
                "event_date": row[idx["Date"]],
                "is_cup": bool(row[idx["Cup?"]]),
                "country": row[idx["Country"]],
                "equal_teams": bool(row[idx["EqualTeams"]]),
                "player_count": row[idx["Players"]],
            }
        )
    return rows


def import_all(
    *,
    mdb: Path,
    l1_dir: Path,
    l2_dir: Path,
    recreate_schema: bool,
) -> dict[str, int]:
    """Full import path: L1→L2→L3 witness + L4 disposition."""
    from scripts.amiga.apply_structure import run_apply_structure
    from scripts.amiga.import_prune import run_import_prune
    from scripts.amiga.import_pristine import run_import_pristine

    if recreate_schema:
        run_import_pristine(mdb=mdb, out_dir=l1_dir)
        run_import_prune(l1_dir=l1_dir, out_dir=l2_dir)
        stats = import_witness_nuclear(l2_dir=l2_dir)
    else:
        stats = import_witness_reload(l2_dir=l2_dir)

    l4 = run_apply_structure(from_disposition=True)
    log.info("import: apply-structure %s", l4.to_dict())
    log.warning(
        "Import cleared derived tables and reloaded ground truth only. "
        "Run `python -m scripts.amiga replay` (or `python -m scripts.amiga run`) before serving the website."
    )
    return stats


def load_access_scores(cur: pyodbc.Cursor) -> list[AccessScore]:
    cur.execute("SELECT ID, [Team A], [Team B], A, B, Tournament, Phase, Extra FROM Scores")
    out: list[AccessScore] = []
    for row in cur.fetchall():
        extra_raw = row[7]
        out.append(
            AccessScore(
                source_id=int(row[0]),
                team_a=normalize_display_name(str(row[1])),
                team_b=normalize_display_name(str(row[2])),
                goals_a=int(row[3]),
                goals_b=int(row[4]),
                raw_tournament=str(row[5]).strip() if row[5] else "",
                phase=str(row[6]).strip() if row[6] else None,
                extra=str(extra_raw).strip() if extra_raw else None,
            )
        )
    return out


def load_country_by_player(cur: pyodbc.Cursor) -> dict[str, str]:
    cur.execute("SELECT Player, Country FROM Rankings")
    out: dict[str, str] = {}
    for row in cur.fetchall():
        name = normalize_display_name(str(row[0]))
        country = str(row[1]).strip() if row[1] else ""
        if name:
            out[name] = country
    return out


def apply_name_map(scores: list[AccessScore], raw_to_canonical: dict[str, str]) -> None:
    for s in scores:
        object.__setattr__(s, "team_a", raw_to_canonical.get(s.team_a, normalize_display_name(s.team_a)))
        object.__setattr__(s, "team_b", raw_to_canonical.get(s.team_b, normalize_display_name(s.team_b)))


def merge_supplemental_scores(scores: list[AccessScore]) -> list[AccessScore]:
    """Append documented games missing from Access Scores (import_corrections.py)."""
    if not SUPPLEMENTAL_SCORES:
        return scores
    existing_ids = {s.source_id for s in scores}
    next_id = IMPORT_SUPPLEMENT_SCORES_ID_BASE
    out = list(scores)
    for sup in SUPPLEMENTAL_SCORES:
        while next_id in existing_ids:
            next_id += 1
        out.append(
            AccessScore(
                source_id=next_id,
                team_a=sup.team_a,
                team_b=sup.team_b,
                goals_a=sup.goals_a,
                goals_b=sup.goals_b,
                raw_tournament=sup.tournament,
                phase=sup.phase,
                extra=sup.extra,
            )
        )
        existing_ids.add(next_id)
        next_id += 1
    return out
