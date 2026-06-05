"""Canonical player names at import — collapse spacing / case duplicates."""

from __future__ import annotations

import re
from collections import Counter, defaultdict

_WS = re.compile(r"\s+")


def normalize_display_name(raw: str) -> str:
    """Trim, collapse whitespace, drop a trailing period (Access abbreviation artefact)."""
    return _WS.sub(" ", raw.strip()).rstrip(".")


def identity_key(raw: str) -> str:
    return normalize_display_name(raw).casefold()


def build_canonical_name_map(
    scores: list,
    *,
    countries: dict[str, str],
    team_a_attr: str = "team_a",
    team_b_attr: str = "team_b",
) -> tuple[dict[str, str], list[dict[str, object]]]:
    """
    Map every raw Access name string → canonical display name.

    Rules:
    - Same identity_key (trim, collapse spaces, strip trailing `.`, casefold) → one player
    - Canonical spelling = variant with the most game rows; tie → prefer Rankings country
    """
    raw_counts: Counter[str] = Counter()
    for row in scores:
        a = getattr(row, team_a_attr)
        b = getattr(row, team_b_attr)
        raw_counts[a] += 1
        raw_counts[b] += 1

    by_key: dict[str, list[str]] = defaultdict(list)
    for raw in raw_counts:
        by_key[identity_key(raw)].append(raw)

    raw_to_canonical: dict[str, str] = {}
    merge_log: list[dict[str, object]] = []

    for key, variants in sorted(by_key.items()):
        unique_variants = sorted(set(variants))

        def variant_score(v: str) -> tuple[int, int, int]:
            norm = normalize_display_name(v)
            country = countries.get(norm) or countries.get(v.strip()) or ""
            return (raw_counts[v], 1 if country.strip() else 0, -len(norm))

        winner = max(unique_variants, key=variant_score)
        canonical = normalize_display_name(winner)
        for v in unique_variants:
            raw_to_canonical[v] = canonical

        if len(unique_variants) > 1:
            merge_log.append(
                {
                    "canonical": canonical,
                    "variants": [
                        {"raw": v, "games": raw_counts[v]} for v in unique_variants
                    ],
                }
            )

    return raw_to_canonical, merge_log


def canonical_country(canonical: str, variants: list[str], countries: dict[str, str]) -> str:
    for v in variants:
        for key in (normalize_display_name(v), v.strip()):
            c = (countries.get(key) or "").strip()
            if c:
                return c
    return (countries.get(canonical) or "").strip()
