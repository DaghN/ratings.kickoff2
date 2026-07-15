# Player profile stat links policy

**Status:** **Policy locked** (Jul 2026). Hero Games links realigned to inventory (online + Amiga).

**Authority:** Product intent for **clickable career numbers on player profiles** (online + Amiga). Dagh's latest chat wins on scope. Visual link styling: [`design-direction.md`](design-direction.md). Link mechanics (row anchors, `k2_sort`, entity name helpers): [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md). Profile layout: [`player-profile-feast.md`](player-profile-feast.md) · Amiga: [`amiga-profile-v0.md`](amiga-profile-v0.md).

**For agents:** read this **before** choosing where a **player profile hero stat** or **profile LB mosaic value** should link. Do not default profile stats to leaderboard row anchors unless this doc says so.

---

## 1) The friction

A single career number can answer two different user questions:

| Question | Mode | Natural home |
|----------|------|----------------|
| **“What is this number made of?”** | **Inventory** | Player-scoped list, filter, chronology, or detail inside the player wing |
| **“Where does this player rank?”** | **Comparison** | Leaderboards mini-universe (sort column + player row) |

Both are valid. Forcing every profile stat through the leaderboard forces an awkward journey — **out** of the player story to compare, then **back in** to see the actual content (e.g. “who are my DD victims?”).

Profile pages are **rich inventory surfaces**. Leaderboards are the **comparison surface**. The product should not train users to treat every stat click as “take me to the ladder.”

---

## 2) Chosen policy

**On player profiles (hero + LB mosaic below it): primary links serve inventory.**

- Clicking a stat opens the **player-specific content** that comprises that number — games list, tournament history, opponent/victim chronology, filtered wing view, establishing game, etc.
- Users who want **ladder context** start in the **Leaderboards** hub (realm mini-universe) and sort there. That is the deliberate home for comparison.

**One rule everywhere on the profile:** same stat → same intent → same destination class (inventory vs comparison), whether the number appears in the hero or in the mosaic tables.

---

## 3) Exceptions (still comparison or no link)

| Case | Rule |
|------|------|
| **Rank, rating (hero)** | **Leaderboard row** — these numbers *are* ladder claims, not inventories. Link to the rating wing at `#k2-lb-player-{id}` (Amiga: preserve `as=`). |
| **Derived ratio / average with no list** | **No link** until a meaningful inventory exists (e.g. goals per game). **Leaderboard-only is acceptable when inventory is genuinely thin** — e.g. **win rate** → Rating LB Win rate column (comparison, not inventory). |
| **Inventory not built yet** | **No link** or plain text until the inventory page ships — do not send users to LB as a stand-in when the user's question is clearly inventory (e.g. DD victims chronology). |

Secondary comparison paths (opening the LB from inside inventory, HoF deep links, Elo column on hub tables) stay on [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) — those are **not** profile hero/mosaic stats.

---

## 4) Implementation register

**Status key:** **Shipped** = matches policy · **Drift** = live code differs · **Planned** = policy target, not wired · **—** = no link by policy

### Amiga player profile

| Stat / value | Policy (primary) | Status | Notes |
|--------------|------------------|--------|-------|
| Rank | Rating LB row | **Shipped** | Ladder exception |
| Rating | Rating LB row | **Shipped** | Ladder exception |
| Games | Games tab (`amiga-player-games`) | **Shipped** | Hero `#matching-games` (above list status) |
| Events | Tournaments tab | **Shipped** | Hero `#k2-player-tournaments-table` (above list status) |
| World Cups | Tournaments tab, WC filter | **Shipped** | Same status anchor + WC filter |
| Mosaic — Games | Games tab | **Shipped** | Same as hero — `#matching-games`; `amiga_profile_lb_slice_player_games_href()` |
| Mosaic — Wins | Games tab, Result = win | **Shipped** | `?result=win` activates Result listbox + filtered list; `#matching-games` |
| Mosaic — Draws | Games tab, Result = draw | **Shipped** | `?result=draw` |
| Mosaic — Losses | Games tab, Result = loss | **Shipped** | `?result=loss` |
| Mosaic — Win rate | Rating LB, Win rate column | **Shipped** | SSR `k2_sort` (col 7/8) + `#k2-lb-player-{id}`; `amiga_lb_rating_win_rate_player_href()` |
| Mosaic — Opponent Average | Rating LB, Opponent Average column | **Shipped** | SSR `k2_sort` (col 8/9) + `#k2-lb-player-{id}`; `amiga_lb_rating_opponent_avg_player_href()` |
| Mosaic — GF · GA · GF/g · GA/g · GD/g · Ratio | Goals LB (cols 4–9) | **Shipped** | SSR sort + `#k2-lb-player-{id}`; GA/g `asc`; `amiga_lb_goals_player_href()` |
| Mosaic — Max GF | Games tab, sort GF desc | **Shipped** | Inventory — peak-scoring game(s); `?sort=goals_for&dir=desc` + `#matching-games`; `amiga_profile_lb_slice_player_games_href()` |
| Mosaic — Max GA | Games tab, sort GA desc | **Shipped** | `?sort=against&dir=desc` + `#matching-games` |
| Mosaic — Max win | Games tab, Result = win, sort GD desc | **Shipped** | `?result=win&sort=diff&dir=desc` + `#matching-games` |
| Mosaic — Max loss | Games tab, Result = loss, sort GD asc | **Shipped** | `?result=loss&sort=diff&dir=asc` + `#matching-games` |
| Mosaic — Max sum | Games tab, sort Sum desc | **Shipped** | `?sort=sum&dir=desc` + `#matching-games` |
| Mosaic — Max draw | Games tab, Result = draw, sort Sum desc | **Shipped** | `?result=draw&sort=sum&dir=desc` + `#matching-games` |
| Mosaic — Double Digits | Games tab, hero GF ≥ 10, id desc | **Shipped** | URL-only `?gf_min=10` + `#matching-games` (no listbox); default sort |
| Mosaic — Clean Sheets | Games tab, hero GA = 0, id desc | **Shipped** | URL-only `?ga_max=0` + `#matching-games` |
| Mosaic — DD conceded | Games tab, hero GA ≥ 10, id desc | **Shipped** | URL-only `?ga_min=10` + `#matching-games` |
| Mosaic — CS conceded | Games tab, hero GF = 0, id desc | **Shipped** | URL-only `?gf_max=0` + `#matching-games` |
| Mosaic — DD Ratio | Double-digits LB col 6 | **Shipped** | SSR `k2_sort` + `#k2-lb-player-{id}`; `amiga_lb_double_digits_player_href()` |
| Mosaic — CS Ratio | Double-digits LB col 7 | **Shipped** | SSR sort + player row anchor |
| Mosaic — DD C Ratio | Double-digits LB col 10 | **Shipped** | SSR sort + player row anchor |
| Mosaic — CS C Ratio | Double-digits LB col 11 | **Shipped** | SSR sort + player row anchor |
| Mosaic — Events, podiums, medals | Tournament history / filtered views | **Planned** | Mostly plain text today |
| Mosaic — DD Victims, CS Victims, … | Player victim/culprit chronology (per-type list, first occurrence order) | **Planned** | Inventory-first; not LB |
| Mosaic — GF, DD count, opponents, … | Filtered games or dedicated lists where they exist | **Planned** / **—** | Other ratios → no link until inventory exists |
| Mosaic — Peak rating / peak rank values | Establishing tournament or event context | **Partial** | Peak cells may link; rank comparison stays LB-adjacent only where explicitly ladder |

### Online player profile

| Stat / value | Policy (primary) | Status | Notes |
|--------------|------------------|--------|-------|
| Rank | Rating LB row | **Shipped** | Ladder exception |
| Rating | Rating LB row | **Shipped** | Ladder exception |
| Games | Games tab (`player-games`) | **Shipped** | Hero `#matching-games` (above list status) |
| Milestones (hero tiers) | Milestones garden tier anchors | **Shipped** | Inventory |
| Mosaic / feast blocks | Same inventory rule as Amiga where parallels exist | **Mixed** | See [`player-profile-feast.md`](player-profile-feast.md) |

Update this table when a stat gains a link or a new inventory page ships.

---

## 5) Agent habits

1. **Ask:** “What question does this click answer?” Inventory → player wing. Comparison → leaderboards (usually **not** from profile stats).
2. **Preserve time travel:** Amiga inventory links use `amiga_url_with_context()` / wing routes that carry `as=`.
3. **Do not** add LB row links to mosaic values “because HoF does it” — HoF lives in the comparison universe.
4. **SSR sort + `#k2-lb-player-{id}`** remains valid for **leaderboard** and **hub** drill-downs ([`k2_table_helpers.php`](../site/public_html/includes/k2_table_helpers.php) `k2_lb_sql_order_from_sort`) — not the default for profile stat clicks under this policy. Target wing SSR = Track A ([`k2-lb-ssr-sort-policy.md`](k2-lb-ssr-sort-policy.md)).
5. **Track B execution handoff:** [`orchestration/agent-handoffs/amiga-profile-mosaic-stat-links-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-profile-mosaic-stat-links-STARTER-PROMPT.md) — one stat at a time; readback before code.

---

*Last updated: Jul 2026 — inventory-first on player profile; leaderboards for comparison; rank/rating remain ladder exceptions.*