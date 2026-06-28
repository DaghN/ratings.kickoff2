"""Apply chat review decisions and bulk game-id matching to review.csv."""

from __future__ import annotations

import csv
from collections import defaultdict

from scripts.amiga.tournament_videos.constants import CSV_COLUMNS, REVIEW_CSV
from scripts.amiga.tournament_videos.game_match import load_tournament_games, match_game
from scripts.amiga.tournament_videos.dropped import load_dropped_ids
from scripts.amiga.tournament_videos.manual_rows import merge_manual_rows

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
BOURNEMOUTH_III = 545
BOURNEMOUTH_III_LABEL = "Bournemouth III"
BOURNEMOUTH_IV = 546
BOURNEMOUTH_IV_LABEL = "Bournemouth IV"
WC_2011 = 526
WC_2011_LABEL = "World Cup XI (Birmingham)"
GLOUCESTER_CUP_2002 = 75
GLOUCESTER_CUP_2002_LABEL = "Gloucester I Cup"
GLOUCESTER_I = 32
GLOUCESTER_I_LABEL = "Gloucester I"
SHEFFIELD_2003 = 104
SHEFFIELD_2003_LABEL = "Sheffield"

# WC Games tab — one 3rd-place decider per World Cup (manual catalog, Jun 2026).
WC_THIRD_PLACE_YOUTUBE: frozenset[str] = frozenset(
    {
        "QXZG9t_THv4",  # 2019 Bremen
        "OUK4KIBe5aQ",  # 2023 Torremolinos
        "lV0Yk5k6njM",  # 2024 Nottingham
        "o-mKEhGVUjc",  # 2022 Athens
        "ZbSFc1HYg1s",  # 2025 Milan (not bronze-cup final QAvqfuA_HqA)
        "Tyl2qK0xVg0",
        "9L7dOlHQ3MU",  # 2003 Groningen legs
        "vs1-bHEHIdI",
        "xJ0j4B6_REA",  # 2004 Milano legs
        "rlUSam_X2BI",
        "_lhsp2abyu4",  # 2005 Cologne legs
        "CKSWCu7uzSU",  # 2006 Rickmansworth 3rd-place leg 2 (ko2cv)
        "KAATGhA6djQ",  # 2008 Athens
        "YQ1mKq7W6w0",  # 2007 Rome
        "7mlpKVT6xHw",  # 2009 Voitsberg
        "8zFuKgbZkwo",  # 2011 Birmingham
        "9knHBfb6ZaA",  # 2012 Voitsberg (not 18th-place IjnX42yQcRY)
        "6CvcdTaNB7Y",  # 2014 Copenhagen
        "DqdBTfC0EOw",  # 2013 Voitsberg 3rd place
        "tOCX7XY0Lgg",  # 2015 Düsseldorf
        "Jrs5BU2gZHI",  # 2016 Milan
    }
)

EXCLUDE_NON_MATCH = frozenset(
    {
        "JRW0kPHTP0Q",
    }
)

DUPLICATE_EXCLUDE: dict[str, tuple[str | None, str]] = {
    "947VFBRpXlk": ("ckj8ZR43Y9k", "ko2cv duplicate; canonical=alkelele silver final"),
    "eO0cByqpD1o": ("wTqyB6iHKjU", "ko2cv duplicate; canonical=alkelele final leg 1"),
    "Qz8CUZ1evzY": ("mZe4uA7CG00", "ko2cv duplicate; canonical=alkelele final leg 2"),
    "I74mFcUp2wc": ("vbivDdeLYzQ", "ko2cv duplicate; canonical=alkelele Game of Shame"),
    "YbBokouIaCc": ("wyfn0CGhpIA", "duplicate of forum/alkelele WC 2009 shame upload"),
    "a-OmwoP1OjM": ("P8C9aNmr9UE", "ko2cv duplicate; canonical=alkelele final leg 1 (was wrongly 16402)"),
    "ws1Z7pYGvmA": ("SzWNnJPtOcg", "ko2cv duplicate; canonical=alkelele final leg 2"),
    "xuCyTNYCli0": ("l9TEWoZoZnI", "ko2cv duplicate; was silver group 16294; canonical=alkelele silver final"),
    "RZSWrrP8ufg": (None, "WC 2005 Game of Shame — no game in ko2amiga_db"),
    "FpovMIdHdKs": (None, "dropped — friendly clip, no tournament context"),
    "gmCjZSeyLqE": ("Mtb4qPBQg6o", "ko2cv duplicate; canonical=KOA WC 2007 higher-quality Semi1 leg 1"),
    "BN-dj4sl0TU": ("bvdkP6rHmMo", "ko2cv duplicate; canonical=alkelele Game of Shame"),
    "hruT4dpRISM": ("vxoUhV8syYU", "ko2cv duplicate; canonical=KO2CV higher-quality Semi1 leg 1"),
    "zhU6-Dzfjrg": ("P_j0YFs76OM", "ko2cv duplicate; canonical=KO2CV higher-quality Semi1 leg 2"),
    "dImjRtXqiDQ": ("NmI2Dr8fcxc", "ko2cv duplicate; canonical=KO2CV higher-quality Semi2 leg 1"),
    "_zF-BvJdcrs": ("LJRUWpE77hM", "ko2cv duplicate; canonical=KO2CV higher-quality Semi2 leg 2"),
    "e1zwTiFc19Q": ("dBaJ49KO0nE", "ko2cv duplicate; canonical=KO2CV higher-quality 3rd-place leg 1"),
    "WqDriFRJyUc": ("CKSWCu7uzSU", "ko2cv duplicate; canonical=KO2CV higher-quality 3rd-place leg 2"),
    "WVbWU3N_67o": ("Gt7Xk1yLacA", "ko2cv duplicate; canonical=KO2CV higher-quality Final leg 1"),
    "IQrS6QX0kFU": ("h5oChWYHl3c", "ko2cv duplicate; canonical=KO2CV higher-quality Final leg 2"),
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
        "player_b_guess": "Astrid L",
        "player_b_id_guess": "41",
        "stage": "shame",
        "score": "0-1",
        "game_id_guess": "16355",
        "relation_group": "dup-wyfn0CGhpIA",
        "relation": "canonical",
        "verified": "Y",
        "notes": "DB phase=33rd Place Final; video title Game Of Shame",
    },
    "c7B7vNWDSG4": {
        "guessed_tournament_id": str(WIESBADEN_TID),
        "tournament_guess_label": WIESBADEN_LABEL,
        "year": "2008",
    },
    "aOTQ7MbdVCU": {"leg": "1"},
    "6him2UvmgV4": {"leg": "2"},
    "BN-dj4sl0TU": {
        "kind": "excluded",
        "notes": "ko2cv duplicate; use alkelele bvdkP6rHmMo",
    },
    "n0bjJU_-Pho": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "stage": "shame",
        "player_a_guess": "Astrid L",
        "player_a_id_guess": "41",
        "player_b_guess": "Andreas Kl",
        "player_b_id_guess": "21",
        "score": "2-1",
        "game_id_guess": "11447",
        "verified": "Y",
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
    "p5d5s11rHMw": {
        "guessed_tournament_id": str(GLOUCESTER_I),
        "tournament_guess_label": GLOUCESTER_I_LABEL,
        "year": "2002",
        "kind": "coverage",
        "verified": "Y",
        "notes": "UKC 2002 Gloucester coverage; not WC 2002 Athens",
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
    "bHjAB8MU_BI": {
        "guessed_tournament_id": str(UKC08_LEAGUE),
        "tournament_guess_label": UKC08_LEAGUE_LABEL,
        "year": "2008",
        "kind": "ceremony",
        "stage": "ceremony",
        "verified": "Y",
        "notes": "UKC08 awards segment 1; atmosphere for Birmingham VIII",
    },
    "43JlZ2QyPeA": {
        "guessed_tournament_id": str(UKC08_LEAGUE),
        "tournament_guess_label": UKC08_LEAGUE_LABEL,
        "year": "2008",
        "kind": "ceremony",
        "stage": "ceremony",
        "verified": "Y",
        "notes": "UKC08 awards segment 2; atmosphere for Birmingham VIII",
    },
    "G0-EQ16K0Ts": {
        "guessed_tournament_id": str(UKC08_LEAGUE),
        "tournament_guess_label": UKC08_LEAGUE_LABEL,
        "year": "2008",
        "kind": "ceremony",
        "stage": "ceremony",
        "verified": "Y",
        "notes": "UKC08 awards segment 3; atmosphere for Birmingham VIII",
    },
    "947VFBRpXlk": {
        "notes": "ko2cv duplicate; canonical=ckj8ZR43Y9k; 14216 FT 3-3, extra=4-5 e.t.",
    },
    "ckj8ZR43Y9k": {
        "kind": "match",
        "stage": "silver",
        "player_a_guess": "George Ka",
        "player_a_id_guess": "141",
        "player_b_guess": "Rodolfo M",
        "player_b_id_guess": "389",
        "score": "3-3",
        "game_id_guess": "14216",
        "relation_group": "wc2008-george-rodolfo-silver",
        "relation": "canonical",
        "verified": "Y",
        "notes": "14216 FT 3-3; amiga_games.extra=4-5 e.t.",
    },
    "vbivDdeLYzQ": {
        "guessed_tournament_id": str(WC_2008),
        "tournament_guess_label": WC_2008_LABEL,
        "year": "2008",
        "kind": "match",
        "stage": "shame",
        "player_a_guess": "Astrid L",
        "player_a_id_guess": "41",
        "player_b_guess": "Andreas Kl",
        "player_b_id_guess": "21",
        "score": "8-1",
        "game_id_guess": "14201",
        "relation_group": "dup-vbivDdeLYzQ",
        "relation": "canonical",
        "verified": "Y",
        "notes": "DB phase=40th Place Final; video title Game of Shame",
    },
    # WC 2008 Athens — alkelele canonical (ko2cv dupes → DUPLICATE_EXCLUDE).
    "PHUo3q_WS_4": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Dagh N",
        "player_a_id_guess": "73",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "7-5",
        "game_id_guess": "14257",
        "verified": "Y",
    },
    "48fS-wdLKn4": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "1-12",
        "game_id_guess": "14258",
        "verified": "Y",
    },
    "G6iNbK-Tv8Q": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Panayotis P",
        "player_a_id_guess": "354",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "2-7",
        "game_id_guess": "14259",
        "verified": "Y",
    },
    "DqgUrZr4K58": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Panayotis P",
        "player_b_id_guess": "354",
        "score": "8-3",
        "game_id_guess": "14260",
        "verified": "Y",
    },
    "KAATGhA6djQ": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Panayotis P",
        "player_b_id_guess": "354",
        "score": "8-3",
        "game_id_guess": "14261",
        "verified": "Y",
    },
    "wTqyB6iHKjU": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Dagh N",
        "player_a_id_guess": "73",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "6-4",
        "game_id_guess": "14262",
        "relation_group": "wc2008-final-leg1",
        "relation": "canonical",
        "featured_final": "true",
        "verified": "Y",
    },
    "mZe4uA7CG00": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "8-2",
        "game_id_guess": "14263",
        "relation_group": "wc2008-final-leg2",
        "relation": "canonical",
        "verified": "Y",
    },
    "l9TEWoZoZnI": {
        "guessed_tournament_id": str(WC_2009),
        "tournament_guess_label": WC_2009_LABEL,
        "year": "2009",
        "kind": "match",
        "stage": "silver",
        "player_a_guess": "Lorenzo C",
        "player_a_id_guess": "253",
        "player_b_guess": "Rodolfo M",
        "player_b_id_guess": "389",
        "score": "1-4",
        "game_id_guess": "16356",
        "relation_group": "wc2009-silver-final",
        "relation": "canonical",
        "verified": "Y",
        "notes": "Tommaso R = Lorenzo C (17th place silver final)",
    },
    # WC 2009 Voitsberg — alkelele canonical match rows (ko2cv dupes → DUPLICATE_EXCLUDE).
    "7mlpKVT6xHw": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Fabio F",
        "player_b_id_guess": "109",
        "score": "3-6",
        "game_id_guess": "16401",
        "verified": "Y",
    },
    "Aw44Zcu0nSM": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "3-8",
        "game_id_guess": "16399",
        "verified": "Y",
    },
    "WWCQVtY1YEo": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Fabio F",
        "player_b_id_guess": "109",
        "score": "2-2",
        "game_id_guess": "16398",
        "verified": "Y",
    },
    "bYVUlrMQ2yw": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Fabio F",
        "player_a_id_guess": "109",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "1-5",
        "game_id_guess": "16397",
        "verified": "Y",
    },
    "hI2M85q0R6E": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "7-4",
        "game_id_guess": "16400",
        "verified": "Y",
    },
    "P8C9aNmr9UE": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "8-7",
        "game_id_guess": "16403",
        "relation_group": "wc2009-final-leg1",
        "relation": "canonical",
        "featured_final": "true",
        "verified": "Y",
    },
    "SzWNnJPtOcg": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "6-7",
        "game_id_guess": "16402",
        "relation_group": "wc2009-final-leg2",
        "relation": "canonical",
        "verified": "Y",
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
    "Mtb4qPBQg6o": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Gianluca T",
        "player_b_id_guess": "148",
        "score": "4-3",
        "game_id_guess": "11345",
        "verified": "Y",
        "notes": "KOA WC 2007 upload; higher quality Semi1 leg 1 (replaces gmCjZSeyLqE)",
    },
    # WC 2006 Rickmansworth — KO2CV Part clips; higher YouTube quality vs alkelele/forum.
    "vxoUhV8syYU": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "7-5",
        "game_id_guess": "8437",
        "verified": "Y",
    },
    "P_j0YFs76OM": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "6-4",
        "game_id_guess": "8438",
        "verified": "Y",
        "notes": "Video title notes e.t. 2-1",
    },
    "NmI2Dr8fcxc": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Mario F",
        "player_a_id_guess": "276",
        "player_b_guess": "Marco D",
        "player_b_id_guess": "273",
        "score": "6-5",
        "game_id_guess": "8439",
        "verified": "Y",
    },
    "LJRUWpE77hM": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Marco D",
        "player_a_id_guess": "273",
        "player_b_guess": "Mario F",
        "player_b_id_guess": "276",
        "score": "3-3",
        "game_id_guess": "8440",
        "verified": "Y",
    },
    "dBaJ49KO0nE": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "bronze",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Marco D",
        "player_b_id_guess": "273",
        "score": "3-6",
        "game_id_guess": "8441",
        "verified": "Y",
        "notes": "Video title Playoff = 3rd Place Final",
    },
    "CKSWCu7uzSU": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "bronze",
        "leg": "2",
        "player_a_guess": "Marco D",
        "player_a_id_guess": "273",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "1-5",
        "game_id_guess": "8442",
        "verified": "Y",
        "notes": "Video title Playoff = 3rd Place Final leg 2",
    },
    "Gt7Xk1yLacA": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Mario F",
        "player_b_id_guess": "276",
        "score": "6-1",
        "game_id_guess": "8443",
        "featured_final": "true",
        "verified": "Y",
    },
    "h5oChWYHl3c": {
        "guessed_tournament_id": str(WC_2006),
        "tournament_guess_label": WC_2006_LABEL,
        "year": "2006",
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Mario F",
        "player_a_id_guess": "276",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "3-6",
        "game_id_guess": "8444",
        "featured_final": "true",
        "verified": "Y",
    },
    # WC 2007 Rome — KO2CV Part clips harvested as stream/coverage; nine match videos total.
    "lMOUiLnRliE": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Gianluca T",
        "player_a_id_guess": "148",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "1-6",
        "game_id_guess": "11346",
        "verified": "Y",
    },
    "2OCyBgWntT0": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Sandro T",
        "player_a_id_guess": "394",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "4-4",
        "game_id_guess": "11347",
        "verified": "Y",
    },
    "8fmLLZNEwLI": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Sandro T",
        "player_b_id_guess": "394",
        "score": "5-3",
        "game_id_guess": "11348",
        "verified": "Y",
    },
    "zKijh7vCBHU": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "silver",
        "player_a_guess": "Rodolfo M",
        "player_a_id_guess": "389",
        "player_b_guess": "Jorg P",
        "player_b_id_guess": "219",
        "score": "6-4",
        "game_id_guess": "11402",
        "verified": "Y",
        "notes": "Video title KOA Cup; DB phase=17th Place Final",
    },
    "YQ1mKq7W6w0": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Sandro T",
        "player_a_id_guess": "394",
        "player_b_guess": "Gianluca T",
        "player_b_id_guess": "148",
        "score": "7-8",
        "game_id_guess": "11349",
        "verified": "Y",
    },
    "Sfgi2K2t36o": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Spyros P",
        "player_b_id_guess": "410",
        "score": "4-4",
        "game_id_guess": "11350",
        "verified": "Y",
    },
    "rnEzge6zaQc": {
        "guessed_tournament_id": str(WC_2007),
        "tournament_guess_label": WC_2007_LABEL,
        "year": "2007",
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Spyros P",
        "player_a_id_guess": "410",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "6-3",
        "game_id_guess": "11351",
        "verified": "Y",
    },
    "C5vvlrDmazU": {
        "guessed_tournament_id": "244",
        "tournament_guess_label": "Reading XIII",
        "year": "2007",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "3-3",
        "game_id_guess": "10065",
        "notes": "UKC07 title; game is Reading XIII 2007 league (not WC VII Rome)",
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
    # Dual-leg WC semis: resolver copied leg-A game_id onto leg-B rows.
    "qYJoeg727ns": {
        "leg": "2",
        "score": "3-12",
        "game_id_guess": "26874",
    },
    "ShvzfJ0oxSE": {
        "leg": "2",
        "score": "0-8",
        "game_id_guess": "21272",
    },
    "hd4GtPhnaqs": {
        "leg": "2",
        "score": "6-4",
        "game_id_guess": "26299",
    },
    # WC 2019 Bremen — dual-leg semis (tid=9); leg-B / swapped IDs from bulk resolver.
    "2I4wFxf_YEY": {
        "leg": "1",
        "score": "7-5",
        "game_id_guess": "25453",
    },
    "h37gtaFJ0g0": {
        "leg": "2",
        "score": "8-5",
        "game_id_guess": "25454",
    },
    "I8YSVVTWK44": {
        "leg": "1",
        "score": "4-6",
        "game_id_guess": "25456",
    },
    "4YchXF8E5VU": {
        "leg": "2",
        "score": "7-7",
        "game_id_guess": "25455",
    },
    # WC 2014 Copenhagen — separate per-leg KO2CV uploads; 2nd leg must not dual-link when leg-1 video exists.
    "2_AnWxbb6ho": {
        "leg": "1",
        "score": "7-7",
        "game_id_guess": "22463",
    },
    "TIixxHSjASc": {
        "leg": "2",
        "score": "9-9",
        "game_id_guess": "22464",
        "notes": "2nd leg only (2_AnWxbb6ho is leg 1); not dual-leg despite duration",
    },
    "z3PkxdG-hx8": {
        "leg": "1",
        "score": "8-1",
        "game_id_guess": "22461",
    },
    "Bv82HaMVQYc": {
        "leg": "2",
        "score": "1-5",
        "game_id_guess": "22462",
    },
    # WC 2022 Athens — KO2CV Part clips were harvested as stream; each verified vs ko2amiga_db tid=14.
    "kmMOfnSFj_A": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Fabio F",
        "player_a_id_guess": "109",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "3-4",
        "game_id_guess": "25985",
    },
    "yk9t62tJ0yM": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Fabio F",
        "player_b_id_guess": "109",
        "score": "7-2",
        "game_id_guess": "25986",
    },
    "ep8C_PjV-Ns": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Christopher D",
        "player_a_id_guess": "66",
        "player_b_guess": "Lorenzo L",
        "player_b_id_guess": "254",
        "score": "1-2",
        "game_id_guess": "25987",
    },
    "MzpUQkr5Nec": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Lorenzo L",
        "player_a_id_guess": "254",
        "player_b_guess": "Christopher D",
        "player_b_id_guess": "66",
        "score": "4-4",
        "game_id_guess": "25988",
    },
    "o-mKEhGVUjc": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Fabio F",
        "player_a_id_guess": "109",
        "player_b_guess": "Christopher D",
        "player_b_id_guess": "66",
        "score": "5-4",
        "game_id_guess": "25989",
    },
    "C9HC2w8HjY0": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Lorenzo L",
        "player_b_id_guess": "254",
        "score": "7-0",
        "game_id_guess": "25990",
    },
    "OgCg0JtmA_w": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Lorenzo L",
        "player_a_id_guess": "254",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "4-9",
        "game_id_guess": "25991",
    },
    "-KXN4GB62i8": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Kostas Ka",
        "player_a_id_guess": "242",
        "player_b_guess": "Jaume P",
        "player_b_id_guess": "193",
        "score": "3-1",
        "game_id_guess": "25949",
        "notes": "Video title Bronze Cup; DB phase=29th Place Final",
    },
    "BnBOehszKcw": {
        "kind": "match",
        "stage": "silver",
        "player_a_guess": "Rodolfo M",
        "player_a_id_guess": "389",
        "player_b_guess": "Gabriele G",
        "player_b_id_guess": "132",
        "score": "1-4",
        "game_id_guess": "25960",
        "notes": "Video title Silver Cup final; DB phase=17th Place Final",
    },
    # WC 2013 Voitsberg — KO2CV Part 04–12 are individual rated games (Parts 01–03 ceremony/shame stay Atmosphere).
    "id4h0U5UvQA": {
        "kind": "match",
        "player_a_guess": "Christopher D",
        "player_a_id_guess": "66",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "5-1",
        "game_id_guess": "21833",
        "notes": "Round 2 Group E",
    },
    "1IJlv36qlAg": {
        "kind": "match",
        "player_a_guess": "Klaus Le",
        "player_a_id_guess": "236",
        "player_b_guess": "Oliver St",
        "player_b_id_guess": "345",
        "score": "3-7",
        "game_id_guess": "21855",
        "notes": "9th Place Final",
    },
    "cW-fs3RkkCc": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Christopher D",
        "player_a_id_guess": "66",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "5-6",
        "game_id_guess": "21872",
    },
    "I1ZVzBQJH0w": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Steve C",
        "player_b_id_guess": "421",
        "score": "10-4",
        "game_id_guess": "21874",
    },
    "t4el3un-DyU": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Christopher D",
        "player_b_id_guess": "66",
        "score": "8-2",
        "game_id_guess": "21873",
    },
    "HC0unXAHeJE": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Steve C",
        "player_a_id_guess": "421",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "6-7",
        "game_id_guess": "21875",
    },
    "DqdBTfC0EOw": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Christopher D",
        "player_a_id_guess": "66",
        "player_b_guess": "Steve C",
        "player_b_id_guess": "421",
        "score": "5-7",
        "game_id_guess": "21876",
        "notes": "3rd Place Final; video marked incomplete",
    },
    "jEIh5fOToEM": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "3-5",
        "game_id_guess": "21877",
    },
    "G05SgzVS0o0": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "3-4",
        "game_id_guess": "21878",
    },
    "mDxXCqbxMR8": {
        "notes": "2v2 Battle of Shame side event; no rated game in ko2amiga_db",
    },
    "sb4jYnARFHk": {
        "player_a_guess": "Peter S",
        "player_a_id_guess": "368",
        "player_b_guess": "Dino D",
        "player_b_id_guess": "98",
        "stage": "shame",
        "notes": "Penalty shootout of shame side event; not Round 1 game 21749 (Dino–Peter 0-4)",
    },
    # UKC 2012 Bournemouth (Jun 9–10) — KO2CV clips were wrongly on WC XII Milan (554).
    "DtFWbubVr2U": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Mark W",
        "player_a_id_guess": "286",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "1-8",
        "game_id_guess": "20415",
        "notes": "UKC 2012 Bournemouth III; filename Durban = Mark W (1–8 vs Dagh)",
    },
    "IY1TgkzEzEQ": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Simon B",
        "player_a_id_guess": "402",
        "player_b_guess": "Simon K",
        "player_b_id_guess": "405",
        "score": "2-7",
        "game_id_guess": "20414",
        "notes": "UKC 2012 Bournemouth III",
    },
    "Oo0DeaZojnw": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Garry C",
        "player_a_id_guess": "134",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "4-7",
        "game_id_guess": "20434",
        "notes": "UKC 2012 Bournemouth III",
    },
    "Qc_IgYQqR3Q": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Steve C",
        "player_a_id_guess": "421",
        "player_b_guess": "Simon K",
        "player_b_id_guess": "405",
        "score": "4-4",
        "game_id_guess": "20435",
        "notes": "UKC 2012 Bournemouth III",
    },
    "XqKIB0Yabno": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Robert S",
        "player_a_id_guess": "386",
        "player_b_guess": "Simon B",
        "player_b_id_guess": "402",
        "score": "1-3",
        "game_id_guess": "20410",
        "notes": "UKC 2012 Bournemouth III",
    },
    "dpA0oqrKhcI": {
        "guessed_tournament_id": str(BOURNEMOUTH_IV),
        "tournament_guess_label": BOURNEMOUTH_IV_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Dagh N",
        "player_a_id_guess": "73",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "9-7",
        "game_id_guess": "20463",
        "notes": "UKC 2012 Bournemouth IV",
    },
    "vIHepaCINjM": {
        "guessed_tournament_id": str(BOURNEMOUTH_IV),
        "tournament_guess_label": BOURNEMOUTH_IV_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "4-7",
        "game_id_guess": "20499",
        "notes": "UKC 2012 Bournemouth IV",
    },
    "yDTtHQ-fcyc": {
        "guessed_tournament_id": str(BOURNEMOUTH_III),
        "tournament_guess_label": BOURNEMOUTH_III_LABEL,
        "year": "2012",
        "kind": "match",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "5-5",
        "game_id_guess": "20430",
        "notes": "UKC 2012 Bournemouth III",
    },
    # WC 2011 Birmingham — semi/final legs were duplicated (1a/2a shared game_id with 1b/2b).
    "nWgq4EJIOJk": {
        "guessed_tournament_id": str(WC_2011),
        "tournament_guess_label": WC_2011_LABEL,
        "year": "2011",
        "kind": "match",
        "stage": "silver",
        "player_a_guess": "Jorn F",
        "player_a_id_guess": "221",
        "player_b_guess": "Jorg P",
        "player_b_id_guess": "219",
        "score": "2-3",
        "game_id_guess": "20009",
        "notes": "Video title Silver Cup Final; DB phase=17th Place Final",
    },
    "4xXJcQl7OEg": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Lorenzo C",
        "player_b_id_guess": "253",
        "score": "5-5",
        "game_id_guess": "20106",
    },
    "oMT6gCY322c": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Lorenzo C",
        "player_a_id_guess": "253",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "1-5",
        "game_id_guess": "20108",
    },
    "zxD-ZvI0kgg": {
        "kind": "match",
        "stage": "semi",
        "leg": "1",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "8-5",
        "game_id_guess": "20107",
    },
    "Wf9EoF-TAEQ": {
        "kind": "match",
        "stage": "semi",
        "leg": "2",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "8-4",
        "game_id_guess": "20109",
    },
    "8zFuKgbZkwo": {
        "kind": "match",
        "stage": "bronze",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Lorenzo C",
        "player_b_id_guess": "253",
        "score": "4-3",
        "game_id_guess": "20110",
    },
    "XcFclfKPO_I": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Alkis P",
        "player_b_id_guess": "14",
        "score": "9-1",
        "game_id_guess": "20111",
    },
    "Qj3sd9e0aNw": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Alkis P",
        "player_a_id_guess": "14",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "4-8",
        "game_id_guess": "20112",
    },
    # WC 2015 Dublin — final legs by title home-away (koatd id order ≠ leg order).
    "pZC2wayc8Hk": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Andy G",
        "player_a_id_guess": "30",
        "player_b_guess": "Gianni T",
        "player_b_id_guess": "149",
        "score": "10-6",
        "game_id_guess": "23054",
        "verified": "Y",
        "notes": "1st leg Andy vs Gianni → Andy player_a (23054)",
    },
    "JnBrb7dgJvU": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Gianni T",
        "player_a_id_guess": "149",
        "player_b_guess": "Andy G",
        "player_b_id_guess": "30",
        "score": "5-7",
        "game_id_guess": "23050",
        "verified": "Y",
        "notes": "2nd leg Gianni vs Andy → Gianni player_a (23050)",
    },
    # WC 2012 Milan — final legs by title home-away (resolver copied leg-1 game_id onto leg-2).
    "d4f5GRbEKsU": {
        "kind": "match",
        "stage": "final",
        "leg": "1",
        "player_a_guess": "Gianluca T",
        "player_a_id_guess": "148",
        "player_b_guess": "Dagh N",
        "player_b_id_guess": "73",
        "score": "3-8",
        "game_id_guess": "21275",
        "verified": "Y",
        "notes": "1st leg Gianluca vs Dagh (3-8)",
    },
    "eaU-lZWTVpk": {
        "kind": "match",
        "stage": "final",
        "leg": "2",
        "player_a_guess": "Dagh N",
        "player_a_id_guess": "73",
        "player_b_guess": "Gianluca T",
        "player_b_id_guess": "148",
        "score": "6-5",
        "game_id_guess": "21276",
        "verified": "Y",
        "notes": "2nd leg Dagh vs Gianluca (6-5)",
    },
}

RELATION_CANONICAL = {
    "Q73jrEIrBWQ",
    "fmgSSgTmEXE",
    "cx68A7ElEE4",
    "NefQKdI85Ls",
    "vLaFZAHJXx8",
    "P8C9aNmr9UE",  # WC 2009 final leg 1 (alkelele)
    "SzWNnJPtOcg",  # WC 2009 final leg 2 (alkelele)
    "l9TEWoZoZnI",  # WC 2009 silver final (alkelele)
    "wyfn0CGhpIA",  # WC 2009 game of shame (alkelele/forum)
    "ckj8ZR43Y9k",  # WC 2008 silver final (alkelele)
    "vbivDdeLYzQ",  # WC 2008 game of shame (alkelele)
    "wTqyB6iHKjU",  # WC 2008 final leg 1 (alkelele)
    "mZe4uA7CG00",  # WC 2008 final leg 2 (alkelele)
    "Mtb4qPBQg6o",  # WC 2007 semi leg 1 (higher quality vs gmCjZSeyLqE)
    "bvdkP6rHmMo",  # WC 2006 game of shame (alkelele)
    "vxoUhV8syYU",
    "P_j0YFs76OM",
    "NmI2Dr8fcxc",
    "LJRUWpE77hM",
    "dBaJ49KO0nE",
    "CKSWCu7uzSU",
    "Gt7Xk1yLacA",
    "h5oChWYHl3c",
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


def apply_row_game_id_locks(rows: list[dict[str, str]]) -> None:
    """Re-apply explicit game_id_guess from ROW_PATCHES after bulk_game_match."""
    for row in rows:
        yt = row.get("youtube_id", "")
        patch = ROW_PATCHES.get(yt)
        if patch and (patch.get("game_id_guess") or "").strip():
            row["game_id_guess"] = patch["game_id_guess"]


def apply_uk_championships(rows: list[dict[str, str]]) -> None:
    for row in rows:
        yt = row.get("youtube_id", "")
        if yt not in UK_BY_YOUTUBE:
            continue
        tid, label = UK_BY_YOUTUBE[yt]
        row["guessed_tournament_id"] = str(tid)
        row["tournament_guess_label"] = label
        row["verified"] = "Y"


def apply_wc_third_place_slots(rows: list[dict[str, str]]) -> None:
    """Tag the one WC 3rd-place decider per event (Games tab ordering)."""
    for row in rows:
        yt = row.get("youtube_id", "")
        if yt in WC_THIRD_PLACE_YOUTUBE:
            row["wc_video_slot"] = "third_place"


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
    dropped_ids = load_dropped_ids()
    if dropped_ids:
        before = len(rows)
        rows = [r for r in rows if (r.get("youtube_id") or "").strip() not in dropped_ids]
        purged = before - len(rows)
        if purged:
            print(f"  purged {purged} row(s) present in dropped.csv")
    added = merge_manual_rows(rows)
    apply_excludes(rows)
    apply_row_corrections(rows)
    apply_core_verified(rows)
    apply_uk_championships(rows)
    apply_wc_third_place_slots(rows)
    apply_2010_relations(rows)
    matched, failures = bulk_game_match(rows)
    apply_row_game_id_locks(rows)
    _write_rows(rows)

    verified = sum(1 for r in rows if r.get("verified") == "Y")
    game_ids = sum(1 for r in rows if r.get("game_id_guess", "").strip())
    match_rows = sum(1 for r in rows if r.get("kind") == "match")
    match_with_gid = sum(
        1 for r in rows if r.get("kind") == "match" and r.get("game_id_guess", "").strip()
    )

    print(f"Wrote {REVIEW_CSV}")
    if added:
        print(f"  manual rows added: {added}")
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