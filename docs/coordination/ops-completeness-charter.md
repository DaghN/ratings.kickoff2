# Ops completeness — charter (Phase 0)

**Status:** Adopted Jun 2026 (post parity audit).  
**Audience:** Dagh, Steve, Cursor agents.  
**Authority for behaviour:** [`website-data-contract.md`](../website-data-contract.md). This doc defines **how we get there**, not table-by-table rules.

**Companion docs:**

| Doc | Role |
|-----|------|
| [`ops-orchestration-adr.md`](ops-orchestration-adr.md) | **When / where / how** — midnight UTC tick, simul interleaving, Steve surface |
| [`ops-derived-data-registry.md`](ops-derived-data-registry.md) | **What** — inventory of every derived artifact (DDR) |
| [`parity-audit-backlog.md`](parity-audit-backlog.md) **AUD-004** | Symptom + audit finding driving this programme |

---

## 1. Problem statement

**Live prod (target):** Steve writes **ground truth** → one **`ProcessCompletedGame`** per rated game → **one calendar job** per UTC day boundary for period/league/day-close derived data.

**Today:** Per-game PHP ops is largely shipped; **periodic** and **simul** paths are incomplete. Default simul (`replay-to` / Mode A) does **not** run league finalize, day-close milestones, or full league milestone keys. Batch rebuild SQL is used as a **shortcut**, which is **not** the definition of “simul complete” ([`work-db-prepare.md`](../work-db-prepare.md) §5, **AUD-004**).

**Programme goal:** Every derived artifact that **must** update in daily ops **does** update — in **live**, in **simul**, and in **docs/code** — without relying on batch rebuilds on the happy path.

---

## 2. Definition of done

### 2.1 Live (Steve / cron)

| Check | Criterion |
|-------|-----------|
| Per game | `CMD=ProcessCompletedGame` after each ground-truth insert; exit codes per [`ops-dispatch.md`](ops-dispatch.md) |
| Per UTC day | **One** scheduled call: `CMD=FinalizeUtcDay` (planned — see ADR); internal steps in fixed order |
| Register | `CMD=ProcessPlayerRegistered` on new account (lobby milestone) |
| No batch on happy path | `player_milestones_rebuild.sql`, `rebuild-all`, `rebuild_website_derived_data_local.ps1` are **parity / repair** only |

### 2.2 Simul (work / staging)

| Check | Criterion |
|-------|-----------|
| Orchestrator | Single documented command replays **games + UTC day ticks** using the **same** module functions as live |
| Equivalence | After game *N* + all day ticks through that date, derived state matches what live would have produced from the same ground truth |
| “Simul complete” | Checklist (contract six-value SQL + league awards present + day-close keys + register seed where needed) — see DDR § Verification |

### 2.3 Documentation & inventory

| Check | Criterion |
|-------|-----------|
| DDR | Every contract-derived artifact has a row: trigger, CMD, sim/live, batch fallback |
| Registers | [`ops-dispatch.md`](ops-dispatch.md), [`periodic-register.md`](periodic-register.md), [`replay-register.md`](replay-register.md) aligned with DDR |
| Gaps closed | **AUD-004** resolution recorded in parity backlog |

---

## 3. Non-goals (explicit)

| Item | Decision |
|------|----------|
| **Rating fade (PER-001)** | **Retired — not in scope.** Product intent is to run **without** rating fade. Do not implement fade in PHP ops or simul. A **project-wide doc sweep** to remove or mark stale PER-001 references is **deferred** (see §6). |
| Byte-for-byte **dev DB parity** | Work + PHP ops = forward truth; frozen dev is reference only |
| Second authority (Python) for prod | Python remains oracle/checkpoint tooling until PHP sim is trusted |
| Physical DB split (facts vs derived tables) | Deferred per [`ladder-ops-platform.md`](../ladder-ops-platform.md) |

---

## 4. Four tracks (how we work)

Run **A + B** in parallel first (reading). **C** audits code against DDR. **D** ties website read paths and sim-complete gates.

| Track | Question | Primary output |
|-------|----------|----------------|
| **A — Derived Data Registry** | What exists? Who updates it? Ops wired? | [`ops-derived-data-registry.md`](ops-derived-data-registry.md) |
| **B — Orchestration** | WHEN / WHERE / HOW for calendar-bound work? | [`ops-orchestration-adr.md`](ops-orchestration-adr.md) |
| **C — Implementation audit** | Does code match contract at first sight? | DDR `code_sanity` column + discrepancy register |
| **D — Surface & sim contract** | Do pages lie when derived is empty? Is simul “done”? | [`ops-simul-runbook.md`](ops-simul-runbook.md) + DDR § Verification |

**Do not** fix code at scale before DDR v1 exists — league/milestone ordering gets rediscovered otherwise.

---

## 5. Phased roadmap

| Phase | Focus | Deliverable |
|-------|--------|-------------|
| **0 — Charter** | Align intent | **This doc** + ADR + DDR template |
| **1 — DDR v1** | Inventory | **Done (Jun 2026)** — [`ops-derived-data-registry.md`](ops-derived-data-registry.md) |
| **2 — Orchestration** | Lock midnight design | **`FinalizeUtcDay` shipped** — Steve cron cutover + full-history orchestrator CLI still open |
| **3 — Gap closure** | Code | **Shipped:** `FinalizeUtcDay` + timeline sim. **Open:** Steve cron cutover; optional `replay-to` → timeline wrapper; rating-fade doc sweep |
| **4 — Steve / cutover** | Handoff | Update [`staging-work-steve-handoff.md`](staging-work-steve-handoff.md), cutover packet |
| **5 — Validation** | Ongoing | `run_verify_ops_sim.php` (read-only) + `ab-post-game`; see §6 testing order |

**Implementation priority inside phase 3** (highest leverage):

1. Sim interleaves **league finalize** at UTC boundaries (honours + totals + `league_wins_*`).
2. **League event milestones** (~20 keys) chained **after** awards commit.
3. **`perfect_day` / `nightmare_day`** day-close CMD.
4. ~~**`entered_arena` in timeline sim**~~ — **satisfied by prepare §4.7** (lobby seed from `JoinDate`); live `ProcessPlayerRegistered` at cutover only.
5. Retire batch from default runbooks (keep labelled for repair).

---

## 6. Testing order (before Steve full simul)

| Step | Who | Action |
|------|-----|--------|
| 1 | Dagh / agent | `prepare` on **local-work** (or private `kooldb1` without broadcast) |
| 2 | Dagh / agent | **Short** proof: `run_timeline_sim` **`--stop-at`** (bisect, seconds–minutes) **or** smoke `run_ops_sim --until-game-id 500` (tens of minutes) — **not** local full history |
| 3 | Dagh / agent | **Optional** `run_verify_ops_sim` — read-only SQL; see [`ops-simul-runbook.md`](ops-simul-runbook.md) § Verify. Trust processed + six-value; **league-awards FAIL after finalize + standings is a bug**. **Do not** run batch rebuilds because verify failed |
| 4 | Dagh / agent | Fix **proven** ops failures (root cause in PHP sim path); repeat 2–3. Site spot-check + optional `ab-post-game` when chunk is large enough |
| 5 | Steve | **One** staging simul to **`--until-game-id 74879`** + nightly `FinalizeUtcDay` when Dagh says ready ([`steve-nightly-ops.md`](steve-nightly-ops.md)) |
| 6 | Both | Parity / sign-off: spot SQL, site, `ab-post-game` — **not** “batch until verify passes” |

**Not recommended:** Steve multi-hour replay while local **six-value** or “won’t run” issues remain; **never** use legacy batch scripts as the definition of simul complete ([`ops-simul-runbook.md`](ops-simul-runbook.md) § What verify is not).

**Prepare parity** (ground `idA`/`idB`/`Date` vs baseline) is a **separate** gate inside `run_prepare.php` — not `run_verify_ops_sim`.

---

## 7. Deferred maintenance

| Item | When |
|------|------|
| **Rating fade doc sweep** | Remove or neutralize PER-001 / “hourly fade” in [`periodic-register.md`](periodic-register.md), [`prod-coordination.md`](../prod-coordination.md), cutover templates, etc. Charter records decision; sweep is editorial, not blocking ops build |
| **DDR exhaustive rows** | Phase 1 after template lands |
| **GST ratio columns** | Intentionally not post-game — do not chase as ops gaps |

---

## 8. Related registers & code

| Area | Path |
|------|------|
| Dispatch | `site/public_html/ops/dispatch.php`, `includes/ops_dispatch.php` |
| Per game | `modules/process_completed_game.php` |
| League periodic | `modules/finalize_league_period.php`, `includes/league_standings.php` |
| Timeline sim (partial) | `modules/timeline_sim.php`, `run_timeline_sim.php` |
| Contract | [`website-data-contract.md`](../website-data-contract.md) § Post-game derived-data |
| Discrepancies | [`post-game-contract-vs-oracle-discrepancies.md`](post-game-contract-vs-oracle-discrepancies.md) |
| Modes A/B/C | [`post-game-php-development.md`](../post-game-php-development.md) §2.2 |

---

## 9. Decision log (charter level)

| Date | Decision |
|------|----------|
| Jun 2026 | Ops completeness programme adopted; **AUD-004** is the audit anchor |
| Jun 2026 | **No rating fade** in target ops |
| Jun 2026 | **One** Steve midnight CMD (`FinalizeUtcDay`) with ordered internal steps — see ADR |
| Jun 2026 | Batch rebuilds = **dev parity / repair only**, not simul definition of done |
| Jun 2026 | **`run_verify_ops_sim`** = read-only post-sim SQL gate; does not run simul or batch; short-run league FAIL is expected; frozen-dev parity is a separate step after Steve **74879** |
