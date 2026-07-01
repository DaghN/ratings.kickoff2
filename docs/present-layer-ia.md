# Present layer & site completion — editorial IA and shippable intent

**Status:** Intent / policy (Jul 2026). **No present-layer implementation slices shipped yet** — captures how we intend to **finish the shippable site**: realm front doors, News, pulse rail, **Misc shelf**, **leaf pages**, **site chrome** (footer, about), and cross-realm onboarding. Room for post-ship additions is explicit.

**Authority:** Dagh's latest chat wins. This doc sits **below** [`hub-ia-agreement.md`](hub-ia-agreement.md) (hub tab contracts) and [`navigation-model.md`](navigation-model.md) (NM1–NM7 invariants). URL shape defers to [`k2-page-structure-checklist.md`](k2-page-structure-checklist.md) and [`url-routes.md`](url-routes.md).

**Related:** [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) (T13 present-only tabs) · [`STATUS_PAGE_DATA.md`](STATUS_PAGE_DATA.md) (online Status landing) · [`creative-ideas-july-2026.md`](creative-ideas-july-2026.md) **C04** (News — reframed here) · [`amiga-realm-vision.md`](amiga-realm-vision.md) (Amiga skips Play & Setup hub tab) · [`design-direction.md`](design-direction.md) (chrome, prose link tokens)

**What this doc is:** Part **spec**, part **policy**, part **intent** for the **completion track** — the IA and editorial surfaces that turn a stats engine into a **complete, shippable product**. Locked rows are citeable as **PL1–PL16**. Open questions stay open until a slice ships.

---

## 1. Executive summary

### 1.1 Two planes

| Plane | State (Jul 2026) | Role |
|-------|------------------|------|
| **Stats plane** | Largely built | Leaderboards, Games, profiles, time travel, HoF, entity pages — the ladder product |
| **Present layer + site chrome** | Mostly intent | Landings, editorial, Misc, leaf pages, footer/about — how the site **feels finished** and **discoverable** |

**Shippable v1** (intent, not a hard gate): stats plane usable **plus** present layer that does not feel empty **plus** minimal site metadata so a stranger knows *what this is*, *who made it*, and *how to reach you*. Exact checklist: §13.

### 1.2 Cadence

| Surface | Cadence (typical) | Examples |
|---------|-------------------|----------|
| **News roll** | **Weekly** | Scrollable posts on Amiga News |
| **Pulse rail** | **Daily** (especially **online**-linked) | Live games, HoF (New!), “online busy → Status” |
| **Misc shelf** | **Evergreen** | Universe map, joystick guides, remarkable-game tours |
| **Stats hubs** | Continuous / historical | Rating LB, Games vault, time travel |

### 1.3 Realm landings (asymmetric)

| Realm | Present landing | Onboarding |
|-------|-----------------|------------|
| **Online** | `status.php` — live pulse | **Play & Setup** (`join.php`) — last hub tab |
| **Amiga** | `/amiga/news.php` — news roll + pulse | News sidebar CTA → join — **no** Amiga join tab |

Global `index.php` → **Status** today. Default-realm options: §8 (open).

---

## 2. Terminology

| Term | Meaning |
|------|---------|
| **Present layer** | Editorial + pulse — “what's happening now / recently?” — distinct from snapshot stats |
| **News roll** | Main-column **scrollable feed** on Amiga News (newest first) |
| **Pulse rail** | News sidebar — data-driven hooks; **daily** cadence where relevant |
| **Misc shelf** | Catalog of **evergreen** editorial — not timed news posts |
| **Leaf page** | Tier-3 long-form or guide page — **no active hub pill** (NM2) |
| **Site chrome** | Cross-cutting UI: **global footer**, about/contact — not hub navigation |
| **Completion track** | Remaining IA/editorial work toward shippable v1 (this doc) |

**Retired framing:** C04 “not a blog” = **no CMS / comments platform** — not “no scrollable post list” (PL2).

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **PL1** | **Amiga present landing = News** | `/amiga/news.php` — present realm home (T19) |
| **PL2** | **News = scrollable roll + pulse rail** | Weekly-scale main column; daily-scale sidebar (PL3) |
| **PL3** | **Cadence split** | Roll ≈ **weeks**; pulse ≈ **days** (especially online-linked) |
| **PL4** | **Present-only editorial** | News, Live, future **Misc** tab — T13; strip `as=` on direct hit |
| **PL5** | **Online onboarding = Play & Setup** | `join.php` — canonical join path; no full duplicate on Amiga |
| **PL6** | **Amiga onboarding = News sidebar** | CTA cards on pulse rail → `join.php` / realm switch |
| **PL7** | **Misc = evergreen shelf** | Guides, maps, stories, site meta — **not** news posts |
| **PL8** | **Misc discoverability — phased** | Teasers on News first; catalog when ~5+ pieces; hub tab only if browse beats hunt |
| **PL9** | **Three-tier URL model** | Tier 1 landings · Tier 2 editorial hubs · Tier 3 leaves — §4 |
| **PL10** | **Global default realm — open** | `index.php` heuristic / Amiga default — not locked |
| **PL11** | **Stale-news fallback — soft** | Pulse + optional “today” strip; prefer over silent redirect (PL10) |
| **PL12** | **About = leaf page, not hub tab** | `/about.php` — story, acknowledgements, data honesty; discover via footer + Misc **Site** + News optional link |
| **PL13** | **Global minimal footer** | One quiet strip on `body.k2-site` pages — copyright, About, Contact, 1–2 editorial links; **not** a second nav bar |
| **PL14** | **Leaf pages — NM2** | Hub bar always present; **no active pill**; same as `boxart.php`, future `/misc/*`, `/about.php` |
| **PL15** | **Contact split** | **Community / play** → Discord (Play & Setup, News CTA). **Site / data issues** → maintainer email on About (+ optional `mailto:` in footer) |
| **PL16** | **Post-ship growth** | News posts and Misc leaves may be added anytime without IA churn — catalog and footer link out, not new hub tabs per article |

---

## 4. Three-tier structure

```text
Tier 1 — Realm landings (hub tabs, active pill)
  Online: Status · Activity · Leaderboards · … · Play & Setup
  Amiga:  News · Leaderboards · World Cups · … · Live

Tier 2 — Editorial hubs (present-only; optional sub-nav)
  News front · Misc catalog (when warranted) · Live ops

Tier 3 — Leaf pages (hub bar present, no pill — NM2)
  /about.php · /boxart.php · /misc/*.php · remarkable-game tours · …

Site chrome (every page, below content)
  includes/site_footer.php — copyright · About · Contact · …
```

**Online:** No parallel **News** hub tab. Status + Play & Setup + Misc/footer links cover the online present story.

**Anti-patterns:** About tab in hub bar · tall footer with paragraphs · one hub tab per Misc article · duplicating join flow on Amiga.

---

## 5. Amiga News — page contract (target)

### 5.1 Layout

Status room grid = structural reference (main + satellites).

```text
┌ optional “today” strip when roll is stale ─────────────────────────┐
├─────────────────────────────┬──────────────────────────────────────┤
│  News roll (scroll)         │  Pulse rail                          │
│  · post (newest)            │  · online activity → Status (daily)  │
│  · post                     │  · HoF (New!) / recent rises         │
│  · …                        │  · finalize / live teaser            │
│                             │  · Get involved (onboarding)         │
│                             │  · From the shelf (Misc teasers)     │
└─────────────────────────────┴──────────────────────────────────────┘
```

### 5.2 News roll

- Scrollable, newest first — full visible history.
- **Weekly-scale** rhythm; site-native authoring (includes / snippets / simple entry list later).
- No comment threads or full CMS requirement for v1.

### 5.3 Pulse rail

- **Daily-scale** where data allows — especially **online** pulse.
- Amiga-native: HoF, finalize, Live hook, clips.
- **Get involved** + **From the shelf** (Misc teasers).

### 5.4 Sparse roll (PL11)

Pulse keeps page alive; optional top strip for cross-realm “today”; threshold **X** not locked.

---

## 6. Misc shelf — bucket, lifecycle, discovery

Misc is the **evergreen editorial universe** — content that should outlive any single news cycle.

### 6.1 Content buckets (intent)

| Bucket | Examples | Typical realm |
|--------|----------|---------------|
| **Scene** | **KO2 universe map** (C16) — illustrated ecosystem map; wow orientation for newcomers, re-engagement, creator nodes | Cross-realm |
| **Hardware** | Joysticks, USB adaptors, building controllers | Cross-realm (online skew) |
| **Stories** | Remarkable games (video walkthrough), box art mystery, WC highlight reels (C11) | Often Amiga; tag per piece |
| **Site** | About, acknowledgements, how ratings work, data notes | Cross-realm |

Creative ledger items (game tours, universe map, joystick guides) **belong here**, not in the news roll, unless promoted as a **timed News post** that links to the leaf.

### 6.2 Lifecycle

| Stage | What happens |
|-------|----------------|
| **Draft** | New leaf page at Tier 3 (`/misc/…` or legacy path) |
| **Tease** | News “From the shelf” link + optional Status heritage-style hook for cross-realm pieces |
| **Catalog** | Listed on `/misc/index.php` or News sub-nav **Guides** when count warrants |
| **Hub tab** | Optional **Misc** Amiga hub tab (T13 present-only) only if catalog is a **repeat destination** |

Each new piece **does not** need a new hub tab (PL16).

### 6.3 URL habit (open until first slice)

| Kind | Path |
|------|------|
| Cross-realm guides | **`/misc/{slug}.php`** + optional **`/misc/index.php`** catalog |
| Amiga-only editorial | **`/amiga/misc/{slug}.php`** only when content is Amiga-specific |
| Legacy | **`/boxart.php`** — may migrate to `/misc/box-art.php` or stay with redirect |

Register routes in `K2_ROUTES` when inbound links multiply ([`url-routes.md`](url-routes.md)).

### 6.4 Discovery stack (priority order)

1. **News pulse rail** — 2–3 “From the shelf” links (always).
2. **Misc catalog** — browse when library grows (Phase B, PL8).
3. **Footer** — About · optional “Guides” → catalog (PL13).
4. **Status** — optional small panel or heritage-adjacent link for cross-realm Scene pieces (mirrors boxart today).
5. **Misc hub tab** — last resort if pill count is acceptable.

---

## 7. Leaf pages — contract (Tier 3)

Leaf pages are **long-form or guide surfaces** that are not hub tabs and not entity pages.

### 7.1 Navigation (PL14)

- **NM1:** Hub bar always present.
- **NM2:** **No active pill** — same as `game.php`, `boxart.php`, planned `/about.php`.
- Entity sub-nav **only** when the leaf is under an entity namespace (not Misc).

### 7.2 Existing precedent

| Page | Bucket | Notes |
|------|--------|-------|
| **`boxart.php`** | Stories | Long-form + credits footer; Status heritage links in |
| **`join.php`** | — | Tier 1 hub place (active **Play & Setup** pill) — not a leaf |
| **`about.php`** (target) | Site | Prose: site story, acknowledgements, disclaimer, contact |

### 7.3 Leaf vs news post

| | **News post** | **Misc leaf** |
|--|---------------|---------------|
| Cadence | Timely; lives in roll | Evergreen; stable URL |
| Home | Amiga News main column | Linked from News, catalog, footer |
| Examples | “WC 2025 recap”, “New HoF record” | Universe map, joystick guide, box art story |

A news post **may link** to a leaf; it does not replace it.

### 7.4 Implementation habit

- Thin entry PHP + include body section + optional page CSS (copy `boxart.php` / `join_page_section.php` patterns).
- Prose: `--k2-text-secondary`, footer links `--k2-link` ([`design-direction.md`](design-direction.md)).
- No sortable tables unless the leaf genuinely needs one.

---

## 8. Site chrome — footer and about (PL12–PL15)

Site metadata **does not belong in the hub bar** — it belongs in **Tier 3 + global footer**.

### 8.1 Global footer (PL13)

One **minimal** strip on themed pages (`includes/site_footer.php`), included from shell `_end` includes over time:

```text
© {year} {maintainer} · About · Contact · Box art story
```

| Property | Rule |
|----------|------|
| Visual weight | Muted 12–13px; `--k2-text-muted`; links `--k2-link` |
| Height | **One line** (wrap on mobile OK) — not a marketing band |
| Placement | Below page content, inside or after `.k2-page-nav` — consistent across realms |
| Content | Discovery only — no paragraphs, no social icon row unless explicitly wanted later |

Optional later: **Privacy** if analytics or non-essential cookies ship.

### 8.2 About page (PL12)

**`/about.php`** — cross-realm leaf (Tier 3):

- What Kick Off 2 ratings is (online ladder + Amiga archive).
- How the site came to be (Dagh's voice).
- **Acknowledgements** — Robert Swift, Alkis, Spyros, Steve C, Steve (hosting), community contributors.
- **Data honesty** — fan/community project; Kick Off 2 trademark note if desired.
- **Contact** — email for site/data issues; Discord/community → Play & Setup (PL15).
- Link to Misc **Site** / **Scene** pieces for depth (universe map, etc.).

Also linked from footer; optional one-line link from News sign-off — not a hub tab.

### 8.3 Contact (PL15)

| Need | Channel |
|------|---------|
| Join, play, joystick help, lobby | **Discord** — Play & Setup primary |
| Bug, wrong stat, feature about the **website** | **Email** on About (+ footer `mailto:` or plain text) |

No contact form required for v1 (maintenance cost > benefit at community scale).

---

## 9. Play & Setup — evolution (online)

| Keep | Optional later |
|------|----------------|
| Download, Discord, joystick, lobby | Trim Amiga “bigger picture” → link News |
| Last online hub tab | Sharper online-only focus |

Amiga: onboarding on News sidebar only (PL6).

---

## 10. Default realm and routing (open — PL10)

| Option | Behaviour |
|--------|-----------|
| **A — Status default (today)** | `index.php` → `status.php` |
| **B — Amiga product default** | `index.php` → News when landing is substantive |
| **C — Remember last realm** | Cookie / `localStorage` |
| **D — Stale-news heuristic** | Prefer PL11 strip over global redirect |

---

## 11. Relationship to existing policy

| Existing | Fit |
|----------|-----|
| **T13 / T19** | News, Live, Misc = present-only |
| **NM1–NM2** | News = place; leaves + about = no pill |
| **C04** | Scrollable roll + pulse |
| **boxart.php** | Misc **Stories** leaf precedent |
| **hub-ia-agreement** | Hub tabs = stats + editorial **places** only |

---

## 12. Path to shippable v1 (checklist intent)

**Shippable** = a newcomer can use the ladder, understand the scene, find guides, and contact the maintainer — not “every creative idea shipped.”

### 12.1 Already largely done (stats plane)

- Online + Amiga hub tabs, LBs, Games, HoF, profiles, time travel, entity pages, cross-realm search, Play & Setup, boxart leaf.

### 12.2 Completion track (present layer + chrome)

| Phase | Deliverable | Shippable signal |
|-------|-------------|------------------|
| **A — News v1** | Two-column layout, roll + pulse stubs, ≥1 real post | Amiga landing feels intentional |
| **B — Misc seeds** | ≥2–3 leaves (e.g. universe map stub, one hardware guide, boxart linked in catalog) | Evergreen content discoverable |
| **C — Site chrome** | `site_footer.php` + `/about.php` with acknowledgements + contact | Stranger knows who/why/how to reach |
| **D — Pulse live** | Daily online teaser + one Amiga data hook on News | “Alive” without new posts |
| **E — Catalog (when ready)** | `/misc/index.php` or News **Guides** sub-nav | Browse beats hunting teasers |
| **F — Polish (optional)** | Join trim, default realm, Misc hub tab | Product taste — not blocking v1 |

Phases **A + C** are the smallest **metadata-complete** slice; **B** makes Misc real; **D** improves daily feel.

### 12.3 Post-ship (PL16)

- Add news posts to the roll on whatever rhythm fits.
- Add Misc leaves; update catalog and News teasers — **no new hub tabs per piece**.
- Footer stays stable; new links appear in catalog and occasional News mentions.

---

## 13. Suggested implementation order

1. News v1 shell + one post.
2. `site_footer.php` + `/about.php` (stub copy OK).
3. First Misc leaves + News shelf teasers.
4. Pulse widgets (online daily + Amiga hook).
5. Misc catalog when piece count warrants.
6. Play & Setup trim; boxart path cleanup optional.
7. Default realm / Misc hub tab — product calls only.

---

## 14. Open questions (not locked)

- Stale-post threshold **X** (PL11).
- News authoring (static includes vs DB vs markdown folder).
- Misc catalog shape: sub-nav vs index vs hub tab (PL8).
- Global default realm (PL10).
- Footer exact link set and copyright name.
- Privacy page (only if tracking/cookies added).
- `boxart.php` migration vs legacy URL.

---

## 15. Changelog

| Date | Change |
|------|--------|
| 2026-07-01 | Initial intent — News roll, pulse, Misc phased, PL1–PL12, C04 reframe. |
| 2026-07-01 | Expanded scope — leaf pages (PL14), footer/about (PL12–PL13, PL15), Misc lifecycle, **path to shippable v1** (§12), PL16 post-ship growth; title broadened to site completion. |