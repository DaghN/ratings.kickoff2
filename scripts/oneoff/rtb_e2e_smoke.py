"""RTB end-to-end: fixture scores -> zero games -> promote -> finalize."""
from __future__ import annotations

from datetime import date, datetime

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.finalize_tournament import finalize_tournament
from scripts.amiga.promote_running_tournament import promote_running_tournament
from scripts.amiga.replay import _connect
from scripts.amiga.tournament_builder import create_kitchen_marathon_tournament
from scripts.amiga.tournament_fixtures import (
    list_fixtures,
    record_fixture_result,
    set_tournament_lifecycle_status,
)


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        result = create_kitchen_marathon_tournament(
            conn,
            name=f"RTB Ref-League-A {datetime.utcnow().strftime('%H%M%S')}",
            event_date=date.today(),
            country="Test",
            player_ids=[1, 2, 3, 4],
            legs=1,
        )
        tid = int(result["tournament_id"])
        set_tournament_lifecycle_status(conn, tournament_id=tid, status="running")
        conn.commit()
        for row in list_fixtures(conn, tournament_id=tid, status="scheduled"):
            record_fixture_result(conn, fixture_id=int(row["id"]), goals_a=2, goals_b=1)
        conn.commit()
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id=%s", (tid,))
            running_games = int(cur.fetchone()["n"])
        promote = promote_running_tournament(conn, tid)
        fin = finalize_tournament(conn, tid)
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id=%s", (tid,))
            official_games = int(cur.fetchone()["n"])
            cur.execute("SELECT rating_finalized FROM tournaments WHERE id=%s", (tid,))
            finalized = int(cur.fetchone()["rating_finalized"])
        print(
            f"tid={tid} running_games={running_games} promoted={promote['promoted']} "
            f"official_games={official_games} finalized={finalized} fin_games={fin.get('games')}"
        )
        assert running_games == 0
        assert promote["promoted"] == 6
        assert official_games == 6
        assert finalized == 1
        print("OK: RTB end-to-end")
        return 0
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())