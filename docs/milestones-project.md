# Milestones project — status & phases



**Kick Off 2 ratings site · May 2026**



Single place to see **where the milestone feature is** in the pipeline.



---



## Current phase



| | |

|--|--|

| **Completed** | **Phase 1–3** (catalog + full rebuild) · **Phase 4 v0** UI · **staging `kooldb`** (May 2026): catalog **112** (`play_streak_100`, **`year_in_heaven`** — 5 holders verified); **rated play streaks** SCH-014 + REP-015 — LB `ranked4` + HoF `server2` · profile **Played weeks** |

| **Next** | Staging browser smoke (PHP/WinSCP) · prod schema+REP+C++ M1–M7 · hub Home [`milestones-hub-ia.md`](milestones-hub-ia.md) |



**Catalog (112 keys):** [`milestones-README.md`](milestones-README.md) → [`milestones-catalog.md`](milestones-catalog.md) (generated). **Seed:** [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json). **Facilitation:** [`milestones-facilitation.md`](milestones-facilitation.md).

**Unlock row counts (staging/local):** After a full rebuild, expect **6615** rows in `player_milestones` (not **151** from the early SCH-008 shell). Timeline: [`coordination/replay-register.md`](coordination/replay-register.md) § Milestone unlock row counts.



---



## Phase map



| Phase | Name | Status | Primary docs |

|-------|------|--------|----------------|

| 0 | **Discovery** | Done | [`milestones-system-discussion.md`](milestones-system-discussion.md) |

| 1 | **Idea creation** | Done | [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) |

| 2 | **Definition** | **Done (May 2026)** | [`milestones-product-spec.md`](milestones-product-spec.md), curated list, meta JSON, seed export |

| 3 | **Data contract** | **Done (local)** | Rebuild 112/112 + parity scripts |

| 4 | **Build & ship** | **v0 + hub stub (local)** | Profile garden, `ranked10.php`, hub tab [`milestones.php`](../site/public_html/milestones.php) stub; full hub WIP |



---



## Phase 3 checklist



| # | Task | Status |

|---|------|--------|

| 1 | `milestone_definitions` schema (SCH-011) + load from seed | **Done** (local) |

| 2 | Facilitation matrix | **Done** — [`milestones-facilitation.md`](milestones-facilitation.md) |

| 3 | Rebuild wave 1 — league + `entered_arena` lobby (23 unlock keys) | **Done** — `player_milestones_rebuild.sql` + source pointers (SCH-012/013) |

| 4 | Rebuild waves 2–6 — remaining ~88 keys | Not started |

| 5 | Post-game rules per wave + Steve/C++ at prod cutover | Not started |

| 6 | Parity: probe counts vs `player_milestones` per key | Partial (league + established/dd) |



**Rebuild order:** `scripts/rebuild_website_derived_data_local.ps1` runs `player_milestones_rebuild.sql` **after** league awards (REP-012).



**Regenerate seed:** `python scripts/oneoff/milestone_unlock_counts.py --write-doc --export-seed`



**Load catalog:** `python scripts/oneoff/load_milestone_definitions.py`

**Catalog copy only (no TRUNCATE):** `data/milestone_catalog_copy_patches.json` + `python scripts/oneoff/apply_milestone_catalog_copy_patch.py` — staging: `patch_milestone_catalog_copy.php` ([`milestones-add-one-playbook.md`](coordination/milestones-add-one-playbook.md) § Staging copy patch).



---



## Technical baseline



| Item | State |

|------|--------|

| `milestone_definitions` | SCH-011 local; **112** rows from seed |

| `player_milestones` | **112/112** keys in rebuild (all waves parity-checked on local `ko2unity_db`) |

| Activity UI | Digest/charts for Established / DD only |

| Profiles / leaderboards | **v0** — profile pill + garden, `ranked10.php` meta board, hub `milestones.php` / `milestone.php`; HoF DD trial list **removed** May 2026 |



---



## Doc index



| Doc | Role |

|-----|------|

| [`milestones-README.md`](milestones-README.md) | **Start here** — workflows & doc map |

| [`milestones-catalog.md`](milestones-catalog.md) | **Generated** per-key catalog (tier, rule, Link, Event) |

| **This file** | Phase status |

| [`milestones-facilitation.md`](milestones-facilitation.md) | Implementation families & waves |

| [`milestones-product-spec.md`](milestones-product-spec.md) | Tier bands, garden UI, leaderboard |

| [`milestones-unlock-event-ui.md`](milestones-unlock-event-ui.md) | Unlock link/event UI spec |

| [`milestones-tier-curated.md`](milestones-tier-curated.md) | Archived snapshot — win-streak + discard notes |

| [`milestones-system-discussion.md`](milestones-system-discussion.md) | Discovery context |

| [`milestones-hub-ia.md`](milestones-hub-ia.md) | **WIP** — server hub IA & build phases |



---



*Phase 2 closed May 2026. Phase 3 opened same month.*

