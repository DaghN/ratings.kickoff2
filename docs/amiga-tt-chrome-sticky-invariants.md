# Amiga TT chrome sticky — known failures register

**Status:** **Living doc** — Jul 2026. **Symptoms and reproduction only.** No root-cause analysis and no solution write-ups here — those belong in slice handoffs **after** each problem is solved and verified on the fresh implementation.

**Policy (product intent):** [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) — CD1–CD10, terminology **§2.4** (**sticky on/off**, **in flow**, **stuck**).

**Shipped baseline (today):** stamp → ribbon **in flow** only — **C02 pin removed** Jul 2026. Jul 2026 sticky slices **0–3 were reverted** earlier; failures register records symptoms from that attempt.

**Policy latch (target):** CD4 — site header **in flow**; ribbon **stuck** at **`top: 0`** only when scroll reaches viewport top. Some rows below are from the reverted attempt (including abandoned header-offset behaviour); still re-check on implementation.

---

## How to use this doc

1. **Before coding** — skim failures + integration tensions; know what to re-check after your change.
2. **While coding** — tackle **one** failure at a time; do not copy reverted commits as a bundle.
3. **After a fix is verified** — document cause + solution in the **slice handoff** or implementation plan, **not** in this file unless adding a new **failure row** (symptom only) or retiring a row with a link to the handoff that proved the fix.
4. **This file stays modest** — if you are tempted to write “because” or “fix:”, put that in a **slice handoff** (below).

---

## Where causes and fixes are documented

This register holds **symptoms only**. When you work methodically — one failure at a time — verified knowledge goes elsewhere:

| Layer | File (when it exists) | What it contains |
|-------|------------------------|------------------|
| **Product** | [`amiga-tt-chrome-dock-policy.md`](amiga-tt-chrome-dock-policy.md) | Locked decisions CD1–CD10, terminology §2.4 — not causes or fixes |
| **Symptoms** | **This file** | F1–F17, tensions, reproduction S1–S8 |
| **Slice plan** | `docs/amiga-tt-chrome-dock-implementation-plan.md` *(not written yet)* | Slice goals, verification steps, STOP gates — the **todo list** for implementation |
| **Per-slice record** | `docs/orchestration/agent-handoffs/YYYY-MM-DD-NNN-tt-chrome-sticky-slice-N.md` | **After each slice:** what you tried, **verified cause**, **verified fix**, files changed, smoke results. One slice = one problem (or one coherent slice from the plan). |
| **Session log** | `PROJECT_MEMORY.md` | One-line “slice N shipped / failure F3 addressed” |
| **When track ships** | Handoffs move to `docs/archive/orchestration/agent-handoffs/` | Same files; archived for history |

**Workflow example**

1. Pick failure **F1** (or slice 0 from the plan when written).
2. Implement + run **S2** (and related smokes).
3. Write handoff `…-tt-chrome-sticky-slice-0.md` with sections: **Failure targeted**, **Cause (verified)**, **Fix (verified)**, **Files**, **Smokes run**.
4. In this register, add under F1: `**Resolved:** [link to handoff]` — still no cause/fix text here, just the link.
5. If the fix changes product rules, update **policy** CD rows; if it changes how slices run, update the **implementation plan**.

Until the implementation plan and first handoff exist, there is **no** verified cause/fix doc for TT sticky — only this symptom list and the policy.

Convention: [`agent-track-playbook.md`](orchestration/agent-track-playbook.md) Phase 4 (slice handoffs).

---

## Integration touchpoints (factual)

Sticky chrome involves several areas that changed together in the reverted attempt. Edits in one area often coincided with regressions in another.

| Touchpoint | Primary files |
|------------|---------------|
| Chrome markup | `includes/amiga_snapshot_chrome.php` |
| Stamp markup + script | `includes/amiga_time_travel_stamp.php`, `js/k2-amiga-tt-stamp.js` |
| Carry-scroll / cloak | `includes/k2_carry_scroll_restore.php` |
| Head / early boot | `includes/k2_head.php`, snapshot URL helpers |
| Layout CSS | `stylesheets/theme.css` |

**Four navigation contexts** showed different failures: cold load, chevron/picker carry-scroll, Present → Time travel toggle, browser back. A change that looks fine in one context often failed in another.

---

## Integration tensions (watchlist)

When you change the left column, **re-run reproduction** for the listed failures before calling the slice done. This is not a diagnosis — only “these failures showed up together before.”

| When you change… | Re-run failures… |
|------------------|------------------|
| Carry-scroll cloak / reveal / payload | F1, F6, F7, F8, F18 |
| Head inline boot or early `<html>` classes | F3, F12 |
| Chrome render order or wrapper markup | F1, F2, F13 |
| Stamp script load order | F13, F14 |
| Pushpin UI or storage read/write | F15, F16, F17 *(deferred — no pin in baseline)* |
| Stamp motion JS | F14 |

---

## Known failures (symptoms only)

Each row: **what it looked like**, **how to try to reproduce**, **touchpoints** (where work tended to happen — not an assignment of blame).

### F1 — Stamp appears after ribbon on chevron nav

| | |
|--|--|
| **What you see** | After chevron or picker navigation, the ribbon is visible first; the temporal stamp appears a moment later (pop-in). |
| **Try to reproduce** | Rating LB with `as=` — scroll mid-page → chevron step → watch stamp + ribbon on uncloak. |
| **Touchpoints** | Carry-scroll, chrome markup, stamp render |

### F2 — Stamp or ribbon unstable after chrome reorder

| | |
|--|--|
| **What you see** | Stamp blink, missing stamp behaviour, or broken stepper/carry after changing stamp vs ribbon order without a coordinated pass. |
| **Try to reproduce** | Jul 2026 order-swap experiment pattern: reorder chrome in `.k2-page-nav` only → chevron + toggle entry smoke. |
| **Touchpoints** | `amiga_snapshot_chrome.php`, `theme.css`, stamp script placement |

### F3 — Ribbon vertical flash on navigation (before settled)

| | |
|--|--|
| **What you see** | Full-page nav shows the ribbon briefly at the wrong vertical position, then it snaps to the expected **stuck** or **in flow** place. |
| **Try to reproduce** | Scroll until ribbon should be **stuck** → chevron → watch first frames after load. |
| **Touchpoints** | Head boot, pin JS, carry-scroll |

### F4 — Ribbon wrong position when leaving page **stuck** at viewport top

| | |
|--|--|
| **What you see** | User scrolled until header was gone and ribbon sat at viewport top; chevron nav flashes ribbon under the header or in-flow, then jumps to viewport top. |
| **Try to reproduce** | Scroll until ribbon **stuck** at `top: 0` → chevron → watch load. |
| **Touchpoints** | Carry-scroll payload, head boot, pin JS, CSS |

### F5 — Ribbon bar horizontally off-screen or misaligned

| | |
|--|--|
| **What you see** | Ribbon bar partially or fully outside the page column after load or nav. |
| **Try to reproduce** | Cold load + chevron nav on rating LB Event wing. |
| **Touchpoints** | Head boot CSS, pin JS bar geometry, `theme.css` |

### F6 — Sub-ribbon content blanks on TT ribbon nav at scroll top

| | |
|--|--|
| **What you see** | At **`scrollY ≈ 0`**, TT ribbon navigation (wings, chevrons, dropdown pickers) causes **hub chapter and everything below** to vanish briefly, then redraw. **Site header, temporal stamp, and snapshot ribbon stay visible** — no blink on the ribbon stack. Sequence is **old content → blank → new content** (not new content appearing first, then blanking). Chevron, wing, and dropdown behave **about the same** — blanking is **very consistent** at top. |
| **Contrast (control)** | Scroll down even slightly → same ribbon nav usually **does not** blank sub-ribbon chrome; mainly the table (or primary body block) updates while hub nav, filters, and ribbon feel stable. |
| **Try to reproduce** | Rating LB `?as=event:{id}` — scroll to **top** → chevron, wing tab, or picker step → watch from hub chapter downward. Repeat with one wheel-tick of scroll for contrast. |
| **Touchpoints** | `k2_carry_scroll_restore.php`, cloak CSS, `js/k2-carry-scroll.js` (TT ribbon `data-k2-carry-scroll`) |
| **Reported** | 2026-07-04 — baseline explore (Dagh); mapped before slice 0 |

### F7 — Ribbon vertical jump during nav while **stuck**

| | |
|--|--|
| **What you see** | While **stuck**, navigation shows the ribbon at the wrong vertical position briefly (e.g. not at viewport top), then jumps. |
| **Try to reproduce** | Scroll until ribbon **stuck** at top → chevron → watch first frames after load. |
| **Touchpoints** | Pin JS init timing vs carry-scroll completion |

### F8 — Page layout shimmer while scrolling (**stuck**)

| | |
|--|--|
| **What you see** | Table or page content appears to shimmer or shift subpixel-wise while scrolling with ribbon **stuck**; general scroll jank. |
| **Try to reproduce** | Long rating LB — scroll up/down repeatedly with ribbon **stuck**. |
| **Touchpoints** | Pin JS scroll handler, bar CSS, section reserve height |

### F9 — Ribbon visually lags scroll while **stuck**

| | |
|--|--|
| **What you see** | Ribbon **stuck** at viewport top appears to trail scroll by about a frame. |
| **Try to reproduce** | Scroll rapidly with ribbon **stuck** — watch bar vs content. |
| **Touchpoints** | Pin JS scroll sync, bar `top` CSS |

### F10 — Site header not clickable at scroll top

| | |
|--|--|
| **What you see** | At **`scrollY ≈ 0`**, header controls not clickable, or ribbon covers header though policy expects **in flow** stack (header → stamp → ribbon). |
| **Try to reproduce** | Scroll to top — click wordmark, mode toggle, search. |
| **Touchpoints** | Pin JS `top`, z-index in `theme.css` |

### F11 — Header flash on Present → Time travel toggle

| | |
|--|--|
| **What you see** | Entering time travel via mode toggle: header or chrome flashes incorrectly during arrival. |
| **Try to reproduce** | Present day → **Time travel** toggle on any Amiga hub page. |
| **Touchpoints** | Head cloak, pin JS, stamp arrival |

### F12 — Sticky off after toggle entry (when policy expects sticky on)

| | |
|--|--|
| **What you see** | After Present → Time travel toggle, ribbon behaves as **sticky off** (scrolls away) though CD2 requires **sticky on** and must **clear** prior pushpin opt-out. |
| **Try to reproduce** | Opt out via pushpin → return to Present → **Time travel** toggle again → scroll long page. |
| **Touchpoints** | Storage read on entry, head boot, pin JS (`k2_tt_entry=1`) |

### F13 — Stamp blank, stuck arrival, or toggle cloak issues

| | |
|--|--|
| **What you see** | Stamp LED empty on load; `k2-tt-arrival-pending` never clears; toggle entry broken. |
| **Try to reproduce** | Toggle entry + wing tab change. |
| **Touchpoints** | Stamp script defer/load order, `k2_head.php` |

### F14 — Stamp theatrical motion on chevron steps

| | |
|--|--|
| **What you see** | LED fade or kicker typewriter on chevron/picker steps (product wants this only on toggle/wing per CD7). |
| **Try to reproduce** | Chevron ×3 on rating LB — watch stamp motion. |
| **Touchpoints** | `k2-amiga-tt-stamp.js` |

### F15 — Pushpin looks wrong vs actual sticky state

| | |
|--|--|
| **What you see** | Pushpin accent or `aria-pressed` does not match **sticky on/off** or **stuck** / **in flow**. |
| **Try to reproduce** | **Stuck** with sticky on; then opt out; compare button state. |
| **Touchpoints** | Pin button markup, `theme.css`, pin JS |

### F16 — Sticky preference wrong after reload

| | |
|--|--|
| **What you see** | User opted out (or in); reload shows opposite behaviour, or legacy C02 pin state surprises. |
| **Try to reproduce** | Toggle pushpin → reload → repeat with prior C02 `localStorage` if present. |
| **Touchpoints** | `localStorage` keys, head boot vs JS read |

### F17 — Ribbon fixed on first paint when policy expects **in flow**

| | |
|--|--|
| **What you see** | At `scrollY ≈ 0`, ribbon already fixed/stuck though CD4 calls for **sticky on, in flow** until scroll latch. |
| **Try to reproduce** | Fresh load rating LB with `as=` — before any scroll, inspect ribbon position. |
| **Touchpoints** | Pin JS, head boot, CSS |
| **Note** | Policy mismatch symptom from the reverted attempt (fixed-from-load). |

### F18 — TT hub-tab nav whole-page blank (late cutoff; Present OK)

| | |
|--|--|
| **What you see** | On **Countries** hub (and sometimes **World Cups → Chronology**), switching views via the **hub bar** sometimes causes a **whole-page** blank and redraw — not limited to content below the ribbon. **Present day** at the same route: effectively instant, no blank. **TT:** severity depends on cutoff — **early** time-travel dates often fine; **late** cutoffs (towards 2025) especially bad on Countries. Suspicious parity gap: at the **same effective “latest tournament” cutoff**, Present is instant but TT **always** whole-page blanks on Countries. Intermittent on WC chronology tab (`?as=event:585`); Countries hub bar nav more pronounced. |
| **Try to reproduce** | `countries.php?as=event:{late}` — hub bar between views at scroll top and mid-scroll; compare `countries.php` Present (no `as=`). Repeat with an **early** `as=event:{id}` vs **late** id. WC: `world-cups/chronology.php?as=event:585` — hub tab vs Present same tab. |
| **Touchpoints** | Hub `data-k2-carry-scroll` nav, `k2_carry_scroll_restore.php`, ranked table cloak (`$k2RankedCloak`), TT snapshot chrome render path, Countries / World Cups hub shell |
| **Reported** | 2026-07-04 — baseline explore (Dagh); separate thread from F6 though both use carry-scroll |

---

## Reproduction recipes (smoke)

Minimum manual pass after touching § Integration touchpoints. Maps to failures to **watch for**, not pass/fail contracts.

| # | Steps | Watch for |
|---|--------|-----------|
| **S1** | Rating LB `?as=event:{id}` — load at top → ribbon chevron / wing / picker | F6, F17, F10, F13 |
| **S1b** | Same page — one wheel-tick down → ribbon chevron ×2 | F6 contrast (should not blank sub-ribbon) |
| **S2** | Scroll mid-page → chevron ×2 | F1, F3, F14 |
| **S3** | Scroll until ribbon **stuck** at viewport top → chevron | F3, F4, F7, F8 |
| **S4** | Scroll until header gone (**stuck** at top) → chevron | F4, F7, F8 |
| **S5** | Present → Time travel toggle (incl. after prior pushpin opt-out → Present → toggle again) | F11, F12, F13 |
| **S6** | Wing tab Event → Month → Year | F14, F2 |
| **S7** | Pushpin opt out → scroll → reload | F15, F16 |
| **S8** | Player profile mid-scroll → hub pill carry | F1 (F6 contrast — mid-scroll) |
| **S9** | Countries `?as=event:{late}` vs Present — hub bar view switch | F18 |
| **S9b** | Countries early vs late `as=` — same hub bar nav | F18 cutoff sensitivity |
| **S10** | WC chronology `?as=event:585` — hub tab vs Present | F18 |

**Local base URL:** `http://ratingskickoff.test/amiga/leaderboards/rating.php?as=event:589` (adjust event id).

---

## Adding a failure row

Use when a **new symptom** is observed — not when a theory is formed.

```markdown
### F{N} — {short label}

| | |
|--|--|
| **What you see** | |
| **Try to reproduce** | |
| **Touchpoints** | |
```

When a failure is **solved and verified**, add one line under the row: **Resolved:** [handoff or plan link] — do not paste the fix into this file.

---

## Changelog

| Date | Note |
|------|------|
| 2026-07-04 | First register — symptoms from reverted Jul 2026 attempt |
| 2026-07-04 | **Stripped** to failures + tensions only; removed causes and solution attempts |
| 2026-07-04 | Added § **Where causes and fixes are documented** (handoff path + workflow) |
| 2026-07-04 | Aligned failure wording with CD4 (**top: 0** latch; in-flow header) |
| 2026-07-04 | **F6 refined** — sub-ribbon blank at scroll top on TT ribbon nav; ribbon stable; old→blank→new; mid-scroll contrast |
| 2026-07-04 | **F18 added** — TT hub-tab whole-page blank (Countries late cutoff; Present OK); smokes S9–S10 |
| 2026-07-04 | Baseline: removed pin touchpoint row; sticky-only tensions marked deferred |