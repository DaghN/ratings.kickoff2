# Amiga organizer finish confirm — implementation plan (Jul 2026)

**Status:** **In progress** — slices **0–1 done**; next = slice **2** (Table UI).

**Policy:** [`amiga-organizer-finish-confirm-policy.md`](amiga-organizer-finish-confirm-policy.md) (FO1–FO10).

**Starter:** [`orchestration/agent-handoffs/amiga-organizer-finish-confirm-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-organizer-finish-confirm-STARTER-PROMPT.md).

**Parents:** [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) §6 · [`amiga-live-ops-practice-track.md`](amiga-live-ops-practice-track.md).

---

## 1. Goal

Ship Phase A: Table-tab **confirm finishing order** → write `amiga_tournament_finish_override` → existing Finish promote+finalize reads Tier E → public Finish / Winner / medals correct for kitchens (including WC-stamped RR).

Phase B (finish mode from structure/templates) is sketched only — do not block Phase A.

---

## 2. Non-goals (slices)

- Re-Finish / rewind completed #609 in-browser (FO7).
- Cup template track (L3).
- Expanding WC Tier D heuristics (FO4, FO9).
- Sparse-band Tier E secretary UX (canon-only unless later slice).

---

## 3. Slices

| Slice | Deliverable | Verify | STOP |
|-------|-------------|--------|------|
| **0** | Inventory: where Table Finish posts today; how PHP loads Tier E into `amiga_participation_derive_event_finish_position`; whether overrides can be written mid-running before promote; UX placement options (modal vs panel). Short note in plan changelog or handoff. | Read-only | **Done** — §3a |
| **1** | **Write path** — ops helper to replace full-ladder overrides for `tournament_id` (validate 1..N, one per entrant). Idempotent replace. | Unit / small PHP smoke on work or staging kitchen | **Done** — `amiga_finish_override_write.php` + smoke |
| **2** | **Table UI** — show proposed order (A–D prefill); edit/reorder; Confirm persists Tier E; copy per FO. | Browser on staging/local kitchen | STOP before changing Finish commit transaction |
| **3** | **Gate Finish** — Make official requires confirmed ladder when derive would leave all finishes NULL **or** always requires confirm once (prefer **always confirm** for generated kitchens — lock in slice 0 notes). Wire so finalize sees overrides. | Kitchen Finish → event-stats Finish filled; profile gold for position 1 | WinSCP sync |
| **4** | **Docs / heuristic** — mark FO9 temporary fallback for retirement or “prefill only”; practice track L1 cycle green; policy status → Implemented (Phase A). | UPDATE_DOCS Part A | — |
| **5** | **Phase B sketch only** (optional same chat or later) — finish mode enum on template / format_overrides; map to A–D prefill. No requirement for Phase A close. | Doc only unless Dagh expands | — |

---

## 3a. Slice 0 inventory + locks (2026-07-17)

### Finish post path today

| Piece | Location |
|-------|----------|
| Table tab UI | `amiga/ops/fixtures.php` — `view=table`; primary button **Finish and make official** (`AMIGA_FIXTURE_ORGANIZER_FINISH_LABEL`) |
| POST | `action=reprocess_tournament_derived` + `tournament_id` → same page |
| Handler | `amiga_fixture_reprocess_tournament_derived()` — void remaining scheduled → `amiga_promote_running_tournament()` → `amiga_finalize_tournament()` → lifecycle `completed` |
| Gate | `$tournamentCanMakeOfficial`: running + ≥1 played fixture + not `rating_finalized` + not limbo |
| Confirm today | Browser `confirm()` **only** when unplayed fixtures remain (void warning). **No finishing-order step.** |
| Limbo | If `rating_finalized` while still `running` → refuse Finish; Advanced **Reset incomplete finish** only |

### Tier E load into derive

```text
amiga_finalize_tournament
  → amiga_ops_participation_refresh_tournament / rows_for_tournament
      → amiga_ops_participation_finish_overrides_for_tournament()
            SELECT player_id, event_finish_position
            FROM amiga_tournament_finish_override WHERE tournament_id = ?
      → amiga_ops_participation_standing_rows_for_tournament()
            FROM amiga_tournament_standings (L5 — written during finalize, not broadcast)
      → amiga_participation_derive_event_finish_position(..., $playerIds, $overrides, $isWorldCup)
            A–D proposal → apply_finish_overrides (Tier E wins per player)
            If $overrides non-empty + $playerIds set: sparse rule — non-override entrants → NULL
```

Python parity: `_load_finish_overrides` + `derive_event_finish_position(..., overrides=)` in `player_tournament_participation.py` / `participation_placement.py`.

**Secretary path implication:** Confirm must write a **full ladder** (1..N, every entrant) or none — sparse band stays canon/CLI only (FO2 / honours Tier E).

### Mid-running writeability

| Question | Answer |
|----------|--------|
| Can Tier E rows exist before promote? | **Yes.** Table is L3 ground; FKs only to `tournaments` + `amiga_players`. No lifecycle column / trigger. |
| Organizer writer today? | **None.** Only CLI/oneoffs (e.g. WC Tier E scripts) + import/export packs. |
| Finalize reads them? | **Yes** — same SELECT as above, after promote rebuilds L5 standings. |

Safe to persist confirm **before** Make official; finalize does not need a second write if Tier E already holds the ladder.

### Prefill standings (locked)

| Lock | Decision |
|------|----------|
| **Authority** | Human-confirmed Tier E **freezes** finishing order. Post-promote L5 standings reshuffle must **not** change medals without another confirm. |
| **Prefill UI** | Proposal only: call derive A–D (plus any existing overrides) using **organizer Table / broadcast** standings while running (`amiga_running_tournament_standings_rows` path already feeds Table). Do **not** require L5 `amiga_tournament_standings` to exist before confirm. |
| **After confirm** | Stored Tier E is ground truth at finalize regardless of A–D outcome (including FO9 temporary WC kitchen fallback). |

### Gate Finish (locked for slice 3)

| Lock | Decision |
|------|----------|
| Generated kitchens | **Always require confirm** before Make official (not only when A–D would be all NULL). One mental path: table → confirm order → finish. |
| Imported historical | Out of scope (browser Finish already blocked for `source_id` imports). |

### UX placement options

| Option | Pros | Cons | Slice-2 lean |
|--------|------|------|----------------|
| **A. Inline panel above Finish** on Table tab | Same place as FO5; no second click family; editable list visible next to league table | Longer Table tab | **Prefer** |
| **B. Modal** on Finish click | Familiar “gate before commit” | Easy to treat as another `confirm()`; hard to reorder many players | Secondary if panel cramped |
| **C. Separate sub-view / step** | Clearest wizard | Extra nav; risks burying Finish | Avoid for Phase A |

**Copy:** “Who finished where?” — Confirm saves Tier E; then existing Finish button (or Confirm+Finish combined once gate lands in slice 3).

### Entrants for full ladder

Prefer **registered** `tournament_entrants` (same roster `amiga_fixture_organizer_table_rows` merges with broadcast standings). Align positions `1..N` with that set. Players who only appear in voided fixtures still count as entrants if registered.

### No schema surprise

`amiga_tournament_finish_override` already exists (`sql/ground/002_tournament_finish_override.sql`). Slice 1 = PHP replace helper only — no DDL.

---

## 4. Technical notes (for implementers)

| Topic | Hint |
|-------|------|
| Ground table | `amiga_tournament_finish_override` — see `sql/ground/002_tournament_finish_override.sql` |
| Derive | PHP `amiga_participation_derive_event_finish_position(..., $overrides)`; Python `derive_event_finish_position(..., overrides=)` |
| Load overrides today | `amiga_post_game_participation.php` / Python `_load_finish_overrides` |
| **Write full ladder (slice 1)** | `amiga/ops/includes/amiga_finish_override_write.php` — `amiga_ops_finish_override_replace_full_ladder()`; validate 1..N vs registered entrants; smoke `scripts/oneoff/amiga_finish_override_write_smoke.php` |
| Finish button | `amiga/ops/fixtures.php` Table tab — RTB §6 |
| Prefill | Call same derive used at finalize **before** commit, with current standings (broadcast/official as available while running) |
| Entrants | Prefer all `tournament_entrants` / stage players for full ladder; align with honours “full ladder or none” |
| Completed #609 | Out of scope; new kitchen proves Phase A |

**Standing while running:** Locked in §3a — prefill from organizer/broadcast Table; confirmed Tier E freezes medals through promote+finalize.

---

## 5. Verification checklist (Phase A done)

- [ ] Generated kitchen (no WC stamp): confirm → Finish → Finish column + Winner on event-stats; gold on winner profile.
- [ ] Generated kitchen **with** WC stamp, RR only: same (no empty Finish).
- [ ] Confirm edit changes who gets gold vs prefill.
- [ ] Finish refused or blocked until confirm when required (per slice 3 lock).
- [ ] Advanced Reset incomplete still limbo-only; no new “reset completed” control.
- [ ] No Track C / cup template work in the same slices.

---

## 6. Changelog

| Date | Change |
|------|--------|
| 2026-07-17 | **Slice 1** — `amiga_finish_override_write.php` full-ladder validate + idempotent replace; smoke validation + `--db` rollback on work. Next = slice 2 Table UI. |
| 2026-07-17 | **Slice 0** — inventory + locks (§3a): Finish = `reprocess_tournament_derived`; Tier E readable mid-running / at finalize; always-confirm kitchens; prefill = broadcast Table + Tier E freeze; UX lean = inline panel. Next = slice 1 write path. |
| 2026-07-17 | Initial plan — slices 0–5; Phase A confirm UI; Phase B finish mode deferred. |