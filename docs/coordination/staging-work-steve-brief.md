# Staging — brief for Steve (redirect)

**Canonical runbook (you run everything on the server):**  
[`site/public_html/ops/docs/post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md)

**Live commands only:** [`steve-live-ops.md`](../../site/public_html/ops/docs/steve-live-ops.md)

---

## Context (unchanged goal)

Prod still uses **C++** after each rated game. PHP ops rebuilds the same **derived** state (Elo, stats, milestones, league inputs, …) from **ground** rows you insert. Batch **simul** on a copy already passed verify; **next** is the same pipeline **one game at a time** on your prod copy, then prod cutover when boring.

**Division of labour:** Dagh uploads `public_html/`; you run CLI, DB, game server, cron, site DB config.

**Do not use** for this phase: `prepare` / `refresh-work` (two-DB refresh shortcut). Use **migrate-work → seed-catalog → zero-derived** on the prod copy you already have — see post-dagh-live-story.

---

*This file is a short pointer; detail lives in post-dagh-live-story (not duplicated here).*
