from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.promote_running_tournament import promote_running_tournament
import pymysql
from pymysql.cursors import DictCursor

cfg = load_amiga_db_config()
conn = pymysql.connect(host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
                       database="ko2amiga_work", charset="utf8mb4", autocommit=False, cursorclass=DictCursor)
try:
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    print(promote_running_tournament(conn, 608, dry_run=False))
    with conn.cursor() as cur:
        cur.execute("SELECT chrono, rating_finalized, (SELECT COUNT(*) FROM amiga_games WHERE tournament_id=608) n FROM tournaments WHERE id=608")
        print(cur.fetchone())
finally:
    conn.close()