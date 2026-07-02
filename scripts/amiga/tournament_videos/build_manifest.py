"""Build shipped tournament_videos.json from review.csv (TV-2)."""

from __future__ import annotations

import argparse
import csv
import json
from datetime import date

from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, MANIFEST_JSON, REVIEW_CSV
from scripts.amiga.tournament_videos.game_links import manifest_game_start_sec

REVIEW_CSV_PUBLIC = MANIFEST_JSON.parent / "tournament_videos" / "review.csv"

STAGE_SORT = {
    "final": 10,
    "semi": 20,
    "quarter": 30,
    "bronze": 40,
    "silver": 45,
    "shame": 50,
    "exhibition": 55,
    "league": 60,
    "presentations": 70,
    "medals": 75,
    "atmosphere": 80,
    "stream": 90,
    "day1": 91,
    "day2": 92,
}


def _int(val: str) -> int | None:
    val = (val or "").strip()
    return int(val) if val.isdigit() else None


def _truthy(val: str) -> bool:
    return (val or "").strip().lower() in ("y", "yes", "true", "1")


def _game_ids(raw: str) -> list[int]:
    out: list[int] = []
    for part in (raw or "").split(","):
        part = part.strip()
        if part.isdigit():
            out.append(int(part))
    return out


def csv_row_to_manifest(row: dict[str, str], *, verified_only: bool) -> dict | None:
    kind = (row.get("kind") or "").strip() or "match"
    if kind == "excluded":
        return None
    if verified_only and not _truthy(row.get("verified", "")):
        return None

    tid = _int(row.get("guessed_tournament_id", ""))
    if not tid:
        return None

    leg = _int(row.get("leg", ""))
    stage = (row.get("stage") or "").strip()
    sort = STAGE_SORT.get(stage.lower(), 100)
    if leg:
        sort += leg

    entry: dict = {
        "youtube_id": row["youtube_id"],
        "title": row.get("title") or "",
        "tournament_id": tid,
        "kind": kind,
        "stage": stage or None,
        "leg": leg,
        "score": (row.get("score") or "").strip() or None,
        "player_a_id": _int(row.get("player_a_id_guess", "")),
        "player_b_id": _int(row.get("player_b_id_guess", "")),
        "duration_sec": _int(row.get("duration_sec", "")),
        "sort": sort,
        "source": row.get("source") or None,
        "source_channel": row.get("source_channel") or None,
        "source_playlist": row.get("source_playlist") or None,
        "relation_group": (row.get("relation_group") or "").strip() or None,
        "relation": (row.get("relation") or "").strip() or None,
        "featured_final": _truthy(row.get("featured_final", "")),
        "verified": _truthy(row.get("verified", "")),
        "notes": (row.get("notes") or "").strip() or None,
        "external_url": (row.get("external_url") or "").strip() or None,
    }
    wc_slot = (row.get("wc_video_slot") or "").strip()
    if wc_slot:
        entry["wc_video_slot"] = wc_slot
    link_mode = (row.get("game_link_mode") or "").strip()
    if link_mode:
        entry["game_link_mode"] = link_mode
    game_ids = _game_ids(row.get("game_id_guess", ""))
    if game_ids:
        entry["game_ids"] = game_ids
        starts = manifest_game_start_sec(row["youtube_id"], game_ids)
        if starts is not None:
            entry["game_start_sec"] = starts
    return entry


def build(*, verified_only: bool = False) -> dict:
    with REVIEW_CSV.open(encoding="utf-8", newline="") as fh:
        rows = list(csv.DictReader(fh))

    videos: list[dict] = []
    for row in rows:
        entry = csv_row_to_manifest(row, verified_only=verified_only)
        if entry:
            videos.append(entry)

    videos.sort(key=lambda v: (v["tournament_id"], v.get("sort") or 999, v["youtube_id"]))
    return {
        "schema_version": 1,
        "updated_at": date.today().isoformat(),
        "videos": videos,
    }


def run(*, verified_only: bool = False) -> int:
    payload = build(verified_only=verified_only)
    MANIFEST_JSON.parent.mkdir(parents=True, exist_ok=True)
    MANIFEST_JSON.write_text(
        json.dumps(payload, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    REVIEW_CSV_PUBLIC.parent.mkdir(parents=True, exist_ok=True)
    REVIEW_CSV_PUBLIC.write_text(REVIEW_CSV.read_text(encoding="utf-8"), encoding="utf-8")
    verified_n = sum(1 for v in payload["videos"] if v.get("verified"))
    print(f"Wrote {MANIFEST_JSON}")
    print(f"Wrote {REVIEW_CSV_PUBLIC}")
    print(f"  videos: {len(payload['videos'])} ({verified_n} verified=true)")
    return 0


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description="Build tournament_videos.json from review.csv")
    p.add_argument(
        "--verified-only",
        action="store_true",
        help="Include only CSV rows with verified=Y (strict gate)",
    )
    args = p.parse_args(argv)
    return run(verified_only=args.verified_only)


if __name__ == "__main__":
    raise SystemExit(main())