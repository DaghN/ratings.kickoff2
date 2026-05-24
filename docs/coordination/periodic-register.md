# Periodic jobs register

Jobs that run on a **schedule** on the server — **not** immediately after each rated game. Steve chooses implementation (C++, cron, etc.); we document **WHAT** to run or stop.

| ID | Job | Schedule (prod today) | Action for our releases | Prod status | Notes |
|----|-----|------------------------|-------------------------|-------------|-------|
| PER-001 | **Rating fade** | **Hourly** (Steve, May 2026) | **Stop before** any prod deploy that changes rating/stat semantics | Running | Steve: can stop easily. Pair with [PG-001](post-game-register.md). Not in `ratings_cpp.txt` excerpt. |
| PER-002 | *(example)* Monthly league medals on profiles | — | Proposed: end-of-month or daily batch writing medal flags | — | Status page league is **L0** read-time SQL today (`docs/STATUS_PAGE_DATA.md`); persistent medals = future L2/L3 + maybe periodic |

### Adding a row

| Field | Content |
|-------|---------|
| **Inputs** | Which tables/columns |
| **Outputs** | What gets written |
| **Frequency** | e.g. hourly, 1st of month 00:05 server TZ |
| **Alternative** | Could this be post-game or replay instead? |

### Cutover rule

Document in [cutover packet](cutover-packet-template.md): fade **off** before schema/replay/C++ deploy (Dagh policy, May 2026).
