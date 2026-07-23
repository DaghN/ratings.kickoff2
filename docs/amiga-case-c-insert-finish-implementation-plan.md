# Amiga Case C insert — mid-history Finish — implementation plan (Jul 2026)

**Status:** **Code shipped (slices 1–5)** Jul 2026 — organizer mid-history Finish on `fixtures.php`; **staged proof pending** (Dagh).

**Policy:** [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md) **AD7** · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §7.3.1 · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) §6.7.

**Parents:** [`amiga-staging-l5-backup-delete-implementation-plan.md`](amiga-staging-l5-backup-delete-implementation-plan.md) (Case C delete — **Complete**) · [`amiga-php-finalize-parity-protocol.md`](amiga-php-finalize-parity-protocol.md) · [`amiga-player-inverse-count-timeline-policy.md`](amiga-player-inverse-count-timeline-policy.md) §5.3 (inverse seed).

**Starter:** [`orchestration/agent-handoffs/amiga-case-c-insert-finish-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-case-c-insert-finish-STARTER-PROMPT.md).

---

## 1. Goal

Ship **mid-history Finish** (“Case C insert”): organizers keep organizer-chosen `tournaments.event_date`; when **later finalized events already exist** in catalog order, **Finish and make official** runs the same repair family as Case C **delete** — but **keeps M** and re-finalizes forward — instead of poisoning present/L5 or blocking the secretary.

**Exit (behavioural):** Tip Finish unchanged; scooped Finish (M before tip) shows loud confirm, phases through truncate → project N → finalize M + forward → BA2 seal; present + inverse oracles green.

---

## 2. Product rules (locked)

| Id | Rule |
|----|------|
| **CI1** | Organizers **may** set `event_date` at create (played Saturday, Finish Monday). Wall-clock Finish time does **not** define catalog order. |
| **CI2** | Another kitchen finishing first and becoming tip is **normal** — not an error to refuse. |
| **CI3** | **No cheap tip-only guard** (block Finish when not chrono-last). Wrong fix. |
| **CI4** | **Automatic on organizer Finish** when `forward[]` non-empty — loud confirmation listing **k** later official events that will be recomputed; not admin-only. |
| **CI5** | **Catalog chrono authority** — ladder/TT truth follows `(event_date, chrono, id)`, not `game_date` append order. |
| **CI6** | **Ground of later tips is sacred** — strip **derived** + reset `rating_finalized`; do **not** delete forward ground. |
| **CI7** | **Inverse changelog seed** mandatory on every forward re-finalize (`amiga_ops_seed_inverse_counts_from_changelog`) — same Jul 23 lesson as Case C delete. |
| **CI8** | **BA2 seal** after successful mid-history Finish (tip-changing / history-changing). |
| **CI9** | **Phased HTTP** (~30s gateway) — same habit as admin Case C project/finalize chain; not one long request. |
| **CI10** | **Concurrency** — same finalize lock family; refuse overlapping Finish / Case B/C / insert repair on staging. |
| **CI11** | **Mid-history `chrono`** — integer forward `+1` bump on ground after **N** (exclude M); M takes opened slot; delete symmetric `-1`. Policy [`amiga-chrono-integer-policy.md`](amiga-chrono-integer-policy.md). Legacy slot/midpoint retired. |

---

## 3. Detection (mid-history Finish)

Run **after** standard Finish preconditions pass (running lifecycle, Tier E confirmed, ≥1 played fixture) and **before** poisoned finalize.

### 3.1 Compute M’s catalog tuple

1. Load M (`tournaments` row).
2. If `chrono` is null and `event_date` set → assign via `amiga_promote_next_tournament_chrono()` (same as promote) **before** comparing — promote step will do this anyway; detection must match post-promote order.
3. Tuple: `(event_date, chrono, id)`.

### 3.2 List `forward[]`

`forward[]` = all tournaments with `rating_finalized = 1` strictly **after** M in catalog order:

- Reuse `amiga_case_c_list_tournaments_after($con, $mTuple, true)`.

### 3.3 Branch

| `forward[]` | Path |
|-------------|------|
| **empty** | **Tip Finish** — today’s path: void scheduled → promote → finalize → lifecycle completed → BA2 seal. **No UI change.** |
| **non-empty** | **Case C insert** — require `confirm_mid_history_finish=1` (POST); run insert pipeline (§4). |

### 3.4 Prior N

`N` = chrono-prior **finalized** event before M:

- Reuse `amiga_case_c_find_prior_finalized($con, $mTuple)`.
- If null → **refuse** with honest message (cannot project-present-at without N — same as Case C delete).

### 3.5 Helper (new)

```php
/**
 * @return array{
 *   is_mid_history: bool,
 *   m: array{id:int, name:string, event_date:?string, chrono:?float},
 *   n: ?array{id:int, name:string, event_date:?string, chrono:?float},
 *   forward: list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>,
 *   forward_count: int
 * }
 */
function amiga_case_c_insert_finish_probe(mysqli $con, int $tournamentId): array
```

Place in `ops/modules/insert_finalized_mid_tournament.php` (new) or shared `delete_finalized_mid_tournament.php` Case C lib section.

**Dry-run:** probe only; no mutations. Used for Table tab confirm copy before POST.

---

## 4. Pipeline / verbs (reuse Case C delete)

Mirror Case C delete forward half; **do not delete M ground**.

### 4.1 Sequence

```text
1. Probe → if mid-history and !confirmed → stop (UI confirm)
2. Void remaining scheduled (existing Finish)
3. Truncate derived chrono > N     → amiga_ops_truncate_derived_after($con, $n)
4. Reset each id in forward[]      → amiga_case_c_reset_for_refinalize($con, $id)
   (M usually has no derived yet; truncate may include M if half-poisoned)
5. Promote M (L3 games)            → amiga_promote_running_tournament() if no games yet
6. Project present at N (phased)   → amiga_ops_project_present_at_phase() × 3
7. Finalize chrono order:
     a. M                           → amiga_finalize_tournament($con, $mId)
     b. each remaining forward tip  → amiga_ops_refinalize_forward_one() one per HTTP
8. Lifecycle M → completed
9. BA2 seal                        → amiga_backup_seal_write_from_config(..., reason: case_c_insert)
```

### 4.2 New verbs

| Verb | Guard | Effect | Status |
|------|-------|--------|--------|
| `insert-finish-probe` | Running M; not `rating_finalized` | Read-only §3 | **Planned** |
| `insert-finish-prepare` | Mid-history + confirmed | Steps 3–5 in one txn (truncate, reset forward, promote M) | **Planned** |
| `insert-finish-project` | After prepare | Phased `project-present-at` at N (reuse admin Case C project params) | **Planned** — may share `case_c_project` handler with `mode=insert` |
| `insert-finish-finalize-one` | After project | Finalize one id (M first, then forward chain) | **Planned** — reuse `amiga_ops_refinalize_forward_one`; M first call uses same finalize |
| `insert-finish-complete` | All finalized | Lifecycle complete for M + BA2 seal | **Planned** |

**Module:** `site/public_html/amiga/ops/modules/insert_finalized_mid_tournament.php`

**CLI (optional slice):** `run_process_game.php insert-finish-prepare --tournament-id=M` for local smoke — mirror admin.

### 4.3 Idempotency / limbo

| Situation | Rule |
|-----------|------|
| M already has `amiga_games` but not `rating_finalized` | **Prepare** must not double-promote; finalize M only |
| M `rating_finalized=1` but lifecycle `running` | Existing limbo path — **not** insert; use Reset incomplete finish |
| Half-done insert (truncated, not finalized) | **Explicit repair** — admin cockpit retry from project/finalize phases (same as Case C admin); do not silent re-run prepare |
| Overlapping ops | Refuse if `GET_LOCK('amiga_finalize_tournament')` or Case C delete lock held |

### 4.4 What we do **not** rewrite

- `project_present_at.php` — call as-is
- `amiga_finalize_tournament()` — call as-is (+ inverse seed already in bootstrap)
- Case C truncate/reset helpers — call as-is
- Admin Case C delete module — **no** ground delete branch for insert

---

## 5. Finish UI (organizer Table tab)

### 5.1 When to show mid-history confirm

On **GET** Table tab, when:

- `lifecycle_status = running`
- Tier E confirmed
- `amiga_case_c_insert_finish_probe()` → `forward_count >= 1`

### 5.2 Copy (locked v1)

**Warning block** above Finish button (amber, not error):

> **This league’s date places it before {forward_count} event(s) already made official** ({names truncated to 3 + “and N more”}).  
> Finishing will **recompute ratings and standings** for those later events so the ladder matches catalog order.  
> This may take a few minutes — stay on this page until it completes.

**Browser confirm** on submit (in addition to voided-fixture confirm if any):

> Recompute {forward_count} later official event(s) and finish this league? This cannot be undone from the browser.

**POST fields:**

- `confirm_mid_history_finish=1` (hidden, set only after JS confirm or second-step form)
- `action=reprocess_tournament_derived` (unchanged) **or** phased `action=insert_finish_*` once implemented

### 5.3 Phased progress (organizer)

**v1:** auto-continue via hidden form + meta refresh or small inline JS (same pattern as admin `caseCNeedNext` on `run_backup_ko2amiga.php`):

| Stage | User sees |
|-------|-----------|
| prepare | “Preparing catalog repair…” |
| project ×3 | “Rebuilding present tables (1/3)…” |
| finalize M | “Making this league official…” |
| finalize forward ×k | “Refreshing later event {i}/{k}: {name}…” |
| seal | “Saving backup…” |
| done | Success flash + redirect Table (completed) |

**Do not** send secretary to admin cockpit on happy path.

### 5.4 Tip Finish unchanged

When `forward_count = 0`, existing copy + single POST — **no** mid-history block.

### 5.5 Files

| File | Change |
|------|--------|
| `fixtures.php` | Probe on GET; confirm UI; phased POST handlers |
| `insert_finalized_mid_tournament.php` | New module |
| `amiga_backup_seal_lib.php` | Accept `reason: case_c_insert` (if reason enum exists) |

---

## 6. Proof gates

Hard **PASS/FAIL** before ship. Run on **`ko2amiga_work`** (local) then repeat smoke on **staged** `ko2amiga_db`.

### 6.1 Smoke A — tip Finish unchanged

| Step | Gate |
|------|------|
| Fingerprint DB before/after tip Finish on kitchen with `event_date` = tip day | `compare_work_vs_staging_cmp.py` or simul-oracle fingerprint **unchanged** vs pre-slice baseline for non-tip tables |
| Seal | BA2 seal written; `inverse_count` part present |

### 6.2 Smoke B — scooped Finish (1–2 forward)

Setup:

1. Create M with `event_date` **before** current tip (e.g. yesterday).
2. Finish tip kitchen T first (normal).
3. Enter results on M; confirm Tier E.

Act: Finish M with mid-history confirm.

| Gate | Oracle |
|------|--------|
| M official + lifecycle completed | `rating_finalized=1`, `lifecycle_status=completed` |
| Forward tips still official | each `forward[]` id `rating_finalized=1` |
| Present parity | `scripts/oneoff/compare_work_vs_staging_cmp.py` present tables **0 mismatches** vs work oracle after same ground on fresh simul **or** side-pull cmp |
| Inverse | `verify-inverse-count-changelog` green; present inverse pointer = changelog |
| Community / realm | Diagnose present green on backup page |

### 6.3 Thorough C — ~10 forward (optional pre-prod)

Replay Jul 23 Case C delete scenario **in reverse**:

- Restore seal with full forward chain (e.g. includes M=#16).
- Create running kitchen dated before M=#16; Finish with insert path.
- Expect **10+** forward re-finalize steps; phased HTTP completes without gateway timeout.
- Same oracles as 6.2 + export round-trip seal includes `inverse_count` part.

### 6.4 Regression guards

| Guard | Fail if |
|-------|---------|
| Inverse seed | Any forward finalize without changelog seed poisons present (Jul 23 bug) |
| Double promote | Second prepare on same M inserts duplicate games |
| Tip refuse | Tip Finish blocked when `forward_count=0` |
| Cheap guard | Finish refused solely because M is not chrono-last **without** offering insert repair |

### 6.5 Proof doc

Record runs in new section of [`amiga-export-inverse-roundtrip-test-plan.md`](amiga-export-inverse-roundtrip-test-plan.md) **Phase D — Case C insert** (or sibling `amiga-case-c-insert-finish-test-plan.md` if Dagh prefers split).

---

## 7. Implementation slices

| Slice | Deliverable | Verify |
|-------|-------------|--------|
| **0** | This plan + policy locks (AD7, §7.3.1, RTB §6.7) | Read-only |
| **1** | `amiga_case_c_insert_finish_probe()` + unit-style local probe on work | Probe matches manual SQL chrono list |
| **2** | `insert-finish-prepare` verb (truncate, reset, promote) | Dry-run + work smoke B setup through prepare |
| **3** | Phased project + finalize-one HTTP in `fixtures.php` | Smoke B complete |
| **4** | Organizer confirm UI + auto-continue progress | Secretary path without admin |
| **5** | BA2 seal + thorough C optional + docs/feature-log **Implemented** | Smoke A+B green; UPDATE_DOCS |

**One slice per chat** unless Dagh says continue.

---

## 8. Non-goals (v1)

| Out | Why |
|-----|-----|
| Cheap tip-only refuse | CI3 |
| Admin-only insert path as **only** path | CI4 — admin retry OK as secondary |
| Full `simul`/`prove` as daily Finish | ALO3 |
| Rewrite `event_date` to today | CI1 |
| Synthetic `game_date` bands | CI11 — document only |
| Deep mid-2000s UX polish | Phased progress sufficient |

---

## 9. Changelog

| Date | Change |
|------|--------|
| 2026-07-23 | **Code shipped** — `insert_finalized_mid_tournament.php` + phased Finish on `fixtures.php` (slices 1–5); staged smoke D pending. |
| 2026-07-23 | **Design locked** — policy AD7 + live-ops §7.3.1 + RTB §6.7; verb/UI/proof specs (slices 0). |