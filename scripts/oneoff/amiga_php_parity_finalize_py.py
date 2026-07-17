from __future__ import annotations

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import finalize_tournament
from scripts.amiga.promote_running_tournament import promote_running_tournament

DB = "ko2amiga_parity_py"
TID = 608


def main() -> None:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=DB,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
        promote = promote_running_tournament(conn, TID, dry_run=False)
        print("promote:", promote)
        result = finalize_tournament(conn, TID, dry_run=False)
        print("finalize:", {k: v for k, v in result.items() if k != "realm_payload"})
        with conn.cursor() as cur:
            cur.execute(
                "SELECT rating_finalized, chrono, lifecycle_status FROM tournaments WHERE id=%s",
                (TID,),
            )
            print("tour_after:", cur.fetchone())
    finally:
        conn.close()


if __name__ == "__main__":
    main()