# Amiga World Cups hub — policy

**Status:** **Wings 1–4 shipped** (Jun 2026-24) — **events catalog** (`amiga_world_cups_events_table.php`); tournament stats (`amiga_world_cup_stats`); **Player stats** + **Country stats** (five sub-wings each) on hub; player + country bodies shared with Leaderboards player wing only (no LB Countries mirror). **`tournament.php` event-stats enrichment** TBD.

**Parent:** [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) · [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md) · [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md)

**Related:** [`url-routes.md`](url-routes.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`design-direction.md`](design-direction.md) · [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md)

**Supersedes:** [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) § UI placement Option **A** (Activity sub-wing) — event-grain WC stats live under this hub wing 2 instead.

---

## 1. Executive summary

Promote **World Cups** from a single Leaderboards wing into a **dedicated Amiga hub tab** — a small **universe** with four foldered wings:

| Wing | Label (product) | Grain | Primary data |
|------|-----------------|-------|--------------|
| **1 — Chronology** | **Chronology** (default) | One WC tournament | WC **list** on hub; per-event detail on **`tournament.php`** |
| **2 — Tournament stats** | **Tournament stats** | One row per WC | `amiga_world_cup_stats` |
| **3 — Player stats** | **Player stats** | Player × WC career | `amiga_player_slice_*` (`slice_key = 'world_cup'`) |
| **4 — Country stats** | **Country stats** | Nation × WC career | `amiga_country_slice_*` (`slice_key = 'world_cup'`) — **shipped** [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) |

**Mental model:** *Which World Cups happened?* → *How wild was each one?* → *Who dominated?* → *Which nations dominated?*

Realm-wide **calendar-year** WC trends (community facts + charts) stay on **Activity** — they answer a different question (*how much WC activity in year Y across the whole realm?*).

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WCH1** | **Top-level hub tab** | **World Cups** is its own Amiga hub tab — not only a Leaderboards wing. |
| **WCH2** | **Present hub order** | **News · Leaderboards · World Cups · Activity · Hall of Fame · Tournaments · Live tournaments** (last). **News** remains present landing. **Leaderboards · World Cups · Activity · Hall of Fame** are contiguous in present order — same sequence as the time-travel hub bar (T13b). Tournaments + Live tournaments stay present-only editorial ops at the end. |
| **WCH3** | **Foldered sub-hub** | List/aggregate wings under `/amiga/world-cups/` — segment sub-nav (not `?view=` on hub pages). Per-tournament drill-down under **`/amiga/tournament/`** foldered tabs ([`url-routes.md`](url-routes.md)). |
| **WCH4** | **Four wings** | **Chronology · Tournament stats · Player stats · Country stats** — player + country stats = **five sub-wings** each ([`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md), [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md)); **both shipped** |
| **WCH5** | **Default wing** | `/amiga/world-cups/chronology/` — **Chronology** (`chronology/index.php`); `/amiga/world-cups/` 302 to chronology. Tournament stats Participation default = `stats/participation.php` (`stats/index.php` + `stats.php` 302). |
| **WCH6** | **Event stats home** | Sortable **`amiga_world_cup_stats`** table lives on **wing 2** — **not** under Activity. |
| **WCH7** | **Activity WC charts** | Shipped community **year** WC charts (**Q-WC-001**–**003**, **006**–**007**, **011**) stay on **Activity** (realm pulse). Wing 2 may **link** to them (“Realm trends in Activity →”). |
| **WCH8** | **Player stats — one implementation** | Honours · Results · Goals · DDs & CSs · Opponents tables live in **`includes/amiga_wc_players_wing_body.inc.php`** + **`amiga_wc_players_table.php`**. **No forked SQL or duplicate table markup.** |
| **WCH9** | **Dual entry, identical content** | **Leaderboards → World Cups** and **World Cups hub → Player stats** both render the **same** five sub-wings (full tables, not a thin pointer). Each surface keeps its own hub/LB chrome and sub-nav URLs; body is shared. |
| **WCH10** | **Milestones analogue** | Same pattern as Milestones hub + `leaderboards/milestones.php`: **one product**, two navigation homes — not two divergent leaderboards. |
| **WCH11** | **Time travel** | Wings **2** and **3** respect `as=` from first UI ship. Wing **1** event list = tournaments finalized on or before cutoff; per-event stats rows are **intrinsic** (fixed at finalize — see WC table plan § time travel). |
| **WCH12** | **TT hub bar** | When `as=` active, World Cups tab **included** in time-travel hub bar (snapshot-worthy). Present-only tabs still **News**, **Tournaments**, **Live tournaments** per [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) T13/T13b. **TT order:** **Leaderboards · World Cups · Activity · Hall of Fame** — matches present order after News (WCH2). |
| **WCH13** | **WC detection** | `amiga_tournament_is_world_cup()` / name `^World Cup\s+\S` — same as slice, honours, writers. |
| **WCH14** | **No new stored truth** | This hub is **read surfaces** only unless a future slice adds columns — writers already finalized. |
| **WCH15** | **One tournament folder** | **No** parallel `/amiga/world-cups/event.php`. Every WC (and every tournament) deep-links to **`/amiga/tournament/event-stats.php?id={tournament_id}`** (default tab). Other tabs = sibling PHP files under `tournament/` — build URLs via `amiga_tournament_event_stats_url()` / `amiga_tournament_href()`; legacy `/amiga/tournament.php?view=` 302s to folder paths. |

---

## 3. What this hub is (and is not)

**Is:**

- The **product home** for the World Cup story on Amiga: catalog of events, per-event texture, career WC leaderboards.
- A **navigation universe** that connects `tournament.php`, WC stats table, and player slice LBs.
- Extensible for richer per-WC pages (bracket highlights, extrema games, podium narrative) without moving player rankings back under Leaderboards.

**Is not:**

- A replacement for **Tournaments** hub (all events) or **Live tournaments**.
- A home for **non-WC** community stats (volume, geography, texture, economy) — those stay **Activity**.
- A reason to duplicate `wc_*` on snapshots (storage rule unchanged — [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) WC12).

---

## 4. Wing contracts

### 4.1 Wing 1 — Chronology (default)

**Question:** *Which World Cups have we played, and where is each one?*

Wing 1 is the **WC catalog index** only. It does **not** host a second copy of the tournament page.

| Element | v1 | v2+ |
|---------|----|-----|
| **Intro** | Short chapter lede (`k2_hub_chapter.inc.php`) — what a World Cup is in this realm | Optional era copy, COVID note |
| **List** | Sortable table — one row per WC: date, host flag, name, games, players, podium (flag + linked name per medal) | Filters by decade / host nation |
| **Row link** | **`/amiga/tournament.php?id={tournament_id}&view=event-stats`** — via `amiga_tournament_event_stats_url($id)` + `amiga_tournament_href()` (preserves `as=`). Optional `#tournament` anchor for scroll. **Default WC landing tab** — already first in WC tournament nav. | Same URL; richer tab content |
| **Per-event richness** | **Enrich `tournament.php`** — especially **`view=event-stats`** (realm row from `amiga_world_cup_stats` + existing per-player event stats). Bracket / stages / games = other `view=` tabs on the **same** `id`. | Extrema game links, podium narrative on existing tabs |

**Do not create** `/amiga/world-cups/event.php` or any other tournament-id route (WCH15).

**List data sources:** `amiga_world_cup_stats` joined to `tournaments` (sort default: `event_date` desc). Rows only for finalized WCs present in stats table.

**Empty / TT:** At cutoff, list only WCs with `event_date`/`chrono`/`id` ≤ cutoff (tournament finalized and in scope). Pre-first-WC cutoff → empty state + copy. List links still use `tournament.php` + `as=`; tournament page applies cutoff reads.

### 4.2 Wing 2 — Tournament stats

**Question:** *How wild / big / diverse was each World Cup?*

| Element | Rule |
|---------|------|
| **Sub-wings** | **Participation · Goals · DDs & CSs · Geography · Podium** — inner nav under Tournament stats (**Participation** default landing) |
| **Anchor columns** | Every table: **Tournament** (link) · **Year** · **Players** · **Games** — no date/host/city on stats tables |
| **Row key** | `tournament_id` |
| **Links** | Tournament → `tournament.php?id=&view=event-stats` (WCH15); goal peaks → clickable value → `game.php?id=` |
| **Draw texture** | **Draw %** only in UI (not draw/decided counts or decided rate) |
| **Blowout %** | Stored `blowout_rate` (`blowout_games ÷ rated_games`) |
| **Geography intl** | Stored `international_games` + `international_game_share` (both nations set and differ) |
| **Podium v2** | Full WC placement table (all finish positions) — future; v1 = gold/silver/bronze only |
| **Realm trends** | Footer link → Activity WC year charts |

**Stored truth:** `amiga_world_cup_stats` only — no live aggregation from `amiga_games` on hot path.

Column layout per sub-wing: [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) §3.13.

### 4.3 Wing 3 — Player stats

**Question:** *Who has the best World Cup career?*

| Element | Rule |
|---------|------|
| **Sub-wings** | **Honours · Results · Goals · DDs & CSs · Opponents** (V2 shipped Jun 2026-23) |
| **Paths** | `/amiga/world-cups/players/honours.php` (default), `results.php`, `goals.php` |
| **Read lib** | Existing slice read libs — `amiga_player_slice_*` at cutoff |
| **Render** | `includes/amiga_wc_players_wing_body.inc.php` (shared with LB wing) |
| **Eligibility / points / averages** | Unchanged — WC2–WC8 in LB policy |

**Dual surface:** Hub wing 3 and Leaderboards → World Cups are **the same tables** in different chrome (WCH9). Sub-nav on each surface links within that surface’s URL tree; cross-links optional in copy only.

### 4.4 Wing 4 — Country stats

**Question:** *Which nations dominated World Cups?*

| Element | Rule |
|---------|------|
| **Status** | **Policy locked** — [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md); DDL / writers / UI **not shipped** |
| **Sub-wings** | **Honours · Results · Goals · DDs & CSs · Opponents** — same names as player stats |
| **Paths** | `/amiga/world-cups/countries/honours.php` (default), `results.php`, `goals.php`, `dds.php`, `opponents.php` (proposed) |
| **Grain** | One row per `country_token` (`Unknown` for blank nationality) |
| **Read lib** | `amiga_country_slice_*` at cutoff — when implemented |
| **LB mirror** | **Hub only** for v1 — no Leaderboards → Countries wing unless product revises |

---

## 5. Boundaries with neighbouring surfaces

| Surface | Relationship |
|---------|----------------|
| **Activity** | **Keeps** all community stat wings + **year-level** WC charts (Q-WC-001–003, 006–007, 011). **Does not** host per-WC stats table. |
| **Leaderboards** | **Rating** and general wings unchanged. **World Cups** LB wing = **same player stats body** as hub wing 3 (WCH9) — LB chrome + `/amiga/leaderboards/world-cups/*` URLs. |
| **Tournaments** | Full catalog (all events). WC hub wing 1 is **WC-filtered** index; same **`tournament.php`** destination as Tournaments hub. |
| **`tournament.php`** | **Canonical** per-tournament address for all events including WCs. WC hub links land on **`view=event-stats`** by default; enrich that tab (and siblings) with `amiga_world_cup_stats` — **no duplicate event URLs** (WCH15). |
| **Hall of Fame** | WC-specific HoF rows **deferred** (LB policy WC10) — not blocked by this hub. |
| **Player profile** | WC medals/stats still on profile via slice/current reads; hub is aggregate discovery. |

### Data grain cheat sheet

| Question | Surface | Storage |
|----------|---------|---------|
| WC games in year 2005? | Activity chart | `amiga_community_stat_facts` (`world_cup` / year) |
| Draw rate at World Cup XVII? | WC hub wing 2 | `amiga_world_cup_stats.draw_rate` |
| Most WC career goals? | WC hub wing 3 | `amiga_player_slice_totals` |
| Most WC career goals by Italy? | WC hub wing 4 | `amiga_country_slice_totals` (when shipped) |
| Who won World Cup XXIII? | `tournament.php` (stages / bracket) | Standings + `gold_player_id` on stats row |

---

## 6. URL and routing

Registered in [`site/public_html/includes/k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php); documented in [`url-routes.md`](url-routes.md).

| Route key | Path | Wing |
|-----------|------|------|
| `amiga-world-cups` | `/amiga/world-cups/chronology/index.php` → Chronology default | 1 |
| `amiga-world-cups-chronology` | `/amiga/world-cups/chronology/index.php` | 1 (list only) |
| `amiga-world-cups-stats` | `/amiga/world-cups/stats/participation.php` | 2 |
| `amiga-world-cups-players` | `/amiga/world-cups/players/honours.php` | 3 default |
| `amiga-world-cups-players-honours` | `…/players/honours.php` | 3 |
| `amiga-world-cups-players-results` | `…/players/results.php` | 3 |
| `amiga-world-cups-players-goals` | `…/players/goals.php` | 3 |

**Per-tournament (not under `world-cups/`):** `amiga-tournament` → `/amiga/tournament.php?id=` — WC list/detail links use `view=event-stats` (existing registry). Helper: `amiga_tournament_event_stats_url()`.

**Player stats (dual surface):** Hub `/amiga/world-cups/players/*` and LB `/amiga/leaderboards/world-cups/*` both stay live — **no 302** between them (WCH9). Shared body: `includes/amiga_wc_players_wing_body.inc.php`.

**Shell:** `amiga/world-cups/world_cups_hub_shell_start.inc.php` + sub-nav include — mirror `games_hub_shell_start.inc.php` / `amiga_lb_nav.php` patterns.

---

## 7. Time travel

| Wing | Behaviour |
|------|-----------|
| **1 — Events** | List tournaments where WC finalized **on or before** cutoff tuple. |
| **2 — Tournament stats** | Rows for WCs in scope at cutoff; column values **do not change** with later `as=` (event-intrinsic). Table may **hide** WCs after cutoff. |
| **3 — Player stats** | `amiga_player_slice_at_event` at cutoff — same as current WC LB (WC11). |
| **4 — Country stats** | `amiga_country_slice_at_event` at cutoff — same pattern as wing 3 (when shipped). |

**Link propagation:** All internal WC hub links append `as=` when active (T4).

**Hub chrome:** Amend T13b to include **World Cups** in TT hub bar when this tab ships (WCH12).

---

## 8. Visual / UX notes

- **Chapter** above sub-nav on each wing — title + one-line lede (realm: Amiga World Cups).
- **Sub-nav** segment track — same component family as Games / Milestones / LB wings.
- **Tint / realm** — Amiga realm chrome only; no Online mirror unless Dagh explicitly asks.
- **Design tokens** — [`design-direction.md`](design-direction.md); tables use standard `k2-table` sort patterns.

---

## 9. Implementation phases (suggested)

| Phase | Deliverable | Depends on |
|-------|-------------|------------|
| **0** | This policy + route registry stubs | — |
| **1** | Wing **2** — `stats.php` table from `amiga_world_cup_stats` | **Done** Jun 2026-23 |
| **2** | Wing **3** + LB wing — shared `amiga_wc_players_*` body on both URL trees | **Done** Jun 2026-23 |
| **3** | Hub tab + shell + sub-nav; wing 1 **list** (intro + table → `tournament.php?view=event-stats`) | Phase 1 read lib |
| **4** | Enrich **`tournament.php`** `view=event-stats` for WCs (`amiga_world_cup_stats` headline block + existing player table) | Phase 1–3 |
| **5** | Activity cross-links; LB chapter lede; TT hub bar amend | Phases 1–3 |
| **6** | Wing **4** — country slice DDL + writers + hub UI | [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md) |

**Out of scope v1:** New writers beyond slice V1, WC HoF rows, histogram UX from community catalog. **Slice V2** (goals texture, DDs, Opponents) — [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md). **Country slice** — policy locked Jun 2026-24; implementation = Phase 6.

---

## 10. Verification (when UI ships)

- Spot-check **23** WC rows in wing 2 match local `prove` oracle.
- TT: wing 3 honours at mid-realm cutoff vs known player — **same** on hub and LB URLs.
- Wing 1 list count at present = row count in `amiga_world_cup_stats`.
- `as=` preserved on sub-nav and table links (both surfaces).
- Hub and LB player-stats pages render identical table body for each sub-wing.

---

## 11. Changed decisions archive

| Earlier idea | Current position |
|--------------|------------------|
| WC stats table under **Activity → World Cups** sub-wing | **Superseded** — wing 2 of this hub ([`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) Option A) |
| WC player stats **only** under Leaderboards | **Dual home** — hub wing 3 + LB wing; one shared body (WCH8–WCH9) |
| LB 302 → hub player paths | **Rejected** — both URL trees stay; shared partial only |
| World Cups tab position after News | **Jun 2026-24:** **third** — after Leaderboards (supersedes “locked second before Leaderboards”); present order aligns TT curated block |
| Separate **`world-cups/event.php`** per WC | **Rejected** — `tournament.php?id=&view=event-stats` only (WCH15) |

---

## 12. Agent checklist (new chat)

1. Read this file + [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) (columns) + [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (player wing rules). **Slice V2:** [`amiga-world-cups-player-slice-v2-policy.md`](amiga-world-cups-player-slice-v2-policy.md). **Country slice:** [`amiga-world-cups-country-slice-policy.md`](amiga-world-cups-country-slice-policy.md).
2. Do **not** put `amiga_world_cup_stats` table on Activity.
3. Do **not** add `world-cups/event.php` — WC rows link to `amiga_tournament_event_stats_url()`.
4. Reuse slice read libs + **`amiga_wc_players_wing_body.inc.php`** for wing 3 and LB — no forked SQL or table markup.
5. Wire `as=` on all three wings from first ship.
6. Update [`url-routes.md`](url-routes.md) + [`hub-ia-agreement.md`](hub-ia-agreement.md) Amiga hub line when PHP lands.
