# Player period games — staging and prod handoff (archived)

**Staging complete May 2026** on `kooldb` (SCH-004/006, REP-003 incl. week, REP-005). Current behavior: [`../website-data-contract.md`](../website-data-contract.md). Status: [`../coordination/schema-register.md`](../coordination/schema-register.md), [`../coordination/replay-register.md`](../coordination/replay-register.md).

---

# Player period games — staging and prod handoff

**Feature:** Fast day/week/month/year games leaderboards plus peak-period cache
**Spec:** [`../website-data-contract.md`](../website-data-contract.md)
**Schema:** SCH-004 · `schema/migrations/003_player_period_games.sql`; SCH-006 · `schema/migrations/005_period_activity_week_and_peaks.sql`
**Post-game (prod):** Contract § `player_period_games` and `player_peak_period_games` — no per-table C++ snippet packs in repo.

---

## Staging handoff for Steve

Staging `kooldb` has no live game writes, so the goal is schema + one rebuild + PHP smoke test.

**Status:** Staging `kooldb` complete May 2026 — SCH-004/006, REP-003 (incl. week), REP-005. Prod pending Steve.

### Files Steve needs

| Purpose | File |
|---------|------|
| Create/alter tables | `schema/migrations/003_player_period_games.sql`, then `schema/migrations/005_period_activity_week_and_peaks.sql` |
| Backfill table | `scripts/ladder/sql/player_period_games_rebuild.sql` |
| Backfill peak cache | `scripts/ladder/sql/player_peak_period_games_rebuild.sql` |
| PHP reader | `site/public_html/includes/period_activity_leaderboard_query.php` |
| Activity Hall of Fame / Records readers | `site/public_html/ranked8.php`, `site/public_html/server2.php` |
| API | `site/public_html/api/server_period_activity_leaderboard.php` |
| Preview page | ~~`dev-period-activity.php`~~ — **removed May 2026**; use Status **Leagues** block on `status.php` |

### Run order

1. Apply schema on staging `kooldb`:

```bash
mysql -u ... kooldb < schema/migrations/003_player_period_games.sql
mysql -u ... kooldb < schema/migrations/005_period_activity_week_and_peaks.sql
```

2. Rebuild aggregate rows from staging `ratedresults`:

```bash
mysql -u ... kooldb < scripts/ladder/sql/player_period_games_rebuild.sql
mysql -u ... kooldb < scripts/ladder/sql/player_peak_period_games_rebuild.sql
```

3. Verify counts:

```sql
SELECT period_type, COUNT(*) AS row_count, SUM(games) AS appearances
FROM player_period_games
GROUP BY period_type
ORDER BY FIELD(period_type, 'day', 'week', 'month', 'year');

SELECT COUNT(*) * 2 AS expected_appearances
FROM ratedresults;
```

For each period type, `appearances` should equal `expected_appearances`.

```sql
SELECT period_type, COUNT(*) AS peak_rows
FROM player_peak_period_games
GROUP BY period_type
ORDER BY FIELD(period_type, 'day', 'week', 'month', 'year');
```

**MariaDB note from staging:** use `COUNT(*)`, not `COUNT()`. Steve had to adjust one copied SQL statement because MariaDB rejected bare `COUNT()`.

4. Deploy PHP to staging `public_html/`.

5. Smoke test:

```text
/api/server_period_activity_leaderboard.php?period=week&key=2026-05-18&limit=5
/api/server_period_activity_leaderboard.php?period=month&key=2026-05&limit=5
/ranked8.php
/server2.php
`status.php` (Leagues block)
```

Expected: API returns JSON with entries; ranked8 and Records render week alongside day/month/year; preview page renders four tables. Staging dates depend on staging DB freshness.

---

## Production handoff — method TBD

Production needs the same schema and rebuild, plus C++ live maintenance from the contract before the feature relies on these tables as truth.

### Required pieces

| Piece | Status |
|-------|--------|
| Schema `003_player_period_games.sql` | Ready / staging original applied |
| Schema `005_period_activity_week_and_peaks.sql` | Ready |
| Backfill `player_period_games_rebuild.sql` | Ready |
| Backfill `player_peak_period_games_rebuild.sql` | Ready |
| C++ post-game (contract) | At prod cutover — not maintained as snippet packs |
| PHP reader | Ready in repo |
| Final prod execution method | TBD with Steve |

### Safe production order

1. Backup / maintenance window: Steve decides.
2. Apply schema `003`.
3. Apply schema `005`.
4. Run period rebuild SQL once on the production DB.
5. Run peak-cache rebuild SQL once on the production DB.
6. Deploy C++ from contract post-game rules (period games + peak cache).
7. Deploy PHP page/API that reads `player_period_games` and `player_peak_period_games`.
8. Smoke test API and a known recent period.

If PHP is deployed before C++ on prod, the tables will be correct only until the next live rated game after rebuild. That is acceptable for staging but not for production launch.

---

## Rollback notes

The new PHP period leaderboard reader depends on `player_period_games`; ranked8 can fall back if `player_peak_period_games` is missing. If prod needs rollback before C++ is live, either:

- remove/hide the period activity UI, or
- revert `period_activity_leaderboard_query.php` to the old `ratedresults` scan version.

The schema table itself is additive and can remain unused.
