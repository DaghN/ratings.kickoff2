# Online realm query audit — STARTER PROMPT (audit-only)

**Track:** Online read-path performance census · **Status:** Ready to launch · **Realm:** Online only (`ko2unity_db` local / staging) · **Date opened:** 2026-07-04

**Model this on:** Amiga [`2026-07-04-004`](2026-07-04-004-amiga-tt-query-optimization-sweep.md) Phase 1 + [`scripts/oneoff/amiga_realm_full_census_probe.php`](../../../scripts/oneoff/amiga_realm_full_census_probe.php) — but **online paths only**, **no Amiga URLs**.

---

## COPY INTO NEW CHAT

```
You are running the **Online realm query audit** — audit and ranking only. Do not ship query fixes in this pass unless Dagh explicitly asks after the report.

**Repo:** C:\Users\daghn\Desktop\Online and Amiga 500 ELO
**Local site:** http://ratingskickoff.test (online DB: `ko2unity_db` via `config/ko2unitydb_config.php`)
**PHP CLI:** C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe

## Mission

Build a **complete ranked census** of every canonical **online** page + online JSON API: curl total time + (for flagged paths) lib-level ms per hot SQL function. Deliver a markdown report sorted worst-first with **feel tiers**, suspect anti-patterns, and a prioritized fix backlog for a **future** read-time-only slice.

**Success = inventory + numbers + recommendations.** Not code changes (except the probe script itself).

---

## HARD BOUNDARIES — read before any work

### In scope (audit + future fix ideas)

- **Read-time PHP/SQL** in `site/public_html/` for the **online realm** (pages, includes, `/api/*.php` where `realm=online` or no Amiga prefix).
- **Query shape analysis:** live scans on `ratedresults`, wide joins, duplicate reads per request, missing request-scoped cache, facet waterfalls, N+1 loops.
- **Recommendations** that stay **read-time only:** narrow indexed reads, request cache (pattern D), dedupe validate/facet passes, pagination/render notes (flag separately from SQL).

### Explicitly OUT OF SCOPE — do NOT do, propose, or assign work that touches

| Forbidden | Why |
|-----------|-----|
| **Any DB schema change** — no `ALTER`, no new tables/indexes, no `scripts/ladder/sql/`, no `site/public_html/ops/sql/migrations/` | Audit is pre-migration; stored-truth changes need Steve/cutover registers |
| **`site/public_html/ops/`** — no reads for “fixes”, no simul, no dispatch, no post-game writers | Ops = ladder truth boundary |
| **`scripts/ladder/` writers, rebuild CLIs, batch `*_rebuild.sql`, `rebuild_website_derived_data_local.ps1` repair** | Not read-path |
| **New aggregate / snapshot / periodic tables** or “store this on profile” migrations | Stored-truth habit — note as *future contract slice*, not this audit’s fix list |
| **C++ / Steve cutover / prod coordination** | Not this track |
| **`/amiga/**` paths, `ko2amiga_db`, `includes/amiga_*`, `api/amiga_*`** | Separate realm — already swept |
| **Product semantics changes** (filters, sort orders, URL shapes) | Audit only |

If a page is slow because **HTML row count** or **Chart.js API waterfalls**, **record it** — but label the lever as **render/client**, not SQL. Do not blur into “needs a new stored table” unless Dagh asks for a separate contract discussion.

---

## Read first (bootstrap)

1. [`PROJECT_MEMORY.md`](../../../PROJECT_MEMORY.md) — recent online vs Amiga context
2. [`docs/url-routes.md`](../../url-routes.md) — online hub + wings (ignore Amiga sections)
3. [`site/public_html/includes/k2_routes.php`](../../../site/public_html/includes/k2_routes.php) — canonical route keys
4. [`docs/activity-charts.md`](../../activity-charts.md) — Activity hub API waterfall
5. [`docs/STATUS_PAGE_DATA.md`](../../STATUS_PAGE_DATA.md) — Status hub panels
6. [`docs/amiga-tt-query-optimization-playbook.md`](../../amiga-tt-query-optimization-playbook.md) — **method only** (probe → shape → pattern); adapt anti-patterns to online `ratedresults` / `playertable`, not Amiga snapshot libs

---

## Phase 1 — build census probe (~1 session)

Create **`scripts/oneoff/online_realm_full_census_probe.php`** (+ optional results md):

### URL register (online only)

Derive from `k2_routes.php` + these groups (extend if routes missing):

| Group | Paths |
|-------|-------|
| **Hub** | `/status.php`, `/activity.php`, `/hall-of-fame.php`, `/league.php` |
| **Leaderboards** | `/leaderboards/{rating,goals,double-digits,streaks,victims,league-honours,milestones,peak-rating}.php` |
| **LB Activity** | `/leaderboards/activity/{participation,peaks,in-a-row}.php` |
| **Games hub** | `/games/{recent,highlights,all}.php` |
| **Milestones hub** | `/milestones/{recent,catalog}.php` |
| **Player busy** | `/player/{profile,games}.php`, `/player/opponents/{h2h,wdl,goals,dds}.php`, `/player/opponents/h2h.php?opponent={id}`, `/player/milestones/{garden,chronology}.php` |
| **Player small** | same tabs with low-game player id |
| **Entities** | `/game.php?id=`, `/milestone.php?key=` (pick real fixtures) |

**Skip:** `/join.php`, `/boxart.php`, admin/orphan tools unless linked from hub IA.

### Online JSON APIs (no `amiga_` prefix)

Include hot paths from Activity + Status + player charts, e.g.:

- `server_daily_active_players.php`, `server_games_by_month.php`, `server_games_by_year.php`, `server_active_players_by_month.php`, `server_play_texture.php`, …
- `player_head_to_head.php`, `player_rating_history.php`, `player_rank_history.php`, `player_games_by_month.php`, …
- `status_period_day_games.php`, `status_period_points_league.php`, `server_period_activity_leaderboard.php`, …

Use `Glob` on `site/public_html/api/` — **exclude** `amiga_*`.

### Fixtures (discover once, document in probe header)

Run a one-liner on local `ko2unity_db`:

- **Busy player:** highest `NumberGames` on `playertable` (or known staging id — document choice)
- **Small player:** low but non-zero games
- **Top opponent:** opponent with most games vs busy player (`ratedresults` / matchup helper)
- **Sample game id**, **milestone key** that exists locally

Online has **no Amiga-style TT cutoffs** — census **present-mode only** unless a page has real period params (Status/Activity); if so, test **default + one heavy period** only.

### Probe output

Mirror Amiga results format:

- Ranked table: Group · Worst (s) · Path · HTTP/body sanity
- Flag threshold: start at **0.8 s** but also tag **feel tiers**:
  - **Instant** ≤0.25 s · **Smooth** ≤0.40 s · **Noticeable** 0.40–0.70 s · **Heavy** >0.70 s
- Write `scripts/oneoff/online_realm_full_census_results.md`

---

## Phase 2 — lib probes for top N suspects (~same session or follow-up)

For the **worst 10–15 online paths** (SQL-suspect, not pure HTML):

- Add **`scripts/oneoff/online_*_tt_probe.php`** or one consolidated **`online_realm_hot_path_probe.php`**
- Call the **actual include functions** the page uses; print ms per call
- Classify anti-pattern: wide `ratedresults` scan, duplicate facet round-trips, uncached COUNT + rows, chart API duplicate of page SQL, etc.

**Do not change production libs in the audit pass.**

---

## Phase 3 — deliverable report

Handoff: **`docs/orchestration/agent-handoffs/2026-07-04-012-online-realm-query-audit.md`** with:

1. **Executive summary** — how many paths, how many >0.8 s, top 5 feel pain points
2. **Full ranked census table** (or link to results md)
3. **SQL vs HTML vs client** classification per flagged page
4. **Recommended fix batches** (Track O1, O2, …) — **read-time PHP only**, estimated ROI
5. **Explicitly deferred** list — anything that would need schema/ops/stored truth (one line each, no designs)

Update **`PROJECT_MEMORY.md`** one line when audit completes.

---

## Verification commands

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe scripts\oneoff\online_realm_full_census_probe.php --out=scripts\oneoff\online_realm_full_census_results.md
```

Sanity: HTTP 200, no `Fatal error` / `Warning:` in body, `<tr` counts noted for game tables.

---

## UTF-8 (Windows)

New `.php` / `.md` via PowerShell `[System.IO.File]::WriteAllText(..., UTF-8 no BOM)`. Edit existing files with StrReplace.

---

## What NOT to do in this chat

- Do not open `ops/` for implementation
- Do not run `run_ops_sim.php`, `prove`, or ladder rebuilds
- Do not assign Steve / Part B migration registers
- Do not fix Amiga pages
- Do not ship query optimizations until Dagh approves a follow-up track from the report
```

---

## Notes for Dagh

- This is **Phase 1 only** (inventory). Fix tracks come after you pick batches from the report.
- Online **`ratedresults`** is the usual suspect (~75k rows, wide) — the Amiga lesson applies: **row count ≠ cost**; probe lib ms, do not guess.
- If the agent proposes indexes or new tables, redirect: *“note under deferred stored-truth; this audit is read-time only.”*