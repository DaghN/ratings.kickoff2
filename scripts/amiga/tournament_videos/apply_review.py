"""Apply chat review decisions and bulk game-id matching to review.csv."""

from __future__ import annotations

import csv
from collections import defaultdict

from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, REVIEW_CSV
from scripts.amiga.tournament_videos.game_match import load_tournament_games, match_game

GREEK_TID = 499
GREEK_LABEL = "Athens LXXVIII"
MILAN_TID = 89
MILAN_LABEL = "Milan"

UK_2009_LEAGUE = 412
UK_2009_GOLD = 413
UK_2009_SILVER = 414
UK_2010_LEAGUE = 451
UK_2010_GOLD = 452
UK_2010_SILVER = 453
UK_2010_BRONZE = 454

UK_BY_YOUTUBE: dict[str, tuple[int, str]] = {
    "8XNXQ_nMPTU": (UK_2009_GOLD, "Birmingham XIV Gold Cup"),
    "7gkUTGvEfK0": (UK_2009_SILVER, "Birmingham XIV Silver Cup"),
    "Naw-nd2TbHs": (UK_2009_LEAGUE, "Birmingham XIV"),
    "GIk2ZJSfRdI": (UK_2010_GOLD, "Birmingham XXI Gold Cup"),
    "-4RE2hs1LDE": (UK_2010_SILVER, "Birmingham XXI Silver Cup"),
    "gVWIaJt3xaI": (UK_2010_BRONZE, "Birmingham XXI Bronze Cup"),
    "zPQeHdmpXQA": (UK_2010_LEAGUE, "Birmingham XXI"),
    "c8MWCUbpIkI": (UK_2010_LEAGUE, "Birmingham XXI"),
    "_41FztyGDFs": (UK_2010_LEAGUE, "Birmingham XXI"),
    "QWWEGczIsXU": (UK_2010_LEAGUE, "Birmingham XXI"),
    "AtdXWKBau6g": (UK_2010_LEAGUE, "Birmingham XXI"),
    "QWW3l6xH7o8": (UK_2010_LEAGUE, "Birmingham XXI"),
    "NR-ZIg1NU_E": (UK_2010_LEAGUE, "Birmingham XXI"),
    "ZAZb7CiQD6Q": (UK_2010_LEAGUE, "Birmingham XXI"),
}

LUND_TID = 411
LUND_LABEL = "Lund II"
LUND_YOUTUBE = frozenset({"44K3dzGJ8FY", "AATTDvx5klA", "J_0D7A3IKyQ", "MaNou8ep0w0", "Y1lWJ3t5n2Y"})

WIESBADEN_TID = 352
WIESBADEN_LABEL = "Wiesbaden V"

WC_2008 = 358
WC_2008_LABEL = "World Cup VIII (Athens)"
WC_2006 = 206
WC_2006_LABEL = "World Cup VI (Rickmansworth)"
WC_2009 = 418
WC_2009_LABEL = "World Cup IX (Voitsberg)"
WC_2007 = 280
WC_2007_LABEL = "World Cup VII (Rome)"
WC_2015 = 585
UKC08_GOLD = 316
UKC08_GOLD_LABEL = "Birmingham VIII Gold Cup"
UKC08_SILVER = 317
UKC08_SILVER_LABEL = "Birmingham VIII Silver Cup"
UKC08_LEAGUE = 315
UKC08_LEAGUE_LABEL = "Birmingham VIII"
GLOUCESTER_CUP_2002 = 75
GLOUCESTER_CUP_2002_LABEL = "Gloucester I Cup"
SHEFFIELD_2003 = 104
SHEFFIELD_2003_LABEL = "Sheffield"

EXCLUDE_NON_MATCH = frozenset(
    {
        "EPB6ZZghpEk",
        "Iq19IVIZ8QY",
        "JRW0kPHTP0Q",
        "JYe18t4jnN0",
    }
)

DUPLICATE_EXCLUDE: dict[str, tuple[str | None, str]] = {
    "vbivDdeLYzQ": ("I74mFcUp2wc", "duplicate of ko2cv Athens08 shame upload"),
    "YbBokouIaCc": ("wyfn0CGhpIA", "duplicate of forum/alkelele WC 2009 shame upload"),
    "RZSWrrP8ufg": (None, "WC 2005 Game of Shame — no game in ko2amiga_db"),
    "FpovMIdHdKs": (None, "dropped — friendly clip, no tournament context"),
}

# Per-row fixes from human review (wrong auto-guess tournament or player id).
ROW_PATCHES: dict[str, dict[str, str]] = {
    "rbAjYQzxx3E": {
        "player_b_guess": "Antonis T",
        "player_b_id_guess": "37",
    },
    "wyfn0CGhpIA": {
        "player_a_guess": "Andreas Kl",
        "player_a_id_guess": "21",
        "stage": "shame",
    },
    "c7B7vNWDSG4": {
        "guessed_tournament_id": str(WIESBADEN_TID),
        "tournament_guess_label": WIESBADEN_LABEL,
        "year": "2008",
    },
    "aOTQ7MbdVCU": {"leg": "1"},
    "6him2UvmgV4": {"leg": "2"},
    "eO0cByqpD1o": {
        "guessed_tournament_id": str(WC_2008),
        "tournament_guess_label": WC_2008_LABEL,
        "year": "2008",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Dagh N",
        "player_a_id_guess": "73",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "6-4",
        "game_id_guess": "14262",
    },
    "Qz8CUZ1evzY": {
        "guessed_tournament_id": str(WC_2008),
        "tournament_guess_label": WC_2008_LABEL,
        "year": "2008",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "8-2",
        "game_id_guess": "14263",
    },
    "I74mFcUp2wc": {
        "guessed_tournament_id": str(WC_2008),
        "tournament_guess_label": WC_2008_LABEL,
        "year": "2008",
        "stage": "shame",
        "player_a_guess": "Astrid L",
        "player_a_id_guess": "41",
        "player_b_guess": "Andreas Kl",
        "player_b_id_guess": "21",
        "score": "8-1",
        "game_id_guess": "14201",
        "notes": "DB phase=40th Place Final; filename says Shame",
    },
    "BN-dj4sl0TU": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "stage": "shame",
        "game_id_guess": "8573",
    },
    "n0bjJU_-Pho": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "stage": "shame",
        "game_id_guess": "11447",
    },
    "a-OmwoP1OjM": {
        "guessed_tournament_id": str(WC_2009),
        "tournament_guess_label": WC_2009_LABEL,
        "year": "2009",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "game_id_guess": "16402",
    },
    "Q_TwhVZdJYg": {
        "guessed_tournament_id": str(UKC08_GOLD),
        "tournament_guess_label": UKC08_GOLD_LABEL,
        "year": "2008",
        "stage": "final",
        "player_a_guess": "Wayne L",
        "player_a_id_guess": "467",
        "player_b_guess": "Jon G",
        "player_b_id_guess": "213",
        "game_id_guess": "12642",
    },
    "IRn7__E2NDY": {
        "guessed_tournament_id": str(WC_2015),
        "tournament_guess_label": "World Cup XV (Düsseldorf)",
        "year": "2015",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Christopher D",
        "player_a_id_guess": "66",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "game_id_guess": "23048",
    },
    "tOCX7XY0Lgg": {
        "guessed_tournament_id": str(WC_2015),
        "tournament_guess_label": "World Cup XV (Düsseldorf)",
        "year": "2015",
        "stage": "bronze",
        "player_a_guess": "Oliver St",
        "player_a_id_guess": "345",
        "player_b_guess": "Christopher D",
        "player_b_id_guess": "66",
        "game_id_guess": "23049",
    },
    "HON9MVA9GNQ": {
        "guessed_tournament_id": str(SHEFFIELD_2003),
        "tournament_guess_label": SHEFFIELD_2003_LABEL,
        "year": "2003",
        "player_a_guess": "Martin J",
        "player_a_id_guess": "295",
        "player_b_guess": "Rikki F",
        "player_b_id_guess": "384",
        "score": "4-3",
        "game_id_guess": "3078",
        "verified": "Y",
        "notes": "Title says UKC 2004; game is Sheffield 2003 league",
    },
    "8aB5RAInyUY": {
        "guessed_tournament_id": str(GLOUCESTER_CUP_2002),
        "tournament_guess_label": GLOUCESTER_CUP_2002_LABEL,
        "year": "2002",
        "stage": "semi",
        "player_a_guess": "Dan S",
        "player_a_id_guess": "78",
        "player_b_guess": "Bill V",
        "player_b_id_guess": "44",
        "score": "2-4",
        "game_id_guess": "604",
        "verified": "Y",
        "notes": "UKC 2002 cup = Gloucester I Cup in DB",
    },
    "ITkMOeRnHg8": {
        "guessed_tournament_id": str(GLOUCESTER_CUP_2002),
        "tournament_guess_label": GLOUCESTER_CUP_2002_LABEL,
        "year": "2002",
        "stage": "semi",
        "player_a_guess": "Nazim C",
        "player_a_id_guess": "331",
        "player_b_guess": "Steve S",
        "player_b_id_guess": "424",
        "score": "2-4",
        "game_id_guess": "605",
        "verified": "Y",
        "notes": "Screech = Steve S; UKC 2002 cup = Gloucester I Cup",
    },
    "r3VCe2ULxtY": {
        "guessed_tournament_id": str(GLOUCESTER_CUP_2002),
        "tournament_guess_label": GLOUCESTER_CUP_2002_LABEL,
        "year": "2002",
        "stage": "final",
        "player_a_guess": "Steve S",
        "player_a_id_guess": "424",
        "player_b_guess": "Bill V",
        "player_b_id_guess": "44",
        "score": "2-7",
        "game_id_guess": "606",
        "verified": "Y",
        "notes": "Screech = Steve S; UKC 2002 cup final",
    },
    "EVRy3x_Rhl0": {
        "guessed_tournament_id": str(UKC08_SILVER),
        "tournament_guess_label": UKC08_SILVER_LABEL,
        "year": "2008",
        "stage": "final",
        "player_a_guess": "Mandhir S",
        "player_a_id_guess": "267",
        "player_b_guess": "Garry C",
        "player_b_id_guess": "134",
        "score": "1-4",
        "game_id_guess": "12648",
        "verified": "Y",
        "notes": "Sid = Mandhir S",
    },
    "sGlHoDTbKCE": {
        "guessed_tournament_id": str(UKC08_LEAGUE),
        "tournament_guess_label": UKC08_LEAGUE_LABEL,
        "year": "2008",
        "player_a_guess": "Steve C",
        "player_a_id_guess": "421",
        "player_b_guess": "Robert S",
        "player_b_id_guess": "386",
        "score": "3-3",
        "game_id_guess": "12608",
        "verified": "Y",
        "notes": "Filename CamberVsSwift is wrong; Steve C vs Robert S",
    },
    "947VFBRpXlk": {
        "score": "3-3",
        "verified": "Y",
        "notes": "14216: goals 3-3 FT; amiga_games.extra=4-5 e.t. (filename 4-5 is AET)",
    },
    "l9TEWoZoZnI": {
        "guessed_tournament_id": str(WC_2009),
        "tournament_guess_label": WC_2009_LABEL,
        "year": "2009",
        "stage": "silver",
        "player_a_guess": "Lorenzo C",
        "player_a_id_guess": "253",
        "player_b_guess": "Rodolfo M",
        "player_b_id_guess": "389",
        "score": "1-4",
        "game_id_guess": "16356",
        "verified": "Y",
        "notes": "Tommaso R = Lorenzo C (17th place silver final)",
    },
    "xuCyTNYCli0": {
        "guessed_tournament_id": str(WC_2009),
        "tournament_guess_label": WC_2009_LABEL,
        "year": "2009",
        "stage": "silver",
        "player_a_guess": "Rodolfo M",
        "player_a_id_guess": "389",
        "player_b_guess": "Lorenzo C",
        "player_b_id_guess": "253",
        "score": "1-2",
        "game_id_guess": "16294",
        "verified": "Y",
        "notes": "Tommaso = Lorenzo C; silver cup group game",
    },
    "PITHWH7eM3Q": {
        "guessed_tournament_id": "554",
        "tournament_guess_label": "World Cup XII (Milan)",
        "year": "2012",
        "stage": "silver",
        "player_a_guess": "Mark W",
        "player_a_id_guess": "286",
        "player_b_guess": "Lorenzo L",
        "player_b_id_guess": "254",
        "score": "3-1",
        "game_id_guess": "21117",
        "verified": "Y",
        "notes": "17th place final (silver bracket; top 16 to gold)",
    },
    "gmCjZSeyLqE": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "stage": "semi",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Gianluca T",
        "player_b_id_guess": "148",
        "score": "4-3",
        "game_id_guess": "11345",
        "verified": "Y",
    },
    "MfAz4uCl090": {
        "kind": "excluded",
        "player_a_guess": "Ektoras K",
        "player_a_id_guess": "100",
        "player_b_guess": "Steve C",
        "player_b_id_guess": "421",
        "verified": "Y",
        "notes": "11s highlight clip (not a full match); players for catalog only",
    },
}

RELATION_CANONICAL = {
    "Q73jrEIrBWQ",
    "fmgSSgTmEXE",
    "cx68A7ElEE4",
    "NefQKdI85Ls",
    "vLaFZAHJXx8",
}

PLAYER_ALIASES: dict[str, int] = {
    "Gianluca": 148,
}


def _int(val: str) -> int | None:
    val = (val or "").strip()
    if not val:
        return None
    return int(val)


def _load_rows() -> list[dict[str, str]]:
    rows: list[dict[str, str]] = []
    with REVIEW_CSV.open(encoding="utf-8", newline="") as fh:
        reader = csv.DictReader(fh)
        for row in reader:
            if "game_id_guess" not in row:
                row["game_id_guess"] = ""
            rows.append(row)
    return rows


def _write_rows(rows: list[dict[str, str]]) -> None:
    with REVIEW_CSV.open("w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def _resolve_player_ids(row: dict[str, str]) -> tuple[int | None, int | None]:
    pa = _int(row.get("player_a_id_guess", ""))
    pb = _int(row.get("player_b_id_guess", ""))
    if not pb and row.get("player_b_guess", "").strip() in PLAYER_ALIASES:
        pb = PLAYER_ALIASES[row["player_b_guess"].strip()]
        row["player_b_id_guess"] = str(pb)
    if not pa and row.get("player_a_guess", "").strip() in PLAYER_ALIASES:
        pa = PLAYER_ALIASES[row["player_a_guess"].strip()]
        row["player_a_id_guess"] = str(pa)
    return pa, pb


def _copy_fields_from_sibling(row: dict[str, str], sibling: dict[str, str], fields: tuple[str, ...]) -> None:
    for field in fields:
        if not (row.get(field) or "").strip() and (sibling.get(field) or "").strip():
            row[field] = sibling[field]


def apply_excludes(rows: list[dict[str, str]]) -> None:
    by_yt = {r.get("youtube_id"): r for r in rows}
    for row in rows:
        yt = row.get("youtube_id", "")
        if yt in EXCLUDE_NON_MATCH:
            row["kind"] = "excluded"
            row["verified"] = "Y"
        if yt in DUPLICATE_EXCLUDE:
            canonical_yt, note = DUPLICATE_EXCLUDE[yt]
            row["kind"] = "excluded"
            row["verified"] = "Y"
            row["game_id_guess"] = ""
            prev = (row.get("notes") or "").strip()
            row["notes"] = "; ".join(x for x in (prev, note) if x)
            if canonical_yt:
                row["relation_group"] = f"dup-{canonical_yt}"
                row["relation"] = "alternate_recording"
                canon = by_yt.get(canonical_yt)
                if canon and not (canon.get("relation_group") or "").strip():
                    canon["relation_group"] = f"dup-{canonical_yt}"
                    canon["relation"] = "canonical"


def apply_row_corrections(rows: list[dict[str, str]]) -> None:
    for row in rows:
        yt = row.get("youtube_id", "")
        if yt in ROW_PATCHES:
            row.update(ROW_PATCHES[yt])
        title = row.get("title", "")
        if yt in LUND_YOUTUBE or "Lund II" in title:
            row["guessed_tournament_id"] = str(LUND_TID)
            row["tournament_guess_label"] = LUND_LABEL
            row["year"] = "2009"
            if row.get("kind") == "match":
                row["verified"] = "Y"
        if yt == "c7B7vNWDSG4" or "Wiesbaden" in title:
            row["guessed_tournament_id"] = str(WIESBADEN_TID)
            row["tournament_guess_label"] = WIESBADEN_LABEL
            row["verified"] = "Y"


def apply_uk_championships(rows: list[dict[str, str]]) -> None:
    for row in rows:
        yt = row.get("youtube_id", "")
        if yt not in UK_BY_YOUTUBE:
            continue
        tid, label = UK_BY_YOUTUBE[yt]
        row["guessed_tournament_id"] = str(tid)
        row["tournament_guess_label"] = label
        row["verified"] = "Y"


def apply_2010_relations(rows: list[dict[str, str]]) -> None:
    by_group: dict[str, list[dict[str, str]]] = defaultdict(list)
    for row in rows:
        rg = (row.get("relation_group") or "").strip()
        if rg:
            by_group[rg].append(row)

    for group_rows in by_group.values():
        forum_rows = [r for r in group_rows if r.get("source") == "forum_index" and r.get("kind") == "match"]
        for row in group_rows:
            if row.get("youtube_id") in RELATION_CANONICAL:
                row["relation"] = "canonical"
            elif row.get("relation_group"):
                row["relation"] = "alternate_recording"
            if row.get("kind") == "stream" and forum_rows:
                _copy_fields_from_sibling(
                    row,
                    forum_rows[0],
                    (
                        "score",
                        "player_a_guess",
                        "player_a_id_guess",
                        "player_b_guess",
                        "player_b_id_guess",
                        "stage",
                        "leg",
                    ),
                )
                if row.get("player_a_id_guess") and row.get("player_b_id_guess") and row.get("score"):
                    row["kind"] = "match"
            row["verified"] = "Y"


def apply_core_verified(rows: list[dict[str, str]]) -> None:
    for row in rows:
        label = row.get("tournament_guess_label", "")
        title = row.get("title", "")
        if "Athens LXXVIII" in label or row.get("guessed_tournament_id") == str(GREEK_TID):
            row["guessed_tournament_id"] = str(GREEK_TID)
            row["tournament_guess_label"] = GREEK_LABEL
            row["verified"] = "Y"
        if MILAN_LABEL == label or row.get("guessed_tournament_id") == str(MILAN_TID) or "Milan I" in title:
            row["guessed_tournament_id"] = str(MILAN_TID)
            row["tournament_guess_label"] = MILAN_LABEL
            row["verified"] = "Y"
        if row.get("kind") == "excluded" and row.get("youtube_id") in (
            "0FRUD98gSx8",
            "hIdXiRATwOs",
            "MfAz4uCl090",
        ):
            row["verified"] = "Y"


def bulk_game_match(rows: list[dict[str, str]]) -> tuple[int, list[str]]:
    cache: dict[int, list] = {}
    matched = 0
    failures: list[str] = []

    for row in rows:
        if row.get("kind") != "match":
            continue
        tid = _int(row.get("guessed_tournament_id", ""))
        if not tid:
            continue
        pa, pb = _resolve_player_ids(row)
        if not pa or not pb or not row.get("score", "").strip():
            continue
        if tid not in cache:
            cache[tid] = load_tournament_games(tid)
        gid, note = match_game(
            cache[tid],
            player_a_id=pa,
            player_b_id=pb,
            score=row.get("score", ""),
            stage=row.get("stage", ""),
            leg=_int(row.get("leg", "")),
        )
        if gid:
            row["game_id_guess"] = str(gid)
            matched += 1
        else:
            row["game_id_guess"] = ""
            if note:
                failures.append(f"{row.get('youtube_id')}: {note} ({row.get('title','')[:50]})")

    return matched, failures


def apply_all() -> int:
    rows = _load_rows()
    apply_excludes(rows)
    apply_row_corrections(rows)
    apply_core_verified(rows)
    apply_uk_championships(rows)
    apply_2010_relations(rows)
    matched, failures = bulk_game_match(rows)
    _write_rows(rows)

    verified = sum(1 for r in rows if r.get("verified") == "Y")
    game_ids = sum(1 for r in rows if r.get("game_id_guess", "").strip())
    match_rows = sum(1 for r in rows if r.get("kind") == "match")
    match_with_gid = sum(
        1 for r in rows if r.get("kind") == "match" and r.get("game_id_guess", "").strip()
    )

    print(f"Wrote {REVIEW_CSV}")
    print(f"  verified={verified}/{len(rows)}")
    print(f"  game_id_guess={game_ids} (match rows linked: {match_with_gid}/{match_rows})")
    if failures:
        print(f"  unmatched match rows ({len(failures)}):")
        for line in failures[:25]:
            print(f"    {line}")
        if len(failures) > 25:
            print(f"    ... and {len(failures) - 25} more")
    return 0


if __name__ == "__main__":
    raise SystemExit(apply_all())