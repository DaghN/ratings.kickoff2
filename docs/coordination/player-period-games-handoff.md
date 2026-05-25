# Player period games — staging and prod handoff

**Feature:** Fast day/month/year games leaderboards
**Spec:** [`../player-period-games.md`](../player-period-games.md)
**Schema:** SCH-004 · `schema/migrations/003_player_period_games.sql`
**Post-game:** PG-005 · [`cpp-snippets/PG-005-player-period-games.md`](cpp-snippets/PG-005-player-period-games.md)

---

## Staging handoff for Steve

Staging `kooldb` has no live game writes, so the goal is schema + one rebuild + PHP smoke test.

**Status:** Done May 2026. Steve ran the schema + rebuild on staging and the appearance-count expectation test passed.

### Files Steve needs

| Purpose | File |
|---------|------|
| Create table | `schema/migrations/003_player_period_games.sql` |
| Backfill table | `scripts/ladder/sql/player_period_games_rebuild.sql` |
| PHP reader | `site/public_html/includes/period_activity_leaderboard_query.php` |
| API | `site/public_html/api/server_period_activity_leaderboard.php` |
| Preview page | `site/public_html/dev-period-activity.php` |

### Run order

1. Apply schema on staging `kooldb`:

```bash
mysql -u ... kooldb < schema/migrations/003_player_period_games.sql
```

2. Rebuild aggregate rows from staging `ratedresults`:

```bash
mysql -u ... kooldb < scripts/ladder/sql/player_period_games_rebuild.sql
```

3. Verify counts:

```sql
SELECT period_type, COUNT(*) AS row_count, SUM(games) AS appearances
FROM player_period_games
GROUP BY period_type
ORDER BY FIELD(period_type, 'day', 'month', 'year');

SELECT COUNT(*) * 2 AS expected_appearances
FROM ratedresults;
```

For each period type, `appearances` should equal `expected_appearances`.

**MariaDB note from staging:** use `COUNT(*)`, not `COUNT()`. Steve had to adjust one copied SQL statement because MariaDB rejected bare `COUNT()`.

4. Deploy PHP to staging `public_html/`.

5. Smoke test:

```text
/api/server_period_activity_leaderboard.php?period=month&key=2026-05&limit=5
/dev-period-activity.php
```

Expected: API returns JSON with entries; preview page renders three tables. Staging dates depend on staging DB freshness.

---

## Production handoff — method TBD

Production needs the same schema and rebuild, plus C++ live maintenance before the feature relies on this table as truth.

### Required pieces

| Piece | Status |
|-------|--------|
| Schema `003_player_period_games.sql` | Ready |
| Backfill `player_period_games_rebuild.sql` | Ready |
| C++ post-game upsert PG-005 | Draft for Steve review |
| PHP reader | Ready in repo |
| Final prod execution method | TBD with Steve |

### Safe production order

1. Backup / maintenance window: Steve decides.
2. Apply schema `003`.
3. Run rebuild SQL once on the production DB.
4. Deploy C++ PG-005 so future games upsert A/B × day/month/year.
5. Deploy PHP page/API that reads `player_period_games`.
6. Smoke test API and a known recent period.

If PHP is deployed before C++ on prod, the table will be correct only until the next live rated game after rebuild. That is acceptable for staging but not for production launch.

---

## Rollback notes

The new PHP period leaderboard reader depends on `player_period_games`. If prod needs rollback before C++ is live, either:

- remove/hide the period activity UI, or
- revert `period_activity_leaderboard_query.php` to the old `ratedresults` scan version.

The schema table itself is additive and can remain unused.
