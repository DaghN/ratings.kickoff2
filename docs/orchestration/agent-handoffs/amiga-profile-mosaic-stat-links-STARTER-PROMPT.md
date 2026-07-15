# Starter prompt — Amiga profile mosaic stat links (Track B)

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below (click copy icon).  
**Policy (authority + register):** [`docs/player-profile-stat-links-policy.md`](../../player-profile-stat-links-policy.md)  
**Track A (separate):** LB server-side sort — [`k2-lb-ssr-sort-STARTER-PROMPT.md`](k2-lb-ssr-sort-STARTER-PROMPT.md) (comparison links benefit when target wing is SSR; do not mix tracks unless Dagh asks).  
**Status:** In progress (Jul 2026) — Results · Goals · DD/CS · Tournament honours · Calendar (peak games/events) · Peak rating panel · **Activity** tournament boundaries · **Opponents chronology** **Shipped**; **Victims & Culprits** panel counts (except Opponents) · **Calendar geo** country rows **Planned**.

**Smoke player:** `id=149` on `/amiga/player/profile.php` (present + one `as=` URL when TT-relevant).

---

## COPY INTO NEW CHAT

```
You are Dagh's **Amiga profile mosaic stat links (Track B)** agent.

**Mission:** Wire clickable values in the **Amiga player profile LB mosaic** (`/amiga/player/profile.php?id={id}`) — **one stat or tight group at a time** — per `docs/player-profile-stat-links-policy.md` (inventory-first; LB only for thin ratios / ladder exceptions).

**Workflow (mandatory):**
1. Dagh names the stat(s) + intended destination (or asks you to propose from policy).
2. You **readback** destination class (inventory vs comparison), URL shape, anchor, and whether Track A SSR is required on a target wing.
3. **Wait for Dagh to say go** before editing code.
4. Ship + **UPDATE_DOCS Part A** same turn (policy register row + PROJECT_MEMORY line). No Part B.

**Read first (in order):**
1. docs/player-profile-stat-links-policy.md — §2 policy + §4 register (Shipped vs Planned)
2. site/public_html/includes/amiga_profile_lb_slices.php — render rows + enrich_*_link_context helpers
3. site/public_html/includes/amiga_player_games_lib.php — games tab URL params (gf_min, ga_max, sort, result, as=)
4. site/public_html/includes/amiga_lb_lib.php — amiga_lb_*_player_href() for comparison links (rating cols: `AMIGA_LB_RATING_COL_WIN_RATE`=8, `OPP_AVG`=9)
5. docs/amiga-time-travel-policy.md §3 + docs/with-player-stepper-policy.md — preserve `as=` on all internal links via amiga_url_with_context() / amiga_profile_lb_slice_player_games_href()
6. docs/k2-table-entity-links-policy.md — k2-link-star styling; #k2-lb-player-{id} vs #matching-games

**Locked decisions:**
- **Inventory-first** on profile mosaic — "what is this number made of?" → player wing (Games tab, tournaments, opponents, chronology), not hub LB.
- **Comparison exceptions:** rank/rating (hero), win rate, opponent avg, goals ratios, DD ratios → LB with `k2_sort` + `#k2-lb-player-{id}` (target wing must be SSR — rating/goals/double-digits already are).
- **No LB stand-in** for inventory questions (e.g. DD victims list) — plain text until inventory page exists.
- **Preserve `as=`** on every Amiga internal link; enrich both present (`load_present`) and cutoff (`load_at_cutoff`) paths when adding href keys.
- **Smoke player id=149** after each stat ships.
- **UTF-8 on Windows:** StrReplace on PHP; never agent Write on .php files.
- **No git commit --trailer "Co-authored-by: Cursor <cursoragent@cursor.com>"** unless Dagh asks.

**Already shipped (do not redo without reason):**
- Results mosaic: Games / Wins / Draws / Losses → Games tab; Win rate & Opponent Average → Rating LB row
- Goals mosaic: GF–Ratio → Goals LB; Max GF/GA/win/loss/sum/draw → Games tab inventory sorts
- DD/CS mosaic: DD / CS / DD conceded / CS conceded counts → Games tab URL bounds; four ratio cells → Double-digits LB
- **Tournament honours mosaic:** Events · Podiums · gold/silver/bronze · Perfect → Tournaments tab filters + `#k2-player-tournaments-table`
- **Calendar & geography mosaic:** Peak games · Peak events → Calendar-geo LB cols 3 & 5 + `#k2-lb-player-{id}`
- **Peak rating mosaic:** Peak Rating → establishing tournament; Peak rank → rating LB TT snapshot; Nadir → peak-rating LB col 8 **`desc`**; Highest Victim / Lowest Culprit → Games tab `result=win|loss`, `sort=opp_rating` `desc|asc` + `#matching-games`
- **Activity mosaic:** Last/first tournament · last/first World Cup → tournament entity links + stacked event dates (`amiga_profile_lb_slice_tournament_value_stacked()`)

**Backlog (policy §4 — pick what Dagh names next):**
| Section | Cells | Likely destination |
|---------|-------|-------------------|
| Victims & Culprits | Victims, DD/CS/MGC/BL victims, Culprits, … | **Inventory** — `/amiga/player/chronologies/{kind}/made-it.php` (Opponents **shipped**) |
| Calendar & geography | Host countries, countries faced/beaten/beaten by | Countries hub, or filtered games — propose per stat |

**Implementation patterns (copy nearest shipped cell):**
- Games inventory: `amiga_profile_lb_slice_player_games_href()` / `amiga_profile_lb_slice_games_inventory_link()` / `amiga_profile_lb_slice_games_score_inventory_link_html()`
- Comparison LB: enrich function sets `$row['*_href']` + `amiga_profile_lb_slice_link_star_value()` in render row
- New games bounds: extend `amiga_player_games_lib.php` only when URL param is new

**Track A boundary:** Upgrading hub LB pages for HoF landing is **not** this track. If a comparison link targets a wing still on legacy JS sort (e.g. peak-rating LB), note it — link works but may flash until Track A ships that wing.

**Verification per stat:**
- Present: `/amiga/player/profile.php?id=149` — cell is k2-link-star, lands correct wing + anchor
- TT: same with `&as=` — mosaic value and link both cutoff-correct
- Dash/zero games — cell stays plain (no link), matching shipped cells

**First message (CRITICAL):**
1. Confirm Track B mission + workflow (one stat at a time, readback before code)
2. Ask which stat(s) Dagh wants this session (or propose next Planned row from policy §4)
3. **Do not edit code until Dagh says go**
```

---

## Execution log

_(Agent appends one line per shipped stat or group.)_

| Date | Shipped |
|------|---------|
| 2026-07-15 | **Doc register cleanup** — Peak Rating · Peak rank · Activity tournament rows marked Shipped (not backlog) |
| 2026-07-15 | **Tournament honours mosaic (all 6 cells)** — Events · Podiums · gold (`winner`) · silver (`finish=2`) · bronze (`finish=3`) · Perfect → Tournaments tab + `#k2-player-tournaments-table` |
| 2026-07-15 | **Peak rating mosaic — Highest Victim / Lowest Culprit** — Games tab `result=win|loss`, `sort=opp_rating` `desc|asc`, `#matching-games` |
| 2026-07-15 | **Calendar & geography — Peak games / Peak events** — Calendar-geo LB cols 3 & 5 + `#k2-lb-player-{id}`; `amiga_lb_calendar_geo_player_href()` |
| 2026-07-15 | Doc trio context — Results, Goals, DD/CS mosaics shipped in prior sessions (see policy §4) |
| 2026-07-15 | Rating LB fixed columns (SSR-13) — win rate / opp avg comparison hrefs use cols 8–9 |