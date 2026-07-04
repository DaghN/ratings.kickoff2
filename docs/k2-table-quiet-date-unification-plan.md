# K2 table quiet date — tooling + inventory

**Status:** Shipped (Jul 2026). Slices A, B-js, B, B′ implemented.

**In one line:** Build shared tooling to **switch off first-load date blast** when a table must open Date-sorted and **ID default is not available**; otherwise prefer ID sort and skip the tool.

Policy: [`k2-table-quiet-date-column-policy.md`](k2-table-quiet-date-column-policy.md).

**Goals**

1. **Mitigation chain in code/docs:** ID default first (Slice C) → quiet date fallback (Slice B) → normal blast when user chooses Date.
2. Shared helpers + `k2-table-col-quiet-date` CSS, gated on **default sort context only** (QD3, QD8).
3. Fix misimplementations (e.g. Amiga player games quieting on user Date sort; legacy `data-k2-quiet-sort-cols` too broad).

**Non-goals**

- Quiet date on every table with a Date column.
- Suppressing emphasis when user explicitly sorts by Date.
- Changing Peak date / Last event date LBs (blast when sorted is intentional).

---

## Resolved product choices (Jul 2026)

| Question | Decision |
|----------|----------|
| What problem? | **First-load date blast** — active-sort styling on open when default sort is Date |
| First mitigation? | **ID default** when game ID column exists (Slice C) |
| Fallback tool? | **Quiet date** — default-load-only body mute when must open Date-sorted without ID |
| User sorts by Date? | **Normal blast** — emphasis warranted |
| Special date LBs? | **Normal blast** when sorted (Peak date, etc.) |
| CSS class | **`k2-table-col-quiet-date`** |
| Header on default Date? | **Normal** sort chrome (body quiet only) |

---

## Tools unification

### Shared PHP (`includes/k2_table_helpers.php`)

Helper must accept **default-sort context**, not only column index:

```php
/**
 * Return $activeSortCol for body emphasis, or -1 to suppress emphasis on $colIndex.
 * Quiet only when $colIndex is in $quietCols AND $isDefaultSortView is true.
 */
function k2_table_sort_col_for_emphasis(
    int $colIndex,
    int $activeSortCol,
    array $quietCols,
    bool $isDefaultSortView
): int {
    if ($isDefaultSortView
        && in_array($colIndex, $quietCols, true)
        && $colIndex === $activeSortCol
    ) {
        return -1;
    }
    return $activeSortCol;
}

/** Detect server-sort default view (no user sort override in request). */
function k2_table_is_default_sort_view(int $defaultSortCol, ?string $sortQueryKey = 'sort'): bool;
```

Refactor duplicates to use shared helper + correct `$isDefaultSortView`.

### Shared CSS (`theme.css`)

`.k2-table-col-quiet-date` — alias legacy date class selectors until migrated.

---

## Default-load-only gating (PHP + JS)

Quiet date is **not** “mute whenever Date is the sorted column.” It is “mute on **default** Date sort until the user interacts.” That requires parallel tracks:

### PHP track (server-sort + SSR first paint)

| Piece | Role |
|-------|------|
| `k2_table_is_default_sort_view()` | True when request has no user sort override (e.g. missing `sort` / `k2_sort`, or only matches table default on first landing) |
| `k2_table_sort_col_for_emphasis(..., $isDefaultSortView)` | Returns `-1` for quiet date col **only** when `$isDefaultSortView` |
| `k2-table-col-quiet-date` on `<td>` | Same gate as emphasis helper |

**Slice A** adds helpers. **Slice B** wires `$isDefaultSortView` on catalog tables.

### JS track (client-sort tables) — **B-js**

**Problem:** `k2-table.js` `isQuietSortCol()` reads `data-k2-quiet-sort-cols` and suppresses header + body emphasis in `setSortState()` and `refreshSortedColumnEmphasis()` whenever that column is active — **including after user header clicks**. Conflicts with QD3 / QD8.

**Current call sites:** `isQuietSortCol` at ~lines 466, 492 in `site/public_html/js/k2-table.js`.

**Target behaviour:**

```
Page load → applyDefaultSortHeaderState / applyDefaultSortState
  → Date is default sort, col in quiet-default list
  → body muted (k2-table-col-quiet-date), header LOUD

User clicks any sort header (including Date)
  → table._k2SortUserChosen = true (or equivalent)
  → quiet suppression OFF for date cols
  → normal header + body emphasis on active column
```

**Proposed markup:**

| Attribute | Semantics | Status |
|-----------|-----------|--------|
| `data-k2-quiet-sort-cols` | Quiet whenever col is active sort | **Legacy** — do not use for new date work |
| `data-k2-quiet-default-sort-cols` | Quiet **only** until first user sort interaction | **Target** for catalog/chronology |

**Implementation sketch (B-js):**

1. Add `isQuietDefaultSortCol(table, index)` — true only when `!table._k2SortUserChosen` and index listed in `data-k2-quiet-default-sort-cols`.
2. In header click handler / `sortTableByIndex` from user action: set `table._k2SortUserChosen = true` before `setSortState`.
3. `refreshSortedColumnEmphasis` + `setSortState`: use quiet-default helper for body; **never** suppress header classes for quiet-default cols (header always loud per QD3).
4. Migrate WC Chronology + tournament catalog: replace `data-k2-quiet-sort-cols="0"` with `data-k2-quiet-default-sort-cols="0"`.
5. Leave `data-k2-quiet-sort-cols` documented as legacy for any non-date use (if any) or remove when unused.

**SSR alignment:** PHP first paint for client-sort tables should match initial JS state — quiet date class on body cells when default sort is Date and no `k2_sort` in URL; omit quiet class when URL carries user sort params.

### Legacy vs target (summary)

| Mechanism | Default Date load | User chose Date sort | Header on default Date |
|-----------|-------------------|----------------------|------------------------|
| `data-k2-quiet-sort-cols` (today) | Body quiet | **Body quiet (wrong)** | **Header quiet (wrong)** |
| PHP `$isDefaultSortView` + CSS | Body quiet | Body loud | Header loud |
| `data-k2-quiet-default-sort-cols` + B-js | Body quiet | Body loud | Header loud |

---

## Inventory — correct quiet date (default Date, no game ID)

| Surface | Has game ID? | Default sort | Intended quiet | Today | Flag |
|---------|--------------|--------------|----------------|-------|------|
| Amiga WC Chronology | No | Date | Default load only | Always quiet when Date active | Fix QD3; header quiet via JS |
| Amiga tournament catalog | No | Date | Default load only | Always quiet when Date active | Same |
| Amiga perf-rating Perfect | No | Date | Default load only | CSS always | Add context guard |

---

## Inventory — should NOT use quiet date

| Surface | Has game ID? | Default sort | Notes |
|---------|--------------|--------------|-------|
| Online player games | Yes | ID | User Date sort → bright OK. Day/streak forced date = contextual default (evaluate) |
| Amiga player games | Yes | ID | **Misimplementation:** quiets on user Date sort today — **remove** on rollout |
| Online / Amiga All games | Yes | ID | ID default sufficient |
| Online Recent, league period | Yes | ID | Same |
| Peak date / Last event date LBs | N/A | Rating/rank | Normal emphasis |

---

## Inventory — inconsistencies

| Issue | Fix track |
|-------|-----------|
| Amiga player games quiets user Date sort | Slice B — **remove** quiet on user sort; ID default already enough |
| `data-k2-quiet-sort-cols` on WC/tournament | Slice B + **B-js** — migrate to `data-k2-quiet-default-sort-cols`; user Date sort → loud |
| Legacy helpers ignore `$isDefaultSortView` | Slice A — shared helper signature |
| Realm hub CSS scope | Only if table opts into quiet default Date |

---

## Suggested slices (order)

Mitigation chain: **C → A → B-js → B → B′**

### Slice C — ID default (first mitigation)

Game ledgers and any table **with** game ID: default sort **ID** so Date is not blasted on first load. **No quiet-date tool** needed in the common case.

**Surfaces:** online/Amiga player games, All games, Recent — mostly already ID; verify day/streak forced-date views.

### Slice A — Shared primitives

Helpers (with `$isDefaultSortView`), CSS class + aliases, refactor duplicates. Enables B without mandating quiet everywhere.

### Slice B-js — Client-sort default-only quiet

See § Default-load-only gating → JS track. `data-k2-quiet-default-sort-cols` in `k2-table.js`. Test on WC Chronology + tournament catalog.

### Slice B — Catalog / no-ID tables (fallback tool)

When Slice C cannot apply — WC Chronology, tournament catalog, perf-rating Perfect:

1. PHP: quiet body on **default load** only (`$isDefaultSortView`).
2. Wire **B-js**; replace legacy `data-k2-quiet-sort-cols`.
3. User selects Date sort → **normal blast**.

### Slice B′ — Remove wrong quiet date

ID-default ledgers (e.g. Amiga player games): stop suppressing emphasis when user sorts by Date — ID default was already enough on first load.

---

## Test checklist

**Default Date sort (no game ID, quiet opted in)**

- [ ] First load / no sort param — date body muted, header shows sort chrome.
- [ ] User sorts another column — that column emphasized; dates normal if not sorted.
- [ ] User then sorts by Date — **date body bright**, header loud (normal active-sort).
- [ ] Client-sort: after B-js, click Name then Date — dates **bright** (not quiet).

**Game ledger (ID default)**

- [ ] First load — ID column emphasized (or none if design), dates not bright.
- [ ] User sorts by Date — **dates bright** (no quiet).

---

## Docs touchpoints

| File | When |
|------|------|
| This plan | Inventory after each slice |
| Policy | QD rules (done Jul 2026 correction) |
| `PROJECT_MEMORY` | One line per shipped slice |