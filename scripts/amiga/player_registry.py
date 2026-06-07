"""Internal KOA-aware player naming and creation for amiga_players."""

from __future__ import annotations

import argparse
import json
import sys
from dataclasses import dataclass
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_names import (
    identity_key,
    normalize_display_name,
    suggest_koa_display_name,
)


@dataclass(frozen=True)
class ExistingPlayer:
    id: int
    name: str
    country: str


@dataclass(frozen=True)
class NameCheckResult:
    input_name: str
    normalized_name: str
    available: bool
    conflict: ExistingPlayer | None = None
    conflict_kind: str | None = None


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    if cfg.database != "ko2amiga_db":
        raise SystemExit(f"Refusing player ops: expected ko2amiga_db, got {cfg.database!r}")
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


def _load_players(conn: pymysql.connections.Connection) -> list[ExistingPlayer]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, name, country FROM amiga_players ORDER BY id")
        rows = cur.fetchall()
    return [ExistingPlayer(int(r["id"]), str(r["name"]), str(r["country"])) for r in rows]


def _build_indexes(
    players: list[ExistingPlayer],
) -> tuple[dict[str, ExistingPlayer], dict[str, ExistingPlayer]]:
    by_identity: dict[str, ExistingPlayer] = {}
    by_exact_name: dict[str, ExistingPlayer] = {}
    for player in players:
        by_identity[identity_key(player.name)] = player
        by_exact_name[player.name] = player
    return by_identity, by_exact_name


def check_player_name(
    raw_name: str,
    *,
    players: list[ExistingPlayer] | None = None,
    conn: pymysql.connections.Connection | None = None,
) -> NameCheckResult:
    normalized = normalize_display_name(raw_name)
    if not normalized:
        raise ValueError("name must be non-empty after normalization")

    if players is None:
        if conn is None:
            raise ValueError("check_player_name requires players or conn")
        players = _load_players(conn)

    by_identity, by_exact_name = _build_indexes(players)
    key = identity_key(normalized)

    if key in by_identity:
        existing = by_identity[key]
        kind = "identity" if existing.name != normalized else "exact"
        return NameCheckResult(
            input_name=raw_name,
            normalized_name=normalized,
            available=False,
            conflict=existing,
            conflict_kind=kind,
        )

    if normalized in by_exact_name:
        return NameCheckResult(
            input_name=raw_name,
            normalized_name=normalized,
            available=False,
            conflict=by_exact_name[normalized],
            conflict_kind="exact",
        )

    return NameCheckResult(
        input_name=raw_name,
        normalized_name=normalized,
        available=True,
    )


def suggest_player_name(
    full_name: str,
    *,
    players: list[ExistingPlayer] | None = None,
    conn: pymysql.connections.Connection | None = None,
) -> dict[str, Any]:
    if players is None:
        if conn is None:
            raise ValueError("suggest_player_name requires players or conn")
        players = _load_players(conn)

    taken = {identity_key(p.name) for p in players}
    suggestion = suggest_koa_display_name(full_name, taken)
    payload: dict[str, Any] = {
        "input": full_name,
        "normalized_input": suggestion.normalized_input,
        "suggested_name": suggestion.suggested_name,
        "available": suggestion.suggested_name is not None,
    }
    if suggestion.reason:
        payload["reason"] = suggestion.reason
    if suggestion.suggested_name is not None:
        payload["normalized_suggestion"] = normalize_display_name(suggestion.suggested_name)
    return payload


def create_player(
    raw_name: str,
    *,
    country: str = "",
    display: int = 1,
    dry_run: bool = False,
    conn: pymysql.connections.Connection | None = None,
) -> dict[str, Any]:
    owns_conn = conn is None
    if owns_conn:
        conn = _connect()

    assert conn is not None

    try:
        normalized = normalize_display_name(raw_name)
        if not normalized:
            raise ValueError("name must be non-empty after normalization")

        check = check_player_name(normalized, conn=conn)
        if not check.available:
            existing = check.conflict
            assert existing is not None
            raise ValueError(
                f"name conflict ({check.conflict_kind}): "
                f"normalized={normalized!r} collides with player_id={existing.id} name={existing.name!r}"
            )

        row = {
            "name": normalized,
            "country": country,
            "display": display,
        }

        if dry_run:
            if owns_conn:
                conn.rollback()
            return {"dry_run": True, "row": row, "player_id": None}

        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO amiga_players (name, country, display) VALUES (%s, %s, %s)",
                (row["name"], row["country"], row["display"]),
            )
            player_id = int(cur.lastrowid)
        if owns_conn:
            conn.commit()
        return {"dry_run": False, "row": row, "player_id": player_id}
    except Exception:
        conn.rollback()
        raise
    finally:
        if owns_conn:
            conn.close()


def _print_json(payload: dict[str, Any]) -> None:
    print(json.dumps(payload, indent=2, sort_keys=True))


def _print_check(result: NameCheckResult) -> None:
    payload: dict[str, Any] = {
        "input": result.input_name,
        "normalized_name": result.normalized_name,
        "available": result.available,
    }
    if result.conflict is not None:
        payload["conflict"] = {
            "player_id": result.conflict.id,
            "name": result.conflict.name,
            "country": result.conflict.country,
            "kind": result.conflict_kind,
        }
    _print_json(payload)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Internal KOA-aware Amiga player registry ops")
    sub = parser.add_subparsers(dest="cmd", required=True)

    p_check = sub.add_parser("check-name", help="Check whether a proposed player name is usable")
    p_check.add_argument("--name", required=True)

    p_suggest = sub.add_parser("suggest-name", help="Suggest a KOA-style display name for a newcomer")
    p_suggest.add_argument("--full-name", required=True)

    p_create = sub.add_parser("create", help="Create a new amiga_players row")
    p_create.add_argument("--name", required=True)
    p_create.add_argument("--country", default="")
    p_create.add_argument("--dry-run", action="store_true")

    args = parser.parse_args(argv)

    try:
        if args.cmd == "check-name":
            conn = _connect()
            try:
                result = check_player_name(args.name, conn=conn)
            finally:
                conn.close()
            _print_check(result)
            return 0 if result.available else 1

        if args.cmd == "suggest-name":
            conn = _connect()
            try:
                payload = suggest_player_name(args.full_name, conn=conn)
            finally:
                conn.close()
            _print_json(payload)
            return 0 if payload.get("available") else 1

        if args.cmd == "create":
            payload = create_player(
                args.name,
                country=args.country,
                dry_run=args.dry_run,
            )
            if payload["dry_run"]:
                print("DRY RUN: rolled back; row that would be inserted:")
            else:
                print(f"OK: created player_id={payload['player_id']}")
            _print_json(payload)
            return 0
    except (ValueError, pymysql.Error) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    return 1


if __name__ == "__main__":
    sys.exit(main())
