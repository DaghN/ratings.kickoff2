"""Guess tournament, kind, players from titles + forum context."""

from __future__ import annotations

import logging
import re
from dataclasses import dataclass

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_names import identity_key, normalize_display_name, split_full_name
from scripts.amiga.tournament_videos.constants import PLAYLIST_OFFLINE_WC_MAX_YEAR, SOURCE_PRIORITY
from scripts.amiga.tournament_videos.forum_parse import ForumBullet
from scripts.amiga.tournament_videos.youtube_harvest import YtVideo

log = logging.getLogger(__name__)

YEAR_RE = re.compile(r"\b(?:WC\s*(\d{4})|World\s+Cup\s+(\d{4})|WC(\d{4})|(\b20(?:0\d|1\d|2[0-5])\b))\b", re.I)
LEG_RE = re.compile(r"\bleg\s*(\d+)\b", re.I)
SCORE_PAREN_RE = re.compile(r"\((\d+\s*-\s*\d+)\)")
COMPACT_VS_RE = re.compile(
    r"([A-Z][a-z]+(?:[A-Z][a-z]*)?)\s*(?:v|vs\.?|-)\s*([A-Z][a-z]+(?:[A-Z][a-z]*)?)",
    re.I,
)
FULL_VS_RE = re.compile(
    r"([A-Za-z][A-Za-z .'-]+?)\s*(?:v|vs\.?|-)\s*([A-Za-z][A-Za-z .'-]+?)(?:\s*\(|\s*,|\s*$)",
    re.I,
)
STREAM_HINTS = (
    "part",
    "day ",
    "koa wc",
    "saturday",
    "sunday",
    "coverage",
    "stream",
    "morning",
    "afternoon",
    "evening",
)
EXCLUDED_HINTS = ("online world cup", "kick off 2 online", "online wc")
CEREMONY_HINTS = ("presentation", "podium", "award", "ceremony", "opening")
COMPILATION_HINTS = ("compilation", "goals comp", "best goals")


@dataclass(frozen=True)
class WcTournament:
    tournament_id: int
    name: str
    year: int


@dataclass
class PlayerIndex:
    by_name_key: dict[str, list[int]]
    by_id: dict[int, str]


def load_wc_catalog() -> dict[int, WcTournament]:
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
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, YEAR(event_date) AS yr "
                "FROM tournaments WHERE name REGEXP '^World Cup' "
                "ORDER BY event_date"
            )
            rows = cur.fetchall()
    finally:
        conn.close()

    by_year: dict[int, WcTournament] = {}
    for row in rows:
        yr = int(row["yr"])
        by_year[yr] = WcTournament(int(row["id"]), str(row["name"]), yr)
    return by_year


def load_player_index() -> PlayerIndex:
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
    by_name_key: dict[str, list[int]] = {}
    by_id: dict[int, str] = {}
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT id, name FROM amiga_players WHERE name IS NOT NULL AND name <> ''")
            for row in cur.fetchall():
                pid = int(row["id"])
                name = normalize_display_name(str(row["name"]))
                by_id[pid] = name
                by_name_key.setdefault(identity_key(name), []).append(pid)
    finally:
        conn.close()
    return PlayerIndex(by_name_key=by_name_key, by_id=by_id)


def _parse_year(text: str, event_label: str = "") -> int | None:
    for src in (text, event_label):
        if not src:
            continue
        m = YEAR_RE.search(src)
        if not m:
            continue
        for g in m.groups():
            if g:
                return int(g)
    m = re.search(r"\b(20\d{2})\b", event_label)
    return int(m.group(1)) if m else None


def _normalize_stage(raw: str | None) -> str:
    if not raw:
        return ""
    s = raw.lower()
    if "final" in s and "semi" not in s and "quarter" not in s and "silver" not in s and "bronze" not in s:
        if "silver" in s:
            return "silver"
        if "bronze" in s:
            return "bronze"
        return "final"
    if "semi" in s:
        return "semi"
    if "quarter" in s or "qf" in s:
        return "quarter"
    if "silver" in s:
        return "silver"
    if "bronze" in s:
        return "bronze"
    if "shame" in s:
        return "shame"
    if "league" in s:
        return "league"
    return re.sub(r"[^a-z0-9]+", "_", s).strip("_")


def _guess_kind(title: str, duration_sec: int | None, stage: str, event_label: str) -> str:
    t = title.lower()
    if any(h in t for h in EXCLUDED_HINTS):
        return "excluded"
    if any(h in t for h in COMPILATION_HINTS):
        return "compilation"
    if any(h in t for h in CEREMONY_HINTS) or "presentation" in (event_label or "").lower():
        return "ceremony"
    if duration_sec and duration_sec > 3600:
        return "stream"
    if any(h in t for h in STREAM_HINTS):
        return "stream"
    if stage in ("final", "semi", "quarter", "silver", "bronze", "shame", "league"):
        return "match"
    if COMPACT_VS_RE.search(title) or FULL_VS_RE.search(title):
        return "match"
    return "coverage"


def _match_compact(token: str, players: PlayerIndex) -> tuple[str | None, int | None]:
    token = token.strip()
    m = re.match(r"^([A-Z][a-z]+)([A-Z][a-z]?)$", token)
    if not m:
        return None, None
    first, last_bit = m.group(1), m.group(2)
    last_initial = last_bit[0].upper()
    matches: list[tuple[int, str]] = []
    for pid, name in players.by_id.items():
        parts = name.split()
        if len(parts) < 2:
            continue
        if parts[0].casefold() != first.casefold():
            continue
        if parts[-1][0].upper() == last_initial:
            matches.append((pid, name))
    if len(matches) == 1:
        return matches[0][1], matches[0][0]
    return token, None


def _match_full(name: str, players: PlayerIndex) -> tuple[str, int | None]:
    name = normalize_display_name(name)
    key = identity_key(name)
    ids = players.by_name_key.get(key, [])
    if len(ids) == 1:
        return players.by_id[ids[0]], ids[0]
    parts = split_full_name(name)
    if parts:
        first, surname = parts
        cands: list[tuple[int, str]] = []
        for pid, pname in players.by_id.items():
            pp = pname.split()
            if len(pp) < 2:
                continue
            if pp[0].casefold() == first.casefold() and pp[-1].casefold().startswith(surname.casefold()[:1]):
                cands.append((pid, pname))
        if len(cands) == 1:
            return cands[0][1], cands[0][0]
    return name, None


def _parse_players(title: str, forum: ForumBullet | None, players: PlayerIndex) -> tuple[str, int | None, str, int | None]:
    if forum and forum.player_a and forum.player_b:
        pa_name, pa_id = _match_full(forum.player_a, players)
        pb_name, pb_id = _match_full(forum.player_b, players)
        return pa_name, pa_id, pb_name, pb_id

    m = COMPACT_VS_RE.search(title)
    if m:
        pa, pa_id = _match_compact(m.group(1), players)
        pb, pb_id = _match_compact(m.group(2), players)
        return pa or m.group(1), pa_id, pb or m.group(2), pb_id

    m = FULL_VS_RE.search(title)
    if m:
        pa_name, pa_id = _match_full(m.group(1).strip(), players)
        pb_name, pb_id = _match_full(m.group(2).strip(), players)
        return pa_name, pa_id, pb_name, pb_id
    return "", None, "", None


def _tournament_guess(
    year: int | None,
    event_label: str,
    title: str,
    wc_by_year: dict[int, WcTournament],
) -> tuple[int | None, str]:
    label = event_label or title
    ll = label.lower()
    if "greek championship" in ll:
        return None, "3rd Greek Championships 2011"
    if "milan i" in ll or "milan 1" in ll:
        return None, "Milan I 2003"
    if "uk championship" in ll:
        return None, f"UK Championships {year or ''}".strip()
    if year and year in wc_by_year:
        wc = wc_by_year[year]
        return wc.tournament_id, wc.name
    if year:
        return None, f"World Cup {year}?"
    return None, event_label or ""


def _confidence(
    *,
    tournament_id: int | None,
    kind: str,
    pa_id: int | None,
    pb_id: int | None,
    forum: ForumBullet | None,
    source: str,
) -> str:
    if kind == "excluded":
        return "high"
    score = 0
    if tournament_id:
        score += 2
    if forum:
        score += 2
    if kind == "match" and pa_id and pb_id:
        score += 2
    elif kind in ("stream", "ceremony", "coverage", "compilation"):
        score += 1
    if source == "wc_finals_playlist":
        score += 1
    if score >= 5:
        return "high"
    if score >= 3:
        return "medium"
    return "low"


def merge_youtube_rows(rows: list[YtVideo]) -> dict[str, list[YtVideo]]:
    grouped: dict[str, list[YtVideo]] = {}
    for row in rows:
        grouped.setdefault(row.youtube_id, []).append(row)
    return grouped


def pick_primary_source(sources: list[str]) -> str:
    return min(sources, key=lambda s: SOURCE_PRIORITY.get(s, 99))


def build_review_row(
    youtube_id: str,
    variants: list[YtVideo],
    *,
    forum: ForumBullet | None,
    wc_by_year: dict[int, WcTournament],
    players: PlayerIndex,
    playlist_ids: set[str],
) -> dict[str, object]:
    variants_sorted = sorted(
        variants,
        key=lambda v: (-len(v.title), SOURCE_PRIORITY.get(v.source, 99)),
    )
    primary = min(variants, key=lambda v: SOURCE_PRIORITY.get(v.source, 99))
    title = variants_sorted[0].title
    duration = next((v.duration_sec for v in variants if v.duration_sec), None)
    sources = [v.source for v in variants]
    if forum:
        sources.append("forum_index")
    source = pick_primary_source(sources)
    source_channel = primary.source_channel
    source_playlist = primary.source_playlist
    extra_sources = sorted(set(sources) - {source})
    notes = f"Also in: {', '.join(extra_sources)}" if extra_sources else ""

    event_label = forum.event_label if forum else ""
    stage = _normalize_stage(forum.stage if forum else "")
    if not stage:
        tl = title.lower()
        if "semi final" in tl or "semi-final" in tl:
            stage = "semi"
        elif "quarter" in tl or "qf" in tl:
            stage = "quarter"
        elif "final" in tl:
            stage = "final"
        elif "shame" in tl:
            stage = "shame"

    leg_m = LEG_RE.search(title)
    leg = int(leg_m.group(1)) if leg_m else ""

    score = forum.score if forum else ""
    if not score:
        sm = SCORE_PAREN_RE.search(title)
        if sm:
            score = re.sub(r"\s+", "", sm.group(1))

    year = _parse_year(title, event_label)
    kind = _guess_kind(title, duration, stage, event_label)
    pa_guess, pa_id, pb_guess, pb_id = _parse_players(title, forum, players)
    if kind in ("stream", "ceremony", "coverage", "compilation", "excluded"):
        pa_guess, pa_id, pb_guess, pb_id = "", None, "", None

    tid, tlabel = _tournament_guess(year, event_label, title, wc_by_year)
    featured = (
        youtube_id in playlist_ids
        and year is not None
        and year <= PLAYLIST_OFFLINE_WC_MAX_YEAR
        and kind == "match"
        and stage == "final"
    )

    relation_group = forum.relation_group if forum else ""
    relation = "uncertain" if relation_group else ""

    external_url = forum.external_urls[0] if forum and forum.external_urls else ""

    conf = _confidence(
        tournament_id=tid,
        kind=kind,
        pa_id=pa_id,
        pb_id=pb_id,
        forum=forum,
        source=source,
    )

    return {
        "youtube_id": youtube_id,
        "title": title,
        "duration_sec": duration if duration is not None else "",
        "guessed_tournament_id": tid if tid is not None else "",
        "tournament_guess_label": tlabel,
        "year": year if year is not None else "",
        "kind": kind,
        "stage": stage,
        "leg": leg,
        "score": score,
        "player_a_guess": pa_guess,
        "player_a_id_guess": pa_id if pa_id is not None else "",
        "player_b_guess": pb_guess,
        "player_b_id_guess": pb_id if pb_id is not None else "",
        "source": source,
        "source_channel": source_channel or "",
        "source_playlist": source_playlist or "",
        "relation_group": relation_group,
        "relation": relation,
        "featured_final": "true" if featured else "false",
        "confidence": conf,
        "verified": "",
        "notes": notes,
        "external_url": external_url,
    }