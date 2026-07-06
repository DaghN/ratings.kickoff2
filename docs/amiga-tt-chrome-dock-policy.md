# Amiga time travel — chrome dock policy (CD track)

**Status:** **Approved** Jul 2026 — product + technical direction locked. **Sticky v1 (Jul 2026):** CSS `position:sticky` shipped — sticky on only, no pushpin; see §8. **Baseline phase (Jul 2026):** C02 pin **removed from code** Jun 2026 — F6 nav stability signed off before sticky v1.

**Parent:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`design-direction.md`](design-direction.md)

**Related:** [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) (C02 optional pin — evolved here) · [`with-player-stepper-policy.md`](with-player-stepper-policy.md) · [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`nav-spacing-policy.md`](nav-spacing-policy.md)

**Implementation plan:** deferred — rewrite when implementation starts.

**Invariants (failures register):** [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md) — **observed symptoms only** (no causes/solutions); read before editing carry-scroll, head boot, pin JS, chrome PHP, or TT sticky CSS. Update when a **new failure** is observed; document fixes in slice handoffs.

---

## 1. Executive summary

When `as=` is active, the **snapshot ribbon** becomes the primary navigator once the user scrolls; the **temporal stamp** remains a **derived readout** of the current cutoff choice — a receipt stamped onto the environment, not a master clock above the controls.

**Ribbon sticky (default on):** when `as=` is active (including **Present → Time travel** toggle entry), sticky is **on** without user action. The ribbon starts **in document flow** (not fixed). The **site header is in flow too** (shipped: `position: relative` on `.k2-site-header` — it scrolls away). As the user scrolls down, header and stamp leave the viewport first; when the ribbon would scroll past the **top of the viewport**, it **sticks** at **`top: 0`**. Scroll back to top → ribbon unsticks; header is reachable again in flow.

**Chrome order (sticky on, in flow):** site header → **stamp** → **ribbon** → hub chapter / page body. Same as shipped today while the ribbon has not yet **stuck**.

**Chrome order (sticky on, stuck):** ribbon fixed at **`top: 0`**; site header and stamp have scrolled off above (in flow — not pinned under a visible header).

**Pushpin:** inverts C02 — **sticky on** is default; pushpin sets **sticky off (opted out)** — ribbon stays **in flow** and scrolls away. Opt-out **persists** across `as=` navigation until the user turns sticky on again — **except** **Present → Time travel** toggle entry always resets to **sticky on** (CD2).

**Nav stability:** full-page loads (Turbo removed Jun 2026) require a **TT chrome coordinator** — wrapper markup, pre-paint boot, carry-scroll readiness — so ribbon sticky and stamp do not flash, pop in, or “disappear” on chevron/hub navigation.

**Terminology:** see **§2.4** — use **sticky on/off**, **in flow**, and **stuck** only; do not use *docked*, *pre-sticky*, or *pinned* for this track (except C02 legacy until migration).

---

## 2. Product intent

### 2.1 Hierarchy

| Layer | Role | User mental model |
|-------|------|-------------------|
| **Site header** | Realm, mode toggle, search | Global chrome — **in flow**; scroll to top to reach it |
| **Snapshot ribbon** | Event · Month · Year + stepper + picker (+ with-player on Event) | **Vehicle** — how you move through time; sticks once you scroll down to it |
| **Temporal stamp** | LED kicker + DSEG7 date + cursor | **Receipt** — where you landed after ribbon/picker choices; **above** ribbon while ribbon is **in flow** |
| **Hub chapter / body** | Section title + content at cutoff | The **environment** the stamp labels |

**Rejected framing:** stamp above ribbon (“LED master, now enter my vehicle”). Stamp content rules (kicker, LED formats, toggle/wing motion) stay per [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §5.0 unless amended below.

### 2.2 Why default sticky

- Long player/tournament/LB pages lose TT controls without sticky chrome (C02 motivation).
- Optional pin (C02) under-used the feature; users forget time travel mid-scroll.
- Scroll-to-stick (not fixed on entry) keeps stamp → ribbon order at page top without wasting viewport on an always-fixed bar.
- **In-flow site header** (shipped) scrolls away before the ribbon latches — **stuck** ribbon uses **`top: 0` only**; no header-height offset phase (see §4.1).
- C02’s fixed pin-at-load could overlap header; CD4 avoids that by **in flow until latch**.

### 2.3 Present mode

Unchanged: no stamp, no ribbon, no sticky chrome. Header **Present day | Time travel** only.

### 2.4 Terminology (locked)

Two layers: **feature setting** (`sticky on` / `sticky off`) and **scroll phase** (`in flow` / `stuck` — only when sticky is on).

| State | Feature | Scroll phase | Meaning |
|-------|---------|--------------|---------|
| **1** | **Sticky on** (default) | **In flow** | Ribbon in document order (stamp → ribbon); scrolls with the page until the latch threshold (CD4). |
| **2** | **Sticky on** | **Stuck** | User scrolled until the ribbon would leave the viewport; ribbon fixed at **`top: 0`**. Site header (in flow) has already scrolled off above. |
| **3** | **Sticky off** (pushpin **opted out**) | **In flow** (always) | No latch; ribbon scrolls away — same as C02 unpinned. |

**Use in docs and code comments**

| Term | Meaning |
|------|---------|
| **Sticky on / sticky off** | User feature toggle (CD2, CD3). Prefer **opted out** when describing pushpin disabling sticky. |
| **In flow** | Normal document flow; ribbon not fixed to the viewport. Applies to state 1 always and state 3 always. |
| **Stuck** | Ribbon latched to the viewport edge (matches CSS `position: sticky` / MDN “stuck” state). State 2 only. |

**Do not use for this feature**

| Term | Why |
|------|-----|
| **Dock / docked** | Ambiguous (OS dock, old CD draft, unrelated UI patterns). |
| **Pinned** | Retired with C02 removal (Jul 2026 baseline); do not reintroduce `--pinned` until CD track |
| **Pre-sticky / sticky active** | Non-standard; agents invent meanings. Use the table above. |

**Filename note:** this doc id is **CD track** / `amiga-tt-chrome-dock-policy.md` — historical label only; feature vocabulary is **sticky on/off**, **in flow**, **stuck**.

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CD1** | **Stamp before ribbon (in flow)** | In `.k2-page-nav`, temporal stamp markup **precedes** snapshot ribbon in DOM and visual order while ribbon is **in flow** (sticky on before latch, or sticky off). Matches shipped order today. |
| **CD2** | **Sticky on (default)** | When `as=` active, ribbon sticky is **on** without user action. **Present → Time travel** toggle entry (`k2_tt_entry=1`) **always** sets **sticky on**, clearing a prior pushpin opt-out (fresh entry). Direct URL / chevron / wing tab with `as=` **honours** saved opt-out (CD3). |
| **CD3** | **Pushpin → sticky off** | Pushpin sets **sticky off (opted out)**; ribbon stays **in flow** and scrolls away (C02 unpinned behaviour). Preference **persisted** until user re-enables sticky on pushpin **or** enters time travel via **mode toggle** (CD2). |
| **CD4** | **In flow → stuck at viewport top** | **Sticky on:** ribbon **in flow** at load (`scrollY ≈ 0`). Becomes **stuck** when scroll would take the ribbon past the **viewport top**. While **stuck:** **`top: 0` only** (site header is in flow and is already off-screen when latch occurs — §4.1) |
| **CD5** | **Header reachable at scroll top** | Site header stays **in flow** (shipped). At **`scrollY ≈ 0`**, ribbon is **in flow** (not **stuck**); header, stamp, and ribbon stack normally — no overlap. User reaches header by scrolling to top. |
| **CD6** | **Stamp scrolls** | Temporal stamp is **never** sticky; it scrolls with page content above the ribbon until the ribbon becomes **stuck** |
| **CD7** | **Stamp motion on nav** | **Chevrons, picker, hub tabs, direct URL** (no `k2_tt_entry`): stamp updates **in place** — no LED fade, no kicker typewriter, no panel arrival. **Wing tab** (`k2_tt_entry=wing`): keep LED fade + kicker typewriter. **Toggle entry** (`k2_tt_entry=1`): keep full arrival. |
| **CD8** | **Carry-scroll preserved** | Ribbon stepper/wing tabs/picker forms keep `data-k2-carry-scroll`; sticky coordinator must not fight carry-scroll restore. **Jul 2026:** when ribbon is **stuck**, store `{ y }` only (no anchor — sticky geometry breaks `viewportOffset`); restore keeps TT ribbon safety net in `k2_carry_scroll_restore.php`. Tab nav below ribbon unchanged. |
| **CD9** | **Surfaces** | Same as snapshot chrome today — all Amiga pages with active `as=`; ops/import excluded (T10) |
| **CD10** | **Event wing wrap** | Sticky ribbon may wrap (`flex-wrap`) on Event wing — same as C02 pinned |

---

## 4. Sticky ribbon — in flow → stuck (CD4 detail)

### 4.1 Behaviour

See **§2.4** for state names. Three user-visible modes: **sticky on + in flow**, **sticky on + stuck**, **sticky off + in flow**.

**Site header (shipped, locked assumption):** `.k2-site-header` is **`position: relative`** — **in flow**, not fixed or sticky. It is **above** stamp and ribbon in the document. When the user scrolls, the header leaves the viewport **before** the ribbon reaches the latch point. Therefore **stuck** ribbon does **not** sit under a visible header; **`top: 0`** is the only sticky offset.

```
scroll top — sticky on, in flow:
  ┌─ site header (in flow) ─────────────────────────────┐
  ├─ stamp (in flow) ──────────────────────────────────┤
  ├─ ribbon bar (in flow, below stamp) ─────────────────┤
  └─ page body ─────────────────────────────────────────┘

scrolled — sticky on, stuck:
  ┌─ ribbon bar (stuck, top: 0) ────────────────────────┐  ← header + stamp scrolled off above
  │  page content scrolls beneath ─────────────────────  │
  └─────────────────────────────────────────────────────┘

scroll back to top — sticky on, in flow again:
  header → stamp → ribbon → body (same as first diagram)

sticky off (opted out) — always in flow:
  stamp → ribbon → body scroll together; ribbon never becomes stuck
```

- **Latch trigger (sticky on only):** user scrolls until the ribbon’s in-flow position would cross the **viewport top**; only then apply sticky/fixed geometry (**not** fixed on first paint).
- While **stuck:** **`top: 0`** (standard `position: sticky` / equivalent fixed bar at viewport top).
- **Ribbon cosmetics (v1):** opaque **`var(--k2-bg-page)`** background + hairline border in both **in flow** and **stuck** (matches site page background).
- Prefer **instant** latch; optional ≤120ms transition only if visual testing demands it.
- **Not in scope:** header-height sticky offset, scroll-linked `top` changes, or fixed site header — unless site header behaviour changes in a separate track.

### 4.2 Z-index ladder (unchanged intent)

| Layer | z-index | Notes |
|-------|---------|-------|
| Hub bar | 1210 | |
| Snapshot ribbon (in flow) | 1220 | picker stacks below in-flow header when at scroll top |
| Site header | 1300 | in flow; search dropdown |
| Sticky ribbon (stuck) | 1390 | same z-index band as C02 `--pinned` section |
| Jukebox FAB | 1400 | |
| Tooltips | 1500 | |

Picker panels while ribbon is **stuck** must not cover content incorrectly; z-index unchanged from C02 pinned intent.

---

## 5. Chrome stack placement

### 5.1 Document order (when `as=` active)

Inside `.k2-page-nav`, top to bottom:

1. **`.k2-amiga-tt-chrome`** wrapper (new — §7)
   - **Stamp** `<aside class="k2-amiga-tt-stamp">`
   - **Ribbon** `<section class="k2-amiga-time-travel …">`
2. Hub chapter (`k2-hub-chapter`) where applicable
3. Hub bar / wing sub-nav / page body

Site header remains **outside** `.k2-page-nav` in `site_header.php`.

### 5.2 Amends parent policy §5.0

When this track ships:

- **Placement:** stamp **above** ribbon while ribbon is **in flow** — **keep** shipped §5.0 order (CD1); do not flip to ribbon-first at page top.
- **Add** sticky ribbon behaviour (CD2–CD4) and pushpin **sticky off** (CD3) to §5.0 layer table.
- Stamp **content** rules (kicker strings, LED formats, DSEG7, a11y, cursor blink) remain unless CD7 narrows motion.

---

## 6. Pushpin — sticky off (CD3)

| State | Behaviour | Storage |
|-------|-----------|---------|
| **Sticky on** (default) | In flow at page top → **stuck** on scroll (CD4) | Default when no opt-out saved; **restored on toggle entry** (CD2) |
| **Sticky off (opted out)** | Ribbon stays **in flow** always; scrolls away with page | Pushpin toggle; **persists** on chevron, wing tab, direct URL, reload — **cleared** on Present → Time travel toggle entry |

**Toggle entry rule (CD2):** `k2_tt_entry=1` (Present → Time travel) **must** apply **sticky on** and clear opt-out for that entry — even if `localStorage` had sticky off. In-lens stepping (chevrons, picker, hub tabs) **does not** reset opt-out.

**UI copy (draft):** pushpin **pressed** = “Float time travel controls with the page” / **unpressed** = “Keep time travel controls sticky while scrolling” (invert today’s C02 labels).

**Migration:** C02 `k2-amiga-tt-ribbon-pinned=1` maps to **sticky on**; absent = sticky on (new default). Users who never pinned get sticky for free.

---

## 7. Nav stability

Full-page navigation must not produce visible TT chrome defects (stamp pop-in, ribbon jump, cloak glitches, wrong sticky state on first paint). **Product requirement only** — no implementation diagnosis in this section.

**Observed symptoms (Jul 2026 reverted attempt):** [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md) — failures **F1–F17**, reproduction **S1–S8**, integration tensions watchlist.

**Causes and fixes:** recorded **after verification** in TT sticky **slice handoffs** (see failures register § “Where causes and fixes are documented”). Not in this policy until a slice proves them and the track closure updates status.

Stamp motion on navigation: **CD7** (§3). Stamp sync script load order: existing [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) §5.0 (shipped contract).

---

## 8. Implementation

**Sticky v1 (Jul 2026):** CSS-first slice shipped — `.k2-amiga-time-travel--active` gets `position: sticky; top: 0; z-index: 1390` in `theme.css`. Ribbon background **`var(--k2-bg-page)`** (opaque site page dark). **Sticky on only** (no pushpin, no `localStorage`). `overflow-x: clip` on `html`, `body.k2-site`, and `.k2-page-nav` (replacing `hidden`, which forced `overflow-y: auto` on body and broke viewport sticky). Event wing bar may wrap when active. Handoff [`2026-07-04-018`](orchestration/agent-handoffs/2026-07-04-018-tt-ribbon-sticky-v1-css.md). **Failures F1–F17** from JS pin attempt **closed** in [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md).

**Baseline phase (done Jul 2026):** TT chrome **in flow** + F6 nav stability before sticky — [`2026-07-04-001`](orchestration/agent-handoffs/2026-07-04-001-tt-chrome-baseline-slice-0.md) · F6 signed off [`2026-07-04-003`](orchestration/agent-handoffs/2026-07-04-003-f6-rating-lb-tt-nav-flawless.md).

**Still deferred:** pushpin **sticky off** (CD3), toggle-entry opt-out clear without pin UI (CD2 partial), TT chrome coordinator wrapper (§7). Re-run smokes **S1–S4** after manual pass on staging.

**Invariants:** [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md) — watch F3/F4/F7/F8/F9 on manual smoke.

---

## 9. Key files (current → target)

| File | Role |
|------|------|
| `includes/amiga_snapshot_chrome.php` | Wrapper render; stamp → ribbon order (CD1) |
| `includes/amiga_time_travel_stamp.php` | Stamp markup + sync JS enqueue |
| `includes/k2_head.php` | Toggle arrival cloak; optional sticky opt-out boot snippet |
| `includes/k2_carry_scroll_restore.php` | Stamp-aware carry reveal |
| `js/k2-amiga-tt-stamp.js` | CD7 motion gating |
| `stylesheets/theme.css` | Wrapper spacing (future sticky CSS when CD ships) |
| `includes/site_header.php` | Unchanged; site header remains **in flow** |

---

## 10. Supersedes / amends

| Prior | Change |
|-------|--------|
| **C02** (optional pin, Jun 2026) | **Retired from code** Jul 2026 baseline — superseded by CD track when shipped |
| **`amiga-time-travel-policy.md` §5.0 placement** | Add sticky ribbon row; **keep** stamp above ribbon while **in flow** (CD1) |
| **`amiga-time-travel-policy.md` §5.1 ribbon placement** | Add sticky behaviour note; “below temporal stamp” unchanged while **in flow** |
| **WP12** (with-player + optional sticky) | Sticky default satisfies long-page `as_with=` scrubbing; update cross-ref when shipped |

Until pushpin ships, **implemented behaviour** = stamp → ribbon with **sticky on** CSS v1 when `as=` active (pushpin **sticky off** still deferred).

---

## 11. Open questions (non-blocking)

- **Storage key:** new sticky-on key vs migrate `k2-amiga-tt-ribbon-pinned` — decide at implement.
- **Mobile:** **stuck** ribbon at `top: 0` only — acceptable per product; revisit only if user testing complains.
- **Reduced motion:** **stuck** snap stays instant; stamp arrival respects `prefers-reduced-motion` (existing).

---

## 12. Changelog

| Date | Note |
|------|------|
| 2026-07-04 | **Sticky v1 shipped** — CSS `position:sticky` on `--active` ribbon; `overflow-x:clip` on html/body/page-nav — handoff [`2026-07-04-018`](orchestration/agent-handoffs/2026-07-04-018-tt-ribbon-sticky-v1-css.md) |
| 2026-07-04 | Policy drafted — ribbon-first, default scroll-linked dock, pushpin opt-out, nav stability architecture |
| 2026-07-04 | **Revised after revert** — stamp→ribbon in flow (CD1); in flow→stuck latch (CD4); implementation plan deferred |
| 2026-07-04 | **Terminology locked (§2.4)** — **sticky on/off**, **in flow**, **stuck**; retired *docked*, *pre-sticky*, *pinned* for this track |
| 2026-07-04 | **Invariants register** — [`amiga-tt-chrome-sticky-invariants.md`](amiga-tt-chrome-sticky-invariants.md) failures F1–F17 + smoke S1–S8 from reverted slice history |
| 2026-07-04 | Failures register **stripped** to symptoms + tensions only; causes/fixes deferred to slice handoffs |
| 2026-07-04 | Policy §7 **stripped** — removed cause/architecture tables; symptoms → failures register only |
| 2026-07-04 | **CD4 simplified** — in-flow site header; ribbon **stuck** at **`top: 0` only**; toggle entry **sticky on** (CD2) |
| 2026-07-04 | **CD2/CD3** — toggle entry **clears** sticky opt-out; in-lens nav **honours** opt-out |
| 2026-07-04 | **C02 removed (baseline)** — pin JS/CSS/control deleted; in-flow ribbon only until nav stable |