# Amiga player create — implementation plan

**Status:** **PC-1–PC-7 shipped (Jul 2026)** — local `ko2amiga_db` via **prove** (ground bundle); staging via prove → export (part 01 schema). **PC-8** staging spot-check; **PC-9** browser delete league = phase 2.

**Policy:** [`amiga-player-create-policy.md`](amiga-player-create-policy.md) (rev. 2.1)  
**Parent:** [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · [`amiga-data-contract.md`](amiga-data-contract.md)

**Prerequisite:** [`amiga-country-registry-implementation-plan.md`](amiga-country-registry-implementation-plan.md) **CR-5+ shipped** — organizer country validate + registry JSON on staging.

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit** unless Dagh asks.

**Schema:** **New DDL** on `amiga_players` (`player_source`) in [`ground/001_core.sql`](../scripts/amiga/sql/ground/001_core.sql) via `prove`; `amiga-data-contract.md` register (Part B done Jul 2026). Re-import does **not** backfill provenance — column default `import`.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**PC1–PC12**), naming algorithm, storage rev. 2.1, orphan rules §6.3 |
| **This plan** | Schema choice, file-level tasks, STOP gates, slice order |
| **Starter prompt** | Optional one-liner for a fresh agent chat — see §PC-0 |

---

## Readiness (Jul 2026)

**Yes — ready to implement.** Policy rev. 2.1 closes the open storage/orphan questions (permanent roster, X→Y re-use, **N on Y blocks delete when X removed**). Country registry dependency is shipped. Reference code exists (`player_names.py`, `player_registry.py`, organizer create-league draft in `fixtures.php`).

**Locked in this plan (not re-open without user):**

| # | Decision |
|---|----------|
| **PL1** | Provenance column **`player_source`** `ENUM('import','live_ops') NOT NULL DEFAULT 'import'` on `amiga_players` |
| **PL2** | Import corpus: all pre-migration rows backfilled **`import`**; L3 import never writes `live_ops` |
| **PL3** | Suggestion algorithm **ported to PHP** for browser (same rules as Python §5; no subprocess) |
| **PL4** | Drop **≤3 char** product cap — use full minimum-prefix sweep (`koa_abbreviation_candidates`) |
| **PL5** | v1 browser surface = **Create league** compose only (policy §7); no mid-tournament create UI |
| **PL6** | Orphan sweep hooks **`fixtures cleanup-generated`** in **PC-7**; browser delete league = **PC-9** phase 2 |
| **PL7** | Organizer **delete chip** on compose draft = explicit orphan delete when eligible (PC-6) |

---

## How to use this plan

1. Execute slices **PC-1 → PC-8** in order (**PC-0** = policy + plan — done).
2. **STOP** after **PC-4** until unit tests + verify script pass on local `ko2amiga_db`.
3. **STOP** after **PC-5** until manual browser drill on local organizer (create → preview name → chip → submit league).
4. **STOP** after **PC-7** until orphan oracle: create **N** on X + Y, delete X via CLI — **N** must survive.
5. Do **not** orphan-delete without **`player_source = 'live_ops'`** guard.
6. After **PC-8**: UPDATE_DOCS Part A + Part B (DDL register).

**Phase 2 backlog:** **PC-9** — browser Advanced tab delete league (same cleanup path as CLI).

---

## Reference files (copy patterns)

| Area | Reference |
|------|-----------|
| Policy + decisions | [`amiga-player-create-policy.md`](amiga-player-create-policy.md) |
| Naming core (Python) | [`scripts/amiga/player_names.py`](../scripts/amiga/player_names.py) — `normalize_display_name`, `koa_abbreviation_candidates`, `suggest_koa_display_name`, `identity_key` |
| CLI create today | [`scripts/amiga/player_registry.py`](../scripts/amiga/player_registry.py) |
| Onboard newcomer (atomic) | [`scripts/amiga/tournament_fixtures.py`](../scripts/amiga/tournament_fixtures.py) — `onboard_newcomer` |
| Tournament delete CLI | [`scripts/amiga/tournament_fixtures.py`](../scripts/amiga/tournament_fixtures.py) — `cleanup_generated_tournament` (**no orphan sweep today**) |
| Country validate | [`site/public_html/includes/k2_amiga_country_registry.php`](../site/public_html/includes/k2_amiga_country_registry.php) |
| Organizer create league | [`site/public_html/amiga/ops/fixtures.php`](../site/public_html/amiga/ops/fixtures.php) |
| Player search picker JS | [`site/public_html/js/amiga-organizer-player-picker.js`](../site/public_html/js/amiga-organizer-player-picker.js) |
| Country picker JS | [`site/public_html/js/amiga-organizer-country-picker.js`](../site/public_html/js/amiga-organizer-country-picker.js) |
| Player search API | [`site/public_html/api/player_search.php`](../site/public_html/api/player_search.php) |
| Ground DDL | [`scripts/amiga/sql/ground/001_core.sql`](../scripts/amiga/sql/ground/001_core.sql) |
| Schema migrations pattern | [`scripts/amiga/sql/`](../scripts/amiga/sql/) — next numbered migration after audit |
| Holy loop | [`scripts/amiga/README.md`](../scripts/amiga/README.md) |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **PC-0** | Policy + this plan | Dagh OK — **done** |
| **PC-1** | DDL `player_source` + backfill | Migration applies cleanly; all existing rows `import` |
| **PC-2** | Python: provenance, country validate, orphan helper, tests | pytest green; `players create` sets `live_ops` |
| **PC-3** | PHP naming lib + shared orphan eligibility | PHP unit-style probe or fixtures test script |
| **PC-4** | `verify_player_create.py` + prove wiring | verify fails on missing provenance / bad orphan delete |
| **PC-5** | Browser create player (suggest preview + confirm + chip) | Local organizer drill OK |
| **PC-6** | Draft chip delete → orphan delete when eligible | Delete removes row; import player chip delete refused |
| **PC-7** | Orphan sweep on `cleanup-generated` + X/Y oracle | CLI delete X keeps N on Y |
| **PC-8** | Staging checklist + docs closure | Staging organizer spot-check |

**PC-9 (phase 2):** Browser **Delete league** on Advanced tab → same cleanup + orphan sweep.

---

## Existing platform vs this track (dependency audit)

**Nothing in PC-1–PC-8 waits on unimplemented work elsewhere.** External prerequisites are already shipped; tournament delete for orphan testing is **CLI today**, not a future slice.

### Already implemented (use, do not rebuild)

| Capability | Status | Where | Used by |
|------------|--------|-------|---------|
| **Delete unplayed generated tournament** | **Shipped (CLI)** | `python -m scripts.amiga fixtures cleanup-generated --tournament-id N` · `cleanup_generated_tournament()` in [`tournament_fixtures.py`](../scripts/amiga/tournament_fixtures.py) | **PC-7** hooks orphan sweep **into** this function |
| Guards: no Access import, no games, builder-generated only | Shipped | Same function + [`amiga-data-contract.md`](amiga-data-contract.md) | PC-7 inherits; PC-9 mirrors |
| KOA suggest / create (CLI) | Shipped | [`player_names.py`](../scripts/amiga/player_names.py), [`player_registry.py`](../scripts/amiga/player_registry.py) | PC-2 aligns provenance; PC-5 browser parity |
| Onboard newcomer (create + entrant) | Shipped | `fixtures onboard-newcomer` | Reference only; v1 browser uses compose draft path |
| Organizer create-league compose + player search | Shipped | [`fixtures.php`](../site/public_html/amiga/ops/fixtures.php), [`amiga-organizer-player-picker.js`](../site/public_html/js/amiga-organizer-player-picker.js) | PC-5 extends |
| Country registry + validate on create | Shipped (CR-5+) | [`k2_amiga_country_registry.php`](../site/public_html/includes/k2_amiga_country_registry.php) | PC-3/PC-5 |
| `tournament_entrants` + kitchen-marathon builder | Shipped | fixture tooling | PC-7 oracle needs entrants on X and Y |

### Not implemented elsewhere (explicitly in this plan)

| Capability | Slice | Notes |
|------------|-------|-------|
| `player_source` provenance column | **PC-1** | Blocks PC-2+ |
| Orphan eligibility helpers | **PC-2** (Python), **PC-3** (PHP) | Shared callables — not a separate product feature |
| Orphan sweep on tournament delete | **PC-7** | **Extends** existing `cleanup_generated_tournament` — does **not** add a new delete verb |
| Browser Create player | **PC-5** | |
| Draft chip orphan delete | **PC-6** | No tournament row; not tournament delete |
| Browser Delete league | **PC-9** | **Phase 2** — thin UI over same cleanup path as CLI; **not** required for PC-1–PC-8 |

### Orphan delete call sites (one helper, three triggers)

```text
delete_orphan_live_players_for_tournament()   ← PC-2 Python; mirror in PC-3 PHP
  ├─ PC-6  draft chip remove (compose — no tournament_id yet; direct player delete)
  ├─ PC-7  cleanup_generated_tournament()   ← EXISTING CLI entry point
  └─ PC-9  fixtures.php Advanced POST         ← future; calls same logic as PC-7
```

**PC-7 is not “implement delete tournament.”** It is “when the **existing** CLI delete runs, also delete eligible `live_ops` zero-game players who have no other entrant links.” The X/Y oracle in PC-7 uses **`fixtures cleanup-generated`** only — no browser work required.

### Slice dependency matrix

| Slice | Depends on | External blocker? |
|-------|------------|-------------------|
| **PC-1** | — | No |
| **PC-2** | PC-1 | No |
| **PC-3** | PC-1 (read `player_source`; suggest needs DB) | No |
| **PC-4** | PC-1, PC-2 | No |
| **PC-5** | PC-3, country registry (shipped) | No |
| **PC-6** | PC-3 (orphan helper) | No — does **not** need tournament delete |
| **PC-7** | PC-2 (orphan helper); **CLI `cleanup-generated` (shipped)** | **No** |
| **PC-8** | PC-1–PC-7 | No |
| **PC-9** | PC-7 + PC-3; **CLI cleanup semantics (shipped)** | No — but **out of PC-1–PC-8 scope** until requested |

### Out of scope / other tracks (not blockers)

| Item | Track | Relation to player create |
|------|-------|---------------------------|
| `delete-unfinalized-tournament` / Case B finalized delete | [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) L5 repair | Different verb — rated/finalized path; **not** PC-7 |
| Mid-tournament Create player (open league) | Policy phase 2 | Not v1 |
| Sitewide hide zero-game players | Policy §6.4 audit | Optional later |

---

## PC-0 — Policy + plan (done)

### Goal

Decisions locked before code.

### Deliverables

- [x] [`amiga-player-create-policy.md`](amiga-player-create-policy.md) rev. 2.1
- [x] This plan

### Optional starter prompt (new chat)

```text
Track: Amiga player create. Read docs/amiga-player-create-policy.md + amiga-player-create-implementation-plan.md. Continue at slice PC-1 (or PC-N if prior slice verified). Do not git commit unless asked.
```

---

## PC-1 — Schema: `player_source` provenance

### Goal

Distinguish import corpus from live-created rows for orphan cleanup (policy §6.3 condition 1).

### Tasks

- [ ] Update **`scripts/amiga/sql/ground/001_core.sql`** (and mirror **`sql/001_core.sql`** if present) for greenfield installs.
- [ ] Document column in **`docs/amiga-data-contract.md`** `amiga_players` row (Part B at PC-8).

### Files

| Action | Path |
|--------|------|
| Edit | `scripts/amiga/sql/ground/001_core.sql` |

### Verification

```powershell
python -m scripts.amiga prove
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -e "SELECT player_source, COUNT(*) FROM ko2amiga_db.amiga_players GROUP BY player_source"
# Expect: import only, count = full corpus
```

**STOP** if prove fails or any row is not `import` after backfill.

---

## PC-2 — Python: provenance, validate, orphan helper

### Goal

CLI path matches policy; shared orphan eligibility for CLI + later PHP parity tests.

### Tasks

- [ ] **`player_registry.create_player`**: require choosable country when `--country` passed (reuse registry loader or shared validate); set **`player_source = 'live_ops'`** on insert.
- [ ] Add **`scripts/amiga/player_orphans.py`** (or module section in `player_registry.py` if small):
  - `is_orphan_deletable(conn, player_id, *, excluding_tournament_id=None) -> bool` — policy §6.3 conditions 1–3.
  - `delete_orphan_live_players_for_tournament(conn, tournament_id, *, dry_run=False) -> list[int]` — entrants on tournament X; per player check other entrant links; delete eligible only.
- [ ] Align **`suggest_koa_display_name`** product path: ensure callers use full candidate list (policy §5.5 — no ≤3 cap as refusal).
- [ ] Add **`scripts/amiga/test_player_create.py`**:
  - suggest uniqueness / mononym reject / exhaustion
  - create sets `live_ops`
  - orphan helper: mock entrant rows — **N on Y blocks delete when cleaning X**

### Files

| Action | Path |
|--------|------|
| Edit | `scripts/amiga/player_registry.py` |
| New | `scripts/amiga/player_orphans.py` (if split) |
| New | `scripts/amiga/test_player_create.py` |

### Verification

```powershell
python -m pytest scripts/amiga/test_player_create.py -q
python -m scripts.amiga players suggest-name --full-name "Test Unique Surname"
python -m scripts.amiga players create --name "Test Un" --country "Norway" --dry-run
```

**STOP** if tests fail or create omits `live_ops`.

---

## PC-3 — PHP naming lib + orphan eligibility

### Goal

Browser suggest/create without Python on the web path; one eligibility function for POST handlers.

### Tasks

- [ ] Add **`site/public_html/includes/k2_amiga_player_naming.php`**:
  - Port: `k2_amiga_normalize_display_name`, `k2_amiga_identity_key`, `k2_amiga_suggest_koa_display_name($fullName, mysqli $con)` — load taken keys from `amiga_players.name`.
  - Refusal payloads match policy §5.4 (mononym, empty, exhaustion, bad country).
- [ ] Add **`k2_amiga_player_orphan_deletable(mysqli $con, int $playerId, ?int $excludingTournamentId = null): bool`** — mirror Python §6.3.
- [ ] Add **`k2_amiga_player_create_live(mysqli $con, string $fullName, string $countryOfficialName): array`** — suggest + validate country + insert `live_ops` row in transaction; return `{player_id, name}` or throw.

### Files

| Action | Path |
|--------|------|
| New | `site/public_html/includes/k2_amiga_player_naming.php` |

### Verification

```powershell
# One-off PHP probe via Laragon (adjust path):
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -r "require 'site/public_html/includes/k2_amiga_player_naming.php'; ..."
```

Or small **`scripts/oneoff/amiga_player_naming_probe.php`** if easier — delete or keep as dev probe per repo habit.

**STOP** if PHP suggest diverges from Python on a shared fixture list (add parity cases to PC-2 tests).

---

## PC-4 — Verify + prove wiring

### Goal

Regression gate before browser UI.

### Tasks

- [ ] Add **`scripts/amiga/verify_player_create.py`**:
  - Every `player_source = 'live_ops'` row has registry-valid `country` (join registry loader).
  - No `live_ops` player with games missing from participation path (sanity).
  - Optional: no `live_ops` row with empty country.
- [ ] Wire into **`scripts/amiga/__main__.py`** prove suite (after country registry verify — ordering documented in README).
- [ ] Extend verify with **orphan invariant probe** when test fixtures exist (or document manual oracle in PC-7).

### Verification

```powershell
python -m scripts.amiga verify-player-create
python -m scripts.amiga prove
```

**STOP** if verify fails on clean DB (should pass with zero `live_ops` rows).

---

## PC-5 — Browser: Create player on compose

### Goal

Organizer **Create league** flow: full name + country → read-only suggested name → confirm → draft chip (policy §7).

### Tasks

- [ ] **`fixtures.php`** compose section: **Create player** block below **Find player**:
  - Full name input, country `<select>` (reuse **`amiga_fixture_render_create_country_field`** pattern or player-specific used/more lists from registry).
  - **Suggest** button or live preview via POST/GET action `suggest_player` (same page, preserve draft query params like `cp_player[]`).
  - Read-only preview of canonical name before confirm.
  - **Create player** POST: `k2_amiga_player_create_live()` → redirect back with new id in draft (`cp_player[]`) — same as **Add player** outcome.
- [ ] Add **`site/public_html/js/amiga-organizer-player-create.js`** (deferred via `k2OnPageReady`):
  - Optional AJAX suggest endpoint **or** form round-trip only for v1 (round-trip OK if UX acceptable).
  - After create, append chip + hidden `player_ids[]` like picker does.
- [ ] Reuse organizer password gate (no new auth).

### UX checklist

- [ ] Mononym shows error, no insert
- [ ] Exhausted abbreviations show error
- [ ] Created player appears in **Find player** search immediately
- [ ] Duplicate add to chips prevented (same as existing picker)

### Verification

Manual on `http://ratingskickoff.test/amiga/ops/fixtures.php` (organizer password):

1. Suggest `Mark Bentley` → preview (e.g. `Mark Be` if free)
2. Confirm → chip on draft
3. Submit league → entrants include new player

**STOP** until drill passes.

---

## PC-6 — Draft chip delete (orphan)

### Goal

Policy PC9 + §6.5 — delete live-created zero-game player from compose draft.

### Tasks

- [ ] Chip **remove** control on compose draft (only for players **`orphan_deletable`** without excluding tournament — no tournament id yet).
- [ ] POST action **`delete_draft_player`**: verify eligibility → `DELETE FROM amiga_players WHERE id = ? AND player_source = 'live_ops'` + game count 0 + no other entrant links.
- [ ] Refuse delete for **`import`** players (remove from draft only — drop chip, do not DELETE row).

### Verification

- Create live player, delete chip → row gone from DB
- Add corpus player, remove chip → row remains in DB

---

## PC-7 — Orphan sweep on tournament delete

### Goal

Policy §6.3.1 — when the **existing** CLI tournament delete runs, also remove eligible live-created zero-game players who were **only** on that tournament.

**Prerequisite (already shipped):** `fixtures cleanup-generated` / `cleanup_generated_tournament()` — see §Existing platform. **Do not** wait for PC-9 (browser delete).

### Tasks

- [ ] Call **`delete_orphan_live_players_for_tournament`** (from PC-2) inside **`cleanup_generated_tournament`** — collect tournament entrant player ids, run per-player eligibility with `excluding_tournament_id`, delete eligible rows, **then** existing `DELETE FROM tournaments` (audit CASCADE on `tournament_entrants`).
- [ ] Optional: fix `--dry-run` so it does not execute the tournament `DELETE` before rollback (today dry-run still runs delete then rolls back — confusing for operators).
- [ ] Oracle test in **`test_player_create.py`** or integration script:
  - Create **N** live on tournament **X** and **Y** (zero games) — use `fixtures onboard-newcomer` or `players create` + `add-entrant`
  - `fixtures cleanup-generated --tournament-id X`
  - Assert **N** still exists
  - Create **M** live only on **X** → cleanup **X** → **M** deleted

### Verification

```powershell
python -m pytest scripts/amiga/test_player_create.py -q -k orphan
python -m scripts.amiga fixtures cleanup-generated --tournament-id ID --dry-run
```

**STOP** until X/Y oracle passes.

---

## PC-8 — Staging + docs closure

### Goal

Ship path documented; staging spot-check.

### Tasks

- [ ] WinSCP sync PHP + JS; **`scripts\export_ko2amiga_db.ps1`** → sync `_import/` → browser import apply
- [ ] Browser checklist on staging organizer (suggest, create, chip, league submit)
- [ ] UPDATE_DOCS Part A: MEMORY, policy implementation plan link, `feature-log.md`, `amiga-live-ops-platform.md`, `scripts/amiga/README.md`
- [ ] UPDATE_DOCS Part B: migration register, `amiga-data-contract.md` `player_source` column

### Verification

Staging organizer create drill + `verify-player-create` on staging import DB after migration.

---

## PC-9 — Browser delete league (phase 2)

### Goal

Expose **existing** CLI cleanup + PC-7 orphan sweep in organizer Advanced tab (policy backlog §12).

**Depends on PC-7** (orphan sweep in cleanup path) and **PC-3** (PHP helper if browser calls PHP directly). **Does not** block PC-1–PC-8 — operators can delete via CLI until PC-9 ships.

### Tasks (outline)

- [ ] Advanced tab POST action — mirror CLI guards (generated only, zero games)
- [ ] Call same cleanup + orphan sweep as `cleanup_generated_tournament` (shared PHP lib or documented subprocess — prefer shared PHP in `k2_amiga_player_naming.php` / ops include)
- [ ] Flash message listing deleted orphan player names (optional UX)

**Not in PC-1–PC-8 order** — start only when Dagh asks.

---

## Orphan eligibility (implementer checklist)

Copy of policy §6.3 — **all** required for delete:

1. `player_source = 'live_ops'`
2. Zero rows in `amiga_games` for player A or B
3. No `tournament_entrants` row on any tournament **≠** excluded delete target (includes draft/running/finalized)
4. Allowed trigger (draft chip delete, or tournament cleanup verb)

**Never** delete `import` rows or any player with ≥1 game.

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| PHP/Python suggest drift | Shared test vectors in PC-2; parity probe in PC-3 |
| CASCADE deletes wrong FK order | Run orphan sweep before tournament DELETE; dry-run first |
| Zero-game clutter in site search | Accepted tradeoff policy §6.4; audit later if needed |
| Operator creates player but never submits league | PC-6 chip delete + optional future sweep (§6.5) |

---

## Changelog

| Date | Note |
|------|------|
| 2026-07-07 | Plan created — policy rev. 2.1; slices PC-1–PC-9; locked `player_source` provenance |
| 2026-07-07 | §Existing platform — dependency audit: PC-7 extends shipped CLI `cleanup-generated` |
| 2026-07-07 | **PC-1–PC-7 shipped** — code + verify; PC-8 staging spot-check + PC-9 browser delete league remain |