# Periodic jobs register

Jobs that run on a **schedule** on the server — **not** immediately after each rated game. Steve chooses implementation (C++, cron, etc.); we document **WHAT** to run or stop.

| ID | Job | Schedule (prod today) | Action for our releases | Prod status | Notes |
|----|-----|------------------------|-------------------------|-------------|-------|
| PER-003 | **UTC day tick** | **Daily ~00:00:01 UTC** (proposed) | `CMD=FinalizeUtcDay` — league finalize + league event milestones + `perfect_day` / `nightmare_day`. Legacy: `FinalizeLeagueDue` (league only). | **Shipped (PHP)** | Steve/cron: `php ops/dispatch.php CMD=FinalizeUtcDay target=staging-work as_of=…`. Dev: `run_finalize_utc_day.php`, `run_timeline_sim.php`. [`ops-orchestration-adr.md`](ops-orchestration-adr.md). |

**Retired periodic jobs** (do not add rows here): [`../archive/retired-product-decisions.md`](../archive/retired-product-decisions.md).

### Adding a row

| Field | Content |
|-------|---------|
| **Inputs** | Which tables/columns |
| **Outputs** | What gets written |
| **Frequency** | e.g. hourly, 1st of month 00:05 server TZ |
| **Alternative** | Could this be post-game or replay instead? |

### Cutover rule

See [cutover packet](cutover-packet-template.md) for schema/replay/C++ deploy checks.
