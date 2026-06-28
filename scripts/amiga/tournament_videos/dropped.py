"""Dropped (non-catalog) tournament video IDs — audit log + harvest denylist."""

from __future__ import annotations

import csv
from datetime import date

from scripts.amiga.tournament_videos.constants import DROPPED_CSV, DROPPED_CSV_COLUMNS, REVIEW_CSV


def load_dropped_ids() -> set[str]:
    if not DROPPED_CSV.is_file():
        return set()
    with DROPPED_CSV.open(encoding="utf-8", newline="") as fh:
        return {
            (row.get("youtube_id") or "").strip()
            for row in csv.DictReader(fh)
            if (row.get("youtube_id") or "").strip()
        }


def append_drop(*, youtube_id: str, title: str, reason: str) -> None:
    youtube_id = youtube_id.strip()
    if not youtube_id:
        raise ValueError("youtube_id required")
    DROPPED_CSV.parent.mkdir(parents=True, exist_ok=True)
    exists = DROPPED_CSV.is_file()
    with DROPPED_CSV.open("a", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=DROPPED_CSV_COLUMNS)
        if not exists:
            writer.writeheader()
        writer.writerow(
            {
                "youtube_id": youtube_id,
                "title": title,
                "reason": reason.strip(),
                "dropped_on": date.today().isoformat(),
            }
        )


def drop_from_review(youtube_id: str, *, reason: str) -> dict[str, str]:
    """Remove one row from review.csv and append to dropped.csv."""
    youtube_id = youtube_id.strip()
    if not youtube_id:
        raise ValueError("youtube_id required")
    dropped_ids = load_dropped_ids()
    if youtube_id in dropped_ids:
        raise ValueError(f"{youtube_id} already in dropped.csv")

    if not REVIEW_CSV.is_file():
        raise FileNotFoundError(REVIEW_CSV)

    with REVIEW_CSV.open(encoding="utf-8", newline="") as fh:
        rows = list(csv.DictReader(fh))

    match = [r for r in rows if (r.get("youtube_id") or "").strip() == youtube_id]
    if not match:
        raise KeyError(f"{youtube_id} not found in review.csv")

    row = match[0]
    title = (row.get("title") or "").strip()
    append_drop(youtube_id=youtube_id, title=title, reason=reason)

    kept = [r for r in rows if (r.get("youtube_id") or "").strip() != youtube_id]
    from scripts.amiga.tournament_videos.constants import CSV_COLUMNS

    with REVIEW_CSV.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(kept)

    return row