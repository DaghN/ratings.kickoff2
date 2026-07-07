# Amiga live ops — practice track (Jul 2026)

**Status:** **Active** — Dagh runs reference tournaments on staging; pain points drive implementation slices.

**Policy parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (lanes A/B/C, ALO1–ALO11, infra menu §12.2).

**Organizer UX reference:** [`archive/orchestration/browser-organizer-workflow-checkpoint.md`](archive/orchestration/browser-organizer-workflow-checkpoint.md).

**Structure model:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (stage → fixture → game).

---

## 1. How we work

1. **Run a reference tournament** on staging (create → play → finalize → website check).
2. **Log every pain point** in §4 below — one row per friction, not per chat.
3. **Pick the next slice** from §5 only when a pain point names a missing capability or UX fix.
4. **Ship the slice** (small, one pain point when possible).
5. **Re-run the same drill** as the smoke test before starting the next slice.

**Do not** implement §12.2 infra phases from the platform doc in numeric order without a drill reason.

**Do not** mix **Track C** canon work (disposition, bulk materialize, prove optimization) in the same session as a live drill.

---

## 2. Reference formats (v1 live product)

Only these two shapes count as live v1 until both drills are boring.

### Ref-League-A — round-robin league

| Field | Value |
|-------|-------|
| Template | `kitchen_marathon` — one `round_robin` stage |
| Players | 4–6 |
| Create | Browser — [`/amiga/ops/fixtures.php`](../../site/public_html/amiga/ops/fixtures.php) (password-gated; **country** = registry select: archive-used + **More countries…**) |
| Host | **Staging** (`ratings.kickoff2.com` / synced ops) |

### Ref-Cup-A — single elimination

| Field | Value |
|-------|-------|
| Template | Smallest KO — `knockout` ties only (4 or 8 players) |
| Create | **CLI first** — `python -m scripts.amiga build-tournament create-group-knockout` or equivalent; browser for play/finalize |
| Unlocks | After Ref-League-A drill is repeatable (Track L step L3) |

---

## 3. Drill checklist (copy per run)

Use one block per drill run. Check staging URL and tournament id when done.

```text
Drill run #: ___
Format:     Ref-League-A / Ref-Cup-A
Host:       staging / local
Date:       ___
Tournament id (after create): ___

[ ] 1. CREATE — name, date, **country from registry select** (used list or More countries…); players by search (not raw ids)
[ ] 2. START  — lifecycle to in-progress; **check Live hub** lists the league
[ ] 3. PLAY    — all fixtures have scores
[ ] 4. MAKE OFFICIAL — Table tab → **Make official** (finalize; N→N+1 ratings + chronology)
[ ] 5. WEBSITE — running: `/amiga/live-tournament.php?id=N`; after complete: historical tournament page + rating LB spot-check
[ ] 6. (Later) DELETE — training event removed without full prove

Pain points this run (→ §4):
-
-
```

**Make official:** Table tab in organizer (or CLI `finalize-tournament`). Commits L5 derived truth.

**WinSCP:** sync `site/public_html/amiga/ops/` before staging drills after local UX fixes.

---

## 4. Pain point log (living)

Add a row **during or immediately after** each drill. This is the backlog input for agents.

| Date | Run # | Format | Step | Pain point (what hurt) | Slice / phase | Status |
|------|-------|--------|------|------------------------|---------------|--------|
| 2026-07-07 | — | — | enter ops | Tournament id field on password gate confused secretaries | Password-only gate; open leagues from Recent list inside | shipped |
| 2026-07-07 | — | League | website | Allowlist blocked public live page until manual config | Remove allowlist; start=public (ALO11) | shipped |
| 2026-07-07 | — | — | enter ops | Staging `/amiga/ops/fixtures.php?once=…` → HTTP 500 before password gate | Fix `amiga_country_slice_game_stats_lib.php` require path | shipped (sync needed) |

**Step** = create · start · play · finalize · website · delete · other

**Slice / phase** = L1 UX · platform §12.2 phase N · ad-hoc — fill when filing work

**Status** = open · in progress · shipped · wontfix

---

## 5. Slice queue (filed from pain log)

Work items land here when a pain point maps to a concrete deliverable. **Empty until first drill.**

| Id | Pain (from §4) | Deliverable | Platform phase | Drill smoke test |
|----|----------------|-------------|----------------|------------------|
| L0-003 | Tournament id on password gate | Password-only gate + in-app Recent leagues | L1 UX | Gate shows password field only |
| L0-002 | Allowlist blocked public live view | Auto public on `running`; **Make official** UX | ALO11 | Start league → visible on Live hub |
| L0-001 | fixtures.php HTTP 500 on load (bad `post_game_outcome` path) | Fix require in `amiga_country_slice_game_stats_lib.php` | ad-hoc | Password gate loads on staging |

---

## 6. Track progress

| Track L step | Description | Done when |
|--------------|-------------|-----------|
| **L0** | First Ref-League-A as-is | §4 has rows; pain log started |
| **L1** | UX fixes for repeatability | Same league runnable same evening |
| **L2** | Ref-League-A ×3 | Lifecycle explainable without code |
| **L3** | Ref-Cup-A path exists | One cup finalized on staging |
| **L4** | Ref-Cup-A ×2 | League + cup feel like one product |
| **L5** | Delete / repair | Training event gone; site coherent; no `prove` |
| **L6** | Ground pack pull | Staging event on laptop |
| **L7** | Media on ran event | YouTube URL on staging tournament |

Current step: **L0** (start today).

---

## 7. Agent checklist (start of slice)

- [ ] Read latest rows in §4 — which pain point does this slice fix?
- [ ] Name reference format + drill step in the handoff / commit message.
- [ ] Slice scope = **one pain point** when possible.
- [ ] After ship: Dagh (or agent) **re-runs the drill** that failed before.
- [ ] Update §4–§6 and platform doc §12.2 row if a phase shipped.

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-07-07 | Drill checklist + §4 — ALO11 start=public; Make official wording. |
| 2026-07-07 | Initial practice track — Ref-League-A / Ref-Cup-A, drill loop, pain log, L0 start. |