# Update docs — agent playbook

**For Cursor agents.** Dagh says **“update docs”** at the end of many sessions — cosmetics, features, refactors, DB work, or “we’re done for today.” Treat it as **session documentation hygiene**, not a migration-only command.

**Do this playbook when:**

- You **complete implementation** in this chat (same assistant turn as the code — **do not wait** for a follow-up), or  
- Dagh says **update docs** / **done** (same playbook).

**Critical:** If Dagh archives the chat without another message, a deferred “I’ll update docs next turn” never runs. **Finish in the same reply where you say the work is done.**

**Do not** open every file under `docs/coordination/` every time — use the two-part flow below.

---

## Part A — Always (every “update docs”)

Capture **what happened this session** so the next agent (or Dagh) can continue without re-reading the chat.

### A1. Session handoff

- [ ] **`PROJECT_MEMORY.md`**
  - One concise line under **Recent log** (what shipped / decided).
  - Adjust **Current focus** / **Next** only if they materially changed.
  - No paste of long chat transcripts.

### A2. Feature / area specs (only if this slice touched them)

Update the doc that owns that area — **if** behaviour or contracts changed:

| Area touched | Spec file |
|--------------|-----------|
| Stored / derived DB tables, rebuild, post-game rules | `docs/website-data-contract.md` |
| Ladder ops, `ops/`, Steve boundary, sandbox DBs, dispatcher conventions | `docs/ladder-ops-platform.md` (+ `docs/coordination/database-copies-2026-06.md` if DB roles change) |
| Work DB prepare, zero derived, simul modes | `docs/work-db-prepare.md` |
| Status hub panels / league | `docs/STATUS_PAGE_DATA.md` |
| Player profile layout / v1 build | `docs/player-profile-feast.md`, `docs/profile-build-playbook.md` (v1 decisions: `docs/archive/profile-content-candidates.md`) |
| Milestones feature (phases, catalog, tier plan) | `docs/milestones-README.md`, `docs/milestones-catalog.md` (regen script), `docs/milestones-project.md`, `docs/milestones-product-spec.md` |
| Hub tabs / IA | `docs/hub-ia-agreement.md` |
| URL routes / new sub-hub tabs | `docs/url-routes.md` § Sub-hub navigation |
| **New page / tab / mode (URL + placement)** | **`docs/k2-page-structure-checklist.md`** (then `url-routes.md`, `navigation-model.md` NM3–NM4) |
| Play & setup / join page | `docs/join-play-setup.md` |
| Visual identity / theme | `docs/design-direction.md` |
| **Page nav spacing** (chrome stack gaps) | [`nav-spacing-policy.md`](nav-spacing-policy.md) · [`nav-spacing-implementation-plan.md`](nav-spacing-implementation-plan.md) |
| **K2 tooltips** (table headers, chart hovers, control help) | [`k2-tooltip-policy.md`](k2-tooltip-policy.md) |
| **K2 table entity links** (player/tournament/country names, Amiga inline flags) | [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) |
| **K2 tables** (sortable, wide, filter reload, table refactor) | **`docs/k2-table-implementation-checklist.md`** (then `docs/k2-table-and-games-plan.md` if needed) |
| **K2 page structure** (new page / tab / mode) | **`docs/k2-page-structure-checklist.md`** (then `url-routes.md`, `navigation-model.md`) |
| Ladder replay scope / reset rules | `docs/replay-v1-scope-and-reset.md` (rare; big replay changes only) |
| **Amiga Opponents wing** | [`amiga-opponents-wing-policy.md`](amiga-opponents-wing-policy.md) (port online H2H/WDL/Goals/DDs; incremental slices) |
| **Amiga Opponents country grain** | [`amiga-opponents-country-grain-policy.md`](amiga-opponents-country-grain-policy.md) · plan [`amiga-opponents-country-grain-implementation-plan.md`](amiga-opponents-country-grain-implementation-plan.md) |
| **Amiga country Rivals (country vs country)** | [`amiga-country-rivals-policy.md`](amiga-country-rivals-policy.md) · plan [`amiga-country-rivals-implementation-plan.md`](amiga-country-rivals-implementation-plan.md) · three-grain table §1.1 |
| **Amiga World Cups LB (slice + columns)** | [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (data contract; UI = hub Player stats) · **slice V2** [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md) |
| **Amiga WC HoF** | [`amiga-wc-hof-policy.md`](amiga-wc-hof-policy.md) · [`amiga-wc-hof-implementation-plan.md`](amiga-wc-hof-implementation-plan.md) |
| **Amiga Countries hub** | [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) · [`amiga-countries-hub-implementation-plan.md`](amiga-countries-hub-implementation-plan.md) |
| Amiga ground layers / community packs | `docs/amiga-ground-stack.md`, `docs/amiga-ground-layers-policy.md`, `docs/amiga-ground-layers-implementation-plan.md` (L0–L5) |
| Amiga derived write policy / prove-only corrections | `docs/amiga-derived-write-policy.md`, `docs/amiga-data-contract.md` |
| Amiga event finish / honours / podiums | `docs/amiga-tournament-honours-rules.md`, `docs/amiga-event-finish-implementation-plan.md`, `docs/amiga-player-universe-contract.md` §5.2–§6 |
| **Amiga perfect event** (undefeated events, honours LB, HoF) | [`amiga-perfect-event-policy.md`](amiga-perfect-event-policy.md) · [`amiga-perfect-event-implementation-plan.md`](amiga-perfect-event-implementation-plan.md) |
| **Amiga perf. rating LB sub-wings** (Best · Top 100 · Perfect) | [`amiga-performance-rating-leaderboard-policy.md`](amiga-performance-rating-leaderboard-policy.md) |
| **Amiga tournament videos** (YouTube embeds, manifest) | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) · [`k2-embedded-video-page-policy.md`](k2-embedded-video-page-policy.md) · [`amiga-tournament-videos-implementation-plan.md`](amiga-tournament-videos-implementation-plan.md) |
| Hall of Fame record C++ / staging defects | `docs/coordination/records-post-game-exception.md`, `docs/staging-post-game-record-defects.md` (when records behavior changes) |
| **Creative / product brainstorm** (pre-track ideas, ledger updates — no code) | [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) — update §5 ledger + §8 changelog; one `PROJECT_MEMORY` line; **not** default cold-start read (see doc §4) |
| **With player stepper** (opt-in filters, tournament/league/TT chevrons; T18 retirement) | [`with-player-stepper-policy.md`](with-player-stepper-policy.md) · plan [`with-player-stepper-implementation-plan.md`](with-player-stepper-implementation-plan.md) |

Skip specs with no change. **Do not** invent new spec files unless Dagh asks.

### A3. User-visible capabilities

- [ ] If you added or materially changed a **user-facing feature**, add or update one row in **`docs/coordination/feature-log.md`** (name + short note). Level column can stay `—` until you run Part B.

### A4. Confirm to Dagh (brief)

One or two sentences: what you updated in docs and anything left undocumented on purpose.

---

## Part B — Migration pass (only when relevant)

Run **after Part A**. Skip entirely for pure cosmetics (CSS, theme tokens, copy, nav chrome) with **no** new stored DB truth.

### B1. Trigger check

Ask: *Will production someday need schema, replay, C++ post-game, or a periodic job for this work?*

| Situation | Part B |
|-----------|--------|
| CSS, layout, chart **display** only | **Skip** |
| PHP reads existing columns only | **Skip** (optional: feature-log L0 if new surface) |
| New/changed column, index, table | **Run** |
| Changed `scripts/ladder/` | **Run** |
| New per-game or scheduled server writer | **Run** |

### B2. Classify level (if Part B runs)

| Level | Meaning | Registers |
|-------|---------|-----------|
| **L0** | Read-time SQL only; no new stored truth | feature-log only |
| **L1** | Schema; no backfill yet | schema-register + `site/public_html/ops/sql/migrations/` |
| **L2** | Schema + ops simul backfill on work DB | schema-register + [`cutover-readiness.md`](coordination/cutover-readiness.md) + contract post-game § |
| **L4** | Prep complete (`kooldb1` / work simul verified) | feature-log: **kooldb1 proof** = Proven |
| **L5** | Live cutover executed | feature-log **Live cutover** = Done + schema-register live column |

Do **not** add `cpp-snippets/` or PG-NNN rows. Post-game behavior → [`website-data-contract.md`](website-data-contract.md). Records exception only: [`records-post-game-exception.md`](coordination/records-post-game-exception.md).

Details: [`prod-coordination.md`](prod-coordination.md#prod-readiness-levels).

### B3. Update registers (L1+ only)

| If… | Update |
|-----|--------|
| SQL migration | `site/public_html/ops/sql/migrations/NNN_….sql` + [`schema-register.md`](coordination/schema-register.md) |
| Ops simul / ladder changes | [`cutover-readiness.md`](coordination/cutover-readiness.md); historical batch log: [`archive/replay-register-2026-05.md`](archive/replay-register-2026-05.md) |
| Post-game rules (contract) | Extend [`website-data-contract.md`](website-data-contract.md) § for the table; records only → [`records-post-game-exception.md`](coordination/records-post-game-exception.md) |
| Periodic job | [`periodic-register.md`](coordination/periodic-register.md) |
| Ops CMD / Steve live handoff | **Edit** [`site/public_html/ops/docs/`](../site/public_html/ops/docs/) (`steve-live-ops.md`, `ops-dispatch.md`) — WinSCP canonical; `docs/coordination/` stubs redirect |
| Ops derived inventory / sim orchestration | [`ops-derived-data-registry.md`](coordination/ops-derived-data-registry.md), [`ops-orchestration-adr.md`](coordination/ops-orchestration-adr.md) |
| One-off script | [`one-off-register.md`](coordination/one-off-register.md) |

Set **feature-log** level column when known.

### B4. Migration note to Dagh (if L≥2)

Short paragraph: level, registers touched, whether **ops simul** was run on work DB. Prod cutover = Steve runbook — not batch `REP-xxx` scripts.

### B5. Do not (unless Dagh asks)

- Create `cpp-snippets/` or cite `PG-00x` as blocking website/staging work  
- Edit all registers “to be safe”  
- Run prod cutover or email Steve  

---

## Mid-session awareness (no “update docs” yet)

While coding, **notice** migration triggers (new column, ladder change). You may mention once: *“When you update docs, this is likely L2 — we should register schema + replay.”* Do not block the slice unless Dagh wants doc work now.

---

## Quick reference

| Need | File |
|------|------|
| Repo entry (humans + agents) | [`../README.md`](../README.md) |
| Project scaffold | [`PROJECT_MAP.md`](PROJECT_MAP.md) |
| Run replay / SQL | [`OPERATIONS_QUICK_START.md`](OPERATIONS_QUICK_START.md) |
| Feature status table | [`coordination/feature-log.md`](coordination/feature-log.md) |
| Steve cutover | [`prod-coordination.md`](prod-coordination.md), [`cutover-packet-template.md`](coordination/cutover-packet-template.md) |

---

## Examples (full “update docs”)

| Session | Part A | Part B |
|---------|--------|--------|
| Theme tint + hub CSS | MEMORY, maybe design-direction | Skip |
| New chart API (existing columns) | MEMORY, maybe feature-log | Skip or L0 |
| Status league copy tweak | MEMORY, STATUS_PAGE_DATA if rules changed | L0 if new panel rules |
| New aggregate table + profile UI | MEMORY, website-data-contract, feature-log | L2: schema + ops simul on work DB + contract § |

*Authority: `PROJECT_BRIEF.md`; Dagh’s latest message wins.*
