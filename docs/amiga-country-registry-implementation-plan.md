# Amiga country registry — implementation plan

**Status:** **CR-1–CR-7 shipped (Jul 2026)** — CR-8 staging/browser closure pending; CR-9 = phase 2 backlog.

**Policy:** [`amiga-country-registry-policy.md`](amiga-country-registry-policy.md)  
**Parent:** [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-ground-stack.md`](amiga-ground-stack.md) · [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md)

**Execution:** Slices **in order**. Run each slice **Verification** before continuing. **Do not git commit** unless Dagh asks.

**Migration:** **L3 witness value change** (no new DDL). After **CR-5**, **`python -m scripts.amiga prove`** is the repair/sign-off path. Part B registers **not** expected unless import contract docs need a one-line note — Part A only at closure.

---

## Why a plan (not just policy)

| Artifact | Role |
|----------|------|
| **Policy** | Locked product (**CR1–CR29**), registry schema, L3 rules, verify gate, flag-icons (CR29) |
| **This plan** | File-level tasks, STOP gates, commands, slice order |
| **Starter prompt** | Optional one-liner for a fresh agent chat — see §CR-0 |

---

## How to use this plan

1. Execute slices **CR-1 → CR-8** in order (**CR-0** = policy + plan — done).
2. **STOP** after **CR-5** until **`prove` exits 0** on local `ko2amiga_db`.
3. **STOP** if Countries hub row counts change vs pre-migration (except token renames: `N. Ireland` → `Northern Ireland`, `UAE` → `United Arab Emirates`).
4. **Do not** patch witness `country` columns with ad-hoc SQL — re-import only (**CR16**).
5. **CR-2 before CR-7** — full choosable flag SVG set must exist before organizer “More countries” UI ships.
6. After **CR-8**: UPDATE_DOCS Part A (MEMORY, policy changelog, import-layer, staging handoff if export path touched).

**Phase 2 backlog (not in slice order):** **CR-9** — URL 301 aliases, sitewide shorthand toggle, edit country after create.

---

## Locked decisions (do not re-open without user)

See policy **CR1–CR29**. Compressed for implementers:

- **String canonical** — DB stores registry `official_name` (not ISO codes in columns)
- **One registry** for player nationality + tournament host
- **`data/amiga/country_registry.json`** — git authority; Python + PHP read it
- **L3 only** — canonicalize legacy aliases after existing import corrections
- **Unknown** — sentinel only; not in registry; not choosable
- **UK home nations** — England, Scotland, Wales, Northern Ireland; **not** United Kingdom
- **Ireland**, **Taiwan** — explicit official names per policy
- **Organizer UI** — used countries first; **“More countries”** for full choosable set
- **No free text** on create
- **Flags** — registry owns `flag_code`; **[lipis/flag-icons](https://github.com/lipis/flag-icons)** `flags/4x3/` vendored + synced to `img/flags/amiga/` (**CR29**); retire PHP/JS duplicate maps
- **No** derived “used countries” table in v1

---

## Reference files (copy patterns)

| Area | Reference |
|------|-----------|
| Policy + JSON schema | [`amiga-country-registry-policy.md`](amiga-country-registry-policy.md) §5 |
| Import corrections | [`scripts/amiga/import_corrections.py`](../scripts/amiga/import_corrections.py) — `apply_catalog_corrections`, `PLAYER_COUNTRY_OVERRIDES`, `WORLD_CUP_VENUES` |
| Import pipeline | [`scripts/amiga/import_access.py`](../scripts/amiga/import_access.py) — `prepare_witness_from_l2`, `persist_witness_to_mysql` |
| Manifest writer | [`scripts/amiga/import_manifest.py`](../scripts/amiga/import_manifest.py) |
| Manifest verify | [`scripts/amiga/verify_import_manifest.py`](../scripts/amiga/verify_import_manifest.py) |
| Prove entry | [`scripts/amiga/__main__.py`](../scripts/amiga/__main__.py) — `prove` subcommand |
| Country slice verify | [`scripts/amiga/verify_country_slice.py`](../scripts/amiga/verify_country_slice.py) — must pass after token rename |
| Flag + table cells | [`site/public_html/includes/k2_amiga_country_flag.php`](../site/public_html/includes/k2_amiga_country_flag.php) |
| Activity chart flags | [`site/public_html/js/amiga-activity-charts.js`](../site/public_html/js/amiga-activity-charts.js) |
| Countries hub token SQL | [`site/public_html/includes/amiga_countries_lib.php`](../site/public_html/includes/amiga_countries_lib.php) |
| Organizer create | [`site/public_html/amiga/ops/fixtures.php`](../site/public_html/amiga/ops/fixtures.php) |
| Flag SVG source + sync | Policy §5.6 · [lipis/flag-icons](https://github.com/lipis/flag-icons) MIT · `data/vendor/flag-icons/flags/4x3/` → `site/public_html/img/flags/amiga/` |
| Flag SVG assets (site) | [`site/public_html/img/flags/amiga/`](../site/public_html/img/flags/amiga/) — 22 SVGs today (same source); **CR-2** expands to full choosable set |
| Holy loop | [`scripts/amiga/README.md`](../scripts/amiga/README.md) |

---

## Slice map

| Slice | Deliverable | STOP gate |
|-------|-------------|-----------|
| **CR-0** | Policy + this plan | Dagh OK — **done** |
| **CR-1** | `country_registry.json` + build script + unit tests | JSON validates; corpus tokens + aliases present; WC host strings ∈ registry |
| **CR-2** | Vend **flag-icons** + `sync_country_flag_svgs.py` + SVG tests | Every choosable `flag_code` has `{code}.svg` on disk; count ≫ 22 |
| **CR-3** | Python: load registry, canonicalize, validate, manifest | `import-witness` writes normalized countries; manifest has `country_token_normalizations` |
| **CR-4** | `verify_country_registry.py` wired into `prove` (DB tokens + flag files) | Standalone verify fails on bad token or missing SVG |
| **CR-5** | Full local **`prove`** + parity spot-checks | `prove` exit 0; Stephen D = Northern Ireland; Dubai I = United Arab Emirates; Countries index still 21 rows |
| **CR-6** | PHP registry lib + flag refactor + JS dedupe | Flags on Denmark roster/profile unchanged; no static `$map` in flag PHP |
| **CR-7** | Organizer country select (used + More countries) + server validate | Create league rejects free text; Taiwan/etc. show flags in More list |
| **CR-8** | Staging sync + browser checklist + docs closure | Staging spot-check; MEMORY updated |

**CR-9 (phase 2):** 301 retired URL tokens · sitewide shorthand · edit country after create — policy only until requested.

---

## CR-0 — Policy + plan (done)

### Goal

Decisions locked before code.

### Deliverables

- [x] [`amiga-country-registry-policy.md`](amiga-country-registry-policy.md)
- [x] This plan

### Optional starter prompt (new chat)

```text
Track: Amiga country registry. Read docs/amiga-country-registry-policy.md + amiga-country-registry-implementation-plan.md. Continue at slice CR-1 (or CR-N if prior slice verified). Do not git commit unless asked.
```

---

## CR-1 — Registry JSON + build tooling

### Goal

Version-controlled registry exists; corpus and WC hosts are covered; legacy aliases declared.

### Tasks

- [ ] Add **`data/amiga/country_registry.json`** with `version`, `built_from`, `countries[]` per policy §5.2.
- [ ] Add **`scripts/amiga/build_country_registry.py`** (or one-off documented ritual):
  - Seed from ISO 3166-1 English short names (vendor a static JSON snapshot in `data/amiga/iso3166/` **or** embed generated list — **no runtime download**).
  - **Add** England, Scotland, Wales, Northern Ireland if absent.
  - **Exclude** United Kingdom from `choosable: true`.
  - **Set** Ireland, Taiwan official names per CR7–CR8.
  - **Set** `legacy_aliases`: `N. Ireland` → Northern Ireland; `UAE` → United Arab Emirates.
  - **Set** `site_shorthand`: `UAE` on United Arab Emirates (metadata only).
  - Assign **`flag_code`** 1:1 (ISO alpha-2 lower case; UK subdivisions `gb-eng`, `gb-sct`, `gb-wls`, `gb-nir`).
- [ ] Add **`scripts/amiga/country_registry.py`** — shared loader: `load_registry()`, `official_names()`, `alias_map()`, `resolve_official(name)`, `validate_official(name)`.
- [ ] Add **`scripts/amiga/test_country_registry.py`**:
  - Every `WORLD_CUP_VENUES` country ∈ official names.
  - Every `PLAYER_COUNTRY_OVERRIDES` value ∈ official names.
  - Legacy aliases map to valid official names.
  - No duplicate `official_name` or `flag_code`.
  - United Kingdom not choosable.

### Files

| Action | Path |
|--------|------|
| New | `data/amiga/country_registry.json` |
| New | `data/amiga/iso3166/` (optional snapshot input for build script) |
| New | `scripts/amiga/country_registry.py` |
| New | `scripts/amiga/build_country_registry.py` |
| New | `scripts/amiga/test_country_registry.py` |

### Verification

```powershell
python -m pytest scripts/amiga/test_country_registry.py -q
python -c "from scripts.amiga.country_registry import load_registry; r=load_registry(); print(len(r['countries']), 'countries')"
```

**STOP** if corpus post-alias tokens missing from registry or WC host mismatch.

---

## CR-2 — Flag SVG pack (lipis/flag-icons)

### Goal

Every **choosable** registry `flag_code` has a matching SVG on disk from a **single** vendored source — same library as the existing 22 Amiga flags (`id="flag-icons-{code}"`, 640×480 viewBox).

### Tasks

- [ ] Vendor **[lipis/flag-icons](https://github.com/lipis/flag-icons)** (MIT) at a **pinned release** under **`data/vendor/flag-icons/`** — commit the **`flags/4x3/`** tree (not npm at runtime).
- [ ] Add **`data/vendor/flag-icons/VERSION.txt`** — tag or commit hash of the vendored release.
- [ ] Add **`scripts/amiga/sync_country_flag_svgs.py`**:
  - Read `country_registry.json`.
  - For each row with `choosable: true` (minimum scope), copy `data/vendor/flag-icons/flags/4x3/{flag_code}.svg` → `site/public_html/img/flags/amiga/{flag_code}.svg`.
  - **Fail** if vendor source file missing for a required `flag_code` (surface gaps before ship — add manual registry row fix or pick alternate code).
  - Idempotent — overwrites existing 22 files with same-source copies.
- [ ] Extend **`test_country_registry.py`** or add **`test_country_flag_svgs.py`**:
  - After sync: every choosable `flag_code` has destination SVG.
  - Spot-check SVG root `id` starts with `flag-icons-` (provenance guard).
- [ ] Add **`flag_icons_version`** (or similar) to registry JSON metadata — matches `VERSION.txt`.
- [ ] One-line MIT credit in **`docs/design-direction.md`** (Amiga flags bullet — flag-icons).
- [ ] Document vendoring ritual in **`scripts/amiga/README.md`** (how to bump flag-icons version + re-sync).

**Registry rows without flag-icons files:** resolve before ship — e.g. omit `choosable` until vendor adds code, or pick a documented alternate. **Do not** import flags from another library.

### Files

| Action | Path |
|--------|------|
| New | `data/vendor/flag-icons/` (+ `VERSION.txt`) |
| New | `scripts/amiga/sync_country_flag_svgs.py` |
| New/edit | `scripts/amiga/test_country_flag_svgs.py` |
| Bulk update | `site/public_html/img/flags/amiga/*.svg` |
| Edit | `docs/design-direction.md` — MIT attribution |

### Verification

```powershell
python scripts/amiga/sync_country_flag_svgs.py
python -m pytest scripts/amiga/test_country_registry.py scripts/amiga/test_country_flag_svgs.py -q
(Get-ChildItem "site/public_html/img/flags/amiga/*.svg").Count
# Expect: one SVG per choosable flag_code (typically ~240+), all from flag-icons 4x3
```

**STOP** if any choosable `flag_code` lacks a synced SVG or vendor source file.

---

## CR-3 — L3 import: canonicalize + validate + manifest

### Goal

Import writes only registry official names; legacy aliases retired in one pass.

### Tasks

- [ ] In **`import_access.py`** (or new **`import_country_registry.py`**), after `apply_player_country_corrections` + `apply_catalog_corrections`, before player/tournament persist:
  - **`canonicalize_country_fields(prepared)`** — rewrite `countries` dict values, tournament `country`, player override targets through `alias_map` + ensure already-official names pass through.
  - Collect **`country_token_normalizations`** list for manifest (include entity hint: player name or tournament name when known).
- [ ] **`validate_prepared_countries(prepared)`** — every non-empty country must `validate_official()`; empty allowed for missing nationality pre-override paths that resolve later.
- [ ] Extend **`import_manifest.py`** — write `transforms.country_token_normalizations` and `registry.version`.
- [ ] Extend **`verify_import_manifest.py`** — expect normalization rows for `N. Ireland` and `UAE` on fresh import; assert all catalog override canonical countries ∈ registry.
- [ ] Document pipeline order in **`import_corrections.py`** module docstring (one line — canonicalize is downstream).

### Files

| Action | Path |
|--------|------|
| Edit | `scripts/amiga/import_access.py` |
| New/edit | `scripts/amiga/import_country_registry.py` (optional split) |
| Edit | `scripts/amiga/import_manifest.py` |
| Edit | `scripts/amiga/verify_import_manifest.py` |
| Edit | `docs/amiga-import-layer.md` — manifest field no longer “Planned” |

### Verification

```powershell
python -m scripts.amiga import-witness --recreate-ground
# Inspect data/amiga/exports/import_manifest.json → country_token_normalizations (2 rows minimum)
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ko2amiga_db -N -e "SELECT name,country FROM amiga_players WHERE name='Stephen D'; SELECT name,country FROM tournaments WHERE name LIKE 'Dubai%';"
```

Expected: `Northern Ireland`, `United Arab Emirates`. No `N. Ireland` / `UAE` left in DB.

**STOP** if any distinct country ∉ registry official names.

---

## CR-4 — `verify_country_registry` + prove wiring

### Goal

Automated gate catches drift before ship.

### Tasks

- [ ] Add **`scripts/amiga/verify_country_registry.py`**:
  - Load registry JSON.
  - Query distinct `TRIM(country)` from `amiga_players` and `tournaments` (non-empty).
  - **Fail** if any value not in official names set.
  - **Fail** if any choosable `flag_code` lacks `site/public_html/img/flags/amiga/{code}.svg` (CR29).
  - Print unused choosable countries (info).
- [ ] Register CLI: **`python -m scripts.amiga verify-country-registry`**
- [ ] Add step to **`prove`** in `__main__.py` **after** `import-witness` / before or after replay (after import is minimum; **after replay** preferred so L5 tokens checked too).
- [ ] Extend **`verify_country_slice.py`** if any hard-coded country strings (`N. Ireland`, `UAE`) — grep repo and fix.

### Files

| Action | Path |
|--------|------|
| New | `scripts/amiga/verify_country_registry.py` |
| Edit | `scripts/amiga/__main__.py` |
| Edit | `scripts/amiga/README.md` — list new verify command |

### Verification

```powershell
python -m scripts.amiga verify-country-registry
# Deliberate failure test: temporarily insert bad country in work DB → verify must non-zero exit
```

---

## CR-5 — Full `prove` + parity spot-checks

### Goal

End-to-end stack sign-off on local `ko2amiga_db` after witness normalization.

### Tasks

- [ ] Run full holy loop:

```powershell
python -m scripts.amiga prove
```

- [ ] **SQL parity** (manual):

```sql
-- Still 21 distinct player nationalities
SELECT COUNT(DISTINCT TRIM(country)) FROM amiga_players WHERE TRIM(country) <> '';
-- Northern Ireland row exists; N. Ireland gone
SELECT COUNT(*) FROM amiga_players WHERE TRIM(country) = 'N. Ireland';
SELECT COUNT(*) FROM amiga_players WHERE TRIM(country) = 'Northern Ireland';
-- Host rename
SELECT COUNT(*) FROM tournaments WHERE TRIM(country) = 'UAE';
SELECT COUNT(*) FROM tournaments WHERE TRIM(country) = 'United Arab Emirates';
```

- [ ] Run **`verify_country_slice`** — country_token in derived tables matches new official names (may require full replay — `prove` handles this).
- [ ] Grep codebase for **`N. Ireland`** and **`'UAE'`** as stored-country assumptions in tests — update to official names where asserting DB truth.

### STOP gate

- **`prove` exit 0**
- Countries index **21 rows** (token rename only — Northern Ireland replaces N. Ireland bucket)
- Roster **`?country=Northern Ireland`** returns Stephen D; **`?country=N.+Ireland`** may 404 until CR-9 (document for Dagh)

---

## CR-6 — PHP registry lib + flag refactor

### Goal

Website reads registry; one flag source; display uses official names.

### Tasks

- [ ] Add **`site/public_html/includes/k2_amiga_country_registry.php`**:
  - `k2_amiga_country_registry(): array` — load JSON from repo path (config-relative or `dirname` to `data/amiga/country_registry.json` — match how other cross-root includes work).
  - `k2_amiga_country_resolve(string $token): ?array`
  - `k2_amiga_country_display_name(string $token, bool $useShorthand = false): string` — shorthand gated by constant **`K2_AMIGA_COUNTRY_SHORTHAND_DISPLAY = false`** (CR18).
  - `k2_amiga_country_choosable_rows(): array`
  - `k2_amiga_country_used_tokens(mysqli $con): list<string>` — DISTINCT union query (CR22).
  - `k2_amiga_country_validate_token(string $token): bool`
- [ ] Refactor **`k2_amiga_country_flag.php`** — `k2_amiga_country_flag_meta()` reads registry; remove static `$map`; keep compositors (`k2_amiga_lb_*_cell`) behaviour.
  - Drift / unknown token → return null (CH9). Choosable registry rows **should** always resolve to an on-disk SVG post CR-2.
- [ ] Refactor **`amiga-activity-charts.js`** — fetch flag codes from a small PHP JSON endpoint **or** inline `window.k2AmigaCountryFlagCodes` from registry at page boot (prefer one PHP helper emitting JSON to avoid duplicating 250 codes in JS).
- [ ] Grep **`N. Ireland`**, **`UAE`** in PHP/JS/tests — update display expectations only where asserting labels, not DB.

### Files

| Action | Path |
|--------|------|
| New | `site/public_html/includes/k2_amiga_country_registry.php` |
| Edit | `site/public_html/includes/k2_amiga_country_flag.php` |
| Edit | `site/public_html/js/amiga-activity-charts.js` |
| Optional | thin API or boot snippet on Activity pages |

### Verification

```text
Browser local:
/amiga/country/roster.php?country=Northern+Ireland — Stephen D + gb-nir flag
/amiga/country/roster.php?country=Denmark — unchanged row count
/amiga/countries.php — 21 rows, "Northern Ireland" label (not N. Ireland)
Profile / LB cells — flags still render for corpus countries
```

**STOP** if flag regression on corpus countries; spot-check **`tw.svg`** (or another “More countries” code) renders after CR-2.

---

## CR-7 — Organizer: registry select + validation

### Goal

Live ops create path uses registry only; UX matches CR21.

### Tasks

- [ ] **`fixtures.php`** — replace free-text country on **create league** (and player create if present):
  - `<select>` built from **`used_tokens`** sorted.
  - Checkbox or link **“More countries…”** reveals full choosable registry list (implementation detail flexible).
  - Labels: **`k2_amiga_country_display_name()`** official (CR19).
- [ ] **Server-side validate** on POST — `k2_amiga_country_validate_token()`; reject with clear error if tampered request.
- [ ] Store **`official_name`** in `tournaments.country` / `amiga_players.country`.
- [ ] Cross-check **`amiga-live-ops-practice-track.md`** — note country select in L0 drill when synced.

### Files

| Action | Path |
|--------|------|
| Edit | `site/public_html/amiga/ops/fixtures.php` |

### Verification

```text
Organizer (local or staging after sync):
Create league — Denmark in default list; pick **Taiwan** (or similar) from More countries — label + flag render.
POST forged country=NotARealPlace — rejected.
```

---

## CR-8 — Staging sync + closure

### Goal

Staging matches local sign-off; docs recorded.

### Tasks

- [ ] WinSCP sync: `data/amiga/country_registry.json`, `data/vendor/flag-icons/` (or site SVGs only if vendor stays dev-side), `site/public_html/img/flags/amiga/`, `scripts/amiga/*`, PHP includes, `fixtures.php`, any JS.
- [ ] Staging: run import on server **or** export local `ko2amiga_db` per [`amiga-staging-handoff.md`](amiga-staging-handoff.md) — follow usual handoff (Dagh WinSCP + browser import if SQL pack).
- [ ] Browser spot-check on staging:
  - Countries hub + Northern Ireland roster
  - Tournament index host filter shows United Arab Emirates for Dubai I
  - Organizer create league country select
- [ ] UPDATE_DOCS Part A: MEMORY, policy §12 changelog, this plan slice checkboxes, `amiga-import-layer.md`, optional `amiga-live-ops-platform.md` one line.

### Verification

Dagh confirms staging spot-check OK.

---

## CR-9 — Phase 2 backlog (do not start in v1)

| Item | Policy |
|------|--------|
| **301 URL aliases** | CR27 — `N.+Ireland` → Northern Ireland |
| **Sitewide shorthand** | CR18 — flip `K2_AMIGA_COUNTRY_SHORTHAND_DISPLAY` |
| **Edit country after create** | CR28 — ops feature |

---

## Risk notes

| Risk | Mitigation |
|------|------------|
| Bookmarked roster URLs with old tokens | CR-9 301; until then Dagh knows Northern Ireland URL changed |
| `verify_country_slice` / rivals keyed on old tokens | Full `prove` in CR-5; grep tests |
| Registry JSON path on staging vs local | PHP loader must resolve repo root reliably (document path in registry PHP) |
| ~240 SVGs in git | Acceptable — single vendor bump + sync script; do not hand-maintain |
| flag-icons missing code for exotic registry row | Block choosable until vendor has file, or fix `flag_code` before CR-2 sign-off |

---

## Changelog

| Date | Note |
|------|------|
| 2026-07-07 | Plan drafted — slices CR-0–CR-7 aligned with policy CR1–CR28. |
| 2026-07-07 | **CR-2 added** — full **flag-icons** 4×3 sync required (CR29); slices renumbered CR-0–CR-8; phase 2 = CR-9. |