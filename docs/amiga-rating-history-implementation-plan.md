# Amiga rating history — implementation plan (V1 slices)

**Status:** **Ready to execute** (Jun 2026). Policy locked.  
**Policy:** [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md)  
**Parent:** [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-tournament-finalize-rating-contract.md`](amiga-tournament-finalize-rating-contract.md)

**In scope (V1):** History hub tab · `/amiga/history.php` · Event / Month / Year wings · chevrons + picker · rating + rank from `amiga_rating_events` (compute on read).

**Out of scope (V1):**

- V2 schema / finalize writers (cumulative career stats)
- JSON API (unless a slice needs it for the page — prefer server-rendered PHP first)
- Animation / bar chart race
- Leaderboards IA split (present vs history)
- Staging export / WinSCP (deploy as usual when Dagh syncs)
- Git commit unless Dagh asks

**Migration:** **L0** — read-time only; **no Part B** for V1.

---

## How to use this plan

1. Execute slices **in order** in this chat (or user says “slice N”).
2. Run each slice **Verification** before moving on.
3. **Do not git commit** unless user asks.
4. After slice 4 (ship): **UPDATE_DOCS** Part A — MEMORY, policy status → Implemented, player-universe §4 row, optional `url-routes.md`, `hub-ia-agreement.md` note.

---

## Locked decisions (do not re-open without user)

See policy **H1–H8**. Summary: rating + rank only; Event / Month / Year wings; no pre-debut players; sort by exact `rating_after`; finalized tournaments only; History hub tab; compute on read.

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **0** | Docs only (policy + this plan) | — **done** |
| **1** | `amiga_rating_history_lib.php` — catalog builders + ladder query | SQL spot checks |
| **2** | History hub tab + `/amiga/history.php` skeleton (one wing) | Browser smoke |
| **3** | Month + Year wings; chevrons + picker all wings | Browser navigation |
| **4** | Polish, cross-links, doc closure, MEMORY | User OK |

---

## Slice 0 — Policy & plan

### Goal

Lock decisions and execution slices before code.

### Tasks

- [x] [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md)
- [x] This implementation plan
- [x] Cross-links in data contract authority map

### Verification

- User reviewed policy decisions H1–H8.

---

## Slice 1 — Read library (catalog + ladder SQL)

### Goal

Single PHP library: build snapshot catalogs and return ranked ladder rows for any wing + key.

### Tasks

- [ ] Create `site/public_html/includes/amiga_rating_history_lib.php`
- [ ] `amiga_rating_history_finalized_tournaments($con)` — tournaments with `rating_finalized = 1` (or all completed import tournaments with rating events), chrono order
- [ ] `amiga_rating_history_catalog_event($con)` — list of `{ key: tournament_id, label, sort_key, event_date, chrono }`
- [ ] `amiga_rating_history_catalog_month($con)` — distinct `YYYY-MM` with ≥1 finalize; each entry points at **last tournament chrono in that month** (store `cutoff_tournament_id` or equivalent)
- [ ] `amiga_rating_history_catalog_year($con)` — same for `YYYY`
- [ ] `amiga_rating_history_ladder_at_cutoff($con, $cutoffEventDate, $cutoffChrono, $cutoffTournamentId)` — returns `[{ player_id, name, country, rating_after, rank }, …]`
  - Use window function `ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC)` filtered to `<= cutoff`, or equivalent grouped subquery
  - Outer sort: `rating_after DESC`, `player_id ASC`; assign rank
- [ ] Helpers: `amiga_rating_history_resolve_wing_at($con, $wing, $atKey)` → cutoff + label; default `at` = last catalog entry
- [ ] Helpers: `amiga_rating_history_prev_next($catalog, $currentKey)` → prev/next keys for chevrons

### Verification

```powershell
# Laragon paths — adjust if needed
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -r "
require 'site/public_html/includes/amiga_rating_history_lib.php';
// minimal bootstrap or one-off probe script if -r too awkward
"
```

Prefer a tiny one-off `scripts/oneoff/amiga_rating_history_probe.php` (optional) or test via history page in slice 2.

**SQL spot checks (mysql):**

```sql
-- Last event wing should match current rating LB count (approx)
SELECT COUNT(DISTINCT player_id) FROM amiga_rating_events;

-- First tournament snapshot: small player count
-- (pick first finalized tournament id from catalog query)
```

- [ ] Last event snapshot player count = distinct players in `amiga_rating_events`
- [ ] Ranks are unique; sorted by `rating_after` DESC
- [ ] No player appears before their first event in an early snapshot

### Files (expected)

- `site/public_html/includes/amiga_rating_history_lib.php`
- Optional: `scripts/oneoff/amiga_rating_history_probe.php`

---

## Slice 2 — Hub tab + History page (Event wing)

### Goal

Navigable dev surface with Event wing working end-to-end.

### Tasks

- [ ] `site/public_html/includes/amiga_hub_nav.php` — add `'history' => ['href' => '/amiga/history.php', 'label' => 'History']` (placement: after Leaderboards or before HoF — **default: after Activity**)
- [ ] `site/public_html/amiga/history.php`:
  - Hub tab active = `history`
  - Chapter: “Historical ladder” + short lede (finalize-boundary note)
  - Sub-wing nav: Event | Month | Year (Month/Year can be disabled or stub until slice 3)
  - Event wing: chevrons + `<select>` picker populated from catalog
  - Table: Rank | Player | Elo | Country
  - `k2_amiga_player_link()` for names
  - Query params: `wing=event`, `at=<tournament_id>`
- [ ] Reuse existing table CSS (`k2-table`, calm stats) — match `leaderboards/rating.php` density where sensible

### Verification

- [ ] `http://ratingskickoff.test/amiga/history.php` loads
- [ ] History tab highlights on hub
- [ ] Default view = latest event; table matches `/amiga/leaderboards/rating.php` ranks (allow integer display rounding)
- [ ] Chevron prev/next moves between tournaments; URL updates
- [ ] Picker jump works

### STOP GATE A

User confirms Event wing OK before slice 3.

### Files (expected)

- `site/public_html/includes/amiga_hub_nav.php`
- `site/public_html/amiga/history.php`
- Optional: `site/public_html/includes/amiga_history_nav.php` (sub-wing pills — mirror `amiga_lb_nav.php` pattern)

---

## Slice 3 — Month + Year wings

### Goal

All three wings complete with independent catalogs and navigation.

### Tasks

- [ ] Sub-wing nav switches `wing=month|year` — reset or preserve `at` sensibly (default to latest in that wing)
- [ ] Month picker labels (`Month YYYY`); year picker (`YYYY`)
- [ ] Chevrons walk month/year catalogs (only periods with ≥1 finalize)
- [ ] Edge case: month with multiple tournaments — ladder matches **after last chrono tournament** in that month (verify vs Event wing on that tournament)

### Verification

- [ ] Each wing reaches first/last snapshot; chevrons disable or hide at ends
- [ ] Pick a month with 2+ tournaments — compare to Event wing on last tournament of that month (identical table)
- [ ] Year cutoff matches last event of that year

### Files

- `site/public_html/amiga/history.php`
- `site/public_html/includes/amiga_rating_history_lib.php` (if tweaks)
- `site/public_html/includes/amiga_history_nav.php` (if extracted)

---

## Slice 4 — Polish & doc closure

### Goal

Ship-ready V1 dev surface; docs reflect reality.

### Tasks

- [ ] Empty/error states: invalid `at` param → 400 or fallback to latest
- [ ] `aria-label` on chevrons; accessible picker
- [ ] Update [`amiga-rating-history-policy.md`](amiga-rating-history-policy.md) status → **V1 implemented**
- [ ] [`amiga-player-universe-contract.md`](amiga-player-universe-contract.md) §4 — new row: Historical ladder | `/amiga/history.php` | `amiga_rating_events` compute | Tier A
- [ ] [`docs/url-routes.md`](url-routes.md) — `/amiga/history.php`
- [ ] [`docs/hub-ia-agreement.md`](hub-ia-agreement.md) — one line: History tab (dev; IA TBD)
- [ ] [`PROJECT_MEMORY.md`](PROJECT_MEMORY.md) Recent log
- [ ] Optional: [`amiga-surface-expansion-overview.md`](amiga-surface-expansion-overview.md) §4 — move “historical ladder” from potential to shipped

### Verification

- [ ] Full manual pass: three wings × chevrons × picker
- [ ] No PHP notices on local

---

## V2 backlog (not scheduled in this plan)

| Item | Work |
|------|------|
| Policy + DDL | Cumulative career columns on sparse event grain |
| Writers | `finalize_tournament` Python + PHP parity |
| Verify | Row matches `amiga_player_stats` at each event for participants |
| Surface | Add columns to History table / optional present-vs-history LB split |

See policy §6.

---

## File register (cumulative)

| Path | Slice |
|------|-------|
| `docs/amiga-rating-history-policy.md` | 0 |
| `docs/amiga-rating-history-implementation-plan.md` | 0 |
| `site/public_html/includes/amiga_rating_history_lib.php` | 1 |
| `site/public_html/amiga/history.php` | 2–3 |
| `site/public_html/includes/amiga_hub_nav.php` | 2 |
| `site/public_html/includes/amiga_history_nav.php` | 2–3 (optional) |
