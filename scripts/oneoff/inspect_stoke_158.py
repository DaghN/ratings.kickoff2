from scripts.amiga.tournament_structure.materialize_legacy import _connect
from scripts.amiga.tournament_phases import parse_phase

conn = _connect()
cur = conn.cursor()
tid = 158
cur.execute(
    """SELECT g.player_a_id, g.player_b_id, g.goals_a, g.goals_b, g.phase, pa.name na, pb.name nb
    FROM amiga_games g
    JOIN amiga_players pa ON pa.id=g.player_a_id
    JOIN amiga_players pb ON pb.id=g.player_b_id
    WHERE g.tournament_id=%s ORDER BY g.source_scores_id""",
    (tid,),
)
games = cur.fetchall()
print("parse_phase('Round 1'):", parse_phase("Round 1"))
players_in_games = set()
for g in games:
    players_in_games.add(g["player_a_id"])
    players_in_games.add(g["player_b_id"])
print("players in games:", len(players_in_games))
for g in games:
    if g["phase"] == "Round 1":
        print(f"  R1: {g['na']} vs {g['nb']} {g['goals_a']}-{g['goals_b']}")
cur.execute(
    "SELECT player_id FROM amiga_tournament_participation WHERE tournament_id=%s",
    (tid,),
)
part = {r["player_id"] for r in cur.fetchall()}
bye = part - players_in_games
print("participation:", len(part), "bye ids:", bye)
conn.close()
