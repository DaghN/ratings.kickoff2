# Starter prompt — Online PHP ops cutover docs sweep (C++ retired)

**Paste into a new Cursor Agent chat.** Docs-only. Scope is narrow on purpose.

**Date context:** 2026-07-18 — Dagh confirmed live online ladder derived post-game is **PHP ops** (C++ derived retired). Topology (local / staging / prod DB hosts) is **out of scope** — do not rewrite environment tables based on uncertain “staged = prod” stories.

---

## Paste block (start here)

```
Read PROJECT_MEMORY.md → AGENTS.md → docs/PROJECT_MAP.md, then this handoff:

docs/orchestration/agent-handoffs/online-php-ops-cutover-docs-sweep-STARTER-PROMPT.md

Task: careful documentation sweep only — record that online live derived post-game is PHP ops and legacy C++ derived is retired (cutover 2026-07-18).

Before editing, reply with a short plan: files you will touch (Tier 1 then Tier 2), search patterns you will use, and what you will explicitly NOT change. Wait for my OK if anything looks like a topology rewrite.

Scope IN
- Replace stale “live still uses C++”, “until Steve cutover”, “scheduled cutover”, “C++ today → PHP at cutover”, “Live cutover = Not executed” (online ops meaning) with accurate “PHP ops live since 2026-07-18 / C++ derived retired”.
- Align registers and authority docs so agents stop assuming pre-cutover runtime.
- Prefer precise wording: Steve still owns ground insert + hosting + invoke; this repo owns derived writers/contracts. Cutover = derived runtime authority, not “Steve retired”.

Scope OUT (do not change unless a sentence is purely C++/cutover-status and you can fix without topology claims)
- Local vs staging vs prod host/DB topology; “no live writes on staging”; joshua vs ratings.kickoff2.com as public prod face.
- Speculating that staged was promoted to prod, dual-write, or renaming kooldb1 as live.
- Amiga staging-as-prod policy (already a different story) — only touch Amiga docs if they wrongly say online still uses C++.
- Archive / old agent-handoffs / historical May 2026 narratives — leave as history unless a live pointer still sends agents there as current truth (then add a one-line “historical; see …” redirect, do not rewrite the archive body).
- Code, ops PHP, ini files, schema — docs only.

Already partially updated (verify, do not thrash)
- AGENTS.md, PROJECT_MEMORY.md (ladder ops / cutover bullets), docs/ladder-ops-platform.md §2 Prod today, docs/coordination/cutover-readiness.md Layer C Done, feature-log header + Ladder ops platform row, .cursor/rules/kool-workspace.mdc cutover vocabulary.

Known stale targets (start here; grep will find more)
Tier 1:
- docs/PROJECT_MAP.md (still may say C++ until Steve switches)
- docs/prod-coordination.md
- docs/coordination/post-game-register.md
- docs/coordination/cutover-readiness.md (fix leftover “when Steve is ready” / future checklist tone if Layer C is Done)
- docs/coordination/schema-register.md (Live prod executed column)
- docs/coordination/feature-log.md (online rows still Not executed — flip carefully; leave Amiga staging Not executed alone)
- docs/website-data-contract.md (intro / prod-today paragraphs only — not every historical “at cutover” in row notes unless misleading)
- site/public_html/ops/docs/post-dagh-live-story.md
- site/public_html/ops/README.md
- docs/LOCAL_DEV.md, docs/STATUS_PAGE_DATA.md

Tier 2 (after Tier 1):
- docs/coordination/staging-work-steve-brief.md
- docs/coordination/ops-completeness-charter.md
- docs/coordination/ops-derived-data-registry.md
- docs/milestones-project.md / docs/milestones-product-spec.md (headers only if they say C++ until cutover)
- site/public_html/ops/docs/steve-live-ops.md if it still implies C++ live

Search patterns (ripgrep from repo root)
- C++ / cpp derived / legacy C++
- until (Steve )?cutover / scheduled cutover / when Steve
- Live cutover.*Not executed / Not yet.*live
- PHP ops at cutover / at cutover \(not C
- Prod today

Method
1. Grep → classify each hit: (a) must fix now, (b) historical OK, (c) topology — leave alone, (d) Amiga unrelated.
2. Edit carefully with StrReplace (UTF-8). On Windows do not use the Write tool for whole-file rewrites of .md.
3. One coherent vocabulary everywhere you touch:
   - Live derived post-game = PHP ops (ProcessCompletedGame / dispatch) since 2026-07-18
   - Legacy C++ derived post-game = retired (do not extend)
   - Steve: ground truth + host + invoke
   - Repo: derived contract + writers + website
4. cutover-readiness.md: keep A/B/C vocabulary; Layer C = Done; retire “future go-live checklist” tone without inventing DB host names.
5. feature-log / schema-register: update online live-executed status for the ops cutover set; do not mass-mark Amiga “Not executed (Amiga staging)” as Done.
6. Run docs/UPDATE_DOCS.md Part A when finished (MEMORY line + any register notes). Part B only if you change migration register truth for live executed — then update schema-register / feature-log consistently.

STOP / ask Dagh
- If a fix requires stating where prod physically lives, whether staging still has no live writes, or joshua’s role — do not guess; leave a short “Unknown — confirm with Dagh” note in MEMORY or ask once.
- If unsure whether a “Not executed” row means online PHP cutover vs Amiga sync vs unrelated deploy — skip or ask.

Done when
- Grep for “C++ until cutover”, “legacy C++ still runs”, “scheduled cutover” (online live meaning) is clean outside archive/history.
- Authority docs agree: PHP ops live, C++ derived retired, Steve/repo split unchanged.
- Report: files changed, files deliberately skipped, any open questions.
```

---

## Authority for this sweep

| Doc | Role |
|-----|------|
| [`AGENTS.md`](../../AGENTS.md) | Agent prod-today already updated — match this |
| [`docs/ladder-ops-platform.md`](../../ladder-ops-platform.md) §2 | Steve ground vs repo derived; Prod today |
| [`docs/coordination/cutover-readiness.md`](../../coordination/cutover-readiness.md) | A/B/C layers |
| [`docs/UPDATE_DOCS.md`](../../UPDATE_DOCS.md) | Finish Part A (+ B if registers) |

## Out of scope reminder

Topology / “staged promoted to prod” / dual DB hosts were discussed 2026-07-18 and **deferred**. Do not reopen in this chat unless Dagh explicitly expands scope.