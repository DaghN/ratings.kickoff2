# K2 table quiet date column policy

**Status:** Locked intent (Jul 2026).

**In one line:** Avoid **blasting dates on first load** with active-sort emphasis unless that emphasis is **earned** — prefer **ID default sort** when a game-ID column exists; use **quiet-date tooling** only when the table must open on Date and has no obvious neutral default.

**Authority:** Product + visual contract; defers to [`design-direction.md`](design-direction.md). Table machinery: [`k2-table-and-games-plan.md`](k2-table-and-games-plan.md), [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md). Rollout: [`k2-table-quiet-date-unification-plan.md`](k2-table-quiet-date-unification-plan.md). Dagh's latest chat wins on scope.

**For agents:** read this when Dagh mentions **bright dates on first load**, **quiet date**, or when designing a sortable table **without** a game-ID column. **Ask Dagh** whether default Date sort should use quiet date — opt-in per table.

---

## Core idea

Sortable calm-stats tables highlight the **active sort column** (bold body, header chrome). On **first load**, that can **blast** an entire Date column — long timestamps in primary ink — even though the user did not ask to sort by date; the table simply **defaults** to newest-first.

**Mitigation order:**

1. **ID default** (or another non-date default) when the table has a game ID — dates stay unhighlighted on open; no special tooling.
2. **Quiet date** when there is **no** neutral column (tournament catalog, WC chronology) but the table must still open Date-sorted — switch off the first-load blast via `k2-table-col-quiet-date` + default-load-only gating.
3. **Normal blast** when emphasis is **warranted** — user chose Date sort, or the column is editorially “the sorted metric” (Peak date on a leaderboard).

Quiet date is the **fallback tool**, not a universal date style.

---

## Purpose

Calm-stats tables apply **active-sort emphasis** on the sorted column (`k2-table-col-sorted`, header sort chrome).

### What quiet date is **for**

Some tables **cannot** default-sort by game ID:

- Tournament catalogs, WC chronology, event lists — rows are events/tournaments, not individual rated games.
- You still want **newest-first** on first paint, so **Date is the table default sort**.
- Without mitigation, every visit **blasts** long written dates in bold primary ink — especially loud next to link-star neighbours.

**Quiet date (opt-in)** = on the **default Date-sorted first load only**, date **body** cells stay muted. Sorting still works; header keeps normal sort chrome.

### What quiet date is **not** for

**User explicitly chose Date sort** — emphasis is warranted; dates may blast/highlight like any sorted column.

**Table has game ID** — use **ID default** instead of quiet date (first mitigation in the chain above).

### Decision flow

```
First load might blast Date column?
        |
        v
  Has game ID (or neutral default column)?
    |                    |
   yes                   no
    |                    |
    v                    v
Default sort ID      Must open Date-sorted?
(no blast, no tool)        |
                      ask Dagh: quiet date
                      on default load only?

User later sorts by Date → normal emphasis (blast OK)
```

---

## Locked decisions

| ID | Decision |
|----|----------|
| **QD0** | **First-load blast** — avoid highlighting the whole Date column on open unless emphasis is earned. **Mitigation order:** (1) ID default if available, (2) quiet date if must open Date-sorted without ID, (3) normal emphasis when user chooses Date or column is editorially special. |
| **QD1** | **Opt-in only** — quiet date when Dagh asks or an existing surface already uses it correctly. |
| **QD2** | **Agent ritual:** tables **without** game ID that default-sort by Date → ask Dagh: *Quiet date on default load?* |
| **QD3** | **Default load only** — quiet date applies when Date is the **table default sort** and the user has **not** explicitly chosen a different sort (server: no sort override in URL; client: initial default, not a subsequent header click). **User-chosen Date sort → normal emphasis.** |
| **QD4** | **Prefer ID default** on game ledgers — no quiet date needed for typical player/realm games tables (default ID; user Date sort is bright). |
| **QD5** | **LB / special date columns** (Peak date, Last event date) — normal emphasis when sorted; sorting by those dates is intentional. |
| **QD6** | **Tooling:** `k2-table-col-quiet-date` CSS + shared PHP helper that gates emphasis on **default sort context** — not “always mute when date col is active”. Class name: **`k2-table-col-quiet-date`**. |
| **QD7** | **Vocabulary:** “Dates too **bright on first load**” / “don't blast dates” → try **ID default** first; else quiet date on default load. User sorted by Date → blast OK. |
| **QD8** | **Legacy `data-k2-quiet-sort-cols` is not sufficient** — it suppresses emphasis whenever that column is the active sort (including after a user header click). Target behaviour requires **default-load-only** gating in **both** PHP (SSR) and `k2-table.js` (client re-sort). See § Default-load-only gating below. |

---

## Default-load-only gating (PHP + JS)

Quiet date must distinguish **two states**:

| State | Body date emphasis | Header sort chrome |
|-------|-------------------|-------------------|
| **Default view** — table opened with default Date sort; user has not chosen another sort | Muted (`k2-table-col-quiet-date`) | Normal (loud) |
| **User-chosen Date sort** — user clicked a sort header (including Date after sorting another column) | Normal `k2-table-col-sorted` | Normal |

### Why `data-k2-quiet-sort-cols` is too broad

On client-sort tables, `k2-table.js` reads `data-k2-quiet-sort-cols` and `isQuietSortCol()` returns true whenever that column index is the **current** active sort — in both `setSortState()` (header classes) and `refreshSortedColumnEmphasis()` (body classes).

That means a user who clicks **Date** after sorting by Name still gets quiet dates. That violates QD3.

**Do not** add new date-quiet surfaces using `data-k2-quiet-sort-cols` alone. Existing WC Chronology / tournament catalog attrs are **legacy** until Slice B migrates them.

### PHP (server-sort and SSR first paint)

- `k2_table_is_default_sort_view()` — true when the request has **no user sort override** (e.g. no `sort` / `k2_sort` query param, or param matches table default only on first landing — see plan for edge cases).
- `k2_table_sort_col_for_emphasis(..., $isDefaultSortView)` — pass `-1` (no body emphasis) only when `$isDefaultSortView && date col is active`.
- Apply `k2-table-col-quiet-date` on date `<td>` under the same condition.

### JS (client-sort tables)

Required change in `k2-table.js` (plan Slice B / sub-slice **B-js**):

1. Track whether the current sort state is **user-initiated** (header click) vs **default** (initial `applyDefaultSortState` / `applyDefaultSortHeaderState`).
2. Apply quiet suppression **only** for default-initiated sort on listed date columns.
3. After any user header click, date columns behave like normal sorted columns (full header + body emphasis).

**Target attribute (proposed):** `data-k2-quiet-default-sort-cols="0,…"` — quiet **only** until first user sort interaction; supersedes date use of `data-k2-quiet-sort-cols`. Implementation detail in plan § JS track.

**Header:** default Date sort keeps **normal** header sort chrome (QD3). Legacy `data-k2-quiet-sort-cols` incorrectly suppresses header classes too — another reason to migrate.

---

## Visual contract (quiet date active — default load only)

| Element | Default Date sort (quiet) | User chose Date sort |
|---------|---------------------------|----------------------|
| **Body date cells** | `k2-table-col-quiet-date`; secondary/muted | Normal `k2-table-col-sorted` emphasis |
| **Header** | Normal default-sort chrome | Normal user-sort chrome |
| **Neighbours (link-star)** | Unchanged | Unchanged |

---

## When to use quiet date (ask Dagh)

| Signal | Example |
|--------|---------|
| No game ID column; table defaults to Date | WC Chronology, tournament catalog |
| Event/tournament row lists | Player tournament history (evaluate) |
| Date default is design choice, not user action | Catalog index first paint |

## When **not** to use quiet date

| Signal | Example |
|--------|---------|
| Table has game ID — use ID default instead | Player games, All games, Recent |
| User explicitly sorted by Date | Any ledger after Date header click |
| Sorting by date **is** the editorial point | Peak date LB, Last event date |
| User chose Date sort and expects highlight | — |

---

## Implementation (summary)

1. **`k2-table-col-quiet-date`** on date `<td>` when **`$isDefaultSortView`** and Date is the active sort column.
2. **Do not** quiet when user sort param / header click indicates explicit Date sort.
3. **Client-sort:** migrate off bare `data-k2-quiet-sort-cols` for date — use default-load-only JS gating (§ Default-load-only gating, plan § JS track).
4. **Shared helpers** in `k2_table_helpers.php` — always pass default-sort context.

Full rollout: [`k2-table-quiet-date-unification-plan.md`](k2-table-quiet-date-unification-plan.md).

---

## Anti-patterns

- Quiet date when user explicitly sorted by Date.
- Quiet date on game ledgers that already default to ID (use ID default; Slice C).
- **`data-k2-quiet-sort-cols` alone** for new quiet date work — always-on quiet while Date is active sort (wrong after user clicks Date).
- Copy-pasting per-table `*_sort_col_for_emphasis()` without default-sort context.
- Assuming every Date column needs quiet treatment.

---

## Related

- Milestone **Unlocked** column — different product reason (`player-milestones.css`).
- [`k2-table-implementation-checklist.md`](k2-table-implementation-checklist.md) §2 step 9.