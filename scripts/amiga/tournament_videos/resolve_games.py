"""Intelligent game_id resolution for review.csv (TV-1 review pass)."""

from __future__ import annotations

import csv
import re
import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, REVIEW_CSV
from scripts.amiga.tournament_videos.game_match import (
    GameRow,
    _parse_score,
    _phase_hint,
    _phase_matches,
    load_tournament_games,
)

DUAL_LEG_MIN_SEC = 20 * 60
KNOCKOUT_STAGES = frozenset({"quarter", "semi", "final", "bronze", "silver"})

TITLE_PLAYERS_RE = re.compile(
    r"(?:[-–]\s*|\b)([A-Za-z][A-Za-z .']*?)\s+vs\.?\s+([A-Za-z][A-Za-z .']*?)"
    r"(?:\s*$|\s*\(|\s*,|\.avi|\s+-|\s+\d)",
    re.I,
)
COMPACT_VS_RE = re.compile(
    r"([A-Z][a-z]+[A-Z][a-z]?)\s*(?:v|vs\.?)\s*([A-Z][a-z]+[A-Z][a-z]?)",
    re.I,
)
FILENAME_PLAYERS_RE = re.compile(
    r"([A-Za-z][A-Za-z0-9]*)_vs_([A-Za-z][A-Za-z0-9]*)", re.I
)
FILENAME_V_RE = re.compile(
    r"([A-Za-z][A-Za-z0-9]*)_v_([A-Za-z][A-Za-z0-9]*)", re.I
)
ATHENS08_RE = re.compile(
    r"Athens08_\d+_(?P<stage>Shame|SilverCup|Final_[AB])_(?P<a>[A-Za-z]+)_(?P<b>[A-Za-z]+)_(?P<score>\d+-\d+)",
    re.I,
)
DASH_PLAYERS_RE = re.compile(
    r"([A-Za-z][A-Za-z0-9 ]*?)\s*[-–]\s*([A-Za-z][A-Za-z0-9 ]*?)(?:\s*\(|\s*$|\s*,)",
    re.I,
)
YEAR_RE = re.compile(
    r"\b(?:WC\s*(\d{4})|World\s+Cup\s+(\d{4})|WC(\d{4})|UKC\s*0?(\d{2})|UKC\s+(\d{4})|(\b20\d{2})\b)",
    re.I,
)
KOA_WC_RE = re.compile(r"KOA_WC(\d{4})", re.I)
ATHENS08_TAG = re.compile(r"Athens08", re.I)
UKC08_TAG = re.compile(r"UKC\s*0?8\b|UKC08", re.I)

UKC08_GOLD = 316
UKC08_SILVER = 317
UKC08_GOLD_LABEL = "Birmingham VIII Gold Cup"
UKC08_SILVER_LABEL = "Birmingham VIII Silver Cup"
WC_2008 = 358
WC_2008_LABEL = "World Cup VIII (Athens)"

PLAYER_ALIASES = {
    "Dahg": "Dagh",
    "Waynie": "Wayne",
    "Chris": "Christopher",
    "Fred": "Frederic",
    "Joerg": "Jorg",
    "Tommaso R": "Tommaso",
    "Rodolfo M": "Rodolfo",
}


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def load_wc_by_year(cur) -> dict[int, tuple[int, str]]:
    cur.execute(
        "SELECT id, name, YEAR(event_date) AS yr FROM tournaments "
        "WHERE name REGEXP '^World Cup' ORDER BY event_date"
    )
    return {int(r["yr"]): (int(r["id"]), str(r["name"])) for r in cur.fetchall()}


def infer_stage(title: str, row_stage: str) -> str:
    if row_stage:
        return row_stage.lower()
    tl = title.lower()
    if "shame" in tl:
        return "shame"
    if "3rd" in tl or "third place" in tl:
        return "bronze"
    if "semi" in tl:
        return "semi"
    if "quarter" in tl or "qf" in tl:
        return "quarter"
    if "silver" in tl:
        return "silver"
    if "final" in tl:
        return "final"
    return ""


def parse_players_from_title(title: str) -> tuple[str, str]:
    m = ATHENS08_RE.search(title)
    if m:
        return m.group("a"), m.group("b")
    m = COMPACT_VS_RE.search(title)
    if m:
        return m.group(1), m.group(2)
    for pat in (FILENAME_PLAYERS_RE, FILENAME_V_RE):
        m = pat.search(title)
        if m:
            return m.group(1), m.group(2)
    m = DASH_PLAYERS_RE.search(re.sub(r"(?i)silver\s+cup\s+final\s*", "", title))
    if m:
        a, b = m.group(1).strip(), m.group(2).strip()
        if a.lower() not in ("silver", "gold", "cup", "final"):
            return a, b
    cleaned = re.sub(
        r"(?i)(silver\s+cup|gold\s+cup|bronze\s+cup|game\s+of\s+shame|3rd\s+place\s*-?\s*)",
        " ",
        title,
    )
    cleaned = re.sub(
        r"(?i)(semi\s*final\s*\d+[ab]?|semi\s*\d+[ab]?|final\s*\d+|part\s*\d+\s*-?\s*)",
        " ",
        cleaned,
    )
    matches = list(TITLE_PLAYERS_RE.finditer(cleaned))
    if matches:
        m = matches[-1]
        a, b = m.group(1).strip(), m.group(2).strip()
        if a.lower() not in ("decider", "place", "final", "semi", "quarter", "leg", "silver", "gold"):
            return a, b
    return "", ""


def normalize_token(token: str) -> str:
    token = token.strip().rstrip(".")
    if token in PLAYER_ALIASES:
        token = PLAYER_ALIASES[token]
    m = re.match(r"^([A-Za-z]+)([A-Z][a-z]?)$", token)
    if m and " " not in token:
        return f"{m.group(1)} {m.group(2)}"
    token = token.replace("_", " ")
    if token.endswith("K") and len(token) > 3 and " " not in token:
        return token[:-1] + " K"
    return token


def infer_leg(title: str, row_leg: str) -> int | None:
    if row_leg and str(row_leg).strip().isdigit():
        return int(row_leg)
    tl = title.lower()
    m = re.search(r"leg\s*(\d)", tl)
    if m:
        return int(m.group(1))
    if re.search(r"1st leg|leg 1|finala|semi1a|semi 1a|\b1a\b|semi final 1\b", tl):
        return 1
    if re.search(r"2nd leg|leg 2|finalb|semi2|semi 2b|\b2b\b", tl):
        return 2
    return None


def infer_year(title: str, row_year: str) -> int | None:
    if row_year and str(row_year).strip().isdigit():
        return int(row_year)
    m = KOA_WC_RE.search(title)
    if m:
        return int(m.group(1))
    if ATHENS08_TAG.search(title):
        return 2008
    m = YEAR_RE.search(title)
    if not m:
        return None
    for g in m.groups():
        if not g:
            continue
        if len(g) == 2:
            return 2000 + int(g)
        return int(g)
    return None


def infer_tournament_from_title(
    title: str, year: int | None, wc_by_year: dict[int, tuple[int, str]]
) -> tuple[int | None, str | None]:
    tl = title.lower()
    if ATHENS08_TAG.search(title):
        return WC_2008, WC_2008_LABEL
    if re.search(r"\bukc\b", tl) and "world cup" not in tl:
        ukc_year = infer_year(title, "")
        if ukc_year == 2008:
            if "gold" in tl:
                return UKC08_GOLD, UKC08_GOLD_LABEL
            if "silver" in tl:
                return UKC08_SILVER, UKC08_SILVER_LABEL
        return None, None
    if year and year in wc_by_year:
        tid, tname = wc_by_year[year]
        return tid, tname
    return None, None


def name_matches(player_name: str, token: str) -> bool:
    token = normalize_token(token)
    if not token or len(token) < 2:
        return False
    m = re.match(r"^([A-Za-z]+)([A-Z][a-z]?)$", token)
    if m:
        first, ini = m.group(1), m.group(2)[0]
        parts = player_name.split()
        if len(parts) < 2:
            return False
        return parts[0].lower() == first.lower() and parts[1][0].upper() == ini.upper()
    parts_t = token.split()
    parts_p = player_name.split()
    if len(parts_t) == 1:
        return parts_p[0].lower() == parts_t[0].lower()
    return (
        parts_p[0].lower() == parts_t[0].lower()
        and parts_p[1].lower().startswith(parts_t[1][0].lower())
    )


def roster_at_tournament(cur, tid: int) -> dict[int, str]:
    cur.execute(
        """
        SELECT DISTINCT p.id, p.name
        FROM amiga_players p
        INNER JOIN amiga_games g ON g.player_a_id = p.id OR g.player_b_id = p.id
        WHERE g.tournament_id = %s
        """,
        (tid,),
    )
    return {int(r["id"]): str(r["name"]) for r in cur.fetchall()}


def games_for_pair(
    all_games: list[GameRow], pa: int, pb: int, stage: str
) -> list[GameRow]:
    phase = _phase_hint(stage)
    out: list[GameRow] = []
    for g in all_games:
        if {g.player_a_id, g.player_b_id} != {pa, pb}:
            continue
        if phase and not _phase_matches(g.phase, phase):
            continue
        out.append(g)
    return sorted(out, key=lambda x: x.game_id)


def score_filter(
    games: list[GameRow], pa: int, pb: int, score: str
) -> list[GameRow]:
    parsed = _parse_score(score)
    if not parsed or not games:
        return games
    ga, gb = parsed
    exact: list[GameRow] = []
    for g in games:
        if g.player_a_id == pa and g.player_b_id == pb and (g.goals_a, g.goals_b) == (ga, gb):
            exact.append(g)
        elif g.player_a_id == pb and g.player_b_id == pa and (g.goals_a, g.goals_b) == (gb, ga):
            exact.append(g)
    return exact if exact else games


def pick_game_ids(
    games: list[GameRow],
    *,
    duration_sec: int | None,
    stage: str,
    leg: int | None,
    dual_leg_ok: bool,
    player_a_id: int | None = None,
    player_b_id: int | None = None,
) -> list[int]:
    if not games:
        return []
    if len(games) == 1:
        return [games[0].game_id]
    games = sorted(games, key=lambda g: g.game_id)
    if player_a_id and player_b_id:
        by_home = [
            g
            for g in games
            if g.player_a_id == player_a_id and g.player_b_id == player_b_id
        ]
        if len(by_home) == 1:
            return [by_home[0].game_id]
    dur = duration_sec or 0
    if (
        dual_leg_ok
        and len(games) == 2
        and dur >= DUAL_LEG_MIN_SEC
        and stage in KNOCKOUT_STAGES
    ):
        return [g.game_id for g in games]
    if leg in (1, 2) and len(games) >= leg:
        return [games[leg - 1].game_id]
    if len(games) == 2 and dur < DUAL_LEG_MIN_SEC and leg is None:
        return [games[0].game_id]
    return []


def find_pair(
    roster: dict[int, str],
    all_games: list[GameRow],
    token_a: str,
    token_b: str,
    stage: str,
) -> tuple[int | None, int | None, list[GameRow]]:
    ids_a = [pid for pid, name in roster.items() if name_matches(name, token_a)]
    ids_b = [pid for pid, name in roster.items() if name_matches(name, token_b)]
    best: tuple[int, int, int, list[GameRow]] | None = None
    for pa in ids_a:
        for pb in ids_b:
            if pa == pb:
                continue
            games = games_for_pair(all_games, pa, pb, stage)
            if not games:
                continue
            rank = len(games)
            if best is None or rank < best[0]:
                best = (rank, pa, pb, games)
    if best:
        return best[1], best[2], best[3]
    return None, None, []


def resolve_row(
    row: dict[str, str],
    cur,
    wc_by_year: dict[int, tuple[int, str]],
    cache: dict[int, list[GameRow]],
) -> tuple[list[int], str | None]:
    if row.get("kind") != "match":
        return [], "not a match row"
    title = row.get("title") or ""
    stage = infer_stage(title, row.get("stage") or "")
    leg = infer_leg(title, row.get("leg") or "")
    dur_raw = (row.get("duration_sec") or "").strip()
    duration_sec = int(dur_raw) if dur_raw.isdigit() else None

    tid_raw = (row.get("guessed_tournament_id") or "").strip()
    year = infer_year(title, row.get("year") or "")
    if tid_raw.isdigit():
        tid = int(tid_raw)
    else:
        tid, tname = infer_tournament_from_title(title, year, wc_by_year)
        if tid:
            row["guessed_tournament_id"] = str(tid)
            row["tournament_guess_label"] = tname or ""
            if year:
                row["year"] = str(year)
        elif year and year in wc_by_year and not re.search(r"\bukc\b", title.lower()):
            tid, tname = wc_by_year[year]
            row["guessed_tournament_id"] = str(tid)
            row["tournament_guess_label"] = tname
            row["year"] = str(year)
        else:
            return [], "no tournament_id"

    ath = ATHENS08_RE.search(title)
    if ath and not row.get("score"):
        row["score"] = ath.group("score").replace(" ", "")
    if ath and not row.get("leg"):
        leg_char = ath.group("stage")[-1].upper()
        if leg_char in ("A", "B"):
            row["leg"] = "1" if leg_char == "A" else "2"

    token_a, token_b = parse_players_from_title(title)
    token_a, token_b = normalize_token(token_a), normalize_token(token_b)
    if row.get("player_a_guess") and row.get("player_a_guess") not in ("decider", ""):
        token_a = token_a or row["player_a_guess"]
    if row.get("player_b_guess"):
        token_b = token_b or row["player_b_guess"]

    if tid not in cache:
        cache[tid] = load_tournament_games(tid)
    all_games = cache[tid]
    roster = roster_at_tournament(cur, tid)

    if not token_a or not token_b:
        if stage == "shame":
            shame_games = [
                g for g in all_games if "shame" in (g.phase or "").lower()
            ]
            if len(shame_games) == 1:
                g = shame_games[0]
                row["player_a_id_guess"] = str(g.player_a_id)
                row["player_b_id_guess"] = str(g.player_b_id)
                row["player_a_guess"] = roster.get(g.player_a_id, "")
                row["player_b_guess"] = roster.get(g.player_b_id, "")
                return [g.game_id], "sole game of shame at event"
        return [], "could not parse players from title"

    pa, pb, games = find_pair(roster, all_games, token_a, token_b, stage)
    if not pa or not games:
        return [], f"no pair at event for {token_a!r} vs {token_b!r}"

    row["player_a_id_guess"] = str(pa)
    row["player_b_id_guess"] = str(pb)
    row["player_a_guess"] = roster.get(pa, row.get("player_a_guess", ""))
    row["player_b_guess"] = roster.get(pb, row.get("player_b_guess", ""))
    if stage and not row.get("stage"):
        row["stage"] = stage

    games = score_filter(games, pa, pb, row.get("score") or "")
    ids = pick_game_ids(
        games,
        duration_sec=duration_sec,
        stage=stage,
        leg=leg,
        dual_leg_ok=True,
        player_a_id=pa,
        player_b_id=pb,
    )
    if ids:
        note = None
        if len(ids) == 2:
            note = "dual-leg video (duration>=20min)"
        return ids, note
    return [], f"ambiguous ({len(games)} games, dur={duration_sec}, leg={leg})"


def run(*, only_missing: bool = True) -> int:
    rows: list[dict[str, str]] = []
    with REVIEW_CSV.open(encoding="utf-8", newline="") as fh:
        rows = list(csv.DictReader(fh))

    conn = _connect()
    cur = conn.cursor()
    wc_by_year = load_wc_by_year(cur)
    cache: dict[int, list[GameRow]] = {}

    resolved = 0
    escalations: list[str] = []

    for row in rows:
        if only_missing and (row.get("game_id_guess") or "").strip():
            continue
        if row.get("kind") != "match":
            continue
        ids, note = resolve_row(row, cur, wc_by_year, cache)
        if ids:
            row["game_id_guess"] = ",".join(str(i) for i in ids)
            if note:
                prev = (row.get("notes") or "").strip()
                row["notes"] = "; ".join(x for x in (prev, note) if x)
            resolved += 1
        else:
            escalations.append(f"{row.get('youtube_id')}: {note} | {row.get('title','')[:60]}")

    conn.close()

    with REVIEW_CSV.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)

    match_rows = [r for r in rows if r["kind"] == "match"]
    with_gid = sum(1 for r in match_rows if (r.get("game_id_guess") or "").strip())
    print(f"Wrote {REVIEW_CSV}")
    print(f"  resolved this pass: {resolved}")
    print(f"  match rows with game_id: {with_gid}/{len(match_rows)}")
    if escalations:
        print(f"  escalations ({len(escalations)}):")
        for line in escalations[:40]:
            print(f"    {line}")
        if len(escalations) > 40:
            print(f"    ... and {len(escalations) - 40} more")
    return 0


def main(argv: list[str] | None = None) -> int:
    import argparse

    p = argparse.ArgumentParser(description="Resolve game_id_guess on review.csv")
    p.add_argument(
        "--all",
        action="store_true",
        help="Re-resolve every match row (default: only rows missing game_id_guess)",
    )
    args = p.parse_args(argv)
    return run(only_missing=not args.all)


if __name__ == "__main__":
    raise SystemExit(main())
