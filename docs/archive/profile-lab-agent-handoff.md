# Profile lab — multi-agent handoff

**Status:** **Archived Jun 2026.** Lab entry pages, `player_feast_*_lab*`, and `player-feast-lab*.css` were **deleted** from the repo; production feast lives on `player/profile.php` only. This file is kept for historical agent prompts and the compare methodology.

**Original role (Jun 2026):** Feeder agent prepares **builder agents** who implement Profile **content v1** on isolated lab URLs. Production `player/profile.php` stayed untouched until Dagh merged a winner.

**Dagh:** Copy one **Builder prompt** § below into a new Cursor chat per agent. Replace `{N}` with the agent number (1, 2, 3, …).

---

## Is this a good method?

**Yes**, for your goal (compare full interpretations before merging):

| Benefit | Why |
|---------|-----|
| Production safe | `player/profile.php` and shared blocks unchanged |
| Side-by-side compare | Open `individual1-profile-lab1.php?id=237` vs `lab2` vs production |
| Same brief, different craft | Tests execution quality (cards, order, copy), not re-negotiating strategy |
| Playbook already locks v1 | Builders don’t re-litigate the catalog |

**Caveats:**

1. **Each agent must use isolated PHP includes** (`player_feast_blocks_lab{N}.php`) — never edit `player_feast_blocks.php` unless explicitly told to merge.
2. **Full v1 per agent** — don’t split waves across agents if you want holistic A/B compare; wave split is for sequential assembly on one lab page.
3. **Lab banner** required so previews are never mistaken for production.
4. **Do not deploy** lab URLs to public prod without `noindex` / access control.

---

## Do we have the documents we need?

**Yes.** Builders should read in this order:

| # | Document | Role |
|---|----------|------|
| 1 | **This file** | Lab rules + your agent number + deliverables |
| 2 | [`profile-build-playbook.md`](profile-build-playbook.md) | Placement charter, module recipes, waves, acceptance |
| 3 | [`profile-content-candidates.md`](profile-content-candidates.md) — **Profile content v1** | Complete build list (keep / reject / defer) |
| 4 | [`player-profile-feast.md`](player-profile-feast.md) | Shipped reference + narrative model + surface rhythm |
| 5 | `site/public_html/includes/player_feast_blocks.php` | Copy patterns from here into your lab file |
| 6 | [`design-direction.md`](design-direction.md) | Visual tokens |
| 7 | [`website-data-contract.md`](website-data-contract.md) | Stored tables for new reads |

**Optional tone reference:** [`archive/profile-redesign-framing.md`](archive/profile-redesign-framing.md) (Chronicle thesis).

**Not needed up front:** Full `docs/coordination/` unless a slice adds stored truth (then note in deliverable for Dagh).

---

## Lab file convention (agent `{N}`)

| Asset | Production (do not edit) | Agent lab copy |
|-------|---------------------------|----------------|
| Page URL | `player/profile.php` | `individual1-profile-lab{N}.php` |
| Blocks | `includes/player_feast_blocks.php` | `includes/player_feast_blocks_lab{N}.php` |
| Load | `includes/player_feast_load.php` | `includes/player_feast_load_lab{N}.php` (fork when adding fields) |
| CSS (optional) | shared feast CSS | `stylesheets/player-feast-lab{N}.css` or `body.player-feast-body--lab{N}` scoping |

**JS / APIs:** Reuse production `js/player-*.js` and `api/` unless behaviour must diverge — then fork with a lab suffix only if necessary.

**Function names:** May match production (`player_feast_render_moments`, etc.) inside **your** lab blocks file — only one lab file is `require`d per request, so no PHP clash.

---

## Scaffold checklist (every builder — Step 0)

Before v1 content, create isolated lab files:

1. Copy `site/public_html/player/profile.php` → `individual1-profile-lab{N}.php`.
2. Copy `includes/player_feast_blocks.php` → `includes/player_feast_blocks_lab{N}.php`.
3. In the lab page: `require` lab load/blocks paths; call the same render functions (defined in your lab blocks file).
4. Add a visible banner at top of feast body, e.g.  
   `Profile lab preview — Agent {N} — not production`  
   (use `pm3-muted` or a small `k2-lab-banner` class; do not use error styling).
5. Verify: `individual1-profile-lab{N}.php?id=237` renders; production `player/profile.php?id=237` unchanged in git diff for blocks/load (only new files + lab page).

---

## Builder scope (all agents)

Implement **Profile content v1 — full “build next” set** from `profile-content-candidates.md` v1 §, following **placement charter** in playbook §3 (target scroll spine).

**Include (summary):**

- B06, B07/B08, C01–C05 (+ rank rethink), C12, M03, M08, M09, P02, P05, MS01, MS02, MS04, MS08, L01, L02/L04/L07/L08, X01, X04, X05/X06 where earned  
- **B1/B2 data** (B01–B03, C01–C05 + v1 adds) — **rethink layout** per § B1/B2 below; do not assume `pm3efg-duo` tables  
- Other shipped blocks: integrate M01–M02, P01, H01–H03, G01–G04 (presentation may follow module recipes)
- **Do not** implement v1 **Reject** or **Defer** IDs without Dagh approval  
- **Consider** items (A04, B09, M10, M11, C14, L06, H05): skip unless your prompt from Dagh says otherwise  

**Reorder DOM** to match playbook target spine when it improves story; document your order in deliverable.

**Do not** add new chart types. **Do not** panel-wrap heatmaps.

### B1 / B2 — Presence & Career (lab: rethink presentation)

Production ships **Presence + Career** as one side-by-side duo (`pm3efg-duo`) with HTML stat tables. That layout is **reference only** in lab builds — **not** required.

| Required | Optional / open in lab |
|----------|-------------------------|
| **B1 job** — still around? (B01–B03 data + B06/B07/B08) | Split into two sections vs one band |
| **B2 job** — what kind of player? (C01–C05 + C12, P02, P05) | Tables vs tiles vs prose/ticker/chips |
| Same **facts** as v1 | `(#rank)` column — rethink, inline, or omit |
| | Open background vs bordered panels |
| | Merge B1+B2 into one “At a glance” strip |

**Lab agents should propose a fresher B1/B2** (Chronicle tone, not spreadsheet). Copying `player_feast_render_presence_career_duo()` verbatim is a valid fallback, not the goal.

**Production merge** (`player/profile.php`) waits until Dagh picks a winner — lab-only layout experiments stay in `player_feast_blocks_lab{N}.php`.

**Isolation unchanged:** work only in your `lab{N}` files; do not read or edit other agents’ lab paths or production blocks.

---

## Builder deliverables (end of chat)

Post a short handoff for Dagh:

1. **Lab URL** — `individual1-profile-lab{N}.php?id=237` (+ any other test ids used).  
2. **Files touched** — list new/changed paths.  
3. **Scroll order** — numbered list of sections on your lab page.  
4. **Deviations** — anything you chose differently from playbook and why.  
5. **Skipped v1 items** — if any, with reason.  
6. **Screenshots optional** — not required; verbal description OK.  
7. **Docs** — Part A: MEMORY line + note in `player-profile-feast.md` only if you changed **production** files (lab-only work → say “lab only, no prod doc change” or one line in this handoff file via Dagh).

---

## Builder prompts (copy into new chats)

**Agents 1–2:** first lab pass (may use production duo as starting point).  
**Agent 3:** **complete v1 first** — then considered layout (Honours band, spine, B1/B2 rethink). Not a partial prototype.  
**Agents 4–5:** **same brief as 1–2**, but **must rethink B1/B2** (handoff § B1/B2). Prompts intentionally overlap — Dagh compares **different models**, not different instructions. Do not read lab1–lab3 files.

---

### Agent 1

```
You are Profile lab builder Agent 1.

Read first (in order):
1. docs/profile-lab-agent-handoff.md
2. docs/profile-build-playbook.md
3. docs/profile-content-candidates.md — Profile content v1
4. docs/player-profile-feast.md — narrative model + surface rhythm

Task:
0. Scaffold lab files per handoff § (individual1-profile-lab1.php, player_feast_blocks_lab1.php, player_feast_load_lab1.php as needed). Do NOT edit production player/profile.php or player_feast_blocks.php.
1. Implement full Profile content v1 on your lab page per playbook (placement charter, module recipes, display rules).
2. Match the quality bar of existing Moments cards — story-driven, not spreadsheet.
3. Test: id=237 (Steve), one mid-volume player, one sparse player.
4. Post deliverables per handoff §.

Authority: playbook + v1 list. Reject/defer IDs stay out unless Dagh said otherwise in this chat.
```

### Agent 2

```
You are Profile lab builder Agent 2.

Read first (in order):
1. docs/profile-lab-agent-handoff.md
2. docs/profile-build-playbook.md
3. docs/profile-content-candidates.md — Profile content v1
4. docs/player-profile-feast.md

Do NOT read or copy Agent 1’s lab files (player_feast_blocks_lab1.php, individual1-profile-lab1.php). Work only in lab2 paths.

Task:
0. Scaffold: individual1-profile-lab2.php, player_feast_blocks_lab2.php, player_feast_load_lab2.php as needed. Do not edit production player/profile.php or player_feast_blocks.php.
1. Implement full Profile content v1 on your lab page — same scope as Agent 1, your own layout/copy/craft choices within playbook rules.
2. Prioritize Chronicle tone: celebration and memory before analyst charts.
3. Test id=237 + sparse player.
4. Post deliverables per handoff §.
```

### Agent 3

```
You are Profile lab builder Agent 3.

Read first (in order):
1. docs/profile-lab-agent-handoff.md
2. docs/profile-build-playbook.md — placement charter §3, module recipes §4, acceptance §8
3. docs/profile-content-candidates.md — Profile content v1 (complete build list; do not skip IDs)
4. docs/player-profile-feast.md — narrative model + surface rhythm

Do NOT read or copy other agents’ lab files. Use lab3 paths only.

Primary goal: a **complete, shippable** Profile content v1 lab page — not a partial prototype or gimmick layout. Layout choices (B1/B2, Honours band, scroll order) should **elevate** the full build, not replace building every v1 module.

Task:
0. Scaffold: individual1-profile-lab3.php, player_feast_blocks_lab3.php, player_feast_load_lab3.php as needed. Do NOT edit production player/profile.php or player_feast_blocks.php.
1. Implement **every** Profile content v1 item per handoff § Builder scope and playbook §3–4 (placement charter, module recipes, display rules). Tick v1 IDs as you go; document any intentional skip.
2. **Rethink B1/B2 (Presence + Career):** all v1 facts, fresh presentation — production `pm3efg-duo` tables are reference only. Prefer Chronicle tone: readable at a glance, not spreadsheet.
3. **Layout direction (polish, not stunt):** combine milestone + league into one **Honours** band (B3); follow playbook target spine (Hero → B1 → B2 → B3 → B5 → B4 → C). B4 vs B5 order is your call per playbook §3 if it improves story — document why.
4. Match Moments card quality across Honours, Personal bests, and rivalry line (M09). Run playbook **acceptance §8** before handoff.
5. Test: id=237 (Steve), one mid-volume active player, one sparse player (<20 games — optimistic empty states, X01).
6. Post deliverables per handoff § (scroll order, skipped v1 items if any, B1/B2 + Honours design notes).

Authority: playbook + v1 list. Reject/defer IDs stay out unless Dagh said otherwise in this chat.
```

### Agent 4

```
You are Profile lab builder Agent 4.

Read first (in order):
1. docs/profile-lab-agent-handoff.md
2. docs/profile-build-playbook.md
3. docs/profile-content-candidates.md — Profile content v1
4. docs/player-profile-feast.md — narrative model + surface rhythm

Do NOT read or copy other agents’ lab files (lab1, lab2, lab3, lab5). Work only in lab4 paths.

This brief overlaps Agent 1–2 on purpose — Dagh is comparing **models**, not different specs. Your independent craft matters.

Task:
0. Scaffold: individual1-profile-lab4.php, player_feast_blocks_lab4.php, player_feast_load_lab4.php as needed. Do NOT edit production player/profile.php or player_feast_blocks.php.
1. Implement full Profile content v1 per playbook (placement charter, module recipes, display rules).
2. **Rethink B1/B2 (Presence + Career):** deliver all v1 facts from handoff § B1/B2, but do **not** copy the production `pm3efg-duo` stat tables by default. Prefer story-led layout — split sections, tickers, tiles, chips, or open background. Document what you tried.
3. Match the quality bar of existing Moments cards — celebration before analyst charts.
4. Test: id=237 (Steve), one mid-volume player, one sparse player.
5. Post deliverables per handoff § (include a short note on your B1/B2 design).

Authority: playbook + v1 list. Reject/defer IDs stay out unless Dagh said otherwise in this chat.
```

### Agent 5

```
You are Profile lab builder Agent 5.

Read first (in order):
1. docs/profile-lab-agent-handoff.md
2. docs/profile-build-playbook.md
3. docs/profile-content-candidates.md — Profile content v1
4. docs/player-profile-feast.md — narrative model + surface rhythm

Do NOT read or copy other agents’ lab files (lab1–lab4). Work only in lab5 paths.

Same scope as Agent 4 — overlap is intentional; Dagh compares **models**, not prompt variants.

Task:
0. Scaffold: individual1-profile-lab5.php, player_feast_blocks_lab5.php, player_feast_load_lab5.php as needed. Do NOT edit production player/profile.php or player_feast_blocks.php.
1. Implement full Profile content v1 per playbook (placement charter, module recipes, display rules).
2. **Rethink B1/B2 (Presence + Career):** all v1 pulse + character facts, fresh presentation — production duo tables are reference only (playbook §3.1, §4.2b). Aim for Chronicle tone: readable at a glance, not a spreadsheet.
3. Honours (B3), Moments, and rivalry line (M09) should feel as polished as B1/B2 — not an afterthought.
4. Test: id=237 (Steve), one mid-volume player, one sparse player.
5. Post deliverables per handoff § (include a short note on your B1/B2 design).

Authority: playbook + v1 list. Reject/defer IDs stay out unless Dagh said otherwise in this chat.
```

### Agent 6+ (template)

Replace `{N}`:

```
You are Profile lab builder Agent {N}.

Read: docs/profile-lab-agent-handoff.md → profile-build-playbook.md → profile-content-candidates.md (v1) → player-profile-feast.md

Do not read other agents’ lab files. Scaffold individual1-profile-lab{N}.php + player_feast_blocks_lab{N}.php (+ load if needed). Do not edit production player/profile.php or player_feast_blocks.php.

Implement full Profile content v1 on your lab page per playbook. **Rethink B1/B2 presentation** (handoff § B1/B2) — facts required, `pm3efg-duo` tables not. Post deliverables per handoff §.
```

---

## Alternative: sequential waves (one lab page)

If Dagh prefers **one** lab URL assembled in waves (not A/B agents), use **one** builder and playbook §7 waves 1–7 on `individual1-profile-lab1.php` only. Do not use multi-agent compare mode.

---

## After all builders finish

Dagh compares:

- `player/profile.php?id=237` (production)  
- `individual1-profile-lab1.php?id=237` … through **lab5** (and any later labs)

Pick winners per module or one overall spine → merge into production in a **separate** merge chat (not automatic).

---

*Feeder doc — no lab PHP committed by feeder agent; builders create lab files.*
