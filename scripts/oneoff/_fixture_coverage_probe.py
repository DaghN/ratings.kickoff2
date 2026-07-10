import pymysql
from scripts.amiga.config import load_amiga_db_config
cfg = load_amiga_db_config()
conn = pymysql.connect(host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password, database=cfg.database, charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor)
with conn.cursor() as cur:
    cur.execute("SELECT COUNT(*) AS n, COALESCE(SUM(gc.c),0) AS games FROM (SELECT t.id, COUNT(g.id) AS c FROM tournaments t INNER JOIN amiga_games g ON g.tournament_id = t.id GROUP BY t.id HAVING SUM(g.fixture_id IS NOT NULL) = 0) gc")
    summary = cur.fetchone()
    cur.execute("SELECT t.id, t.name, COUNT(g.id) AS games FROM tournaments t INNER JOIN amiga_games g ON g.tournament_id = t.id GROUP BY t.id, t.name HAVING SUM(g.fixture_id IS NOT NULL) = 0 ORDER BY games DESC LIMIT 15")
    rows = cur.fetchall()
    cur.execute("SELECT t.id FROM tournaments t INNER JOIN amiga_games g ON g.tournament_id = t.id WHERE t.name LIKE 'World Cup%' GROUP BY t.id HAVING SUM(g.fixture_id IS NOT NULL) = 0")
    wc_unlinked = len(cur.fetchall())
    cur.execute("SELECT COUNT(*) AS n FROM tournaments WHERE name LIKE 'World Cup%'")
    wc_total = cur.fetchone()["n"]
conn.close()
print("Unlinked tournaments:", summary["n"], "total games:", summary["games"])
print("World Cups in catalog:", wc_total, "fully unlinked:", wc_unlinked)
for r in rows:
    print("  id=%s games=%s %s" % (r["id"], r["games"], r["name"][:55]))