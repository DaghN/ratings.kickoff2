"""Parse ko-gathering forum video index (t=15358)."""

from __future__ import annotations

import logging
import re
import urllib.request
from dataclasses import dataclass, field
from html import unescape

from scripts.amiga.tournament_videos.constants import FORUM_URL, USER_AGENT

log = logging.getLogger(__name__)

YOUTUBE_ID_RE = re.compile(
    r"(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})"
)
WMV_URL_RE = re.compile(
    r'href="(https?://[^"]+\.(?:wmv|WMV|avi|AVI|mpg|MPG)[^"]*)"', re.I
)
STRONG_RE = re.compile(r"<strong[^>]*>(.*?)</strong>", re.I | re.S)
EM_RE = re.compile(r"<em[^>]*>(.*?)</em>", re.I | re.S)
MATCH_RE = re.compile(
    r"^(.+?)\s*-\s*(.+?)\s*\(([^)]+)\)\s*(?:\(([^)]+)\))?\s*$"
)


@dataclass
class ForumBullet:
    event_label: str
    stage: str | None
    player_a: str
    player_b: str
    score: str
    youtube_ids: list[str] = field(default_factory=list)
    external_urls: list[str] = field(default_factory=list)
    relation_group: str = ""


def _strip_tags(html: str) -> str:
    text = re.sub(r"<br\s*/?>", "\n", html, flags=re.I)
    text = re.sub(r"<[^>]+>", "", text)
    return unescape(text)


def _slug(*parts: str) -> str:
    raw = "-".join(p for p in parts if p).lower()
    raw = re.sub(r"[^a-z0-9]+", "-", raw)
    return re.sub(r"-+", "-", raw).strip("-")


def fetch_forum_html(url: str = FORUM_URL) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return resp.read().decode("utf-8", errors="replace")


def _extract_post_body(html: str) -> str:
    m = re.search(r'<div class="content">(.*?)<div id="', html, re.I | re.S)
    if m:
        return m.group(1)
    m = re.search(r'class="postbody"(.*?)<div class="', html, re.I | re.S)
    if m:
        return m.group(1)
    return html


def parse_forum_index(html: str) -> tuple[list[ForumBullet], dict[str, ForumBullet]]:
    body = _extract_post_body(html)
    body = re.sub(r"<br\s*/?>", "\n", body, flags=re.I)

    bullets: list[ForumBullet] = []
    by_yt: dict[str, ForumBullet] = {}

    event_label = ""
    stage: str | None = None
    pending: ForumBullet | None = None

    tokens = re.split(
        r"(<strong[^>]*>.*?</strong>|<em[^>]*>.*?</em>)",
        body,
        flags=re.I | re.S,
    )

    def flush_pending() -> None:
        nonlocal pending
        if pending is None:
            return
        if pending.youtube_ids:
            if len(pending.youtube_ids) > 1 and not pending.relation_group:
                pending.relation_group = _slug(
                    "forum",
                    pending.event_label,
                    pending.stage or "",
                    pending.player_a,
                    pending.player_b,
                    pending.score,
                )
            bullets.append(pending)
            for yt in pending.youtube_ids:
                by_yt[yt] = pending
        pending = None

    for token in tokens:
        token = token.strip()
        if not token:
            continue
        sm = STRONG_RE.fullmatch(token)
        if sm:
            flush_pending()
            event_label = _strip_tags(sm.group(1)).strip()
            if event_label.lower().startswith("update "):
                event_label = ""
            stage = None
            continue
        em = EM_RE.fullmatch(token)
        if em:
            flush_pending()
            stage = _strip_tags(em.group(1)).strip() or None
            continue

        chunk = _strip_tags(token)
        for line in chunk.splitlines():
            line = line.strip()
            if not line or line.startswith("http://") and "youtube" not in line:
                continue

            mm = MATCH_RE.match(line.replace("\t", " "))
            if mm:
                flush_pending()
                pending = ForumBullet(
                    event_label=event_label,
                    stage=stage,
                    player_a=mm.group(1).strip(),
                    player_b=mm.group(2).strip(),
                    score=mm.group(3).strip(),
                )
                continue

            if pending is not None:
                for yt in YOUTUBE_ID_RE.findall(line):
                    if yt not in pending.youtube_ids:
                        pending.youtube_ids.append(yt)
                for href in WMV_URL_RE.findall(token):
                    if href not in pending.external_urls:
                        pending.external_urls.append(href)

    flush_pending()
    log.info("Forum index: %d bullets, %d youtube ids", len(bullets), len(by_yt))
    return bullets, by_yt


def load_forum(*, url: str = FORUM_URL) -> tuple[list[ForumBullet], dict[str, ForumBullet]]:
    html = fetch_forum_html(url)
    return parse_forum_index(html)