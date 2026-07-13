import sys
sys.path.insert(0, r"C:\Users\daghn\Desktop\Online and Amiga 500 ELO")
import os, subprocess
import pyodbc, pymysql
from scripts.amiga.import_corrections import access_reference_tournament_name
from scripts.amiga.player_names import normalize_display_name, canonical_player_name

tid = int(sys.argv[1])
MDB = r"C:\Users\daghn\Desktop\Online and Amiga 500 ELO\data\amiga\source\koatd.mdb"
mysql = pymysql.connect(host='127.0.0.1', user='root', password='', database='ko2amiga_work', charset='utf8mb4', cursorclass=pymysql.cursors.DictCursor)
with mysql.cursor() as c:
    c.execute('SELECT name FROM tournaments WHERE id=%s', (tid,))
    tname = c.fetchone()['name']
    c.execute('SELECT pl.id, pl.name, COUNT(*) games FROM amiga_games g JOIN amiga_players pl ON pl.id IN (g.player_a_id, g.player_b_id) WHERE g.tournament_id=%s GROUP BY pl.id, pl.name', (tid,))
    games = {normalize_display_name(canonical_player_name(r['name'])): (int(r['id']), r['name'], int(r['games'])) for r in c.fetchall()}
access_label = access_reference_tournament_name(tname)
conn = pyodbc.connect(f"DRIVER={{Microsoft Access Driver (*.mdb, *.accdb)}};DBQ={MDB};")
cur = conn.cursor()
cur.execute('SELECT P, Player FROM [Tables] WHERE Tournament = ? ORDER BY P', access_label)
access = [(int(r[0]), str(r[1]).strip()) for r in cur.fetchall()]
conn.close()
if not access:
    raise SystemExit(f'FLAG: no Access rows for {access_label!r}')
rows = []
for p,n in access:
    key = normalize_display_name(canonical_player_name(n))
    if key not in games:
        raise SystemExit(f'FLAG: Access player not in games: {p} {n} -> {key}')
    rows.append((tid, games[key][0], p))
missing = [games[k] for k in sorted(set(games)-{normalize_display_name(canonical_player_name(n)) for _,n in access})]
with mysql.cursor() as c:
    c.execute('DELETE FROM amiga_tournament_finish_override WHERE tournament_id=%s', (tid,))
    c.executemany('INSERT INTO amiga_tournament_finish_override (tournament_id, player_id, event_finish_position) VALUES (%s,%s,%s)', rows)
mysql.commit(); mysql.close()
env=os.environ.copy(); env['KO2AMIGA_DATABASE']='ko2amiga_work'
subprocess.run([sys.executable,'-m','scripts.amiga','refresh-event-finish-snapshots','--tournament-id',str(tid)], cwd=r"C:\Users\daghn\Desktop\Online and Amiga 500 ELO", env=env, check=True)
print(f'OK {tid} {tname} | {len(rows)} rows | absent-from-access: {len(missing)}')
for m in missing: print(f'  no position: {m[1]} id={m[0]} games={m[2]}')