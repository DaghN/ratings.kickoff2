# Periodic jobs register

Jobs that run on a **schedule** on the server — **not** immediately after each rated game. Steve chooses implementation (C++, cron, etc.); we document **WHAT** to run or stop.

| ID | Job | Schedule (prod today) | Action for our releases | Prod status | Notes |
|----|-----|------------------------|-------------------------|-------------|-------|
| PER-001 | **Rating fade** | **Hourly** (Steve, May 2026) | **Stop before** any prod deploy that changes rating/stat semantics | Running | Steve: can stop easily. Not in `ratings_cpp.txt` excerpt. |
| PER-002 | *(example)* Monthly league medals on profiles | — | Superseded by PER-003 | — | — |
| PER-003 | **League finalize** | **Daily ~00:00:01 UTC** (proposed) | Finalize all leagues with `period_end <= now`; write `player_league_award`, `player_league_totals`, `league_period.finalized_at`; milestone threshold checks | **Pending** | Rules `docs/leagues-rules-spec.md`; not per-game. Local/work: `php site/public_html/ops/run_finalize_league.php finalize-due --target local-work`. Local dev DB: `--target local-dev`. Prod: Steve cron / `dispatch.php` (TBD). |

### Adding a row

| Field | Content |
|-------|---------|
| **Inputs** | Which tables/columns |
| **Outputs** | What gets written |
| **Frequency** | e.g. hourly, 1st of month 00:05 server TZ |
| **Alternative** | Could this be post-game or replay instead? |

### Cutover rule

Document in [cutover packet](cutover-packet-template.md): fade **off** before schema/replay/C++ deploy (Dagh policy, May 2026).
