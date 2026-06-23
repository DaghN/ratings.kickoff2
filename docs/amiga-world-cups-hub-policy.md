# Amiga World Cups hub — policy

**Status:** **Wing 2 shipped** (Jun 2026-23) — five stats sub-wings (Goals · DDs & CSs · Participation · Geography · Podium) from `amiga_world_cup_stats`. Geography step 2 (`international_games` / share) TBD. Wing 1 list + wing 3 player LB wire + **`tournament.php` event-stats enrichment** TBD.

**Parent:** [`hub-ia-agreement.md`](hub-ia-agreement.md) · [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) · [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) · [`amiga-community-stats-policy.md`](amiga-community-stats-policy.md)

**Related:** [`url-routes.md`](url-routes.md) · [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) · [`design-direction.md`](design-direction.md) · [`amiga-community-stats-question-catalog.md`](amiga-community-stats-question-catalog.md)

**Supersedes:** [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) § UI placement Option **A** (Activity sub-wing) — event-grain WC stats live under this hub wing 2 instead.

---

## 1. Executive summary

Promote **World Cups** from a single Leaderboards wing into a **dedicated Amiga hub tab** — a small **universe** with three foldered wings:

| Wing | Label (product) | Grain | Primary data |
|------|-----------------|-------|--------------|
| **1 — Events** | **World Cups** (default) | One WC tournament | WC **list** on hub; per-event detail on **`tournament.php`** |
| **2 — Tournament stats** | **Tournament stats** | One row per WC | `amiga_world_cup_stats` |
| **3 — Player stats** | **Player stats** | Player × WC career | `amiga_player_slice_*` (`slice_key = 'world_cup'`) |

**Mental model:** *Which World Cups happened?* → *How wild was each one?* → *Who dominated across them?*

Realm-wide **calendar-year** WC trends (community facts + charts) stay on **Activity** — they answer a different question (*how much WC activity in year Y across the whole realm?*).

---

## 2. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **WCH1** | **Top-level hub tab** | **World Cups** is its own Amiga hub tab — not only a Leaderboards wing. |
| **WCH2** | **Present hub order** | **News · World Cups · Leaderboards · Tournaments · Activity · Hall of Fame · Live tournaments** (last). **News** remains present landing. World Cups is **second** — signature events lane before generic ladder. |
| **WCH3** | **Foldered sub-hub** | List/aggregate wings under `/amiga/world-cups/` — segment sub-nav (not `?view=` **on hub pages**). Per-tournament drill-down stays on **`tournament.php`** (`view=` = entity section — [`url-routes.md`](url-routes.md)). |
| **WCH4** | **Three wings** | **Events · Tournament stats · Player stats** — fixed v1 set; more wings only after explicit product pass. |
| **WCH5** | **Default wing** | `/amiga/world-cups/` → **Events** (`index.php` or `events.php`); folder index may 302 to default. |
| **WCH6** | **Event stats home** | Sortable **`amiga_world_cup_stats`** table lives on **wing 2** — **not** under Activity. |
| **WCH7** | **Activity WC charts** | Shipped community **year** WC charts (**Q-WC-001**–**003**, **006**–**007**, **011**) stay on **Activity** (realm pulse). Wing 2 may **link** to them (“Realm trends in Activity →”). |
| **WCH8** | **Player stats canonical** | Wing 3 is the **canonical** home for WC player leaderboards (Honours · Results · Goals). **One PHP implementation** — shared table partials. |
| **WCH9** | **LB entry point** | **Leaderboards → World Cups** remains a **ranking entry**: chapter lede + link to World Cups hub wing 3, and/or **one** thin sub-wing (Honours default). **No second table builder.** |
| **WCH10** | **Milestones pattern** | Same as Milestones hub + `leaderboards/milestones.php`: fat universe hub + thin LB pointer — not two divergent products. |
| **WCH11** | **Time travel** | Wings **2** and **3** respect `as=` from first UI ship. Wing **1** event list = tournaments finalized on or before cutoff; per-event stats rows are **intrinsic** (fixed at finalize — see WC table plan § time travel). |
| **WCH12** | **TT hub bar** | When `as=` active, World Cups tab **included** in time-travel hub bar (snapshot-worthy). Present-only tabs still **News**, **Live tournaments** per [`amiga-time-travel-policy.md`](amiga-time-travel-policy.md) T13. **Proposed TT order:** **Leaderboards · World Cups · Activity · Hall of Fame** (amend T13b when wing ships). |
| **WCH13** | **WC detection** | `amiga_tournament_is_world_cup()` / name `^World Cup\s+\S` — same as slice, honours, writers. |
| **WCH14** | **No new stored truth** | This hub is **read surfaces** only unless a future slice adds columns — writers already finalized. |
| **WCH15** | **One tournament URL** | **No** parallel `/amiga/world-cups/event.php`. Every WC (and every tournament) deep-links to **`/amiga/tournament.php?id={tournament_id}`** — use existing `view=` modes (`event-stats`, `stages`, `games`). Build `amiga_tournament_event_stats_url()` / `amiga_tournament_href()` — do not invent new paths per event. |

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

### 4.1 Wing 1 — Events (default)

**Question:** *Which World Cups have we played, and where is each one?*

Wing 1 is the **WC catalog index** only. It does **not** host a second copy of the tournament page.

| Element | v1 | v2+ |
|---------|----|-----|
| **Intro** | Short chapter lede (`k2_hub_chapter.inc.php`) — what a World Cup is in this realm | Optional era copy, COVID note |
| **List** | Sortable table — one row per WC: year, name, host, `rated_games`, `goals_per_game`, podium names/links | Filters by decade / host nation |
| **Row link** | **`/amiga/tournament.php?id={tournament_id}&view=event-stats`** — via `amiga_tournament_event_stats_url($id)` + `amiga_tournament_href()` (preserves `as=`). Optional `#tournament` anchor for scroll. **Default WC landing tab** — already first in WC tournament nav. | Same URL; richer tab content |
| **Per-event richness** | **Enrich `tournament.php`** — especially **`view=event-stats`** (realm row from `amiga_world_cup_stats` + existing per-player event stats). Bracket / stages / games = other `view=` tabs on the **same** `id`. | Extrema game links, podium narrative on existing tabs |

**Do not create** `/amiga/world-cups/event.php` or any other tournament-id route (WCH15).

**List data sources:** `amiga_world_cup_stats` joined to `tournaments` (sort default: `event_date` desc). Rows only for finalized WCs present in stats table.

**Empty / TT:** At cutoff, list only WCs with `event_date`/`chrono`/`id` ≤ cutoff (tournament finalized and in scope). Pre-first-WC cutoff → empty state + copy. List links still use `tournament.php` + `as=`; tournament page applies cutoff reads.

### 4.2 Wing 2 — Tournament stats

**Question:** *How wild / big / diverse was each World Cup?*

| Element | Rule |
|---------|------|
| **Sub-wings** | **Goals** (default) · **DDs & CSs** · **Participation** · **Geography** · **Podium** — inner nav under Tournament stats |
| **Anchor columns** | Every table: **Tournament** (link) · **Year** · **Players** · **Games** — no date/host/city on stats tables |
| **Row key** | `tournament_id` |
| **Links** | Tournament → `tournament.php?id=&view=event-stats` (WCH15); goal peaks → clickable value → `game.php?id=` |
| **Draw texture** | **Draw %** only in UI (not draw/decided counts or decided rate) |
| **Blowout %** | Read-time from `blowout_games ÷ rated_games` until optional stored column |
| **Geography step 2** | Add `international_games` + `international_game_share` (writer + DDL) — step 1 ships stored cols only |
| **Podium v2** | Full WC placement table (all finish positions) — future; v1 = gold/silver/bronze only |
| **Realm trends** | Footer link → Activity WC year charts |

**Stored truth:** `amiga_world_cup_stats` only — no live aggregation from `amiga_games` on hot path.

Column layout per sub-wing: [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) §3.13.

### 4.3 Wing 3 — Player stats

**Question:** *Who has the best World Cup career?*

| Element | Rule |
|---------|------|
| **Sub-wings** | **Honours · Results · Goals** — same three as shipped LB ([`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) WC1) |
| **Paths** | `/amiga/world-cups/players/honours.php` (default), `results.php`, `goals.php` |
| **Read lib** | Existing slice read libs — `amiga_player_slice_*` at cutoff |
| **Eligibility / points / averages** | Unchanged — WC2–WC8 in LB policy |

**Migration:** Move or alias current `/amiga/leaderboards/world-cups/*` to canonical paths; LB wing becomes **redirect or thin shell** pointing here (WCH9).

---

## 5. Boundaries with neighbouring surfaces

| Surface | Relationship |
|---------|----------------|
| **Activity** | **Keeps** all community stat wings + **year-level** WC charts (Q-WC-001–003, 006–007, 011). **Does not** host per-WC stats table. |
| **Leaderboards** | **Rating** and general wings unchanged. **World Cups** LB wing → entry to hub wing 3 (WCH9). |
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
| Who won World Cup XXIII? | `tournament.php` (stages / bracket) | Standings + `gold_player_id` on stats row |

---

## 6. URL and routing

Registered in [`site/public_html/includes/k2_amiga_routes.php`](../site/public_html/includes/k2_amiga_routes.php); documented in [`url-routes.md`](url-routes.md).

| Route key | Path | Wing |
|-----------|------|------|
| `amiga-world-cups` | `/amiga/world-cups/` → Events default | 1 |
| `amiga-world-cups-events` | `/amiga/world-cups/index.php` | 1 (list only) |
| `amiga-world-cups-stats` | `/amiga/world-cups/stats.php` | 2 |
| `amiga-world-cups-players` | `/amiga/world-cups/players/honours.php` | 3 default |
| `amiga-world-cups-players-honours` | `…/players/honours.php` | 3 |
| `amiga-world-cups-players-results` | `…/players/results.php` | 3 |
| `amiga-world-cups-players-goals` | `…/players/goals.php` | 3 |

**Per-tournament (not under `world-cups/`):** `amiga-tournament` → `/amiga/tournament.php?id=` — WC list/detail links use `view=event-stats` (existing registry). Helper: `amiga_tournament_event_stats_url()`.

**Legacy:** `/amiga/leaderboards/world-cups/*` → **302** to matching `/amiga/world-cups/players/*` (query preserved, including `as=`).

**Shell:** `amiga/world-cups/world_cups_hub_shell_start.inc.php` + sub-nav include — mirror `games_hub_shell_start.inc.php` / `amiga_lb_nav.php` patterns.

---

## 7. Time travel

| Wing | Behaviour |
|------|-----------|
| **1 — Events** | List tournaments where WC finalized **on or before** cutoff tuple. |
| **2 — Tournament stats** | Rows for WCs in scope at cutoff; column values **do not change** with later `as=` (event-intrinsic). Table may **hide** WCs after cutoff. |
| **3 — Player stats** | `amiga_player_slice_at_event` at cutoff — same as current WC LB (WC11). |

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
| **2** | Wing **3** — move LB PHP to `world-cups/players/*`; LB stub + 302 | Slice libs shipped ✓ |
| **3** | Hub tab + shell + sub-nav; wing 1 **list** (intro + table → `tournament.php?view=event-stats`) | Phase 1 read lib |
| **4** | Enrich **`tournament.php`** `view=event-stats` for WCs (`amiga_world_cup_stats` headline block + existing player table) | Phase 1–3 |
| **5** | Activity cross-links; LB chapter lede; TT hub bar amend | Phases 1–3 |

**Out of scope v1:** New writers, WC HoF rows, DDs/CSs player sub-wing (LB policy V2), histogram UX from community catalog.

---

## 10. Verification (when UI ships)

- Spot-check **23** WC rows in wing 2 match local `prove` oracle.
- TT: wing 3 honours at mid-realm cutoff vs known player.
- Wing 1 list count at present = row count in `amiga_world_cup_stats`.
- `as=` preserved on sub-nav and table links.
- Legacy LB URLs 302 correctly.

---

## 11. Changed decisions archive

| Earlier idea | Current position |
|--------------|------------------|
| WC stats table under **Activity → World Cups** sub-wing | **Superseded** — wing 2 of this hub ([`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) Option A) |
| WC player stats **only** under Leaderboards | **Canonical** home moves to hub wing 3; LB keeps entry (WCH9) |
| World Cups as **first** tab after News vs second | **Locked second** (WCH2) — after News, before Leaderboards |
| Separate **`world-cups/event.php`** per WC | **Rejected** — `tournament.php?id=&view=event-stats` only (WCH15) |

---

## 12. Agent checklist (new chat)

1. Read this file + [`amiga-world-cup-stats-table-plan.md`](amiga-world-cup-stats-table-plan.md) (columns) + [`amiga-world-cups-leaderboard-policy.md`](amiga-world-cups-leaderboard-policy.md) (player wing rules).
2. Do **not** put `amiga_world_cup_stats` table on Activity.
3. Do **not** add `world-cups/event.php` — WC rows link to `amiga_tournament_event_stats_url()`.
4. Reuse slice read libs for wing 3 — no forked SQL.
5. Wire `as=` on all three wings from first ship.
6. Update [`url-routes.md`](url-routes.md) + [`hub-ia-agreement.md`](hub-ia-agreement.md) Amiga hub line when PHP lands.
