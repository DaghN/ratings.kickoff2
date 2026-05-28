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
| Status hub panels / league | `docs/STATUS_PAGE_DATA.md` |
| Player profile layout | `docs/player-profile-feast.md` |
| Milestones feature (phases, catalog, tier plan) | `docs/milestones-project.md`, `docs/milestones-product-spec.md`, `docs/milestones-ideas-catalog.md` |
| Hub tabs / IA | `docs/hub-ia-agreement.md` |
| Visual identity / theme | `docs/design-direction.md` |
| Ladder replay scope / reset rules | `docs/replay-v1-scope-and-reset.md` (rare; big replay changes only) |
| Hall of Fame record C++ / staging defects | `docs/coordination/records-post-game-exception.md`, `docs/staging-post-game-record-defects.md` (when records behavior changes) |

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
| **L1** | Schema; no backfill yet | schema-register + `schema/migrations/` |
| **L2** | Schema + REP backfill | schema-register + replay-register + contract post-game § |
| **L4** | Staging-tested; cutover ready | feature-log: schema + REP done on staging |
| **L5** | Prod done | feature-log **Prod live** + registers closed |

Do **not** add `cpp-snippets/` or PG-NNN rows. Post-game behavior → [`website-data-contract.md`](website-data-contract.md). Records exception only: [`records-post-game-exception.md`](coordination/records-post-game-exception.md).

Details: [`prod-coordination.md`](prod-coordination.md#prod-readiness-levels).

### B3. Update registers (L1+ only)

| If… | Update |
|-----|--------|
| SQL migration | `schema/migrations/NNN_….sql` + [`schema-register.md`](coordination/schema-register.md) |
| Replay | `scripts/ladder/` + [`replay-register.md`](coordination/replay-register.md) |
| Post-game rules (contract) | Extend [`website-data-contract.md`](website-data-contract.md) § for the table; records only → [`records-post-game-exception.md`](coordination/records-post-game-exception.md) |
| Periodic job | [`periodic-register.md`](coordination/periodic-register.md) |
| One-off script | [`one-off-register.md`](coordination/one-off-register.md) |

Set **feature-log** level column when known.

### B4. Migration note to Dagh (if L≥2)

Short paragraph: level, registers touched, whether local REP was run. Prod C++ is contract-driven at cutover — not a standing snippet task.

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
| New aggregate table + profile UI | MEMORY, website-data-contract, feature-log | L2: schema + REP + contract § |

*Authority: `PROJECT_BRIEF.md`; Dagh’s latest message wins.*
