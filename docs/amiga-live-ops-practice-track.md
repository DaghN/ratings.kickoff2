# Amiga live ops — practice track (Jul 2026)

**Status:** **Active** — secretary clarity via **serial feedback** (one issue at a time). Not an open pain inventory.

**Policy parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (lanes A/B/C, ALO1–ALO11, infra menu §12.2).

**Organizer UX reference:** [`archive/orchestration/browser-organizer-workflow-checkpoint.md`](archive/orchestration/browser-organizer-workflow-checkpoint.md) (ideas menu — not a sprint backlog).

**Structure model:** [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (stage → fixture → game).

---

## 1. How we work — serial feedback

**Queue depth = 1.** Do not collect a living list of everything that feels imperfect.

```text
1. Do ONE step of the happy path (create · players · start · play · table · Make official · website)
2. Give ONE piece of feedback (one sentence: what blocked or felt wrong)
3. Fix ONLY that (one chat / one slice)
4. Re-do the same step (confirm the fix)
5. Only then: next step, or next one feedback
```

**Dagh prompt shape:**

> I tried **[step]**. Feedback: **[one issue]**. Fix only that.

After the fix:

> OK / still broken. Next: **[same step again]** or **[next step]**.

### Raise feedback when

- It **blocks** the step, or
- You are **unsure what to do next**, or
- You **explicitly** want that one thing improved before continuing

### Do not raise (yet)

Cosmetic “a bit ugly” items you can work around — finish the step first. Purist UI forks (naming edge cases, CR-9 country polish, imprint, Lane C) stay deferred until a serial cycle names them.

### Agent rules

- **One feedback → one fix.** No “while we’re here” extras.
- Do **not** implement §12.2 infra phases in numeric order without a named feedback cycle.
- Do **not** mix **Track C** canon (WC materialize, imprint P2–P3, chronologies) in the same session as a live secretary cycle.
- After ship: Dagh re-does the **same step** before opening a new cycle.

---

## 2. Reference formats (v1 live product)

Only these two shapes count as live v1 until both are boring.

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
| Unlocks | After Ref-League-A is repeatable (Track L step L3) |

---

## 3. Happy-path steps (work in order)

Use these as the **step** name in feedback. Advance only when the current step is boring enough to continue without getting stuck.

| # | Step | Success looks like |
|---|------|--------------------|
| 1 | **CREATE** | Name, date, country from registry; players by search (not raw ids); optional newcomer create |
| 2 | **START** | Lifecycle in progress; league listed on Live hub |
| 3 | **PLAY** | Results on fixtures only; `amiga_games` count for this id stays **0** while running |
| 4 | **TABLE** | Broadcast standings match expectation |
| 5 | **MAKE OFFICIAL** | Table tab → **Finish and make official** (promote + finalize) |
| 6 | **WEBSITE** | Running page while live; historical tournament + rating spot-check after official |
| 7 | **CLEANUP** | Abandoned never-official workspace removable without orphan L3 games |

**Make official:** Table tab (promote → `amiga_games`, then `finalize_tournament`). CLI: `python -m scripts.amiga finalize-tournament --tournament-id N`. See [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md).

**WinSCP:** sync `site/public_html/amiga/ops/` before staging cycles after local UX fixes.

---

## 4. Active cycle (queue depth 1)

Only the **current** cycle is live. When fixed and re-checked, clear or archive the row — do not grow an open backlog here.

| Field | Value |
|-------|-------|
| **Status** | idle — awaiting Dagh’s next one-step feedback |
| **Format** | Ref-League-A |
| **Step** | — |
| **Feedback** | — |
| **Slice** | — |

When a cycle is open, agents read **this table only** — not historical notes — as the work order.

---

## 5. Track progress (milestones)

Milestones are **gates**, not a parallel backlog. Advance when Dagh says the prior gate feels boring enough — not by inventorying UX.

| Track L step | Description | Done when |
|--------------|-------------|-----------|
| **L0** | First Ref-League-A as-is | One full create → Make official → website on staging |
| **L1** | UX only from serial feedback on that path | Same league runnable same evening without getting stuck |
| **L2** | Ref-League-A ×3 | Lifecycle explainable without opening PHP |
| **L3** | Ref-Cup-A path exists | One cup finalized on staging |
| **L4** | Ref-Cup-A ×2 | League + cup feel like one product |
| **L5** | Delete / repair | Training event gone; site coherent; no `prove` |
| **L6** | Ground pack pull | Staging event on laptop (finer than full pull) |
| **L7** | Media on ran event | YouTube URL on staging tournament |

**Current gate:** **L0** — start with CREATE on staging; one feedback at a time.

**Explicit defer until a cycle names them:** structure imprint P2–P3, WC materialize, Lane C media DDL, CR-9 country polish, player-create phase 2, cups before L2 boring.

---

## 6. Agent checklist (start of slice)

- [ ] Read §4 **Active cycle** — is there exactly one open feedback?
- [ ] Scope = **that feedback only** (format + step named in commit / handoff).
- [ ] No Track C / no second UX issue in the same chat.
- [ ] After ship: Dagh re-does the **same step**.
- [ ] Update §4 (clear or set next idle) + §5 if a Track L gate advanced; platform §12.2 only if an infra phase shipped.

---

## 7. Shipped history (archive — not a backlog)

Closed cycles from early drills. Do **not** treat as open work.

| Date | Step | Feedback | Outcome |
|------|------|----------|---------|
| 2026-07-07 | enter ops | Tournament id on password gate confused secretaries | Password-only gate + Recent leagues inside — shipped |
| 2026-07-07 | website | Allowlist blocked public live page | Start=public (ALO11) — shipped |
| 2026-07-07 | enter ops | Staging fixtures.php HTTP 500 before gate | Require path fix — shipped |

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-07-16 | **Serial feedback** replaces pain-log inventory — queue depth 1; §4 active cycle; shipped rows → archive; agent rules updated. |
| 2026-07-07 | **RTB shipped** — play = fixture-only scores; Make official = promote + finalize. |
| 2026-07-07 | Drill checklist + ALO11 start=public; Make official wording. |
| 2026-07-07 | Initial practice track — Ref-League-A / Ref-Cup-A, pain log (retired 2026-07-16), L0 start. |