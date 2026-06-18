# Amiga format backbone — orchestration prompt

**Status (2026-06-08):** **Paused.** Slices **A–E** and **double elimination** are complete. All 6 format templates implemented. Homburg is the pilot structure backfill. See pause checkpoint: [`docs/archive/orchestration/agent-handoffs/2026-06-08-036-format-backbone-pause.md`](agent-handoffs/2026-06-08-036-format-backbone-pause.md).

**For Dagh:** Start a new agent chat, paste the **Copy-paste starter** below (once), then drive progress by saying **`go on`** (or **`go on — slice N`**). Each slice ends with a written checkpoint so the next agent does not re-audit from zero.

**Your ask (plain):** A professional backbone so we can **define any tournament format we need** (new events + corrected historical imports) — Swiss, double elimination, groups+KO, World Cup class, etc. — by **adding templates and rules**, not one-off koatd hacks.

**Is this realistic?** Yes. Schema and live-ops foundations already exist. What remains is the **middle layer**: structure specs → stages/fixtures → games → standings. Finish that in small slices; do not boil the ocean.

---

## Copy-paste starter (new chat — paste once)

```
You are continuing the Amiga offline tournament **format backbone** in ko2amiga_db.

## North star (do not lose this)

We are building a **format platform**, not a koatd repair shop.

- **New tournaments:** create from reusable format templates + stages + fixtures.
- **Historical tournaments:** same model — instantiate structure from evidence (forum, results), store in DB, re-import reproducibly.
- **Future formats** (Swiss, double elimination, …): new **templates + rules**, not new ad-hoc import hacks per event.
- **Legacy phase strings** remain a **fallback reader** for old rows until backfilled — not where structure should live long-term.

## Groundwork already shipped (do NOT redo)

Read once, then build on it:

- docs/amiga-tournament-format-vision.md — architecture & phasing
- docs/amiga-data-contract.md — ground vs derived; format tables
- docs/amiga-import-layer.md — import transforms policy

Code reality (Jun 2026):

- DDL: tournament_format_templates, tournaments.format_*, tournament_stages, tournament_fixtures, amiga_games.fixture_id
- scripts/amiga/tournament_format.py — templates + has_league/has_cup inference
- scripts/amiga/tournament_fixtures.py — stage/fixture CRUD (live ops)
- scripts/amiga/tournament_builder.py — kitchen_marathon, minimal group_knockout (generated events only)
- scripts/amiga/tournament_standings.py — fixture-backed OR phase fallback; KO leg aggregation
- site/public_html/includes/amiga_tournament_lib.php — reads structure for UI
- Import truncates stages/fixtures today; all historical rows use legacy_inferred + phase parser

Slice 1–2 from the old handoff (docs/amiga-tournament-format-handoff-prompt.md) are **done**. You are starting **Slice 3+** of the backbone, not catalog flags.

## Anti-goals (stay out of the swamp)

- Do NOT edit koatd.mdb.
- Do NOT backfill all ~603 tournaments in one effort.
- Do NOT store long-term structure only as patched phase strings in import_corrections.py (OK as temporary bridge for one slice only if explicitly marked deprecated).
- Do NOT re-litigate World Cup V KOA / name merges / WC city overrides unless a test fails.
- Do NOT build Swiss or double-elim **engines** until Slice 4–5 plumbing works for one known format (group_knockout backfill).
- Do NOT expand browser UI unless the slice explicitly says so.

## Operating mode

1. Read docs/archive/orchestration/amiga-format-backbone-orchestration-prompt.md (this file) for the **current slice**.
2. Execute **only the current slice** end-to-end (code + verify + checkpoint note).
3. End with a **Slice checkpoint** block (template below) and tell Dagh: “Say **go on** for slice N+1.”
4. If blocked, state the blocker in one paragraph and the smallest decision Dagh must make.

## Pilot tournament (first real backfill)

**Homburg** — catalog name `Homburg`, MySQL often id≈137, Access source_id 114, 2004-06-12, 33 players, 86 games, all Phase NULL in koatd (wrong).

Evidence: https://ko-gathering.com/forum/viewtopic.php?t=7711  
Structure: 8 groups (A–H, H has 5 players) + Last 16 + QF + SF + 3rd place + Final; two-legged KOs; at least one replay tie.

Success for the backbone: Homburg renders with **group tabs + knockout bracket** from **stored structure**, not a single overall league table.

## Slice roadmap (execute in order)

### Slice A — Contract & module skeleton (no Homburg data yet)

**Goal:** One version-controlled place for **structure definitions** and import manifest audit — shaped for templates/stages/fixtures, not ad-hoc phases.

**Do:**
- Add `scripts/amiga/tournament_structure/` (or `import_tournament_structures.py` + package) with:
  - datatypes: `StructureSpec`, `StageSpec`, `FixtureSpec`, `GroupRosterSpec`
  - `apply_structure_spec()` hook called from import **after** scores load, **before** games insert
  - manifest section `transforms.structure_specs`
- Add `python -m scripts.amiga audit-suspicious-marathons` → JSON report (NULL phases + uneven game counts + not full round-robin)
- Document in `docs/amiga-import-layer.md` (short §)

**Do not:** Backfill Homburg yet.

**Verify:** unit tests for spec parsing; audit runs; import still passes with zero specs.

**Checkpoint:** module exists, import hook no-op, audit lists Homburg.

---

### Slice B — Homburg through the backbone (minimal group_knockout)

**Goal:** First end-to-end proof: forum → **stages + fixtures + fixture_id on games** (or staged phase emit from fixtures — prefer fixture_id).

**Do:**
- Encode Homburg `StructureSpec` from forum t=7711 (groups + KO rounds; two legs = separate fixtures with `leg_no`)
- On import for `Homburg` only: create stages/fixtures, link `amiga_games.fixture_id` by matching players + tournament (deterministic)
- Set `format_template_id` → `group_knockout`, `has_league=1`, `has_cup=1`, `format_overrides` with evidence URL
- Import must **re-apply** structure each full import (today truncates stages — apply after truncate + game insert, or preserve policy documented)

**Verify:**
- `tournament.php` shows groups + bracket for Homburg
- `python -m scripts.amiga replay` clean
- Manifest records structure spec
- Spot-check game counts = 86, no orphan games

**Do not:** Swiss, double-elim, other tournaments.

**Checkpoint:** Homburg correct on local; export note for staging if schema unchanged.

---

### Slice C — Generalize backfill API

**Goal:** Adding tournament #2 is **data + spec**, not new import code.

**Do:**
- Register specs by catalog name in one registry file
- CLI: `python -m scripts.amiga structure verify --tournament "Homburg"`
- CLI: `python -m scripts.amiga structure list` / `audit-suspicious-marathons` integration
- Extend `group_knockout` template spec JSON minimally (round keys: last_16, quarter, semi, final, placement_3rd)

**Verify:** Second tournament can be an empty stub spec that fails verify gracefully.

**Checkpoint:** README slice in scripts/amiga/README.md — “how to add a structure spec”.

---

### Slice D — Template extensibility (design checkpoint, small code)

**Goal:** Prove the model accepts **future** Swiss / double-elim without implementing them.

**Do:**
- Document template extension contract in `docs/amiga-tournament-format-vision.md` (§ new): what a template must provide (stage factory, standings resolver hook)
- Add stub templates `swiss`, `double_elimination` to seed rows with `spec_json` shape only (`status: "planned"`)
- No standings logic for stubs

**Verify:** import seeds templates; verify script counts templates.

**Checkpoint:** written “how to add Swiss later” checklist (≤1 page).

---

### Slice E — Swiss (done)

**Checkpoint:** [`2026-06-08-034-format-backbone-slice-e.md`](agent-handoffs/2026-06-08-034-format-backbone-slice-e.md) — `swiss_pairing.py`, builder smoke, template `swiss` implemented.

### Slice F — Double elimination (done)

**Checkpoint:** [`2026-06-08-035-double-elimination-implemented.md`](agent-handoffs/2026-06-08-035-double-elimination-implemented.md) — `double_elim_bracket.py`, 4/8-player brackets, template `double_elimination` implemented.

### Next work (when resuming — pick one)

- **Staging:** export + sync; verify Homburg UI (`tournament.php?id≈137`)
- **Backfill #2:** Athens LXI or another audit candidate — spec + registry only
- **Browser:** Swiss / double-elim organizer flows (not started)

---

## Slice checkpoint template (required at end of every slice)

```markdown
## Slice checkpoint — [letter] [title]

**Done:**
- …

**Verified:**
- commands run + result

**Not done (intentionally):**
- …

**Files touched:**
- …

**Next slice:** [letter] — one sentence

**Dagh:** say `go on` to continue.
```

## Verification commands (repo root)

python -m scripts.amiga import --recreate-schema   # only when DDL changes
python -m scripts.amiga run                        # import + replay
python -m scripts.amiga verify-import-manifest
python -m scripts.amiga verify-chronology
python -m scripts.amiga audit-suspicious-marathons  # after Slice A

## Constraints

- Real environment; run commands yourself.
- Minimal scope per slice; solid boundaries over cleverness.
- Do not commit unless Dagh asks.
- After DB ground-truth changes Dagh may want staging: scripts/export_ko2amiga_db.ps1 (remind in checkpoint, do not run unless asked).

---

**Start now:** Read the roadmap, confirm which slice is not yet checkpointed (begin **Slice A** if none), execute it completely, write the checkpoint, stop.
```

---

## For Dagh — how to ride this

| You say | Agent should |
|--------|----------------|
| *(paste starter once)* | Read docs, begin Slice A |
| **`go on`** | Read last **Slice checkpoint** in chat or `docs/archive/orchestration/agent-handoffs/`, run next slice |
| **`go on — slice B`** | Jump to slice B (only if prior checkpoint exists) |
| **`status`** | Summarize checkpoints, no new code |
| **`pause — decide X`** | Stop and ask you one product question |

Save each slice checkpoint to `docs/archive/orchestration/agent-handoffs/YYYY-MM-DD-NNN-format-backbone-slice-X.md` when a slice finishes (agent should offer to write this file).

---

## Relationship to older handoff

`docs/amiga-tournament-format-handoff-prompt.md` targeted **Slice 1** (has_league/has_cup + templates). That work landed. **This file supersedes it for continuation** toward stages/fixtures backfill and future format families.

---

## Realistic expectations

| Expectation | Realistic? |
|-------------|------------|
| “Any format we dream of” as a **platform direction** | Yes — that is the design |
| Swiss / double-elim **working next week** | No — after plumbing + one backfill proof |
| Say **`go on`** and agent makes steady progress | Yes — if you keep checkpoints between chats |
| Never any manual curation for weird old events | No — each historic event needs evidence once, then spec is reusable on re-import |

---

*Created Jun 2026 — format backbone orchestration for incremental agent runs.*
