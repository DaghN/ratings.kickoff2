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
| **Status** | idle — L1 next = finish-confirm **slice 2** (Table UI) |
| **Format** | — |
| **Step** | — |
| **Feedback** | (none open) |
| **Slice** | finish-confirm 0–1 done → 2 next |

**Context for next chat:** Finish-confirm **slice 1** write path shipped (`amiga_finish_override_write.php`). Next = **slice 2** Table confirm UI (prefill + persist). Do **not** mix Track C / cup templates. Starter [`amiga-organizer-finish-confirm-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-organizer-finish-confirm-STARTER-PROMPT.md).

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

**Current gate:** **L1** — organizer finish confirm **slices 0–1 done**; next **slice 2** (Table UI).

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
| 2026-07-17 | WEBSITE / Finish | #609 WC kitchen: no Finish / no gold | Root cause Tier D vs RR; Reset N/A when completed; durable = finish-confirm policy track (docs); temp Tier C fallback in code |
| 2026-07-17 | WEBSITE | (none — spot-check green) | Catalog + tournament page + profile effect + gone from Live — **L0 complete**; §4 idle |
| 2026-07-17 | WEBSITE | Staging cleaned for parity — old #608 gone | No public event to spot-check; §4 → CREATE new kitchen (L0 restart) |
| 2026-07-16 | MAKE OFFICIAL | `as_of_tournament_id` on slice_totals INSERT | Strip chrono cols from totals upsert; WC-only gate — **Finish success reported**; §4 had → WEBSITE (void after staging clean) |
| 2026-07-16 | MAKE OFFICIAL limbo | Staging #608 extent unclear | **Push local oracle** (`export_ko2amiga_work`) — not browser rewind/repair |
| 2026-07-16 | MAKE OFFICIAL | Silent rewind unsafe | Removed; `rating_finalized` only after full derive; Advanced explicit reset |
| 2026-07-16 | MAKE OFFICIAL | LowestRatingGameID INSERT + limbo | Strip retired GameID cols; (rewind approach superseded — see above) |
| 2026-07-16 | MAKE OFFICIAL | Finish hidden until all results; reject all-must-play gate | Partial finish: button with ≥1 played; unplayed → void on finish |
| 2026-07-16 | START | “Void tournament” unexplained on Setup | Void removed from Setup; Advanced = Abandon league (void) + copy — **re-check green** |
| 2026-07-16 | CREATE re-entry | #608 missing from Recent leagues | Wrong `bind_param` types stored `format_overrides` as `0`; fixed types + auto-repair — **re-check green** (Recent leagues → Open) |
| 2026-07-07 | enter ops | Tournament id on password gate confused secretaries | Password-only gate + Recent leagues inside — shipped |
| 2026-07-07 | website | Allowlist blocked public live page | Start=public (ALO11) — shipped |
| 2026-07-07 | enter ops | Staging fixtures.php HTTP 500 before gate | Require path fix — shipped |

---

## 8. Changelog

| Date | Change |
|------|--------|
| 2026-07-17 | **Finish confirm slice 1** — Tier E full-ladder write helper + smoke; §4 idle → next slice 2 Table UI. |
| 2026-07-17 | **Finish confirm slice 0** — inventory + locks in plan §3a; §4 idle → next slice 1 write path. |
| 2026-07-17 | **Finish confirm track (docs)** — policy FO1–FO10 + plan + starter; §4 idle pending slice 0; #609 cycle archived (Reset limbo-only clarified). |
| 2026-07-17 | **L1 kitchen WC finish** — WC + `has_cup=0` kitchen RR: Tier D empty → fall back to Tier C league finish (PHP + Python); temporary pending finish-confirm UI. |
| 2026-07-17 | **L0 complete** — Dagh WEBSITE spot-check green (catalog, tournament page, profile, left Live); §4 idle; gate → L1. |
| 2026-07-17 | **L0 §4 → CREATE (restart)** — staging cleaned for PHP parity; old kitchen #608 gone; WEBSITE deferred until a new Ref-League-A is finished on staging. |
| 2026-07-17 | **L0 §4 → WEBSITE** — MAKE OFFICIAL success already reported; parity track separate; next secretary step = public site check. *(superseded same day by staging clean)* |
| 2026-07-16 | **Finish slice_totals leak** — PHP copied `as_of_tournament_id`/`event_*` into `amiga_player_slice_totals` INSERT; unset those cols + WC-only persist gate. |
| 2026-07-16 | **L0 limbo → push local** — staging kitchen Finish limbo; Dagh: local `ko2amiga_work` oracle → `export_ko2amiga_work` for staged replace (not in-place rewind). |
| 2026-07-16 | **Finish limbo redesign** — no silent Finish rewind; `rating_finalized` set only after full derive; Advanced “Reset incomplete finish” is explicit and narrow. |
| 2026-07-16 | **Finish limbo / SCH-043** — Amiga current upsert still sent `LowestRatingGameID`; stripped + limbo rewind on Finish retry. |
| 2026-07-16 | **Partial finish** — Finish and make official with unplayed matches (auto-void scheduled); RTB policy §6.2 updated. |
| 2026-07-16 | **START Void clarity** — removed unexplained “Void tournament” from Setup; Advanced has “Abandon league (void)” with plain copy; Setup after start points to Results / Finish. |
| 2026-07-16 | **CREATE Recent-leagues miss** — kitchen create `bind_param` typed `format_overrides` as int (`0`); fixed `ssisiiisiiis` + repair on organizer load for broken rows (e.g. #608). |
| 2026-07-16 | L0 Ref-League-A: CREATE ok on staging; §4 → awaiting START. |
| 2026-07-16 | **Serial feedback** replaces pain-log inventory — queue depth 1; §4 active cycle; shipped rows → archive; agent rules updated. |
| 2026-07-07 | **RTB shipped** — play = fixture-only scores; Make official = promote + finalize. |
| 2026-07-07 | Drill checklist + ALO11 start=public; Make official wording. |
| 2026-07-07 | Initial practice track — Ref-League-A / Ref-Cup-A, pain log (retired 2026-07-16), L0 start. |