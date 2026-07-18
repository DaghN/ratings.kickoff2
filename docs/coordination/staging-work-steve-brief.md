# Staging — brief for Steve (redirect)

**Canonical runbook (you run everything on the server):**  
[`site/public_html/ops/docs/post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md)

**Live commands only:** [`steve-live-ops.md`](../../site/public_html/ops/docs/steve-live-ops.md)

---

## Context

**Live since 2026-07-18:** after each rated game Steve inserts **ground** rows, then invokes PHP ops (`ProcessCompletedGame` / `FinalizeUtcDay`). **C++ derived post-game is retired.** PHP rebuilds derived state (Elo, stats, milestones, league inputs, …) from those ground rows. Batch **simul** on a copy remains the proof path for schema packets.

**Division of labour:** Dagh uploads `public_html/`; you run CLI, DB, game server, cron, site DB config. Steve owns ground insert + hosting + invoke; this repo owns derived writers/contracts.

**Do not use** for this phase: `prepare` / `refresh-work` (two-DB refresh shortcut). Use **migrate-work → seed-catalog → zero-derived** on the prod copy you already have — see post-dagh-live-story.

---

*This file is a short pointer; detail lives in post-dagh-live-story (not duplicated here).*
