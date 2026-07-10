"""Forum + Access witness context for SC-11 extension review handoffs."""

from __future__ import annotations

import re
from pathlib import Path
from typing import Any
from urllib.parse import urlencode

from scripts.amiga.tournament_structure.disposition_register import DispositionRegister

_REVIEW_QUEUE_PATH = (
    Path(__file__).resolve().parents[2]
    / "docs"
    / "amiga-tournament-structure-review-queue.md"
)
_FORUM_BASE = "https://ko-gathering.com/forum/viewtopic.php"
_FULL_FORUM_URL_RE = re.compile(
    r"https?://ko-gathering\.com/forum/viewtopic\.php[^\s)\]]*",
    re.I,
)
_FORUM_POST_RE = re.compile(r"forum\s+p\s*=\s*(\d+)", re.I)
_FORUM_TOPIC_RE = re.compile(r"forum\s+t\s*=\s*(\d+)", re.I)

# Cup playoffs often share forum thread with parent league event.
_RELATED_TOURNAMENT_FORUM: dict[int, int] = {
    111: 110,
}


def _expand_forum_shorthand(text: str) -> list[str]:
    urls: list[str] = []
    for m in _FORUM_POST_RE.finditer(text):
        pid = m.group(1)
        urls.append(f"{_FORUM_BASE}?p={pid}#p{pid}")
    for m in _FORUM_TOPIC_RE.finditer(text):
        tid = m.group(1)
        urls.append(f"{_FORUM_BASE}?{urlencode({'t': tid})}")
    return urls


def _unique_preserve(items: list[str]) -> list[str]:
    seen: set[str] = set()
    out: list[str] = []
    for item in items:
        if item in seen:
            continue
        seen.add(item)
        out.append(item)
    return out


def _extract_forum_urls_from_text(text: str | None) -> list[str]:
    if not text:
        return []
    urls = _FULL_FORUM_URL_RE.findall(str(text))
    urls.extend(_expand_forum_shorthand(str(text)))
    return _unique_preserve(urls)


def _review_queue_line(tournament_id: int) -> str | None:
    if not _REVIEW_QUEUE_PATH.is_file():
        return None
    tid = int(tournament_id)
    pattern = re.compile(rf"^-\s+\*\*{tid}\*\*")
    for line in _REVIEW_QUEUE_PATH.read_text(encoding="utf-8").splitlines():
        if pattern.match(line):
            return line[2:].strip()
    return None


def _game_snippets_from_text(text: str | None, game_id: int) -> list[str]:
    if not text:
        return []
    gid = f"g{int(game_id)}"
    gid_bare = str(int(game_id))
    snippets: list[str] = []
    for part in re.split(r"[.;]\s+", str(text)):
        if gid in part or re.search(rf"\bg{gid_bare}\b", part) or re.search(
            rf"\b{gid_bare}\b", part
        ):
            snippets.append(part.strip())
    return snippets


def tournament_forum_context(tournament_id: int) -> dict[str, Any]:
    """Disposition notes, review-queue line, and forum URLs for an event."""
    tid = int(tournament_id)
    reg = DispositionRegister.load()
    row = reg.get(tid)
    disposition_notes = (row.notes if row else None) or None
    review_line = _review_queue_line(tid)

    texts = [disposition_notes or "", review_line or ""]
    related_tid = _RELATED_TOURNAMENT_FORUM.get(tid)
    related_notes: str | None = None
    if related_tid is not None:
        related_row = reg.get(related_tid)
        related_notes = (related_row.notes if related_row else None) or None
        texts.append(related_notes or "")
        related_review = _review_queue_line(related_tid)
        if related_review:
            texts.append(related_review)

    forum_urls = _unique_preserve(
        [u for t in texts for u in _extract_forum_urls_from_text(t)]
    )

    out: dict[str, Any] = {
        "forum_urls": forum_urls,
        "disposition_notes": disposition_notes,
        "review_queue_line": review_line,
    }
    if related_tid is not None:
        out["related_tournament_id"] = related_tid
        out["related_disposition_notes"] = related_notes
    return out


def game_forum_context(tournament_id: int, game_id: int) -> dict[str, Any]:
    """Tournament forum context plus game-specific snippets when documented."""
    ctx = tournament_forum_context(tournament_id)
    gid = int(game_id)
    snippets: list[str] = []
    for key in ("disposition_notes", "review_queue_line", "related_disposition_notes"):
        val = ctx.get(key)
        snippets.extend(_game_snippets_from_text(val, gid))
    ctx["game_snippets"] = _unique_preserve(snippets)
    return ctx


def format_forum_context_lines(ctx: dict[str, Any]) -> list[str]:
    lines: list[str] = []
    urls = ctx.get("forum_urls") or []
    if urls:
        lines.append("  forum_urls:")
        for url in urls:
            lines.append(f"    - {url}")
    else:
        lines.append("  forum_urls: (none in disposition / review queue)")

    review_line = ctx.get("review_queue_line")
    if review_line:
        lines.append(f"  review_queue: {review_line}")

    disposition = ctx.get("disposition_notes")
    if disposition:
        lines.append(f"  disposition_notes: {disposition}")

    related_tid = ctx.get("related_tournament_id")
    if related_tid is not None:
        lines.append(
            f"  related_event: tournament {related_tid} (shared forum thread for cup/league split)"
        )

    snippets = ctx.get("game_snippets") or []
    if snippets:
        lines.append("  forum_game_hints:")
        for snip in snippets:
            lines.append(f"    - {snip}")
    elif review_line or disposition:
        lines.append("  forum_game_hints: (no game-specific note in repo docs)")

    return lines