"""Map the KO2CV_TV World Cup XXIV (Oslo) day streams to games via Steve's timestamps.

Sources (git): data/amiga/forward/2026-11-14-world-cup-xxiv-oslo/stream_day{1,2}_*_description.txt
Targets (shared editorial, git):
  data/amiga/tournament_videos/video_game_links.csv  — one sidecar row per timestamped game
  data/amiga/tournament_videos/review.csv            — one stream_map row per day stream

Every proposed link is resolved through the same resolver the video verify uses
(game_links.resolve_fact_link); a link is only accepted when it resolves uniquely to the
intended game. Timestamps that are not official games (friendlies, intros, "Game Room",
presentations, or pairs that never met officially) are listed and skipped.

Default = report only. ``--apply`` writes both CSVs (refuses if rows for these videos exist).
Then: python -m scripts.amiga align-video-work / verify-tournament-videos-work / promote-video-deploy
"""
from __future__ import annotations

import argparse
import csv
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO))

from scripts.amiga.tournament_videos.game_links import MatchFactLink, resolve_fact_link  # noqa: E402
from scripts.amiga.tournament_videos.game_match import load_tournament_games  # noqa: E402
from scripts.amiga.tournament_videos.manifest_db import DbSnapshot, connect_db  # noqa: E402

SRC = REPO / "data/amiga/forward/2026-11-14-world-cup-xxiv-oslo"
SIDECAR = REPO / "data/amiga/tournament_videos/video_game_links.csv"
REVIEW = REPO / "data/amiga/tournament_videos/review.csv"

TOURNAMENT_ID = 608
TOURNAMENT_LABEL = "World Cup XXIV (Oslo)"

STREAMS = [
    {
        "youtube_id": "SW3Ry0t53FI",
        "file": "stream_day1_SW3Ry0t53FI_description.txt",
        "title": "Kick Off 2 World Cup 2026 - Oslo, Norway - Day 1",
        "duration_sec": "32196",
        "stage": "day1",
        "day_phase": lambda phase: phase.startswith("Round 1 - "),
    },
    {
        "youtube_id": "H7YSe23l708",
        "file": "stream_day2_H7YSe23l708_description.txt",
        "title": "Kick Off 2 World Cup 2026 - Oslo, Norway - Day 2",
        "duration_sec": "41284",
        "stage": "day2",
        "day_phase": lambda phase: not phase.startswith("Round 1 - "),
    },
]

# Stream short names -> amiga_players.name
SHORT = {
    "Nikos": "Nikos A", "Ian": "Ian K", "JohnH": "John H", "SteveC": "Steve C", "SteveB": "Steve B",
    "SteveCl": "Steve Cl", "Mick": "Mick C", "Chris": "Christopher D", "Frank": "Frank F", "Dagh": "Dagh N",
    "Ilker": "Ilker C", "Fabio": "Fabio F", "Robert": "Robert S", "Sandro": "Sandro T", "Jan": "Jan G",
    "Alessandro": "Alessandro V", "Allessandro": "Alessandro V", "Rodolfo": "Rodolfo M", "Gianni": "Gianni T",
    "Giannni": "Gianni T", "Jorn": "Jorn F", "Thor": "Thor S", "Terje": "Terje S", "Mark": "Mark W",
    "Jacob": "Jacob K",
}

LINE_RE = re.compile(r"^(\d{1,2}):(\d{2}):(\d{2})\s+(.+)$")


def parse_stream(path: Path) -> list[tuple[int, str]]:
    out = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        m = LINE_RE.match(raw.strip())
        if m:
            out.append((int(m[1]) * 3600 + int(m[2]) * 60 + int(m[3]), m[4].strip()))
    return out


GROUP_PHASE = re.compile(r"^(Round [12] - Group [A-F]|Silver Cup - Group G)$")
PLACE_RE = re.compile(r"(\d+)(st|nd|rd|th) Place", re.I)


def phase_filter(label: str):
    """Stage hint from Steve's label prefix ("Semi A 1st Leg - ...", "15th Place ...", ...)."""
    if " - " not in label:
        return lambda p: bool(GROUP_PHASE.match(p))  # unlabeled line = group / league game
    prefix = label.rsplit(" - ", 1)[0].lower()
    if prefix.startswith("game of shame"):
        return lambda p: p == "Game of Shame"
    if prefix.startswith("silver final"):
        return lambda p: p == "17th Place Final"
    if prefix.startswith("semi"):
        return lambda p: p == "Semi Final"
    if prefix.startswith("final"):
        return lambda p: p == "Final"
    m = PLACE_RE.search(prefix)
    if m:
        want = f"{m[1]}{m[2].lower()} Place Final"
        return lambda p: p == want
    return lambda p: True


def fmt(sec: int) -> str:
    return f"{sec // 3600}:{sec % 3600 // 60:02d}:{sec % 60:02d}"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()

    conn = connect_db()
    try:
        snap = DbSnapshot.load(conn)
    finally:
        conn.close()
    games = load_tournament_games(TOURNAMENT_ID)
    names = snap.players_by_id
    ids_by_name = {v: k for k, v in names.items()}

    used: set[int] = set()
    sidecar_rows: list[dict[str, str]] = []
    review_rows: list[dict[str, str]] = []
    problems = 0

    for st in STREAMS:
        entries = parse_stream(SRC / st["file"])
        print(f"\n== {st['youtube_id']} {st['title']} ({len(entries)} timestamps)")
        ordinal = 0
        linked: list[int] = []
        skipped: list[str] = []
        for sec, label in entries:
            text = label
            if " vs " not in text:
                skipped.append(f"{fmt(sec)} {label}  [not a game]")
                continue
            if label.lower().startswith("friendlies"):
                skipped.append(f"{fmt(sec)} {label}  [friendly]")
                continue
            pair = text.rsplit(" - ", 1)[-1]
            left, right = (s.strip() for s in pair.split(" vs ", 1))
            if left not in SHORT or right not in SHORT:
                skipped.append(f"{fmt(sec)} {label}  [unknown short name]")
                problems += 1
                continue
            a_id, b_id = ids_by_name[SHORT[left]], ids_by_name[SHORT[right]]
            stage_ok = phase_filter(label)
            cands = [g for g in games if st["day_phase"](g.phase) and stage_ok(g.phase) and g.game_id not in used
                     and g.player_a_id == a_id and g.player_b_id == b_id]
            if not cands:
                anyday = [g for g in games if {g.player_a_id, g.player_b_id} == {a_id, b_id}]
                why = "no official game for this pair" if not anyday else "pair only met on the other day / other orientation"
                skipped.append(f"{fmt(sec)} {label}  [{why}]")
                continue
            if len(cands) > 1:
                skipped.append(f"{fmt(sec)} {label}  [AMBIGUOUS within day: {[c.game_id for c in cands]}]")
                problems += 1
                continue
            g = cands[0]
            score = f"{g.goals_a}-{g.goals_b}"
            leg: int | None = None
            link = MatchFactLink(st["youtube_id"], ordinal + 1, TOURNAMENT_LABEL, names[a_id], names[b_id],
                                 score, "stream", None, sec)
            gid, note = resolve_fact_link(link, snap)
            if gid != g.game_id:
                # Same players + same score elsewhere in the event: pin by occurrence (leg = rank by id).
                same = sorted([x for x in games if x.player_a_id == a_id and x.player_b_id == b_id
                               and (x.goals_a, x.goals_b) == (g.goals_a, g.goals_b)], key=lambda x: x.game_id)
                leg = [x.game_id for x in same].index(g.game_id) + 1
                link = MatchFactLink(st["youtube_id"], ordinal + 1, TOURNAMENT_LABEL, names[a_id], names[b_id],
                                     score, "stream", leg, sec)
                gid, note = resolve_fact_link(link, snap)
            if gid != g.game_id:
                skipped.append(f"{fmt(sec)} {label}  [resolver gives {gid} ({note}), wanted {g.game_id}]")
                problems += 1
                continue
            ordinal += 1
            used.add(g.game_id)
            linked.append(g.game_id)
            sidecar_rows.append({
                "youtube_id": st["youtube_id"], "link_ordinal": str(ordinal), "tournament_label": TOURNAMENT_LABEL,
                "player_a": names[a_id], "player_b": names[b_id], "score": score, "stage": "stream",
                "leg": str(leg) if leg else "", "start_sec": str(sec), "verified": "Y",
            })
            print(f"  {ordinal:2d}. {fmt(sec)}  {label:45s} -> {g.game_id} {g.phase:22s} "
                  f"{names[a_id]} {score} {names[b_id]}{'  (leg ' + str(leg) + ')' if leg else ''}")
        for s in skipped:
            print(f"  skip  {s}")
        review_rows.append({
            "youtube_id": st["youtube_id"], "title": st["title"], "duration_sec": st["duration_sec"],
            "guessed_tournament_id": str(TOURNAMENT_ID), "tournament_guess_label": TOURNAMENT_LABEL,
            "year": "2026", "kind": "stream", "stage": st["stage"], "game_id_guess": ",".join(map(str, linked)),
            "source": "manual", "source_channel": "ko2cv", "confidence": "high", "verified": "Y",
            "notes": (f"KO2CV_TV live stream archive; {len(linked)} of {len(entries)} description timestamps "
                      f"(Steve C) mapped in video_game_links.csv; non-game/friendly markers skipped"),
            "game_link_mode": "stream_map",
        })

    print(f"\nlinked {len(sidecar_rows)} games across {len(STREAMS)} streams; problems needing a decision: {problems}")
    if not args.apply:
        print("report only (no --apply): nothing written")
        return 0
    if problems:
        print("refusing --apply while problems remain")
        return 1

    with SIDECAR.open(encoding="utf-8", newline="") as fh:
        reader = csv.DictReader(fh)
        side_fields = reader.fieldnames
        existing = [r for r in reader]
    with REVIEW.open(encoding="utf-8", newline="") as fh:
        reader = csv.DictReader(fh)
        rev_fields = reader.fieldnames
        rev_existing = [r for r in reader]
    ours = {s["youtube_id"] for s in STREAMS}
    if any(r["youtube_id"] in ours for r in existing) or any(r["youtube_id"] in ours for r in rev_existing):
        print("refusing --apply: rows for these videos already exist")
        return 1
    with SIDECAR.open("a", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=side_fields, lineterminator="\n")
        w.writerows(sidecar_rows)
    with REVIEW.open("a", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=rev_fields, lineterminator="\n")
        w.writerows([{f: r.get(f, "") for f in rev_fields} for r in review_rows])
    print(f"appended {len(sidecar_rows)} sidecar rows and {len(review_rows)} review rows")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
