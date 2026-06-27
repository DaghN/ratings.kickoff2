import json
from pathlib import Path
data = json.loads(Path("site/public_html/data/amiga/tournament_videos.json").read_text(encoding="utf-8"))
wcs = {}
for v in data["videos"]:
    if v.get("kind") != "match":
        continue
    tid = v["tournament_id"]
    wcs.setdefault(tid, []).append(v)
for tid in sorted(wcs):
    print(f"=== tid {tid} ({len(wcs[tid])} match clips) ===")
    for v in wcs[tid]:
        st = v.get("stage") or ""
        gids = v.get("game_ids") or []
        title = v["title"][:75]
        print(f"  {st:8} {v['youtube_id']:12} games={gids} | {title}")