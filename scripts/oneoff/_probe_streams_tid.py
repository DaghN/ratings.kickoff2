from scripts.amiga.tournament_videos.enrich import load_wc_catalog
import pymysql
from scripts.amiga.config import load_amiga_db_config
from pymysql.cursors import DictCursor
wc = load_wc_catalog()
for y in [2016, 2019, 2022, 2023, 2024, 2025]:
    w = wc.get(y)
    print(y, w.tournament_id if w else None, w.name if w else None)
cfg = load_amiga_db_config()
conn = pymysql.connect(host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password, database=cfg.database, charset="utf8mb4", cursorclass=DictCursor)
with conn.cursor() as cur:
    cur.execute("SELECT id, name, event_date FROM tournaments WHERE name LIKE '%Championship%' OR name LIKE '%Preston%' ORDER BY event_date DESC LIMIT 25")
    for r in cur.fetchall():
        print(r["id"], r["name"], r["event_date"])
conn.close()