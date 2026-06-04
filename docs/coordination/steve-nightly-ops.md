# Steve — nightly and per-game ops (plain language)

**For:** Steve · **Detail:** [`ops-dispatch.md`](ops-dispatch.md) · **Simul:** [`ops-simul-runbook.md`](ops-simul-runbook.md)

---

## After each rated game (live / staging test)

When a match is saved to `ratedresults`, run **one** PHP line so ratings, stats, and milestones update:

```bash
php ops/dispatch.php CMD=ProcessCompletedGame game_id=GAME_ID target=staging-work
```

Replace `GAME_ID` with the new row’s `id`. If exit code is **0**, derived data for that game is done. If **1**, nothing was committed — fix and retry. If **2**, that game was already processed — safe to skip.

---

## Once per night (just after midnight UTC)

Run **one** PHP line — not several. It closes the UTC day: league period results, league-related medals, and “perfect day” / “nightmare day” style daily medals.

```bash
php ops/dispatch.php CMD=FinalizeUtcDay target=staging-work
```

Schedule around **00:00:01 UTC** (cron / task scheduler). Dagh will confirm `target=` for prod when you cut over.

**Do not** use the older `FinalizeLeagueDue` for new cron jobs — it only does part of the night work. If it still runs, logs will remind you to switch to `FinalizeUtcDay`.

---

## New player registration (when wired)

When someone registers (enters the lobby), run:

```bash
php ops/dispatch.php CMD=ProcessPlayerRegistered player_id=PLAYER_ID target=staging-work
```

Historical replay on the work DB does **not** need this — prepare already wrote `entered_arena` from join dates.

---

## Rating fade

**Not used** in the PHP ops plan. No hourly fade job required for this cutover.
