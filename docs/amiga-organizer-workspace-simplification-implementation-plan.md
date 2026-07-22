# Amiga organizer workspace simplification — implementation plan (Jul 2026)

**Status:** **Done** — slices **0–6** complete (Jul 2026). Happy-path chrome shipped; docs/RTB vocabulary closed.

**Policy:** [`amiga-organizer-workspace-simplification-policy.md`](amiga-organizer-workspace-simplification-policy.md) (**Implemented**; OW12 stage builder deferred).

**Parents:** same as policy · practice track · fixtures.php inventory in slice 0.

**Starter:** [`orchestration/agent-handoffs/amiga-organizer-workspace-simplification-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-organizer-workspace-simplification-STARTER-PROMPT.md) (**track complete** — do not open new OW slices unless Dagh expands).

---

## 1. Goal

Ship organizer UX and gates that match OW1–OW14 without breaking RTB Finish or Live broadcast.

**Exit (behavioural):** Ref-League-A night: create → stage-scoped scores → finish confirm → Finish; Hide optional; Recent tournaments = Open only; no Start/void/withdraw/replace on happy path.

---

## 2. Non-goals (this track)

| Out | Why |
|-----|-----|
| Full cup / on-the-fly stage builder | Later slice or separate track |
| RR mid-event add-player regen | Explicit later; freeze OK for kitchens |
| Case B/C tip delete | L5 track |
| Void-row migration epic | Irrelevant; opportunistic map if needed |
| Demotion / L6 | Shelved elsewhere |

---

## 3. Suggested slices

| Slice | Deliverable | Verify | STOP |
|-------|-------------|--------|------|
| **0** | Inventory: every gate on `running` / `void` / entrant withdraw-replace; Live hub query; Recent leagues SQL; tab map. Notes → §5. | Read-only | — |
| **1** | **Recent tournaments** rename + Open-only filter (incl. Hidden when Hide exists; until then exclude finalized + void if still present). | Official tip gone from list; Open kitchen remains | — |
| **2** | **Hide / Show on Live** (flag or remap); default on at create; Finish allowed while hidden; retire void/Abandon happy path. | Hidden off Live; still in Recent; Finish OK | — |
| **3** | **No Start** — Open scoreable at create; remove/lock Start from happy path; adjust result-entry gates. | Create → score without Start | STOP if Live/broadcast assumptions break |
| **4** | **Merge play surface** — one stage-scoped Matches/Play UI replacing Fixtures+Results. | Single-stage kitchen night works | — |
| **5** | **Chrome cleanup** — demote Advanced; remove withdraw/replace UI; Setup = create/meta only (Hide not “owned by Setup”). | Happy path runnable without Advanced | — |
| **6** | Docs / RTB vocabulary pass + practice-track note; starter if needed | — | — |

**One slice per chat** unless Dagh says continue. Prefer serial feedback on Ref-League-A after chrome changes.

---

## 4. Technical risks

| Risk | Mitigation |
|------|------------|
| Live hub and result entry both key off `lifecycle_status = running` | Slice 0 map; slice 2–3 introduce Open + live_visible without breaking Finish |
| Finish confirm FO5 assumes Table tab | Revise FO5 placement when slice 4–5 ships |
| Half-migrated void/Start in Advanced | Demote, don’t leave two happy paths |
| Stage navigator with one stage feels heavy | Default-select sole stage; no extra clicks |

---

## 5. Slice 0 inventory (2026-07-22)

Read-only map of today’s organizer + Live gates. Primary file: `site/public_html/amiga/ops/fixtures.php`. Live eligibility: `site/public_html/includes/amiga_tournament_lib.php`. Finish confirm: FO5 on Table tab (`amiga_finish_confirm_proposal.php` + Table panel).

**Create (slice 3):** kitchen insert sets `lifecycle_status = 'running'` + `started_at` (Open, scoreable, Live-eligible when `live_visible`). Leftover draft/ready kitchens auto-heal to running on open / result entry.

### 5.1 Gates on `running` / pre-start (slice 3 update)

| Concern | Gate / symbol | Allowed when | Notes |
|---------|---------------|--------------|-------|
| **Result entry (server)** | `amiga_fixture_ensure_open_scoreable()` then `require_running_lifecycle` | Promotes draft\|registration\|ready → **running**; then requires running | OW3 shipped |
| **Result entry (UI)** | Play tab `$lifecycleRunning` | Forms when running (create lands on Play) | Start copy removed; Fixtures+Results merged (slice 4) |
| **Add entrant** | registration lifecycles only | draft\|registration\|ready | Still blocked once running (create already has roster) |
| **Stage placement** | same pre-start set | Blocked once running | OW12 later |
| **Start** | **Retired** happy path (`can_start` always false; button removed) | — | Heal + create-as-running |
| **Finish** | `$tournamentCanMakeOfficial` / reprocess | generated + **running** + ≥1 played + confirm | Unchanged; create-as-running keeps Finish OK |
| **Live** | running + generated + live_visible | Appears after create when not Hidden | Intentional with OW3 |

### 5.2 Void / Abandon / Live eligibility (slice 2 shipped)

| Surface | Behaviour |
|---------|-----------|
| **Hide / Show** | `format_overrides.live_visible` (`1` default at create; `0` = Hidden). Header chrome **Hide from Live** / **Show on Live** on Open workspaces. Does not change lifecycle; does not block Finish |
| **Live hub** | `lifecycle_status = running` **and** generated **and** live_visible (missing key ⇒ visible) — `amiga_live_tournament_live_visible_where()` |
| **Abandon / void** | **Retired** from browser happy path (button removed; `void_tournament` refused; void not in Advanced lifecycle targets). Fixture void-on-Finish **kept** |
| **Recent tournaments** | Still Open-only by lifecycle; **includes** Hidden (`live_visible=0`) |

### 5.3 Recent tournaments query (slice 1 shipped)

**Where:** Create/Recent landing only — `$view === 'setup' && $tournamentId <= 0` — heading **“Recent tournaments”**.

**SQL** (`fixtures.php` — Open-only as of slice 1):

```sql
… WHERE t.source_id IS NULL
  AND (format_overrides LIKE builder OR fixtures ops marker)
  AND COALESCE(t.rating_finalized, 0) = 0
  AND t.lifecycle_status IN ('draft', 'registration', 'ready', 'running')
…
```

**Includes:** draft / registration / ready / running generated kitchens (Hidden Open still in this set).  
**Excludes:** Official tip (`rating_finalized = 1` and/or completed/archived) and `void`. Open link lands on **Play** tab.

### 5.4 Withdraw / replace call sites (slice 5 — browser abandoned)

| Layer | Sites |
|-------|--------|
| **Browser UI** | **Removed** from Players (OW11). Roster table is read-only for actions; add-entrant search only for leftover draft/registration/ready kitchens |
| **POST handlers** | `withdraw_entrant` / `replace_entrant` **refused** with flash (CLI repair still available) |
| **PHP impl** | Functions kept for CLI parity: `amiga_fixture_withdraw_entrant` / `amiga_fixture_replace_entrant` |
| **CLI (parity)** | `scripts/amiga/tournament_fixtures.py`: `withdraw-entrant`, `replace-entrant` — repair only, not OW product surface |

### 5.5 Tab / chrome map

Peer happy-path tabs: `players` · `play` · `table`. `setup` = create + Recent tournaments landing only (no `tournament_id`). Legacy `view=fixtures` / `view=results` → `play`. In-tournament `view=setup` remaps → `play`.

| Tab / surface | Label | Role |
|---------------|-------|------|
| Landing (`setup`, no id) | Create / Recent | Create league; Recent tournaments Open-only |
| `players` | Players | Roster read; rare pre-Open add |
| `play` | Play | Stage-scoped score entry |
| `table` | Table | FO5 + Finish |
| `advanced` | Technical / repair tools | Demoted muted link |

**Outer chrome:** Live hub link · organizer active · **Create new league** (→ setup landing) · lifecycle badge + Hide/Show in header · Technical / repair tools link.

---

## 6. Verification checklist (track done)

- [x] Create → score → Finish with no Start / no void — **Start retired slice 3**; void retired slice 2; Finish path unchanged
- [x] One stage-scoped play surface — **slice 4** (`view=play` + optional `stage_id`)
- [x] Hide/Show Live; Finish while Hidden — **slice 2 shipped** (`format_overrides.live_visible`)
- [x] Recent tournaments = Open only (incl. Hidden) — **slice 1 + Hidden via slice 2**
- [x] No withdraw/replace on happy path — **slice 5** (POST refused; CLI may remain)
- [x] Advanced not required for Ref-League-A — **slice 5** (demoted to Technical / repair tools)
- [x] RTB Finish still promotes correctly — **unchanged** by OW chrome; boundary kept in RTB policy
- [x] Policy status → **Implemented** (OW12 stage builder / mid-event RR add deferred) — **slice 6**

---

## 7. Changelog

| Date | Change |
|------|--------|
| 2026-07-22 | **Follow-up** — Setup dropped from in-tournament tab row (create/Recent landing only; `view=setup`+id → Play). |
| 2026-07-22 | **Slice 6 done** — policy Implemented; practice-track + RTB vocabulary aligned; track closed. |
| 2026-07-22 | **Slice 5 done** — Advanced demoted; withdraw/replace abandoned in browser; Setup slim meta; next slice 6 docs. |
| 2026-07-22 | **Slice 4 done** — Fixtures+Results → stage-scoped **Play** (`view=play`, `stage_id`); legacy URLs remap; next slice 5. |
| 2026-07-22 | **Slice 3 done** — create as running; Start retired; draft/ready auto-heal; next slice 4. |
| 2026-07-22 | **Slice 2 done** — Hide/Show Live (`live_visible` in format_overrides); Abandon/void retired from browser; next slice 3. |
| 2026-07-22 | **Slice 1 done** — Recent tournaments rename + Open-only SQL filter; next slice 2. |
| 2026-07-22 | **Slice 0 done** — §5 inventory filled; next slice 1. |
| 2026-07-22 | Starter prompt added (slice 0 default). |
| 2026-07-22 | Initial plan — slices 0–6 from locked OW policy. |