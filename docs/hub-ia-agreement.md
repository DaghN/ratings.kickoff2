# Hub IA & staging agreement ledger

Record of decisions from the theme-lab / navigation IA conversation (May 2026).
Consult before staging production changes. **Theme lab HTML removed** (May 2026); production reference is live pages + `theme.css`.

**Status:** agreed direction — **Phase A + Status Phase B v1.2 shipped in repo** (May 2026). See `docs/STATUS_PAGE_DATA.md`. Still needs Steve for **prod DB read**, joshua redirect, embed.

---

## Phased implementation (staging strategy)

Broad IA is solved; integration and polish are sequenced. **Do not block Phase A on Steve.** Phase B Status UI is **not** blocked on a separate API — it uses the **same MySQL tables** as the legacy page (`docs/STATUS_PAGE_DATA.md`); Steve is still needed for **prod read deploy**, joshua redirect, and embed.

### Phase A — Safe staging (repo + WinSCP; no new DB dependencies)

Shell first, live data never, existing PHP pages as backends. Legacy URLs keep working.

| # | Item | Phase A scope |
|---|------|----------------|
| 1 | Hub nav shell | Five **segment-track** hub tabs (outline active); shared `includes/hub_nav.php`. **Status tab default** on hub entry. |
| 2 | Status content | **Phase B shipped:** SQL panels on `status.php` — see `docs/STATUS_PAGE_DATA.md` § v1.2. Period-activity tables **not** on Status (Trends only). |
| 3 | Trends tab | **Nav label + route only** → existing `server1.php`. **Do not move or merge** chart content. |
| 4 | Leaderboards | Wing-tab **chrome** on ranked pages; first sub-tab **Rating**; same tables underneath. |
| 5 | Games / Records | **Nav reorder only** → `server3.php` / `server2.php`; no content migration. |
| 6 | Header | **Kick Off 2** wordmark only; mock-style search; realm switcher; **no** kickoff2.com link. |
| 7 | Player backbone | Shared hero + five pills (Profile · Games · W/D/L · Goals · DDs) on individual pages; **scaffold** — content depth later via lab. No back link. |
| 8 | CSS promotion | Flat nav, wing tabs, header search, **neon C without grid** → production `theme.css`. |
| 9 | kickoff2 embed / status redirect | **Out of scope** for Phase A. |

**Phase A routing map (target):**

| Tab | Backend (existing) |
|-----|-------------------|
| Status | `status.php` — **Phase B v1.2** room grid (`docs/STATUS_PAGE_DATA.md`) |
| Leaderboards | `ranked7.php` default (Results wing); `ranked1`–`ranked5`, `ranked7` (+ wing sub-nav; `ranked6` removed) |
| Games | `server3.php` |
| Trends | `server1.php` |
| Records | `server2.php` |

**Safety rules for Phase A:**

1. No **production** DB credentials or destructive writes without Steve; **local/staging copies are OK** for building Status (same schema as joshua).
2. No breaking bookmarks — direct links to ranked/server/individual URLs unchanged.
3. No pretending **stale dump** data is live prod; honest labels or live `kooldb` when deployed.
4. One shared nav include — label/order changes in one place.
5. Staging deploy first; compare old vs new nav side by side.

**Suggested first implementation commit after this doc:** shared hub nav + header tweak + CSS from lab + Status bridge page only.

### Phase B — Status page (shipped v1.2, May 2026)

**Data:** SQL against existing KOOL tables — **not** a new Steve API. Mapping: `docs/STATUS_PAGE_DATA.md`. Build on **local `ko2unity_db`** / **staging `kooldb`** first.

**In scope now (v1 — agreed May 2026, see `docs/STATUS_PAGE_DATA.md` § Hub Status v1):**

- **Pulse** — online now, live games, recency; optional headline totals (`generalstatstable`).
- **Active top rated (20)** — `playertable.Rating` (Elo, **0 decimals**), `LastGame` within **12 months**; not legacy `PlayerRank`. Link to full Leaderboards (all players).
- **Monthly league** — current **calendar month** (server TZ); 3 / 1 / 0 points; W-D-L, goals, GD, pts from `ratedresults`; top ~20; only players with ≥1 rated game that month (implicit from aggregation).
- **Room** — online list, live games (`resulttable`), recent logins, recent rated games.
- **Recent registrations** — `JoinDate` DESC (~10); community “new blood” signal (legacy page parity).
- Polling / refresh (when on live DB). Legacy joshua link until redirect agreed.

**Out of v1 on Status:** games-played-by-period triple tables (brainstorm only; not required); AWOL wall (→ Trends later if wanted); period-activity leaderboards section unless revived deliberately later.

**Still needs Steve / prod agreement (can follow UI work):**

- Read access on live `kooldb` for ratings.kickoff2.com deploy.
- `joshua.kickoff2.net/status.php` redirect or thin wrapper.
- Optional kickoff2.com embed (pulse + few logins → full Status).
- Optional shared JSON layer for embed + hub (can start as PHP includes, extract later).

### Phase C — Polish (lab-driven, anytime)

- Player feast content depth; hero iterations.
- Wing tab labels (Rating · Results · …); ~~Rating records~~ tab removed — columns merged into Rating/Results.
- Logo in header; URL rebrand (`online.kickoff2.com` etc.).
- ~~Full `#aboutmenu` removal once all entry points use shared nav.~~ **Done (medium refactor, May 2026).**
- Amiga realm production wiring; nav spacing clearfix.

---

## Already on main (before / during this thread)

Committed and pushed earlier; not re-opened unless noted below.

- Site-wide dark theme on main PHP pages (`theme.css`, `site_header.php`, ranked/server/individual pages, etc.).
- Design tokens (production): subtle neon baseline, Exo 2 + IBM Plex, **UI tint** via `--k2-accent` (see `docs/tint-vs-realm.md`), Cyan · Magenta table positive/negative (`.blue` / `.red` semantics).
- Realm switcher: **segment** outline active (uses site tint like hub nav; not realm-specific colours).
- Tables: `.k2-table-wrap` ~1200px centered (forced full-width experiment reverted).
- Theme lab (May 2026) promoted to production; static lab files later removed from repo.
- **Phase A hub shell (May 2026):** `includes/hub_nav.php` (five tabs + tint picker), `includes/lb_nav.php` (segment-track wing tabs), `status.php` bridge, `theme_boot_head.php`, `js/realm-switch.js`. Hub tabs are **full page `<a href>`** navigation — not client-side SPA panels.
- ~~Legacy `#aboutmenu` rows may still exist on some pages~~ — removed with shared hub/player nav (May 2026).

---

## Product identity & header (agreed)

| Item | Decision |
|------|----------|
| Site title / wordmark | **Kick Off 2** only — drop tacked-on "ratings". |
| URL | Keep **ratings.kickoff2.com** for now; rename later if desired. |
| Logo | Maybe later — KO2 logo as optional mark beside text; bare text wordmark for now. |
| Header contents | Wordmark · Find player search (mock styling is target) · Online / Amiga realm switcher. |
| kickoff2.com header link | **No** — agreed waste of space. |
| Back to Results on player pages | **No** — browser back + wordmark/hub enough. |
| Decorative header band (site-wide) | **No** — minimal chrome only; imagery page-by-page. See `docs/design-direction.md` §2 *Chrome vs imagery*. |

---

## Hub information architecture — Option B (agreed)

**Five home tabs, this order:**

1. **Status** (default landing)
2. **Leaderboards**
3. **Games**
4. **Trends**
5. **Records**

### Tab naming

- **Status** (not Live) — honors status.php habit; covers logins + live games + counts.
- Micro-copy uses live language ("3 online · 2 live games · last login 12 min ago").
- **Activity** as single tab — superseded by **Status + Trends**.

### Default landing

- Hub opens on **Status** (phone check: anyone on tonight?).
- **Leaderboards** is tab 2.

### Status tab (absorbs status.php)

**Purpose:** presence / tonight / FOMO to launch KOOL — plus **current competition** (active Elo + monthly league), not only “who’s in the room.”

**Data source:** `docs/STATUS_PAGE_DATA.md` (MySQL; full v1 panel list in § Hub Status v1).

**Include (v1):**

- Pulse strip: online now, live games, recency (**not** CPU/disk/mem — legacy ops page only).
- **Active top rated (20):** Elo (`Rating`), last rated game within **12 months**; smaller type if needed; 0 decimal places. Distinct from Leaderboards tab (comprehensive, all players — default there stays “everyone”).
- **Monthly league (current month):** calendar month in **server timezone**; 3 pts win / 1 draw / 0 loss; W, D, L, GF, GA, GD, Pts from `ratedresults`; ~20 rows; tie-break GD then GF. New community hook — games “count” for the month as well as for Elo.
- Recent logins; **recent registrations** (`JoinDate`); live games; recent finished rated games — all names → player profiles.
- Meaningful when 0 online — logins, registrations, recent games, monthly table still show life.
- kickoff2.com embed (later): compact pulse + few logins + "Full status →" here.

**Exclude from player-facing Status:**

- Legacy **dual** Top 10 blocks (Steve `PlayerRank` + old ratings snippet) — replaced by **active Elo top 20** + monthly league, not a duplicate of full Leaderboards.
- CPU / disk / mem ops metrics (admin-only if anywhere).
- Historical charts and long-horizon analytics (→ Trends).
- AWOL wall on v1 (may live on Trends later).

**Leaderboards tab (unchanged intent):** full ladder, all players by default; **active-only filter** (e.g. last game &lt; 12 months) added later — Status uses that filter from day one.

**Eventually:** joshua.kickoff2.net/status.php redirect or thin wrapper; one data feed for hub + kickoff2 embed.

### Leaderboards tab

- Six wing tabs above the table (not a second site nav row).
- Order: **Rating** · Goals · DDs & CSs · Streaks · Victims & Culprits · **Rating records** · **Hall of Fame** (`ranked7`, `ranked2`–`ranked5`, `ranked1`, `ranked8`). Hall of Fame = busiest day/month/year tables (moved from Trends).
- Legacy **`ranked6`** (old Rating records page) removed; `ranked1.php` is now the Rating records wing tab.
- Wing tabs: segment track + outline active (not tied to header row brightness).

### Games tab

- Rated match ledger — proof over days/weeks.
- Distinct from Status live/recent; Games is authoritative rated archive (server3-class).

### Trends tab (historical)

- Games per month/year charts, established-player growth, lifetime totals (server1-class).
- Lazy-load charts when tab opened.
- Room later: AWOL, more charts. (**Recent registrations** are on **Status**, not deferred here.)

### Records tab

- Still last — extremes after life (Status/Games) and rank (Leaderboards).

---

## Player context ("feast") — agreed

Global hub nav replaced by player context:

- Same header (Kick Off 2, search, realm) — no back link, no kickoff2.com link.
- Hero block (rank, rating, peak, games, bio; Amiga photo path later).
- Five pills, order: **Profile · Games · W/D/L · Goals · DDs**
  - Player "Results" → **W/D/L**.
  - Games in slot #2.
  - Profile = warm landing (charts, highlights).
- Player names from Status / ladder link to feast.

---

## Navigation chrome — agreed for production

| Item | Decision |
|------|----------|
| Home + player pills | **Segment track** — active: softened accent text + single mixed ring (`--k2-segment-active-*`); no fill tint. Override via `?k2_hub_nav=`. |
| Leaderboard wing tabs (shipped) | **Segment track + outline active cell** — same language as hub/player pills. |
| Hub tint picker | **Staging (keep for now)** — Amber · Pitch · Chrome · Holo; **hidden by default** + **Show tint**. Sets `--k2-accent`; default amber in CSS. Independent of realm switch. Launch decision deferred. |
| Hub nav style A/B | **`?k2_hub_nav=solid\|segment\|soft`** + `nav-preview.php` for community compare; `sessionStorage` sticky. Production default **segment**. Player sub-nav matches hub. |
| Nav hover (inactive) | **`--k2-text-secondary`** — between muted and primary; not full white flash. Sort/table data stay primary. |
| Neon intensity | **C · Bold** — stronger accent glow. |
| Bold neon grid | **Remove background grid** on C (lab done; promote to theme.css when staging). |
| Neon rail, boxed default, solid glow, etc. | Lab comparisons only — rejected for hub. |

---

## Visual / theme (still agreed)

- Table highlights: Cyan · Magenta.
- **Text ladder:** full detail in `design-direction.md` (Text & link hierarchy). Summary: `--k2-link-star` for player names + profile highlight text; `--k2-link` for prose; `--k2-accent` for chrome only.
- Table column headers: muted labels on `bg-surface`, weight 500; thin mixed-accent rule under last header row; sort hover → `text-secondary` + 2px inset accent.
- Sort header hover: secondary text + inset tint accent bar (same hover step as nav).
- individual3 filter row: 16 cells, transparent filter row, single green line on last header row only.

---

## kickoff2.com relationship (agreed strategy)

- Not the full status dashboard.
- Embed: pulse + ~3 recent logins + link to full Status on this hub.
- Single JSON/API eventually powers embed + hub + replaces legacy fragmentation.

---

## Explicitly deferred (beyond Phase A)

- Status SQL panels on hub (→ **Phase B in progress**; data doc above).
- kickoff2.com embed; joshua status.php redirect (→ Phase B, after Steve).
- Moving or merging server1/server2/server3 page bodies (Trends/Games/Records = nav only in Phase A).
- `docs/design-direction.md` update (this file is the record).
- Sidebar layout.
- Logo; URL rebrand.
- Throwaway files not part of scope.

**Removed from deferred (now Phase A):** shared hub nav include, header cleanup, player visual backbone scaffold, CSS promotion — see table above.

---

## Legacy: original 9-point list (superseded by phased table)

The list below was the first "all at once" staging target. Use **Phase A / B / C** above instead.

1. Hub nav — Phase A  
2. Status feed — **Phase B in progress** (Phase A = bridge + period-activity DB block)  
3. Trends — Phase A (nav only; was "move charts")  
4. Leaderboards wings — Phase A  
5. Games/Records order — Phase A  
6. Header — Phase A  
7. Player feast — Phase A (scaffold)  
8. CSS — Phase A  
9. Embed/redirect — Phase B  

---

## Changed our mind (exclude from staging spec)

| Earlier idea | Final position |
|--------------|----------------|
| Leaderboards as default | **Status** default |
| 4 tabs with Activity | **5 tabs:** Status · Leaderboards · Games · Trends · Records |
| Single Activity tab | **Status + Trends** split |
| Records before Games | **Games before Records** |
| Live as tab name | **Status** |
| "Kick Off 2 ratings" wordmark | **Kick Off 2** only |
| kickoff2.com header link | Removed |
| Back to Results | Removed |
| Wing tab green underline / green bar | Chrome wings, header-row brightness |
| Green inset topline on wings | Removed |
| Neon rail as nav choice | Not chosen |
| Solid glow as nav choice | Too loud → rejected |
| Solid flat hub pills | Too imposing (“light bulb”) → **segment** (May 2026) |
| Full-accent links on every `<a>` | Too bright → **`--k2-link-star`** / `--k2-link` ladder; see `design-direction.md` |
| Ladder sub-tab Results | **Rating** |
| Table width 100% force | Reverted |

---

## Open items to re-confirm at implementation

- ~~Rating records wing tab~~ — removed (`ranked6.php` deleted).
- Prod **read** DB host/credentials and refresh cadence when live (Phase B deploy).
- Neon C without grid: apply to full `theme.css` in Phase A.
- Legacy [joshua status.php](https://joshua.kickoff2.net/status.php) link until redirect agreed.

---

*Last updated: May 2026 — Status v1 scope: active Elo top 20, monthly league, recent registrations, room panels (`docs/STATUS_PAGE_DATA.md`). Phase B unblocked (same DB as legacy status). UI tint default amber (realm ≠ paint); profile feast stat glow removed; hub nav geometry aligned with wings.*

---

## Hub nav style staging (May 2026)

Production default: **segment** (`theme_boot_head.php`). Compare alternatives without theme-lab:

| Variant | `?k2_hub_nav=` | Active pill |
|---------|----------------|-------------|
| **Segment (production)** | `segment` | Track + outline (parity with `.k2-chrome-tabs` wings) |
| Solid | `solid` | Full tint accent fill, dark text |
| Soft | `soft` | ~22% accent tint, accent text |

- Set on `<html data-k2-hub-nav="…">` in `theme_boot_head.php` (URL wins, then `sessionStorage` key `k2-hub-nav-tune`).
- Link index: `nav-preview.php` → Status / Leaderboards / Trends per variant.
- Tint picker: Amber · Pitch · Chrome · Holo; **hidden by default**; `sessionStorage` `0` = shown; **Show tint** / **Hide tint** on hub bar.
