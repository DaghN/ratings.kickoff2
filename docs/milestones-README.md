# Milestones — start here

**Kick Off 2 ratings site · agent + human entry point**

One place to find **what each milestone is** and **how the site implements it**. Open **[`milestones-catalog.md`](milestones-catalog.md)** to look up any key before editing anything.

---

## What players see (two different texts)

Do not confuse these — users and Dagh often say **“explainer”** without specifying which:

| On the site | Column in catalog | Typical example |
|-------------|-------------------|-----------------|
| **Rule** — short line under the milestone name on profile cards, Recent feed, and the top of `milestone.php` | **Rule (short)** | “Scored 5 goals or more in one game” |
| **Event** — extra line in the **achievers table** only (`milestone.php`, “Who unlocked it”) | **Event** | Perfect day: “All wins that UTC day (5+ rated games).” |
| **Link** — button-like link in achievers (and compact surfaces) | **Link** | Game · League · Games |

**Default for vague requests:** “change the explainer / description / rule text” → **Rule (short)**, not Event.

**Event** for most milestones is an automatic **match scoreline** (e.g. `3–1 Opponent`), not editable prose — only change Event when the catalog row shows custom text (e.g. `perfect_day`, `nightmare_day`, `entered_arena`).

---

## Agent cheat sheet — what to edit

| Goal | Edit this | Then run |
|------|-----------|----------|
| **Rule (short)** — cards, Recent, detail page header | See **Rule copy** below | Reload DB copy on each environment |
| **Event** prose — achievers table only | `OVERRIDES` → `event_context_label` in `scripts/oneoff/build_milestone_garden_links.py` | `python scripts/oneoff/build_milestone_garden_links.py` → deploy `data/milestone_garden_links.json` |
| **Link** label/destination (Game / League / Games) | Same file → `event_link` in `OVERRIDES` (or defaults) | Same build script + deploy JSON |
| **Title** (`display_name`) | Seed or copy-patches file | Same as rule |
| **Tier, rebuild, post-game** | Not this README — [`milestones-facilitation.md`](milestones-facilitation.md) + [`website-data-contract.md`](website-data-contract.md) | Part B if stored truth changes |

After **any seed change**, refresh the generated catalog md:

```text
python scripts/oneoff/build_milestone_garden_links.py
```

---

## Rule copy — two valid paths (pick one)

Both end up in `site/public_html/ops/data/milestones_definitions_seed.json` and `milestone_definitions` in the DB.

### A) Single key — edit seed directly

1. Edit `rule_short` / `display_name` in [`site/public_html/ops/data/milestones_definitions_seed.json`](../site/public_html/ops/data/milestones_definitions_seed.json).
2. Run `python scripts/oneoff/build_milestone_garden_links.py` (updates `milestones-catalog.md`).
3. Load into DB:
   - **Work / dev (preferred):** `php site/public_html/ops/run_prepare.php seed-catalog --target local-work` (or `local-dev` for `ko2unity_db`).
   - **Local alt:** `python scripts/oneoff/load_milestone_definitions.py` (reads same ops path).
   - **Staging legacy:** `load_milestone_definitions.php` (deprecated; prefer `seed-catalog` after WinSCP `ops/`).

### B) Copy pass — patch list (good for several keys or staging-only sync)

Used when a batch of display/rule tweaks already lives in [`data/milestone_catalog_copy_patches.json`](../data/milestone_catalog_copy_patches.json).

```text
python scripts/oneoff/apply_milestone_catalog_copy_patch.py
```

- Updates **seed + local DB** (default).
- `--seed-only` or `--db-only` if you need just one side.
- **Staging:** upload `staging-data/milestone_catalog_copy_patches.json`, then `php staging-scripts/patch_milestone_catalog_copy.php` (no full TRUNCATE).

**Adding a new rule change:** append a `{ "milestone_key": "…", "rule_short": "…" }` row to the patches file, then run the apply script (and the build script so `milestones-catalog.md` stays in sync).

**Do not** hand-edit `milestones-catalog.md` — it is generated.

---

## Unlock Event + Link register

| Artifact | Role |
|----------|------|
| [`milestones-catalog.md`](milestones-catalog.md) | **Master table** — tier, title, rule, Link, Event |
| [`data/milestone_garden_links.json`](../data/milestone_garden_links.json) | What PHP reads for links |
| [`milestones-garden-links.md`](milestones-garden-links.md) | Link + Event index only |
| [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md) | Behaviour spec & PHP API |

```text
python scripts/oneoff/build_milestone_garden_links.py
```

Deploy `milestone_garden_links.json` to `staging-data/` on staging. **Rule copy** does not require this unless you also changed Link/Event.

---

## Other docs (by role)

| Doc | Role |
|-----|------|
| [`milestones-project.md`](milestones-project.md) | Phase status & technical baseline |
| [`milestones-product-spec.md`](milestones-product-spec.md) | Tier bands, colours, presentation plan |
| [`milestones-facilitation.md`](milestones-facilitation.md) | Rebuild waves & `source_kind` families |
| [`milestones-hub-ia.md`](milestones-hub-ia.md) | Hub tab IA (WIP) |

**Archived (history only):** [`archive/milestones-system-discussion.md`](archive/milestones-system-discussion.md), [`archive/milestones-ideas-catalog.md`](archive/milestones-ideas-catalog.md), [`archive/milestones-tier-curated.md`](archive/milestones-tier-curated.md), staging cutover packet — stubs at [`coordination/milestones-staging-cutover-packet.md`](coordination/milestones-staging-cutover-packet.md).
