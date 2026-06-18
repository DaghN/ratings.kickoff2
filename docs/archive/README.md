# Archived docs

Planning and audit material kept for history — **not** the live spec for the site.

**Agent rule:** Do **not** assign tasks from archived registers (especially **“Pending on prod”** in [`replay-register-2026-05.md`](replay-register-2026-05.md)). Forward cutover: [`../coordination/cutover-readiness.md`](../coordination/cutover-readiness.md).

## Frozen environments (May 2026)

| Name | Role today |
|------|------------|
| **`kooldb`** | Single staging DB; batch `*_rebuild.sql` era — **frozen, historical logs only** |
| **`staging-scripts/`** | Removed Jun 2026 — inventory [`staging-scripts-inventory.md`](staging-scripts-inventory.md) |
| **`run_staging_ladder_replay.sh`** | May 2026 Python replay on `kooldb` — [`STAGING_REPLAY-2026-05.md`](STAGING_REPLAY-2026-05.md) |

**Forward proof DB:** **`kooldb1`** / local **`ko2unity_work`** — ops simul, not batch REP marathon.

## File index

| File | Contents |
|------|----------|
| `replay-register-2026-05.md` | Full May 2026 REP register + run log |
| `STAGING_REPLAY-2026-05.md` | May 2026 staging Python ladder one-shot |
| `session-log-2026-q2.md` | Trimmed `PROJECT_MEMORY.md` Recent log rows (May–Jun 2026) |
| [`orchestration/`](orchestration/README.md) | **Jun 2026** — archived agent slice handoffs (105), retired starters, `prompt-001`–`022`; live disposition starters stay in `docs/orchestration/agent-handoffs/` |
| `profile-data-audit-pass2.md` | Pass-2 data map, B1/B2 ranking, feast contract, mock briefs (pre-ship) |
| `profile-redesign-framing.md` | Audience, JTBD, Steve anchor, tone |
| `profile-lab-agent-handoff.md` | Multi-agent lab prompts (Jun 2026; lab PHP removed; production feast shipped) |
| `profile-content-candidates.md` | ~70 profile content candidates; v1 curation complete |
| `derived-data-refactor-plan.md` | May 2026 refactor plan (executed; live spec is `website-data-contract.md`) |
| `ladder-engine-plan.md` | Python replay / Elo sandbox intent (May 2026); cutover = ops simul — stub at [`../ladder-engine-plan.md`](../ladder-engine-plan.md) |
| `player-period-games-handoff.md` | Staging runbook for SCH-004/006 (staging done May 2026) |
| `play-streaks-staging-handoff.md` | Rated play streaks staging runbook (done May 2026) |
| `milestones-year-in-heaven-handoff.md` | `year_in_heaven` staging handoff (done May 2026) |
| `milestones-staging-diversity-merchant-fix.md` | `diversity_merchant` surgical fix (done May 2026) |
| `milestones-staging-cutover-packet.md` | Full milestones staging WinSCP + Steve packet |
| `milestones-staging-steve-handoff.md` | Index to cutover packet |
| `parity-audit-backlog.md` | Work vs dev parity audit (closed Jun 2026) |
| `cursor-move-agent-root-stuck.md` | Cursor MCP `move_agent_to_root` hang investigation |
| `milestones-system-discussion.md` | Phase 0 discovery paper |
| `milestones-ideas-catalog.md` | Phase 1 brainstorm catalog |
| `milestones-want-maybe-by-theme.md` | Thematic tier-band grouping pass |
| `milestones-tier-curated.md` | Phase 2 tier snapshot + win-streak notes |
| `retired-product-decisions.md` | Tombstone for retired product ideas (e.g. PER-001 rating fade) |
| `status-period-competitions-phase-1.5-handoff.md` | Agent handoff for Phase 1.5 (retired Jun 2026) |
| `status-period-competitions-wip.md` | Status Leagues WIP diary + closed Phase 1.5 checklist (archived Jun 2026) |

**Current maintainer docs:** `docs/player-profile-feast.md` · `docs/milestones-README.md` · `docs/website-data-contract.md` · `docs/coordination/cutover-readiness.md`

**Code checkpoint before mock deletion:** git commit `b8c5a98`.
