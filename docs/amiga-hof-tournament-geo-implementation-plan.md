# Amiga HoF tournament / calendar-year / geography — implementation plan

**Status:** Complete (Jun 2026) — **date fix track:** [`amiga-hof-record-date-implementation-plan.md`](amiga-hof-record-date-implementation-plan.md)  
**Policy:** [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md)

---

## Slices (all shipped)

| Slice | Deliverable |
|-------|-------------|
| **0** | Policy locked (H1–H12) |
| **1** | DDL `028_hof_tournament_geo.sql` — snapshot/current geo-year scalars; `generalstats` + realm holder columns |
| **2** | `player_geo_year.py` — incremental tracker at finalize |
| **3** | Finalize/replay/refinalize wire; `realm_incremental` career holders from `amiga_player_current` |
| **4** | `verify-hof-geo-year` + `prove` step; unit tests |
| **5** | PHP finalize parity (`amiga_player_geo_year_lib.php`, snapshot persist, realm incremental) |
| **6** | HoF UI — eight rows on `/amiga/hall-of-fame.php` |
| **6b** | Leaderboards wing `/amiga/leaderboards/calendar-geo.php` + column help + HoF deep links |
| **7** | Docs + export tail; `python -m scripts.amiga prove` green (~5 min) |

---

## Proof

```text
python -m scripts.amiga prove
python -m unittest scripts.amiga.test_player_geo_year scripts.amiga.test_generalstats_columns -v
```

Staging: `scripts\export_ko2amiga_db.ps1` after local prove green.
