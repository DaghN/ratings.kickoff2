# Amiga country registry — policy

**Status:** **Shipped (Jul 2026)** — CR-1–CR-7 complete on local `ko2amiga_db`; staging verified after WinSCP sync of `public_html/data/amiga/country_registry.json` + flag SVGs. Phase 2 = **CR-9** backlog only.

**Parent:** [`amiga-ground-stack.md`](amiga-ground-stack.md) (S7 nationality + host country) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) (L3 witness) · [`amiga-import-layer.md`](amiga-import-layer.md) (import manifest)

**Related:** [`amiga-country-registry-implementation-plan.md`](amiga-country-registry-implementation-plan.md) · [`amiga-countries-hub-policy.md`](amiga-countries-hub-policy.md) (CH4 country token, CH9 flags, CH17 host vs nationality) · [`amiga-data-contract.md`](amiga-data-contract.md) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (organizer create) · [`k2-table-entity-links-policy.md`](k2-table-entity-links-policy.md) · [`amiga-hof-tournament-geo-policy.md`](amiga-hof-tournament-geo-policy.md) (H8 token SQL)

---

## 1. Executive summary

Kick Off 2 Amiga uses **one country concept** for both **player nationality** and **tournament host country**. Today those are separate free-text columns that happen to share string tokens; flags and display names drift in ad-hoc maps.

This policy introduces a **single version-controlled registry** (`data/amiga/country_registry.json`) as the authority for:

- Which country names may be **stored** in L3 ground truth
- **Flag codes** (1:1 with registry entries)
- **Legacy aliases** normalized at L3 import (retire `UAE`, `N. Ireland`, etc.)
- Optional **site shorthand** labels (metadata only — off by default until enabled)

**Stored identity:** the registry **official English name** string (not ISO codes in DB columns). **Unknown** remains a read-time sentinel for empty nationality — never choosable on create.

**Repair contract:** registry or normalization changes on **living ground** → edit registry/aliases → **`simul`** on **`ko2amiga_work`**. Oracle-only: full L3 re-import + **`prove`** on frozen **`ko2amiga_db`** (no ad-hoc SQL on witness columns).

---

## 2. Scope

### 2.1 In scope

| Area | Rule |
|------|------|
| **Registry artefact** | JSON in repo; human-edited; no auto ISO sync |
| **L3 import** | Canonicalize aliases; validate all stored countries ∈ registry |
| **L3 manifest** | Log every normalization (`access` / `canonical` / `reason`) |
| **Website read** | Display + flags via registry lookup |
| **Create paths** | Tournament + player create — registry tokens only (no free text) |
| **Verify gate** | Audit script before / as part of `prove` |

### 2.2 Out of scope (v1)

| Item | Notes |
|------|-------|
| **L0–L2 changes** | Archival layers stay verbatim; normalization is L3-only |
| **Online realm (`kooldb`)** | Amiga-only policy |
| **Materialized “used countries” table** | Defer — DISTINCT query is enough at KOO scale |
| **URL slugs / ISO codes in URLs** | Keep `?country=<official name>` |
| **301 redirects for retired URL tokens** | Phase 2 (e.g. `N.+Ireland` → `Northern Ireland`) |
| **Edit country after create** | Future ops feature — noted, not v1 |
| **User preference for shorthand** | Sitewide toggle only when implemented; default = official |
| **Auto ISO/CLDR refresh** | Manual edits as needed |

### 2.3 Relationship to Countries hub (CH*)

The Countries hub policy (**CH4**, **CH9**, **CH17**) stays valid. After this track ships:

- **CH4 token SQL** unchanged (`TRIM(country)` → `Unknown` when empty).
- **Stored values** become registry **official names** only (post-L3 normalization).
- **CH9 flags** read **`flag_code` from registry** — retire duplicate maps in PHP and `amiga-activity-charts.js`.
- **CH17** unchanged in meaning: host and nationality are different fields, same registry token → same roster URL.

---

## 3. Locked product decisions

| # | Decision | Rule |
|---|----------|------|
| **CR1** | **One country concept** | Player nationality and tournament host use the **same registry** and the **same canonical token** for a given nation. |
| **CR2** | **Stored canonical = official name** | `amiga_players.country` and `tournaments.country` store the registry **`official_name`** string. No separate ISO code column in v1. |
| **CR3** | **Unknown sentinel** | Empty/NULL nationality → read-time **`Unknown`** (Countries hub). **Not** a registry row; **never** choosable on create. |
| **CR4** | **Flag code 1:1** | Each registry entry has exactly one `flag_code`; no two entries share a code. |
| **CR5** | **No free text on create** | Organizer / player create must pick from registry — no arbitrary strings. |
| **CR6** | **UK home nations** | **England**, **Scotland**, **Wales**, **Northern Ireland** are first-class choosable entities. **United Kingdom** is **not** choosable — use home nations (KOA legacy). |
| **CR7** | **Ireland** | Official name **`Ireland`** (matches corpus — not “Republic of Ireland”). |
| **CR8** | **Taiwan** | Official name **`Taiwan`** (explicit KOO choice — not ISO’s long political string). |
| **CR9** | **Registry source file** | **`data/amiga/country_registry.json`** in git — authoritative edit surface. Python import and PHP website both read this file (PHP may static-cache). |
| **CR10** | **ISO build ritual** | Registry built **once** from **ISO 3166-1 English short names**, then hand-edited for CR6–CR8 and KOO extras. **No auto-sync.** |
| **CR11** | **Legacy aliases** | Stored only in registry (`legacy_aliases[]`). Applied at **L3 import** after existing corrections. |
| **CR12** | **L3-only normalization** | Do **not** rewrite L2 `witness_player_identity` or L1/L0. |
| **CR13** | **Import pipeline order** | Name merge → `PLAYER_COUNTRY_OVERRIDES` → catalog / WC corrections → **`country_token_canonicalize`** → registry validate → persist. |
| **CR14** | **Manifest** | New section **`transforms.country_token_normalizations`** — same shape as other overrides (`access`, `canonical`, `reason`, optional `entity` hint). |
| **CR15** | **Unmapped at import** | After canonicalize + validate, any country string ∉ registry → **import error** (fail loud; fix registry or alias). |
| **CR16** | **Repair path** | Registry change on **work** → **`simul`**. Oracle archaeology → **`import-witness` + `prove`** on frozen **`ko2amiga_db`**. |
| **CR17** | **Default display** | Website shows **`official_name`** via registry lookup (matches DB post-migration). |
| **CR18** | **Site shorthand** | Optional per-row `site_shorthand` in registry (e.g. `UAE` for United Arab Emirates). **Sitewide display toggle off in v1** — metadata ready for later. |
| **CR19** | **Filter listboxes** | Official names in labels for v1 (same as CR17). |
| **CR20** | **URL identity** | Entity links keep **`?country=<official name>`** (URL-encoded). No slug/code routes in v1. |
| **CR21** | **Organizer country UI** | Default list = countries **already used** in DB (player nationality ∪ tournament host); expandable **“More countries”** shows full choosable registry. UI details deferred; concept locked. |
| **CR22** | **“Used countries” query** | Read-time `DISTINCT` union — **no** new derived table in v1. |
| **CR23** | **Community ground (Pack A)** | Witness rows at L3+ use registry official names only. |
| **CR24** | **L5 `country_token`** | Derived tables keep string tokens equal to registry **`official_name`**. |
| **CR25** | **Rename / new countries** | Manual registry edits as need arises — no formal rename migration policy in v1. |
| **CR26** | **Drift handling** | Unmapped post-migration DB value — undefined fail-soft for v1 (show raw / no flag); not a launch blocker. |
| **CR27** | **301 retired tokens** | Phase 2 — optional redirect `?country=N.+Ireland` → `Northern Ireland`. |
| **CR28** | **Edit after create** | Deferred — spec notes future ops ability to change country on existing player/tournament. |
| **CR29** | **Flag SVG source** | **[lipis/flag-icons](https://github.com/lipis/flag-icons)** (MIT) **`flags/4x3/`** set only — same source as existing 22 Amiga SVGs. Vend pinned release under `data/vendor/flag-icons/`; sync script copies to `site/public_html/img/flags/amiga/{flag_code}.svg`. **Every choosable registry row must have an SVG before ship** — not deferred. |

---

## 4. Country entity model

```text
┌─────────────────────────────────────────────────────────┐
│  data/amiga/country_registry.json  (authority)          │
│    official_name · flag_code · legacy_aliases ·         │
│    site_shorthand · choosable                           │
└──────────────────────────┬──────────────────────────────┘
                           │
         L3 import         │         Website read
              ┌────────────┴────────────┐
              ▼                         ▼
   amiga_players.country      k2_amiga_country_*()
   tournaments.country         display · flags · validate
              │                         │
              └──────── same token ─────┘
                    "Denmark"
```

| Field | Table | Meaning |
|-------|-------|---------|
| Nationality | `amiga_players.country` | Where the player is from |
| Host | `tournaments.country` | Where the event took place |

Same token → same country roster (`/amiga/country/roster.php?country=…`), same rivals entity, same flag mapping, same filter semantics.

---

## 5. Registry artefact

### 5.1 Path

**`data/amiga/country_registry.json`** — committed to git (Python build authority). **Website deploy copy:** **`site/public_html/data/amiga/country_registry.json`** — written by `build-country-registry`; **required on staging** (WinSCP syncs `public_html/` only). Read by:

- `scripts/amiga/` import pipeline (Python)
- `site/public_html/includes/k2_amiga_country_registry.php` (PHP static cache; prefers `public_html/data/amiga/` path)

Optional future: mirror into MySQL for SQL joins — **not required v1**.

### 5.2 Top-level shape

```json
{
  "version": 1,
  "built_from": "ISO 3166-1 English short names + KOO manual edits (Jul 2026)",
  "countries": [
    {
      "official_name": "Denmark",
      "flag_code": "dk",
      "legacy_aliases": [],
      "site_shorthand": null,
      "choosable": true
    },
    {
      "official_name": "Northern Ireland",
      "flag_code": "gb-nir",
      "legacy_aliases": ["N. Ireland"],
      "site_shorthand": null,
      "choosable": true
    },
    {
      "official_name": "United Arab Emirates",
      "flag_code": "ae",
      "legacy_aliases": ["UAE"],
      "site_shorthand": "UAE",
      "choosable": true
    },
    {
      "official_name": "Taiwan",
      "flag_code": "tw",
      "legacy_aliases": [],
      "site_shorthand": null,
      "choosable": true
    }
  ]
}
```

| Field | Required | Rule |
|-------|----------|------|
| `official_name` | yes | **Stored in DB**; unique; primary key for lookups |
| `flag_code` | yes | SVG basename under `/img/flags/amiga/{code}.svg`; unique; file from **flag-icons** 4×3 (CR29) |
| `legacy_aliases` | no | Strings rewritten to `official_name` at L3 import |
| `site_shorthand` | no | Shorter display label; **not stored** in DB |
| `choosable` | yes | If `false`, row exists for flags/display only (edge case); default `true` |

**`Unknown`** is **not** a registry row.

### 5.3 Building the initial registry

1. Start from **ISO 3166-1** English short names (~249 entries).
2. **Add** England, Scotland, Wales, Northern Ireland (if not present as separate rows).
3. **Omit** United Kingdom from choosable set (`choosable: false` or exclude entirely).
4. **Set explicit names** where KOO differs from ISO defaults:
   - **Ireland** (not “Republic of Ireland”)
   - **Taiwan** (not ISO long form)
5. **Add extras** unlikely in ISO sovereign list but allowed in “More countries” when needed (see §5.4).
6. **Attach `legacy_aliases`** from corpus audit (§12).
7. **Attach `site_shorthand`** where useful (`UAE` → United Arab Emirates).
8. Commit JSON; **do not** auto-refresh from ISO thereafter.

### 5.4 KOO extras (choosable in “More countries”)

| Entity | Notes |
|--------|-------|
| **Hong Kong** | Already in corpus; ISO `HK` |
| **Taiwan** | CR8 |
| **Macau** | Rare; add when needed |
| **Faroe Islands** | Nordic football; separate from Denmark |
| **Kosovo** | Add manually if ever needed (ISO situation messy) |

Skip obscure territories until a player or tournament requires them. Full ISO sovereign list covers most “More countries” picks.

### 5.5 Adding a new country later

Manual edit to `country_registry.json` + run **`sync_country_flag_svgs.py`** (copy from vendored flag-icons if `flag_code` is new) + L3 re-import + prove. No automation.

### 5.6 Flag SVG assets (lipis/flag-icons)

**Source (locked — CR29):** existing Amiga flags already come from **[lipis/flag-icons](https://github.com/lipis/flag-icons)** (MIT). Each SVG uses `id="flag-icons-{code}"` and `viewBox="0 0 640 480"` — the repo’s **`flags/4x3/`** export. Initial batch shipped **2026-06-14** (`e9421fd`); **`ae.svg`** added **2026-06-25**.

| Concern | Rule |
|---------|------|
| **Vendor path** | `data/vendor/flag-icons/` — pinned release; record version in `VERSION.txt` or registry metadata |
| **Site path** | `site/public_html/img/flags/amiga/{flag_code}.svg` |
| **Naming** | `flag_code` = flag-icons basename: ISO alpha-2 lower case; UK subdivisions `gb-eng`, `gb-sct`, `gb-wls`, `gb-nir` |
| **Sync** | `scripts/amiga/sync_country_flag_svgs.py` copies 4×3 files for every registry row that needs a flag (minimum: all `choosable: true`) |
| **Completeness** | **Ship gate:** every choosable `flag_code` has a site SVG after sync — verified in tests + `prove` |
| **New countries** | Add registry row → run sync → add SVG from same vendor if code exists in flag-icons |
| **Attribution** | MIT licence — credit flag-icons in [`design-direction.md`](design-direction.md) when bulk sync lands |

**Do not** mix other flag libraries or hand-drawn SVGs for registry countries — one visual family sitewide.

**United Kingdom (`gb`):** not choosable (CR6); omit from sync unless a non-choosable registry row is added later.

---

## 6. L3 import

### 6.1 Pipeline order (locked)

```text
L2 SQL read
  → player name merge (player_names.py)
  → apply_player_country_corrections()   # Italy, Norway, … — already official names
  → apply_catalog_corrections()          # WC venues, dates, names — country → official names
  → country_token_canonicalize()       # NEW — legacy_aliases → official_name
  → validate_countries_in_registry()   # NEW — fail if any country ∉ registry
  → persist amiga_players, tournaments, …
  → import_manifest.json
```

Existing overrides may keep legacy spellings in their **source** fields; canonicalize runs **after** them so one place owns alias retirement.

### 6.2 Known legacy aliases (Jul 2026 corpus audit)

| Legacy (current DB) | Official name | Where |
|---------------------|---------------|-------|
| `N. Ireland` | `Northern Ireland` | Player Stephen D |
| `UAE` | `United Arab Emirates` | Tournament Dubai I |

Run **`verify_country_registry`** after implementation to catch any new drift.

### 6.3 Manifest

Extend **`import_manifest.json`**:

| Field | Content |
|-------|---------|
| `transforms.country_token_normalizations` | Rows applied by `country_token_canonicalize()` |
| `registry.version` | Copy of `country_registry.json` `version` field at import time |

Example row:

```json
{
  "entity": "player",
  "name": "Stephen D",
  "field": "country",
  "access": "N. Ireland",
  "canonical": "Northern Ireland",
  "reason": "Retire site shorthand; registry official_name (CR11)."
}
```

### 6.4 World Cup hosts

`WORLD_CUP_VENUES` in `import_corrections.py` already uses official-style names (England, Greece, …). After registry ships, **verify** every WC host string is an exact `official_name` in JSON (test in `verify_import_manifest` or registry audit).

---

## 7. Website read path

### 7.1 Shared helpers (shipped Jul 2026)

Registry-backed helpers in **`k2_amiga_country_registry.php`**; flags in **`k2_amiga_country_flag.php`**:

| Helper | Purpose |
|--------|---------|
| `k2_amiga_country_registry()` | Load / cache JSON |
| `k2_amiga_country_resolve(string $token)` | Token → registry row or null (incl. legacy aliases at read) |
| `k2_amiga_country_display_name(string $token, bool $shorthand = false)` | CR17 / CR18 |
| `k2_amiga_country_flag_meta(string $token)` | via `k2_amiga_country_flag.php` — `flag_code` + display label |
| `k2_amiga_country_choosable_rows()` | All `choosable: true` |
| `k2_amiga_country_used_tokens(mysqli $con)` | CR22 DISTINCT union |
| `k2_amiga_country_validate_token(string $token)` | Choosable official name only (organizer POST) |

Activity geography charts: `window.k2AmigaCountryFlagCodes` booted from PHP in `amiga_activity_hub_shell_start.inc.php` (replaces hardcoded JS map).

### 7.2 Flags

- Registry **`flag_code`** → `/img/flags/amiga/{code}.svg` (from **flag-icons** 4×3 via sync script — §5.6)
- Retire static `$map` in `k2_amiga_country_flag.php` and duplicate map in `amiga-activity-charts.js`
- **CH9** behaviour for **unknown / drift tokens** (not in registry): no flag img (no text fallback)
- **Choosable registry rows:** SVG **must exist** on disk before slice sign-off (CR29)

### 7.3 Display mode (v1)

- **Default:** `official_name` (equals stored DB string after migration)
- **Shorthand:** sitewide constant or config flag — **off** until explicitly enabled (CR18)

### 7.4 URLs

Keep existing routes: `k2_amiga_route('amiga-country-roster', ['country' => $officialName])`. **CR27** redirects deferred.

---

## 8. Write surfaces

### 8.1 v1 validation

| Surface | Rule |
|---------|------|
| **Organizer — create tournament** | `fixtures.php` country field → registry select only |
| **Organizer — create player** | **Shipped** — [`amiga-player-create-implementation-plan.md`](amiga-player-create-implementation-plan.md) PC-1–PC-7 |
| **Free text** | Rejected server-side if not ∈ registry |

### 8.2 Organizer UI (CR21) — shipped

**[`fixtures.php`](../site/public_html/amiga/ops/fixtures.php)** create league:

1. **Default:** archive-used countries only (~21) from `k2_amiga_country_used_tokens()`.
2. **“More countries…”** checkbox — **`amiga-organizer-country-picker.js`** (deferred; must load after `k2-page-boot.js`) appends full choosable registry from `data-amiga-more-countries` JSON on the select.
3. Labels: `k2_amiga_country_display_name()` official names (CR19).
4. Server POST: `k2_amiga_country_validate_token()` — rejects tampered/free text.

### 8.3 Edit after create (CR28)

Future slice — allow organizers to change country on existing player/tournament with same registry validation. Not v1.

---

## 9. Verify and prove gate

### 9.1 `verify_country_registry` (new script)

Run after **simul** on work and (oracle) as part of **`python -m scripts.amiga prove`**:

1. Load registry JSON.
2. Query distinct non-empty `amiga_players.country` and `tournaments.country`.
3. **Fail** if any value is not an `official_name` in registry.
4. **Fail** if any choosable registry `flag_code` lacks `site/public_html/img/flags/amiga/{code}.svg`.
5. **Report** (informational): registry choosable entries never used in DB; legacy aliases still present in L2 (pre-canonicalize audit only).

### 9.2 Repair

Wrong witness country strings → fix registry and/or aliases → **`simul`** on work (forward) or full L3 re-import + **`prove`** (oracle only) — not `UPDATE` patches, not L5-only repair.

---

## 10. Implementation slices (suggested order)

| Slice | Deliverable |
|-------|-------------|
| **CR-1** | `data/amiga/country_registry.json` + build notes; initial ISO + KOO edits |
| **CR-2** | Vend **flag-icons** + `sync_country_flag_svgs.py`; full SVG set for choosable registry rows |
| **CR-3** | Python: load registry, `country_token_canonicalize`, manifest, import validate |
| **CR-4** | `verify_country_registry.py` wired into `prove` (includes flag SVG gate) |
| **CR-5** | L3 re-import on work DB; manifest + corpus audit clean |
| **CR-6** | PHP registry helpers; refactor `k2_amiga_country_flag.php` + activity charts JS |
| **CR-7** | Organizer create — registry select + used / “More countries” UI |
| **CR-8** | Staging export sync + spot-check Countries hub, filters, rivals, WC host |
| **CR-9** | Phase 2 backlog: 301 aliases (CR27), shorthand sitewide toggle (CR18), edit after create (CR28) |

**Implementation plan:** [`amiga-country-registry-implementation-plan.md`](amiga-country-registry-implementation-plan.md) — slices CR-0–CR-8.

---

## 11. Appendix — Jul 2026 corpus audit (local `ko2amiga_db`)

Distinct values **before** L3 normalization (reference for CR-11):

**Player nationalities (21):** Austria, Belgium, Denmark, England, France, Germany, Greece, Hong Kong, Ireland, Italy, **N. Ireland**, Netherlands, Norway, Poland, Portugal, Scotland, Spain, Sweden, Switzerland, Turkey, Wales

**Tournament hosts (12):** Austria, Denmark, England, Germany, Greece, Ireland, Italy, Netherlands, Norway, Spain, Sweden, **UAE**

All except **N. Ireland** and **UAE** already match intended official names.

---

## 12. Changelog

| Date | Note |
|------|------|
| 2026-07-07 | Policy drafted — decisions locked in product discussion (string canonical, JSON registry, L3 normalization, UK nations, Ireland/Taiwan naming, defer used-countries table + URL slugs + edit-after-create). |
| 2026-07-07 | **CR29** — flag SVGs = vendored **lipis/flag-icons** `flags/4x3/`; full choosable set required before ship (CR-2). |
| 2026-07-07 | **Shipped CR-1–CR-7** — 254 registry rows (253 choosable); L3 normalizes `N. Ireland`→Northern Ireland, `UAE`→United Arab Emirates; 253 site SVGs; PHP registry + activity chart boot map; organizer country select. Deploy copy: `public_html/data/amiga/country_registry.json`. Staging empty-table pitfall documented in [`amiga-staging-handoff.md`](amiga-staging-handoff.md). |