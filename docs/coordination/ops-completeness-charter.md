# Ops completeness — charter (Phase 0)

**Status:** Adopted Jun 2026 (post parity audit). **Simul sign-off:** staging `kooldb1` verify PASS + visual parity vs frozen dev (Jun 2026). **Live PHP ops cutover:** **Done (2026-07-18)**.  
**Audience:** Dagh, Steve, Cursor agents.  
**Authority for behaviour:** [`website-data-contract.md`](../website-data-contract.md). This doc defines **how we get there**, not table-by-table rules.

**Companion docs:**

| Doc | Role |
|-----|------|
| [`ops-orchestration-adr.md`](ops-orchestration-adr.md) | **When / where / how** — midnight UTC tick, simul interleaving, Steve surface |
| [`ops-derived-data-registry.md`](ops-derived-data-registry.md) | **What** — inventory of every derived artifact (DDR) |
| [`parity-audit-backlog.md`](parity-audit-backlog.md) **AUD-004** | Closed Jun 2026 — audit anchor for this programme |

---

## 1. Problem statement

**Live prod (since 2026-07-18):** Steve writes **ground truth** → one **`ProcessCompletedGame`** per rated game → **one calendar job** per UTC day boundary for period/league/day-close derived data. **C++ derived retired.**

**Simul / Mode C:** Per-game PHP ops and **Mode C** simul (`run_ops_sim` + `FinalizeUtcDay`) are **shipped and signed off on staging** ([`parity-audit-backlog.md`](parity-audit-backlog.md) **AUD-004** closed). Default **Mode A** (`replay-to`) still does **not** run league finalize or day-close keys — use Mode C for ops-complete replay. Batch rebuild SQL remains **repair only**, not simul definition of done ([`work-db-prepare.md`](../work-db-prepare.md) §5).

**Programme goal:** Every derived artifact that **must** update in daily ops **does** update — in **live**, in **simul**, and in **docs/code** — without relying on batch rebuilds on the happy path.

---

## 2. Definition of done

### 2.1 Live (Steve / cron)

| Check | Criterion |
|-------|-----------|
| Per game | `CMD=ProcessCompletedGame` after each ground-truth insert; exit codes per [`ops-dispatch.md`](ops-dispatch.md) |
| Per UTC day | **One** scheduled call: `CMD=FinalizeUtcDay` (planned — see ADR); internal steps in fixed order |
| Register | `CMD=ProcessPlayerRegistered` on new account (lobby milestone) |
| No batch on happy path | Archived `*_rebuild.sql`, `rebuild-all`, retired dev batch PS1 are **parity / repair** only |

### 2.2 Simul (work / staging)

| Check | Criterion |
|-------|-----------|
| Orchestrator | Single documented command replays **games + UTC day ticks** using the **same** module functions as live |
| Equivalence | After game *N* + all day ticks through that date, derived state matches what live would have produced from the same ground truth |
| “Simul complete” | Checklist (contract six-value SQL + league awards present + day-close keys + register seed where needed) — see DDR § Verification |
| **Work hygiene** | **`ko2unity_work` / `kooldb1`:** derived truth filled **only** by prepare + `run_ops_sim.php`. Wrong state → **`zero-derived` → simul again** — not batch repair ([`work-db-prepare.md`](../work-db-prepare.md) §1.5) |

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
| **2 — Orchestration** | Lock midnight design | **`FinalizeUtcDay` shipped** — **Open:** Steve cron on staging/prod |
| **3 — Gap closure** | Code | **Done** for simul happy path — `FinalizeUtcDay`, timeline sim, league event milestones in day tick |
| **4 — Steve / cutover** | Handoff | **Done (2026-07-18)** — live PHP ops; see [`staging-work-steve-brief.md`](staging-work-steve-brief.md) · [`post-dagh-live-story.md`](../../site/public_html/ops/docs/post-dagh-live-story.md) |
| **5 — Validation** | Sign-off | **Done (staging):** `run_verify_ops_sim` + visual parity. **Ongoing:** Live-shaped games, optional `ab-post-game` |

**Implementation priority inside phase 3** (highest leverage):

1. Sim interleaves **league finalize** at UTC boundaries (honours + totals + `league_wins_*`).
2. **League event milestones** (~20 keys) chained **after** awards commit.
3. **`perfect_day` / `nightmare_day`** day-close CMD.
4. ~~**`entered_arena` in timeline sim**~~ — **satisfied by prepare §4.7** (lobby seed from `JoinDate`); live `ProcessPlayerRegistered` when wired.
5. Retire batch from default runbooks (keep labelled for repair).

---

## 6. Testing order

| Step | Who | Status |
|------|-----|--------|
| 1 | Dagh / agent — `prepare` on local-work | **Done** (ongoing per cycle) |
| 2 | Dagh / agent — short smoke / bisect | **Done** |
| 3 | Dagh / agent — `run_verify_ops_sim` after smoke | **Done** locally |
| 4 | Fix proven ops failures | **Done** (UTC/league finalize, milestone rules `a3cb1c0`) |
| 5 | Steve — staging simul + verify | **Done** — `kooldb1`, verify **0 fail / 0 warn** |
| 6 | Dagh — visual parity staging vs frozen dev | **Done** Jun 2026 |
| **7** | **Steve** — optional staging Live-shaped experiments | **Optional / deferred** (not required for prod) |
| **8** | Both — prod cutover | **Done (2026-07-18)** |

Detail for steps 1–6: [`ops-simul-runbook.md`](ops-simul-runbook.md) § Verify. **Do not** use batch rebuild as sign-off.

**Not recommended:** Steve multi-hour replay while local **six-value** or “won’t run” issues remain; **never** use legacy batch scripts as the definition of simul complete ([`ops-simul-runbook.md`](ops-simul-runbook.md) § What verify is not).

**Prepare parity** (ground `idA`/`idB`/`Date` vs baseline) is a **separate** gate inside `run_prepare.php` — not `run_verify_ops_sim`.

---

## 7. Deferred maintenance

| Item | When |
|------|------|
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
| Jun 2026 | **One** Steve midnight CMD (`FinalizeUtcDay`) with ordered internal steps — see ADR |
| Jun 2026 | Batch rebuilds = **dev parity / repair only**, not simul definition of done |
| Jun 2026 | **`run_verify_ops_sim`** = read-only post-sim SQL gate; does not run simul or batch; short-run league FAIL is expected; frozen-dev parity is a separate step after Steve **74879** |
| Jun 2026 | **Staging simul signed off** — Steve verify PASS on `kooldb1`; Dagh visual parity vs frozen dev; **AUD-004/005** closed in parity backlog; two milestone fixes (`clean_sheet_spread`, `giant_slayer`) |
