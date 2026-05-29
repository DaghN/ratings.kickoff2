# Milestones project — status & phases



**Kick Off 2 ratings site · May 2026**



Single place to see **where the milestone feature is** in the pipeline.



---



## Current phase



| | |

|--|--|

| **Completed** | **Phase 1–3** (catalog + full rebuild) · **Phase 4 v0** (May 2026) — read-only UI on local DB: garden tab, profile glance, meta-leaderboard, trial DD Merchant achiever list on Hall of Fame |

| **Next** | Milestones hub Home (picker + achievers) per [`milestones-hub-ia.md`](milestones-hub-ia.md) · story · HoF achiever migration · staging/post-game |



**Working set:** [`milestones-tier-curated.md`](milestones-tier-curated.md) (110 milestones). **Seed:** [`data/milestones_definitions_seed.json`](../data/milestones_definitions_seed.json). **Facilitation:** [`milestones-facilitation.md`](milestones-facilitation.md).



---



## Phase map



| Phase | Name | Status | Primary docs |

|-------|------|--------|----------------|

| 0 | **Discovery** | Done | [`milestones-system-discussion.md`](milestones-system-discussion.md) |

| 1 | **Idea creation** | Done | [`milestones-ideas-catalog.md`](milestones-ideas-catalog.md) |

| 2 | **Definition** | **Done (May 2026)** | [`milestones-product-spec.md`](milestones-product-spec.md), curated list, meta JSON, seed export |

| 3 | **Data contract** | **Done (local)** | Rebuild 110/110 + parity scripts |

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



---



## Technical baseline



| Item | State |

|------|--------|

| `milestone_definitions` | SCH-011 local; 110 rows from seed |

| `player_milestones` | **110/110** keys in rebuild (all waves parity-checked on local `ko2unity_db`) |

| Activity UI | Digest/charts for Established / DD only |

| Profiles / leaderboards | **v0** — profile pill + garden, `ranked10.php` meta board, HoF trial achiever list; hub tab deferred |



---



## Doc index



| Doc | Role |

|-----|------|

| **This file** | Phase status |

| [`milestones-facilitation.md`](milestones-facilitation.md) | Implementation families & waves |

| [`milestones-product-spec.md`](milestones-product-spec.md) | Tier bands, garden UI, leaderboard |

| [`milestones-tier-curated.md`](milestones-tier-curated.md) | Locked 110-key snapshot |

| [`milestones-system-discussion.md`](milestones-system-discussion.md) | Discovery context |
| [`milestones-hub-ia.md`](milestones-hub-ia.md) | **WIP** — server hub IA & build phases |



---



*Phase 2 closed May 2026. Phase 3 opened same month.*

