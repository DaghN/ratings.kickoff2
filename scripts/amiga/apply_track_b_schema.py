#!/usr/bin/env python3
"""Apply Track B DDL (002) to an existing ko2amiga_db."""
from scripts.amiga.import_access import _SQL_TRACK_B, _split_sql, connect_mysql
from scripts.amiga.config import load_amiga_db_config
import pymysql

cfg = load_amiga_db_config()
conn = connect_mysql(cfg)
sql = _SQL_TRACK_B.read_text(encoding="utf-8")
with conn.cursor() as cur:
    for stmt in _split_sql(sql):
        if stmt.strip().upper().startswith("ALTER TABLE"):
            try:
                cur.execute(stmt)
                print("ALTER applied")
            except pymysql.err.OperationalError as exc:
                if exc.args[0] == 1060:
                    print("extra column already exists — skip")
                else:
                    raise
        else:
            cur.execute(stmt)
            print("CREATE applied")
conn.commit()
conn.close()
print("Track B schema ready")
