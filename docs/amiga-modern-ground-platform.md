# Amiga modern ground platform — policy (Jul 2026)

**Status:** **Shipped (Jul 2026)** — cutover bootstrap complete (§10.2). **Start here** for all forward Amiga ground / simul / staging export work.

**Audience:** Dagh, Cursor agents, future maintainers.

**Online analogue:** [`ladder-ops-platform.md`](ladder-ops-platform.md) — live `kooldb*` ground accumulates after go-live; **simul** clears derived and re-runs writers; ground is not rewound to a pristine snapshot on every ops cycle.

**Supersedes (daily habits, not history):** Treat **L0→L3 Access witness import** as the default Amiga sign-off path — archived after day 0 seal (§6). Lane A wording in [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §1 defers here for **local ground authority**.

**Related:** [`amiga-data-contract.md`](amiga-data-contract.md) (table register) · [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) (L5 writers — **update repair verbs** during cutover) · [**`amiga-modern-simul-implementation-plan.md`**](amiga-modern-simul-implementation-plan.md) (**S-1** slice plan) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) (staging live ops — Lane B/C unchanged) · [**`amiga-staging-authority-policy.md`**](amiga-staging-authority-policy.md) (**staged prod, local repair shop, pull → push**) · [`amiga-staging-handoff.md`](amiga-staging-handoff.md) (export/import runbook) · [`amiga-tournament-structure-policy.md`](amiga-tournament-structure-policy.md) (L4 model) · [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) · [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) · **Archive (Access pipeline):** [`amiga-ground-stack.md`](amiga-ground-stack.md) · [`amiga-import-layer.md`](amiga-import-layer.md) · [`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md)

---

## 1. Executive summary

The Amiga realm adopts a **living ground database** model, matching online ladder ops after cutover:

| Era | Model |
|-----|--------|
| **Before (retired)** | Re-derive historical ground from Access on every full `prove` (L0→L2→L3 nuclear import), then L4, L5, video id repair. Staging full import **replaced** the whole DB — destroying community events not on the laptop. |
| **After (this policy)** | **Day 0 L3** = one-time sealed witness ground (L3 tables only — no L4, no L5). **Living ground** = **`ko2amiga_work`** — seeded from day 0, then accumulates structure, video, post–day-0 events. **Simul** = DDL migrate + clear **derived only** + replay + video align/verify. **Never** rewind living ground to pure day 0 in normal ops. |

**Access (`koatd.mdb`) is retired from the product pipeline.** Everything Access had to say is accounted for in day 0 L3. New historical evidence is added as **forward ground** (append), not re-import.

---

## 0. New agent — quick reference (Jul 2026)

**Do not** treat `python -m scripts.amiga prove`, `import-witness`, or `setup_ko2amiga_db.ps1` as the daily Amiga path. Those are **oracle / archaeology** on frozen **`ko2amiga_db`**.

| Question | Answer |
|----------|--------|
| **Local working DB** | **`ko2amiga_work`** (living ground) |
| **Frozen oracle** | **`ko2amiga_db`** (P-1 baseline; legacy prove only) |
| **Staging DB name** | **`ko2amiga_db`** (import target on server — same name, different machine) |
| **Daily sign-off** | `python -m scripts.amiga simul` on work (video on by default) |
| **DDL / schema bundles** | Edit `sql/ground|structure|derived` → **simul** on work |
| **Push to staging** | `export_ko2amiga_work.ps1` → WinSCP → browser import |
| **Forward code** | `scripts/amiga/modern/` only (**MG11** — audit: `python scripts/audit_amiga_modern_compartment.py`) |
| **Access L0–L5 docs** | **Archived** — [`archive/amiga-access-pipeline-index.md`](archive/amiga-access-pipeline-index.md) |
| **Community mistakes on staging** | [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) — anchored repair, not full prove |

---

## 2. Motivation

### 2.1 Two problems were conflated

| Problem | Nature |
|---------|--------|
| **A — Historical Access corpus** | One-time translation to MySQL witness ground. Corrections, merges, supplements — **finished** when day 0 is sealed. |
| **B — Live community realm** | Staging tournaments, structure expansion, video links, schema/writer work — **continuous**, like games after online go-live. |

Running **A** on every feature slice (full `prove` → full staging import) **destroyed B** — running drills, finalized test events, and any staging-only ground vanished.

### 2.2 Pain observed (Jul 2026)

- Full browser import = `DROP` + replace; not merge.
- `import_witness_nuclear` truncates all L3/L4 ground — incompatible with living staging data.
- Video `sync_db_ids` in prove existed to fix **id churn** from witness reimport — not because video is derived.
- Agents and docs assumed `import-witness` for DDL-only work (e.g. `is_world_cup`).
- Mental overhead of L0–L5 strict chain for daily work.

### 2.3 Design intent

**Simpler mental model:**

```text
day 0 L3 (archived bootstrap)  →  living ground grows  →  simul refreshes derived (+ video align)
```

Same shape as online: **ground accumulates; derived is replayable.**

---

## 3. End goal

1. **One living ground** — historical + community events, structure rows, video bindings, forward overrides — **additive**, not stripped on simul.
2. **Day 0 L3** — versioned archive + dev seed; **not** a hammer on every prove.
3. **Access retired** — `koatd.mdb` is museum/reference only; no L0–L2–L3 in forward path.
4. **L4 pipeline retires eventually** — when structure coverage reaches target; **fixture rows remain ground.**
5. **Video** — editorial ground bound to **stable ids**; align+verify is a **simul step**, not a witness-reimport patch.
6. **Staging** — merge/forward sync (future); stop treating laptop full import as authority over community ground.
7. **Legacy quirks** — per-tournament (phase fallback, format overrides), not per-pipeline.

---

## 4. Vocabulary

| Term | Meaning |
|------|---------|
| **Day 0 L3** | Versioned **witness ground** bundle: `tournaments`, `amiga_players`, `amiga_games`, `amiga_tournament_finish_override`, `tournament_format_templates` — **L3 only**; **no L4, no L5 derived**. |
| **Day 0 archive** | Immutable L3 snapshot at seal time — **git-tracked** under `data/amiga/day0/` (manifest + SQL parts). |
| **Work DB (`ko2amiga_work`)** | **Living local ground** — always created by **loading day 0 L3** into empty schema, then schema + simul + forward accumulation. **Not** a full clone of legacy `ko2amiga_db`. |
| **Legacy oracle (`ko2amiga_db`)** | Frozen **reference** during cutover — last full Access-era prove output. Used for **parity check** after simul on work; **not** the daily simul target. Legacy nuclear `prove` may only touch this name (disposable). |
| **Living ground** | **`ko2amiga_work`** after cutover — day 0 witness + L4 + video + post–day-0 events. Staging server keeps **`ko2amiga_db`** as community realm name (export from work → import to staging). |
| **Forward witness** | New claims on **stable ids** after day 0: append tournament, Tier E override, supplemental games, country fix — **not** `import_access.py` from L2. |
| **Simul (modern prove)** | `apply_schema` → clear **L5 derived only** → `replay` → video align → verify. **Does not** truncate L3 games, L4 fixtures, or video bindings. |
| **L4 (structure ground)** | `tournament_stages`, `tournament_fixtures`, `fixture_id` links — **expanding ground** until coverage target; then L4 **pipeline** retires, rows stay. |
| **Video editorial ground** | `review.csv`, `tournament_videos.json` (until Lane C DB), stable keys: `youtube_id` + match facts; DB id caches aligned to living ground. |
| **L5 derived** | Snapshots, ratings, realm/community aggregates — **recomputed** by simul; not used to justify wiping ground. |

**Layer reminder (G1):** Day 0 seal is **L3 witness tables only** — no L4 structure rows, **no L5 derived**. L4 and video are applied or accumulated **on work** after day 0 load. L5 is always projection from simul.

### 4.1 Database names

| Name | Role | Mutable? |
|------|------|----------|
| **`data/amiga/day0/`** | Sealed L3 archive (manifest + SQL) — **versioned in git** | Append-only new day-0 versions (rare) |
| **`ko2amiga_work`** | Living local ground — **seed from day 0**, then simul + forward ops | Yes — accumulates |
| **`ko2amiga_db` (local)** | Cutover **oracle** — frozen last legacy prove; parity reference only | Frozen during cutover; legacy `prove` graveyard |
| **`ko2amiga_db` (staging)** | Community realm on server | Yes — live ops authority |

**Safety:** Modern simul hardcodes **`ko2amiga_work`** only. Legacy nuclear `prove` hardcodes **`ko2amiga_db`** only. Accidental `prove` must not touch work.

**Work DB bootstrap rule:** `ko2amiga_work` is **always** `CREATE` + `apply_schema` + **load day 0 L3** — never `mysqldump` clone of full `ko2amiga_db`.

---

## 5. Locked decisions

| # | Decision | Rule |
|---|----------|------|
| **MG1** | **Living ground is prod-shaped** | After cutover, **`ko2amiga_work`** ground **accumulates** like online `ratedresults` after go-live. Simul does **not** delete games, fixtures, video links, or post–day-0 tournaments. |
| **MG2** | **Day 0 = bootstrap, not reload** | Day 0 L3 bundle seeds **`ko2amiga_work`** (and fresh dev clones). **Normal simul does not reload day 0 over living work.** |
| **MG10** | **Work seeds from day 0 only** | **`ko2amiga_work` is never a full clone of `ko2amiga_db`.** Cutover and fresh dev: load L3 day 0 → schema → simul. Parity vs legacy oracle is a **verify step**, not the seed method. |
| **MG3** | **Access retired** | No `koatd.mdb`, L0, L1, L2, or `import-witness` in forward agent path. New KOA evidence → **forward append** on modern ids (agent or tool), not ODBC reimport. |
| **MG4** | **L2→L3 step finished** | Witness import from Access runs **once** to produce day 0 (or last prove before seal). Then **archived**. |
| **MG5** | **L4 is expanding ground** | Materialized structure **persists** and grows. Full simul after **ground reload** (dev seed only) must reapply L4 from disposition; **normal simul does not strip L4.** |
| **MG6** | **Video in simul** | Video align + `verify-tournament-videos` are **simul steps** against stable ground — not witness-reimport repair. |
| **MG7** | **DDL holy path unchanged** | Schema bundles in `scripts/amiga/sql/{ground,structure,derived}/` — applied via **simul** on living DB ([`amiga-ground-layers-policy.md`](amiga-ground-layers-policy.md) G12). |
| **MG8** | **Live ops unchanged** | [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) Lane B/C — `site/public_html/amiga/ops/`, staging authority for community events. This doc fixes **local ground + simul + staging handoff direction.** |
| **MG9** | **Legacy quirks stay local** | Per-tournament phase fallback, `format_overrides`, games-before-fixtures provenance — **not** a second pipeline. |
| **MG11** | **Copy, do not mutate legacy prove** | Forward work lives in **`scripts/amiga/modern/`**. **Do not** edit or import from legacy prove orchestration (`prove.py`, `import_access.py`, nuclear import path) except read-only archaeology. Need legacy behaviour → **copy + rename** into `modern/` and evolve there. Keeps frozen `ko2amiga_db` oracle path reproducible. |

### 5.1 Legacy script compartment

| Zone | Path | Rule |
|------|------|------|
| **Modern (mutable)** | `scripts/amiga/modern/` | All cutover + forward simul/seed/load code |
| **Legacy prove (frozen)** | `scripts/amiga/prove.py`, `import_access.py`, `import_pristine.py`, `import_prune.py`, … | **No edits** for transition work; last green path preserved for oracle + archaeology |
| **Shared read-only** | `scripts/amiga/sql/`, `finalize_tournament.py`, verify modules | **Read/copy** OK; changes only when deliberately shared (DDL bundles) or after fork into `modern/` |

**Allowed without fork:** `schema_bundles.apply_schema` on **`ko2amiga_work`** (DDL only — same bundles as legacy, different DB target).

**Not allowed:** `from scripts.amiga.replay import …` or `from scripts.amiga.prove import …` in modern code — fork first. `seed_work` uses `modern/clear_derived.py` (S-1.0).

## 6. What we retire

| Retired from forward path | Archive location |
|---------------------------|------------------|
| `koatd.mdb` as pipeline input | `data/amiga/source/` (museum) |
| `import-pristine`, `import-prune`, `import-witness`, `import_witness_nuclear` as daily prove | `scripts/amiga/` (legacy tree; do not extend) |
| `prepare_witness_from_l2` / L2→L3 on every sign-off | [`docs/archive/amiga-access-pipeline/`](archive/amiga-access-pipeline/) (banner docs — create during cutover) |
| Prove tail `sync_db_ids` **because ids churned** | Replaced by **video align** on stable ids (§8) |
| Verify oracles tied to Access (`verify-l2-l3`, `verify-import-manifest` as simul gate) | Rare archaeology / optional CI job |
| **Default:** full staging import **replaces** community ground | Superseded by merge direction (§9) — implementation follows |

**Not retired:** `scripts/amiga/sql/` DDL bundles · `replay` / `finalize_tournament` · PHP `amiga/ops/` · disposition register as **archive input** to L4 work · `standings-parity` as optional archaeology.

---

## 7. Day 0 L3 — seal contract

### 7.1 Contents (witness ground only)

Tables (minimum):

- `tournaments`, `amiga_players`, `amiga_games`
- `amiga_tournament_finish_override`
- `tournament_format_templates` (seed rows)

**Excluded from day 0 bundle** (applied or accumulated on **work** after load):

- **L5 derived** — all snapshot, rating, realm, community, matchup, standings tables (simul rebuilds)
- **L4 structure** — `tournament_stages`, `tournament_fixtures`, `tournament_entrants`, … (re-materialize from disposition on work seed, or accumulate in living work)
- Video manifest files
- Post–day-0 events (forward ground only)

### 7.2 Manifest (required fields)

- `version` (e.g. `day0-2026-07-08`)
- `generated` timestamp
- `tournament_count`, `game_count`, `player_count`
- `source` note: last Access witness / prove commit
- `sql_parts` or single dump path

**Storage:** `data/amiga/day0/` — manifest + SQL parts — **git-tracked** (versioned archive; not gitignored like staging `_import/` dumps).

### 7.3 When to bump day 0 version

**Rare.** Only when deliberately re-deriving the **entire** historical L3 corpus from archived Access pipeline (archaeology). **Not** for DDL columns, new tournaments, L4 progress, or video.

Forward changes → **living ground**, not day 0 v2.

---

## 8. Living ground — what accumulates

| Addition | Layer | How |
|----------|-------|-----|
| New tournament (staging, append, KOA evidence) | L3 (+ L4 when structured) | `amiga/ops/`, forward append tool, ground pack merge |
| Structure materialize | L4 ground | Disposition / live builders — **rows persist** |
| Video link / cache update | Editorial ground | Harvest/build offline; **simul align** |
| Tier E finish override | L3 witness | Direct row or forward manifest |
| `live_ops` players | L3 | Organizer create |

**Online analogy:** new rated game insert → ground. Simul → derived only.

---

## 9. Simul (modern prove)

### 9.1 Default loop

```text
preflight (L3 exists; day 0 = reference only)
→ apply_schema (DDL on ko2amiga_work)
→ clear derived (L5 tables; reset rating_finalized flags)
→ replay (finalize all tournaments with games, chrono order)
→ video align + verify-tournament-videos
→ verify suite (modern subset)
→ postcheck (L3 ground counts unchanged vs preflight)
```

### 9.2 What simul does **not** do

- Truncate `tournaments` / `amiga_games` / `amiga_players`
- Reload day 0 bundle over production-like DB
- Invoke Access or L2→L3
- Strip L4 fixtures or video bindings

### 9.3 When L4 runs

| Situation | L4 |
|-----------|-----|
| **Normal simul** (ground unchanged) | **Skip** — structure rows already in living ground |
| **Dev seed from day 0** (empty DB + load L3 only) | **Run** `apply-structure` from disposition — L3-only seed has no fixtures |
| **Disposition register changed** | **Run** L4 for affected tournaments, then simul |
| **New live_ops event** | Structure via **`amiga/ops/`** — not disposition bulk |

### 9.4 Sign-off

**Simul green** replaces **full L0–L5 prove green** for forward development. Implementation: `scripts/amiga/modern/simul.py` (or `prove --simul-only`) — cutover slice S-1.

### 9.5 Staging handoff (direction)

**Authority:** [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md) — **staged `ko2amiga_db` = prod**; **local `ko2amiga_work` = repair shop**; **pull → repair → push** (not merge-import).

Until **PULL-1** ships: treat **full browser import** as **destructive** to unstaged ground ([`amiga-staging-handoff.md`](amiga-staging-handoff.md)). **Pull before push** when staging has community events. PoC pull = manual mysqldump + import into work (§8 of staging authority policy).

---

## 10. Cutover program (Jul 2026)

Execute in order. Each slice ends with a recorded check.

### 10.1 Bootstrap sequence (locked)

```text
1. Seal day 0 L3     current ko2amiga_db → export L3 witness tables only → data/amiga/day0/ → commit to git
2. Seed work         empty ko2amiga_work → apply_schema → load day 0 L3 bundle (explicit copy of archive, not full DB clone)
3. Schema + simul    apply_structure (L4 from disposition) + simul on ko2amiga_work → green
4. Parity oracle     compare work post-simul vs frozen ko2amiga_db (derived + key ground invariants) — ko2amiga_db untouched
5. Promote work      point local config + export at ko2amiga_work; ko2amiga_db = legacy graveyard only
```

**Why this order:** Proves the **bootstrap path** (day 0 → work → simul) before trusting it as living ground. `ko2amiga_db` stays a read-only oracle until parity passes — not the seed source for work.

### 10.2 Slices

| ID | Work | Exit |
|----|------|------|
| **D0-1** | Seal **day 0 L3** from current `ko2amiga_db` — **witness tables only** (§7.1); manifest + SQL under `data/amiga/day0/` | **Done** `day0-2026-07-08` — 605 / 469 / 27,418; `python -m scripts.amiga seal-day0` |
| **D0-2** | **Freeze** local `ko2amiga_db` — no further writes; label as cutover oracle in manifest note | Oracle frozen flag in manifest; manual discipline until P-1 |
| **W-1** | Create **`ko2amiga_work`**: `apply_schema` + **load day 0 L3 only** (not mysqldump of `ko2amiga_db`) | **Done** — `python -m scripts.amiga seed-work`; 605 / 469 / 27,418; derived cleared |
| **S-1** | **`apply-structure`** (disposition) + **simul** on `ko2amiga_work` — no ground truncate | **Done** — `python -m scripts.amiga simul`; 27,418 ratings; 16,046 fixtures; 22 verify steps (video deferred S-1.8) |
| **P-1** | **Parity check** — work post-simul vs frozen `ko2amiga_db` (derived tables, snapshots, key scalars) | **Done** — `python -m scripts.amiga parity`; 29 tables; report `data/amiga/modern/parity-last.json` |
| **L4-1** | Confirm L4 on work matches disposition expectations | **Done** — `python -m scripts.amiga verify-structure-work`; 16,046 fixtures; Homburg + pure_rr smoke OK |
| **V-1** | Video on work — stable `game_id` binding, oracle/work compartments, simul `--with-video` | **Done** — `seal-video-oracle` · `seed-video-work` · `align-video-work` · `verify-tournament-videos-work`; work manifest `data/amiga/work/tournament_videos.json` (299 videos) |
| **PROMOTE-1** | `ko2amiga_config.local.php` → `ko2amiga_work`; export reads work; legacy `prove` locked to `ko2amiga_db` only | **Done** — `export_ko2amiga_work.ps1` · `promote_ko2amiga_work_local.ps1` · `promote-video-deploy`; oracle export = `export_ko2amiga_db.ps1` shim |
| **DOC-1** | Archive Access pipeline docs; agent routing | **Done** — archive index + doc pass 2–3 (55+ historical/sign-off banners on shipped plans/policies) |
| **CODE-1** | `scripts/amiga/modern/` compartment; legacy import frozen (**MG11** — copy, never mutate) | **Done** — `modern/README.md`; FROZEN headers; `audit_amiga_modern_compartment.py` |

**Out of scope for day-one cutover (follow-on):** **PULL-1** staging → work · ground pack pull · `append-event` CLI · retiring L4 pipeline at 100% structure. Policy: [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md).

---

## 11. Video

| Topic | Rule |
|-------|------|
| **Modern policy** | [`amiga-modern-video-policy.md`](amiga-modern-video-policy.md) — canonical **`amiga_games.id`** on work; oracle/work file compartments; **V-1** |
| **Legacy mechanics** | [`amiga-tournament-videos-game-links-policy.md`](amiga-tournament-videos-game-links-policy.md) — fact remap on nuclear reimport (`prove` only) |
| **Product** | [`amiga-tournament-videos-policy.md`](amiga-tournament-videos-policy.md) |
| **Simul** | Align + verify on **work** by default; `--skip-video` to opt out |
| **Harvest/build** | Offline editorial; not every simul |
| **Lane C (future)** | Staging DB writes for community clips — [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §8 |

---

## 12. Forward witness (post day 0)

New evidence **without Access:**

- Catalog-tail tournament: insert L3 rows + `finalize-tournament` (or staging Make official).
- Mid-history insert: anchored repair (Case C) — [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) §7 — not day 0 rewind.
- Corrections: `amiga_tournament_finish_override`, direct ground fix with audit note.

**Agents:** do not run `import-witness` or full nuclear prove for these.

---

## 13. Agent policy

1. **Amiga forward ground / simul** → read **this doc** first.
2. **Live staging ops** → [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) + practice track.
3. **Do not** extend `import_access.py`, L0–L2 CLIs, or assign “run full prove” for staging-only mistakes.
4. **Do not** treat day 0 reload as normal prove.
5. **DDL** → edit `scripts/amiga/sql/` bundles → **simul** on **`ko2amiga_work`**.
6. **Never** seed `ko2amiga_work` from full `ko2amiga_db` clone — **day 0 L3 load only** (MG10).
7. **Access pipeline docs** → archive only — [`archive/amiga-access-pipeline-index.md`](archive/amiga-access-pipeline-index.md).
8. **MG11 — copy, do not mutate legacy prove** — forward code in `scripts/amiga/modern/` only; fork legacy helpers into `modern/` before use; never edit `prove.py` / `import_access.py` for transition slices.

---

## 14. Relationship to prior docs

| Doc | Relationship |
|-----|----------------|
| [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) | Lane B/C **unchanged**. Lane A “canon pipeline” **defers here** for local ground authority. ALO8 bidirectional flow still required; merge import is follow-on. |
| [`amiga-derived-write-policy.md`](amiga-derived-write-policy.md) | L5 writers unchanged; **forward sign-off = simul** on work (DOC-1). |
| [`amiga-ground-stack.md`](amiga-ground-stack.md) | **Historical** strict chain; archived for Access era. |
| [`amiga-import-layer.md`](amiga-import-layer.md) | **Historical** L3 import transforms; archived after day 0. |
| [`amiga-staging-handoff.md`](amiga-staging-handoff.md) | Export from work; pull-before-push ([`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md)). |

---

## 15. Rejected alternatives

| Alternative | Why not |
|-------------|---------|
| Clone full `ko2amiga_db` → `ko2amiga_work` as cutover seed | Skips bootstrap proof; conflates L3 seal with living state; hides day-0 load bugs |
| Reload day 0 L3 on every simul | Erases community ground, L4, video — same staging wipe failure |
| Keep Access in loop for “new KOA tournament” | Forward append is simpler; Access retired |
| Bundle L4 into day 0 seal | L4 is expanding product ground, not pure community witness |
| Skip video in simul | Align+verify is cheap with stable ids; keeps product coherent |
| Full prove as daily ops on staging | No Access/L0; use anchored repair — live ops platform |

---

## 16. Open questions

- Exact **pull** automation (`pull-staging-to-work`, sync manifest) — policy locked [`amiga-staging-authority-policy.md`](amiga-staging-authority-policy.md); implementation PULL-1 / SYNC-1.
- When to declare **L4 pipeline retired** (structure coverage % threshold).
- Lane C video DB migration vs JSON read path.

---

## 17. Changelog

| Date | Change |
|------|--------|
| 2026-07-08 | **L4-1 done** — `verify-structure-work` on `ko2amiga_work`; disposition register complete. |
| 2026-07-08 | **Simul preflight/postcheck** — living-ground rule: no day 0 count pin; postcheck = L3 unchanged during run. |
| 2026-07-08 | **P-1 done** — `modern/parity.py` CLI; semantic signatures exclude replay timestamps + standings surrogate `id`. |
| 2026-07-08 | **S-1 done** — `modern/simul.py` + verify suite on `ko2amiga_work`; `KO2AMIGA_DATABASE` config hook. |
| 2026-07-08 | **DOC-1 + CODE-1 shipped** — Access pipeline docs archived; `modern/` compartment README + MG11 audit; legacy prove/import/replay FROZEN. |
| 2026-07-08 | **MG11 locked** — copy legacy prove scripts into `modern/`; do not mutate legacy path (§5.1). |
| 2026-07-08 | **W-1 done** — `seed-work` CLI; `ko2amiga_work` from day 0 archive. |
| 2026-07-08 | **Policy locked** — living ground, day 0 bootstrap, Access retired, simul model, cutover program D0/W/S/P. |
