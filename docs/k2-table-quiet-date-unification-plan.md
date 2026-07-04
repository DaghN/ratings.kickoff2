# K2 table quiet date — shipped reference

**Status:** **Complete** (Jul 2026). Slices C, A, B-js, B, B′ plus player tournament history, live tournament index, and legacy JS removal.

**In one line:** Shared tooling **switches off first-load date blast** when a table must open Date-sorted and **ID default is not available**; otherwise prefer ID sort and skip the tool.

Policy: [`k2-table-quiet-date-column-policy.md`](k2-table-quiet-date-column-policy.md).

---

## Delivered

1. **Mitigation chain:** ID default first → quiet date fallback → normal blast when user chooses Date.
2. **Shared helpers** in `k2_table_helpers.php` + **`k2-table-col-quiet-date`** CSS, gated on **default sort context only** (QD3, QD8).
3. **Client-sort JS:** `data-k2-quiet-default-sort-cols` + `_k2SortUserChosen` in `k2-table.js`.
4. **Removed** legacy `data-k2-quiet-sort-cols` / `isQuietSortCol()` (Jul 2026).
5. **Removed** wrong always-on quiet on Amiga player games when user sorts by Date (B′).

**Non-goals (unchanged):** quiet every Date column; suppress user-chosen Date sort; change Peak date / Last event date LBs.

---

## Product choices (locked)

| Question | Decision |
|----------|----------|
| What problem? | **First-load date blast** — active-sort styling on open when default sort is Date |
| First mitigation? | **ID default** when game ID column exists |
| Fallback tool? | **Quiet date** — default-load-only body mute when must open Date-sorted without ID |
| User sorts by Date? | **Normal blast** — emphasis warranted |
| Special date LBs? | **Normal blast** when sorted (Peak date, etc.) |
| CSS class | **`k2-table-col-quiet-date`** |
| Header on default Date? | **Normal** sort chrome (body quiet only) |

---

## Tooling (reference)

### PHP (`includes/k2_table_helpers.php`)

| Helper | Role |
|--------|------|
| `k2_table_is_default_client_sort_view()` | No `k2_sort` in URL (client-sort tables) |
| `k2_table_is_default_server_sort_view($sortQueryKey)` | No user `sort` override (server-sort tables) |
| `k2_table_sort_col_for_emphasis($col, $activeSortCol, $quietDateCols, $isDefaultSortView)` | Returns `-1` to skip body emphasis on quiet date col when default view |
| `k2_table_quiet_date_cell_class(...)` | Appends `k2-table-col-quiet-date` on date `<td>` when gated |
| `k2_table_quiet_default_sort_col_attr([...])` | Emits `data-k2-quiet-default-sort-cols` |

Copy wiring from **`amiga_tournament_index_render_table()`** in `amiga_profile_blocks.php`.

### CSS (`theme.css`)

One rule under `.k2-table--calm-stats` for `td.k2-table-col-quiet-date` (secondary colour, inherit weight).

### JS (`k2-table.js`)

- `isQuietDefaultSortCol()` — true when col listed in `data-k2-quiet-default-sort-cols` and `!table._k2SortUserChosen`.
- Header click / URL sort sets `_k2SortUserChosen = true` → Date body uses normal `k2-table-col-sorted`.

---

## Opt-in surfaces (all shipped)

| Surface | Page / include | Date col |
|---------|----------------|----------|
| Amiga WC Chronology | `amiga/world-cups/chronology.php` · `amiga_world_cups_events_table.php` | 0 |
| Amiga tournament catalog | `amiga/tournaments.php` · `amiga_tournament_index_render_table()` | 0 |
| Amiga perf-rating Perfect | perf-rating LB · `amiga_lb_performance_rating_table.php` | 9 |
| Amiga player tournament history | `amiga/player/tournaments.php` · `amiga_profile_render_tournament_history_table()` | 0 |
| Amiga live tournament index | `amiga/live-tournaments.php` · `amiga_live_tournament_index_render_table()` | 1 |

---

## Should NOT use quiet date

| Surface | Default sort | Notes |
|---------|--------------|-------|
| Online / Amiga player games, All games, Recent, league period | ID | ID default sufficient; user Date sort → bright |
| Peak date / Last event date LBs | Rating/rank | Editorial date sort — normal emphasis |
| Amiga realm games hub (client path) | Date in code | Live pages use ID or server `sort=id`; prefer ID if surfaced |

---

## Open follow-up (optional)

| Item | Notes |
|------|-------|
| Realm hub client default | `AMIGA_REALM_GAMES_HUB_DEFAULT_SORT_COL = 1` (Date) unused on live pages — switch to ID if that path ships |

---

## Smoke expectations

**Quiet opted-in table, default Date load**

- Date body muted; Date header shows sort chrome.
- User sorts another column → that column emphasized.
- User sorts Date → **date body bright**.

**Game ledger (ID default)**

- First load — dates not bright; user Date sort → bright.

---

## Docs touchpoints

| File | Role |
|------|------|
| [`k2-table-quiet-date-column-policy.md`](k2-table-quiet-date-column-policy.md) | QD0–QD8 rules |
| [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) §1 + step 9 | Reference tables + wiring ritual |
| `PROJECT_MEMORY.md` | Session log |
