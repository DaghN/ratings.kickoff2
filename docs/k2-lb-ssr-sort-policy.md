# K2 leaderboard server-side sort (SSR) — policy

**Status:** **Planned** (Jul 2026). Track **A** — leaderboard **page** upgrades only.

**Authority:** Dagh's latest message → this doc → [`k2-lb-ssr-sort-implementation-plan.md`](k2-lb-ssr-sort-implementation-plan.md). Table stack contract: [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md). Deep-link column indices (HoF): [`amiga_records_hof_links.php`](../site/public_html/includes/amiga_records_hof_links.php) (Amiga) · [`records_hof_links.php`](../site/public_html/includes/records_hof_links.php) (online). **Profile mosaic link wiring** is **Track B** — [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) — not this track.

**For agents:** read this **before** upgrading any hub leaderboard wing for URL landing sort. Copy the shipped reference wings; do not invent a parallel sort mechanism.

---

## 1) Purpose

Hub leaderboard wings use `k2-table.js` for column sort. When a user lands with `?k2_sort=` / `?k2_dir=` (Hall of Fame deep links, profile comparison links, filter toggles that preserve sort), **legacy wings** highlight the right header but still load rows in the wing's **default SQL order**, then let JavaScript re-sort on first paint — wrong order flash and wrong rank numbers until JS runs.

**Track A** makes the **destination page** apply the URL sort in **SQL `ORDER BY`** on first paint, and skip the redundant client reorder when SSR already matched the URL.

**User-visible win:** HoF and other deep links open the table **already sorted** — stable ranks, no flash.

---

## 2) Locked decisions

| ID | Decision |
|----|----------|
| **SSR-1** | **URL owns first paint.** When the request carries `k2_sort` (and optional `k2_dir`), the wing's row query uses `k2_lb_sql_order_from_sort()` so PHP row order matches the URL before HTML is sent. |
| **SSR-2** | **Skip initial JS sort when SSR applied.** Table attr via `k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $defaultCol, $defaultDir, $lbSqlOrder['ssr_applied_url_sort'])` — not bare `k2_table_skip_initial_sort_attr()` when URL sort was honoured. |
| **SSR-3** | **Column map = `<th>` index.** Each wing defines `*_order_column_map(): array<int, string>` (0-based, matches `k2_lb_th` column index → SQL expression **without** direction). Tie-break with the wing's existing default `ORDER BY` clause (usually rating DESC, id ASC). |
| **SSR-4** | **Default view unchanged.** No `k2_sort` in URL → same default column, same SQL order, same skip-initial-sort behaviour as today. |
| **SSR-5** | **After landing, header clicks stay client-only.** Column header clicks reorder in `k2-table.js` without reload; URL is **not** updated on click (existing contract). |
| **SSR-6** | **Incoming link generators are not rewritten per slice.** HoF (`amiga_records_hof_lb_href` / `records_hof_lb_href`) already emit `k2_sort`, `k2_dir`, and `#k2-lb-table`. Upgrading the wing fixes landing. **Verify** HoF column index parity per wing — do not bulk-edit HoF unless a map bug is found. |
| **SSR-7** | **Slice size ~5 tables** per session for Dagh manual QA. One implementation plan slice = one QA batch unless Dagh says otherwise. |
| **SSR-8** | **Amiga time travel.** SSR sort must work on present **and** cutoff (`as=`) read paths — same column map; ORDER BY appended to the wing's existing snapshot/career query helper. |
| **SSR-9** | **Read-time only.** No DDL, no stored-truth / post-game / ops changes. Part B of UPDATE_DOCS does **not** apply to this track. |
| **SSR-10** | **Track B is separate.** Wiring profile mosaic cells (`amiga_lb_*_player_href`, games inventory links) ships in [`player-profile-stat-links-policy.md`](player-profile-stat-links-policy.md) — optional in the same slice only when Dagh asks; not required for HoF parity. |
| **SSR-11** | **Reference implementations (copy first).** Amiga career: `amiga/leaderboards/goals.php` + `amiga_lb_goals_order_column_map()` in `amiga_lb_lib.php`. Also: `rating.php`, `double-digits.php`. Online: `leaderboards/activity/peaks.php`. |
| **SSR-12** | **Player row anchors.** HoF uses `#k2-lb-table` (table top). Profile comparison uses `#k2-lb-player-{id}`. Do **not** change HoF anchors to player rows as part of Track A unless Dagh opens a separate product slice. |

---

## 3) Behaviour model

```text
Request with ?k2_sort=N&k2_dir=desc
        │
        ▼
k2_lb_table_sort_state($defaultCol)  →  header emphasis + sort_col/dir
        │
        ▼
k2_lb_sql_order_from_sort($lbSort, $columnMap, $defaultOrder)
        │
        ├─ URL sort + mapped col  →  ORDER BY expr DIR, default tiebreak  (ssr_applied_url_sort=true)
        └─ no URL / unmapped col  →  default ORDER BY only               (ssr_applied_url_sort=false)
        │
        ▼
SQL query returns rows in final order
        │
        ▼
k2_lb_table_skip_initial_sort_attr_for_ssr(...)  →  data-k2-skip-initial-sort="1" when SSR applied
        │
        ▼
First paint: correct order + correct rank column
        │
        ▼
User clicks another <th>  →  k2-table.js client reorder only (no navigation)
```

**Not in scope:** server-side sort on **player Games** tab (already server-sorted), catalog tables, tournament standings, highlights boards.

---

## 4) Incoming deep links (inventory by authority)

| Source | File | Anchor | Track A action |
|--------|------|--------|----------------|
| Amiga HoF career + WC LB rows | `amiga_records_hof_links.php` | `#k2-lb-table` | Verify metrics for upgraded wing; **no href rewrite** |
| Online HoF LB rows | `records_hof_links.php` | `#k2-lb-table` | Same |
| Amiga profile hero rank/rating | `amiga_player_hero.php` | `#k2-lb-player-{id}` | Benefits when rating wing SSR (already shipped) |
| Amiga profile mosaic comparison | `amiga_profile_lb_slices.php` | `#k2-lb-player-{id}` | Track B — wire per stat register |
| Online profile hero | `player_hero.php` | `#k2-lb-player-{id}` | Benefits when online rating SSR ships |
| Elo cells on LB tables | `k2_amiga_lb_rating_cell_link` | rating wing | Benefits when target SSR shipped |
| LB include toggles | `k2-table.js` | same page | Preserves `k2_sort` on reload — automatic after SSR |

Single-game HoF rows → **Games highlights** — out of scope.

---

## 5) Wing register (SSR status)

**Shipped (Amiga career):** `rating.php`, `goals.php`, `double-digits.php`, `victims.php`, `peak-rating.php`, `tournament-honours.php`, `calendar-geo.php`, `performance-rating/best.php`

**Shipped (online):** `leaderboards/activity/peaks.php`

**Legacy (Track A backlog):** see implementation plan § Wing register.

---

## 6) Rejected alternatives

| Alternative | Why rejected |
|-------------|--------------|
| Rewrite all HoF hrefs each slice | Generators already correct; page was the gap |
| Make `<th>` into `<a href>` links | Different product; Games tab pattern not hub LB |
| Update URL on every header click | Would reload pages; breaks filter toggles and TT |
| One mega-PR for all ~25 wings | No manual QA gate; Dagh asked for ~5-table slices |
| Fold profile mosaic wiring into every SSR slice | Track B has its own policy register and inventory-first rules |

---

## 7) Out of scope

- Track B profile mosaic stat links (except when Dagh explicitly bundles a stat in a slice)
- Changing HoF anchor from table to record-holder row
- Online/Amiga DDL or aggregate tables
- `k2-table.js` behaviour change for post-land header clicks
- WC **events** catalog / chronology tables (different sort contract)
- Player games, realm games vault, victims/culprits chronology inventory pages

---

## 8) Verification habit (per wing)

1. Open wing with **no** query params — default sort unchanged.
2. Open HoF row (or constructed URL) with `k2_sort` + `k2_dir` — top row matches metric; **no** visible re-sort flash.
3. Click a different column header — client reorder works; no full reload.
4. Amiga: repeat one URL with `as=` cutoff — order still correct.
5. If wing has include toggles (`provisional`, pool filters) — toggle preserves sort on reload.

---

## 9) Related docs

- Plan + slices: [`k2-lb-ssr-sort-implementation-plan.md`](k2-lb-ssr-sort-implementation-plan.md)
- Starter prompt: [`orchestration/agent-handoffs/k2-lb-ssr-sort-STARTER-PROMPT.md`](orchestration/agent-handoffs/k2-lb-ssr-sort-STARTER-PROMPT.md)
- K2 table stack: [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md)
- Entity links / row anchors: [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md)