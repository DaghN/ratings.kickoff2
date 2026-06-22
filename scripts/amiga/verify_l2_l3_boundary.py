#!/usr/bin/env python3
"""L2→L3 boundary gate: manifest lineage, re-prepare parity, nationality join oracle."""

from __future__ import annotations

import json
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import prepare_witness_from_l2
from scripts.amiga.import_corrections import SUPPLEMENTAL_SCORES
from scripts.amiga.import_l2_witness import DEFAULT_L2_DIR, DEFAULT_L2_SQL, load_l2_witness_inputs
from scripts.amiga.import_manifest import default_manifest_path
from scripts.amiga.player_names import canonical_country, identity_key

_REPO = Path(__file__).resolve().parents[2]

_STAT_KEYS = (
    ("tournaments", "tournaments"),
    ("games", "amiga_games"),
    ("players_canonical", "amiga_players"),
)


def verify_manifest_l2_lineage(
    manifest: dict[str, object],
    *,
    l2_sql_path: Path,
) -> list[str]:
    errors: list[str] = []
    source = manifest.get("source")
    if not isinstance(source, dict):
        errors.append("manifest missing source object")
        return errors

    layer = source.get("layer")
    if layer != "L2":
        errors.append(f"manifest source.layer={layer!r}, want 'L2' (strict stack)")

    if "path" in source and str(source.get("filename", "")).endswith(".mdb"):
        errors.append("manifest source still references .mdb — re-run import-witness from L2")

    manifest_path = source.get("path")
    if manifest_path:
        resolved = Path(str(manifest_path)).resolve()
        want = l2_sql_path.resolve()
        if resolved != want:
            errors.append(
                f"manifest L2 path {resolved} != verify target {want} "
                "(re-import witness or pass matching --l2-dir)"
            )
    elif not l2_sql_path.is_file():
        errors.append(f"L2 SQL missing: {l2_sql_path}")

    return errors


def verify_l2_l3_boundary(
    *,
    l2_dir: Path | None = None,
    manifest_path: Path | None = None,
) -> list[str]:
    errors: list[str] = []
    l2_dir = l2_dir or DEFAULT_L2_DIR
    l2_sql_path = l2_dir / DEFAULT_L2_SQL
    manifest_path = manifest_path or default_manifest_path(_REPO)

    if not l2_sql_path.is_file():
        errors.append(f"L2 SQL missing: {l2_sql_path} (run import-prune first)")
        return errors

    if not manifest_path.is_file():
        errors.append(f"missing manifest: {manifest_path} (run import-witness first)")
        return errors

    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("manifest_version") != 1:
        errors.append(f"unexpected manifest_version: {manifest.get('manifest_version')}")

    errors.extend(verify_manifest_l2_lineage(manifest, l2_sql_path=l2_sql_path))

    _source, l2_tournaments, l2_scores, l2_identity = load_l2_witness_inputs(l2_dir)
    prepared = prepare_witness_from_l2(l2_dir)
    manifest_stats = manifest.get("stats", {})
    if not isinstance(manifest_stats, dict):
        errors.append("manifest missing stats object")
        manifest_stats = {}

    want_games = len(l2_scores) + len(SUPPLEMENTAL_SCORES)
    got_games = len(prepared.scores_sorted)
    if want_games != got_games:
        errors.append(
            f"L2 scores ({len(l2_scores)}) + supplements ({len(SUPPLEMENTAL_SCORES)}) "
            f"= {want_games}, prepared games {got_games}"
        )

    for key, _table in _STAT_KEYS:
        want = manifest_stats.get(key)
        if want is None:
            errors.append(f"manifest stats missing {key}")
            continue
        prepared_val = {
            "tournaments": len(prepared.tournaments),
            "games": len(prepared.scores_sorted),
            "players_canonical": len(prepared.names),
        }[key]
        if int(want) != prepared_val:
            errors.append(
                f"manifest stats.{key}={want} != re-prepare from L2 ({prepared_val})"
            )

    variants_by_key: dict[str, list[str]] = {}
    for raw, canonical in prepared.raw_to_canonical.items():
        variants_by_key.setdefault(identity_key(canonical), []).append(raw)

    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        for key, table in _STAT_KEYS:
            want = manifest_stats.get(key)
            if want is None:
                continue
            cur.execute(f"SELECT COUNT(*) AS n FROM `{table}`")
            got = int(cur.fetchone()["n"])
            if got != int(want):
                errors.append(f"{table} count {got} != manifest stats.{key}={want}")

        cur.execute("SELECT name, country FROM amiga_players")
        db_players = {row["name"]: (row["country"] or "").strip() for row in cur.fetchall()}

    conn.close()

    identity_hits = 0
    for name in sorted(prepared.names):
        variants = variants_by_key.get(identity_key(name), [name])
        expected = canonical_country(name, variants, prepared.countries)
        got = db_players.get(name)
        if got is None:
            errors.append(f"amiga_players missing canonical name: {name!r}")
            continue
        if expected and got != expected:
            errors.append(
                f"nationality mismatch {name!r}: DB={got!r}, L2 identity oracle={expected!r}"
            )
        if expected:
            identity_hits += 1

    if errors:
        return errors

    coverage = (
        f"{identity_hits}/{len(prepared.names)} game players with L2 identity country"
        if prepared.names
        else "0 game players"
    )
    print(
        f"OK: L2->L3 boundary - {len(l2_scores)} L2 scores + {len(SUPPLEMENTAL_SCORES)} supplements "
        f"-> {got_games} games; {len(l2_tournaments)} L2 catalog / {len(l2_identity)} identity rows; "
        f"{coverage}"
    )
    return []


def main(argv: list[str] | None = None) -> int:
    import argparse

    parser = argparse.ArgumentParser(description="Verify L2 pruned SQL ↔ L3 witness boundary")
    parser.add_argument(
        "--l2-dir",
        type=Path,
        default=DEFAULT_L2_DIR,
        help="Directory with L2_pruned.sql",
    )
    parser.add_argument(
        "--manifest",
        type=Path,
        default=default_manifest_path(_REPO),
    )
    args = parser.parse_args(argv)

    errors = verify_l2_l3_boundary(l2_dir=args.l2_dir, manifest_path=args.manifest)
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
