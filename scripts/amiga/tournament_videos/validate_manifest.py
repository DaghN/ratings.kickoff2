"""Validate tournament_videos.json (TV-2)."""

from __future__ import annotations

import json
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_videos.constants import MANIFEST_JSON


def run() -> int:
    data = json.loads(MANIFEST_JSON.read_text(encoding="utf-8"))
    videos = data.get("videos") or []
    errors: list[str] = []

    ids = [v.get("youtube_id") for v in videos]
    if len(ids) != len(set(ids)):
        errors.append("duplicate youtube_id in manifest")

    groups: dict[str, list[str]] = {}
    for v in videos:
        rg = v.get("relation_group")
        if rg:
            groups.setdefault(rg, []).append(v.get("relation") or "")

    for rg, rels in groups.items():
        canon = [r for r in rels if r == "canonical"]
        if len(canon) > 1:
            errors.append(f"relation_group {rg!r} has multiple canonical rows")

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
    cur = conn.cursor()
    tids = {int(v["tournament_id"]) for v in videos if v.get("tournament_id")}
    if tids:
        cur.execute(
            "SELECT id FROM tournaments WHERE id IN (%s)"
            % ",".join(str(t) for t in sorted(tids))
        )
        found = {int(r["id"]) for r in cur.fetchall()}
        missing = tids - found
        if missing:
            errors.append(f"unknown tournament_id values: {sorted(missing)[:10]}")

    conn.close()

    if errors:
        for e in errors:
            print(f"ERROR: {e}", file=sys.stderr)
        return 1
    print(f"OK: {len(videos)} videos, {len(groups)} relation groups")
    return 0


if __name__ == "__main__":
    raise SystemExit(run())