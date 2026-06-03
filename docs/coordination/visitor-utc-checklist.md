# Visitor UTC checklist (Status leagues)

**Purpose:** Every visitor gets the same league countdown and display-medal timing (UTC), independent of browser timezone.

**Automated (Dagh/CI/local):**

```powershell
php scripts/verify_visitor_utc_clock.php
php scripts/verify_visitor_utc_clock.php --config site/config/ladder-work.ini
```

**Code guarantees (Jun 2026):**

- `k2_site_ensure_utc()` — forces PHP `date.timezone` to UTC on public DB connect (`k2_db_connect_or_public_error`).
- MySQL `SET time_zone = '+00:00'` on Status + league JSON APIs.
- `k2_status_league_end_epoch()` — parses period `end` as explicit UTC.
- Status APIs return `server_now_epoch`, `show_medals` with points league JSON.

**Prod/staging (Steve/hosting — manual once per deploy):**

| Check | How |
|-------|-----|
| PHP `date.timezone` | `php -i \| findstr date.timezone` → **UTC** (or rely on `k2_site_ensure_utc`). |
| Live Status meta | Open current **week** league; “ends … UTC” + “X left” should match [time.is UTC](https://time.is/UTC). |
| PER-003 | Cron `php …/run_finalize_league.php finalize-due` ~**00:00:01 UTC** (persisted awards; separate from display medals). |

**Display medals vs stored awards:** Status shows podium icons when `period end ≤ server now` using aggregates; `player_league_award` requires PER-003.
