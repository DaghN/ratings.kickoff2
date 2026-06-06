#!/usr/bin/env python3
"""Apply Track B DDL (002 + 003) to an existing ko2amiga_db."""
import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.import_access import _SQL_KNOCKOUT, _SQL_TRACK_B, _split_sql, connect_mysql

cfg = load_amiga_db_config()
conn = connect_mysql(cfg)
for sql_path in (_SQL_TRACK_B, _SQL_KNOCKOUT):
    sql = sql_path.read_text(encoding="utf-8")
    with conn.cursor() as cur:
        for stmt in _split_sql(sql):
            if stmt.strip().upper().startswith("ALTER TABLE"):
                try:
                    cur.execute(stmt)
                    print(f"ALTER applied ({sql_path.name})")
                except pymysql.err.OperationalError as exc:
                    if exc.args[0] == 1060:
                        print("extra column already exists — skip")
                    else:
                        raise
            else:
                cur.execute(stmt)
                print(f"applied ({sql_path.name})")
    conn.commit()
conn.close()
print("Track B schema ready")
