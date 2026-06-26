"""Optional HEAD probe for legacy WMV mirrors (forum external URLs)."""

from __future__ import annotations

import logging
import urllib.request

from scripts.amiga.tournament_videos.constants import USER_AGENT

log = logging.getLogger(__name__)


def probe_url(url: str, *, timeout: int = 15) -> bool:
    if not url:
        return False
    req = urllib.request.Request(url, method="HEAD", headers={"User-Agent": USER_AGENT})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return 200 <= resp.status < 400
    except Exception:
        try:
            req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
            req.get_method = lambda: "GET"
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return 200 <= resp.status < 400
        except Exception as exc:
            log.debug("Probe failed %s: %s", url, exc)
            return False