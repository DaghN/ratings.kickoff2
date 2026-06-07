"""Canonical player names at import — collapse spacing / case duplicates."""

from __future__ import annotations

import re
from collections import Counter, defaultdict
from dataclasses import dataclass

_WS = re.compile(r"\s+")


def normalize_display_name(raw: str) -> str:
    """Trim, collapse whitespace, drop a trailing period (Access abbreviation artefact)."""
    return _WS.sub(" ", raw.strip()).rstrip(".")


def identity_key(raw: str) -> str:
    return normalize_display_name(raw).casefold()


def split_full_name(raw: str) -> tuple[str, str] | None:
    """Return (first_name, surname) when input has at least two tokens; else None."""
    normalized = normalize_display_name(raw)
    if not normalized:
        return None
    tokens = normalized.split(" ")
    if len(tokens) < 2:
        return None
    return tokens[0], tokens[-1]


def is_canonical_style_name(raw: str) -> bool:
    """True when input already looks like a short KOA display name (e.g. Mark B)."""
    normalized = normalize_display_name(raw)
    tokens = normalized.split(" ")
    if len(tokens) != 2:
        return False
    return len(tokens[1]) <= 3


def koa_abbreviation_candidates(first_name: str, surname: str) -> list[str]:
    """First S, First Su, … through the full surname spelling."""
    surname = surname.strip()
    if not first_name.strip() or not surname:
        return []
    candidates: list[str] = []
    for length in range(1, len(surname) + 1):
        candidates.append(f"{first_name} {surname[:length]}")
    return candidates


@dataclass(frozen=True)
class NameSuggestion:
    suggested_name: str | None
    normalized_input: str
    reason: str | None = None


def suggest_koa_display_name(full_name: str, taken_identity_keys: set[str]) -> NameSuggestion:
    """
    Conservative KOA-style suggestion for a newcomer full name.

    Uses identity_key collision checks only; does not merge with existing players.
    """
    normalized = normalize_display_name(full_name)
    if not normalized:
        return NameSuggestion(None, normalized, reason="empty name")

    if is_canonical_style_name(normalized):
        key = identity_key(normalized)
        if key in taken_identity_keys:
            return NameSuggestion(
                None,
                normalized,
                reason=f"canonical-style name already taken: {normalized}",
            )
        return NameSuggestion(normalized, normalized)

    parts = split_full_name(normalized)
    if parts is None:
        return NameSuggestion(
            None,
            normalized,
            reason="need at least first name and surname to suggest a KOA abbreviation",
        )

    first_name, surname = parts
    for candidate in koa_abbreviation_candidates(first_name, surname):
        if identity_key(candidate) not in taken_identity_keys:
            return NameSuggestion(candidate, normalized)

    return NameSuggestion(
        None,
        normalized,
        reason="all KOA abbreviation candidates for this name are already taken",
    )


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
