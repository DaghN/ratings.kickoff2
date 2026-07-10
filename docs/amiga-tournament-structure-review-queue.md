# Amiga tournament structure — review queue

**Manual materialize (forward):** [**runbook**](amiga-tournament-structure-manual-materialize-runbook.md) — one tournament at a time; log decisions below.  
**Handlers reference:** [`amiga-tournament-structure-handlers.md`](amiga-tournament-structure-handlers.md)  
**Register:** `scripts/amiga/tournament_structure/disposition_register.json`

Legacy disposition review starter (bulk bootstrap): [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md)

---

## Import splits — do this first

**Starter:** [`amiga-import-split-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-import-split-REVIEW-STARTER-PROMPT.md)

| Parent id | Scores label | Dagh decision |
|-----------|--------------|---------------|
| 48 Groningen VII | `Groningen VII Cup` (14g) | **Split** — implemented id **604** |
| 62 Gloucester III | `Gloucester III Team` (10g) | **Split** — implemented id **605** |
| 156 Milan X | KO fragments | **Not split** — closed (alias + `resolve_phase`) |
| 187 Hertford IV | league + cup (one Scores label) | **Split decided, deferred** — 24g league + 4g cup; import partition TBD (→ synthetic **Hertford IV Cup**) |

Disposition review pauses on affected parents until the gate in the split starter is checked off.

---

## Status (bootstrap Jun 2026 — bulk mostly done)

**Forward:** remaining catalog ids → [manual materialize runbook](amiga-tournament-structure-manual-materialize-runbook.md). Bulk handler counts below are **historical bootstrap**, not “still to bulk.”

| Handler | Count | Action |
|---------|------:|--------|
| `pure_rr` | 503 | Assigned — verify spot-check only |
| `pure_knockout` | 11 | Assigned — preview confirmed |
| `structure_spec` | 24 | Homburg + unsettled multi-stage |
| `wc_deferred` | 23 | WC track later |
| **`pending_review`** | **44** | **Review chat — promote handlers** |

```powershell
python -m scripts.amiga tournament-structure verify-disposition-register --json
```

Regenerate after code changes: `generate-disposition-register` (overwrites proposals).

---

## Handler assignment (per tournament)

| Handler | When |
|---------|------|
| `pure_rr` | Round-robin format (complete or incomplete — note withdrawals in `notes`) |
| `pure_knockout` | Preview CLI matches reality — [`pure-knockout-handler.md`](amiga-tournament-structure-pure-knockout-handler.md) |
| `structure_spec` | Groups, multi-stage, exotic |
| `wc_deferred` | World Cups for now |
| `pending_review` | Format not settled |

**On confirm:** set `handler` + one-line `notes` from Dagh's description of oddities.

---

## URLs (local)

| View | URL |
|------|-----|
| Games | `http://ratingskickoff.test/amiga/tournament.php?id={id}&view=games` |
| Standings | `http://ratingskickoff.test/amiga/tournament.php?id={id}` |
| **Prod stats** | `https://ratings.kickoff2.com/amiga/tournament.php?id={id}` |

---

## Review log

### 2026-06-13 — Disposition register bootstrap

- Generated `disposition_register.json` — **605/605** coverage
- Pure knockout handler + preview CLI shipped
- **70** `pending_review` to triage

- **17** Milan XXXIX → `pure_rr` — Double RR; Sandro T left early — 5 games unplayed (withdrawal).
- **22** Athens XCI → `structure_spec` (`league_placement`) — 12p league RR → two-leg placement finals (11th through Final).
- **29** Rome → `structure_spec` (`league_placement`) — 6p double RR (g194–223) + two-leg Final (g224–225); NULL witness phases. **Jul 2026:** manual materialize; stages **`League`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianluca · 2 Alessandro Co · 3 Giacomo · 4 Franco · 5 Filippo · 6 Fabio.
- **48** Groningen VII → `structure_spec` (no spec_slug) — 46g main after cup split to 604; format uncertain.
- **54** Kristiansand → `structure_spec` (no spec_slug) — 2×4 groups + KO playoffs; forum p=48040; AET/pens not in DB.
- **62** Gloucester III → `pure_rr` — 10p double RR (90g); Team split to id 605.
- **64** Venice → `structure_spec` (no spec_slug) — 4p two-leg league (g1451–62) + KO semis 2v3/1v4, 3rd-place, two-leg final (g1463–69); NULL witness phases; forum p=63225. **Jul 2026:** manual materialize (T11 blocked legacy auto); 5 stages — **`League`** · **`Semi Finals`** (×2) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Franco · 2 Filippo · 3 Francesco · 4 Luciano.
- **74** Athens IV Cup → `structure_spec` (no spec_slug) — lucky-loser cup; forum t=2668.
- **75** Gloucester I Cup → `pure_knockout` — 24p single-elim, 8 byes R1.
- **89** Milan → `structure_spec` (no spec_slug) — 2×8 uneven groups + two-leg KO playoffs.
- **108** Grimstad II → `pure_rr` — forum Grimstad 1; G E Land–Kjetil M 3×; forum t=6550.
- **110** Kristiansand II → `structure_spec` — 8p league (28g) **seeding only**; cup playoffs on id **111** (separate catalog event); forum p=106321. **Jul 2026:** legacy `materialize --replace`; stage **`League`**; `has_league=1` `has_cup=0`; Tier C league finish (no cup merge onto 110).
- **111** Kristiansand II Cup → `pure_knockout` — **two mini-cups** seeded from league **110** bands: places **1–4** (g3195–98) + places **5–8** (g3191–94); each 4p KO (2 SF + 3rd-place + F). NULL witness phases; g3192/g3193 `extra` ET+pens. **Jul 2026:** materialize + manual stage names. **Cup finish (111 only, Tier E on work):** 1 Klaus · 2 Glenn · 3 Kjetil · 4 Aasmund · 5 Oskar · 6 Jon · 7 Jens (pens) · 8 Espen. **110** = league seeding only (Tier C; no override).
- **121** Norwegian Champs → `structure_spec` — 4 groups + R2 play-in + KO; forum p=106321.
- **134** Milan IV → `structure_spec` — 7p double-RR league (g4099–4140, 42g) + 8g playoffs (g4141–48: two-leg semis, 3rd, final); **g4141 = semi 2v3 leg 1** (not league — third Gianni–Alessandro meeting). **Jul 2026:** manual materialize; **`League`** · **`Semi Finals`** (×2, two-leg each) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianni · 2 Luigi · 3 Alessandro V · 4 Franco · 5 Filippo · 6 Sandro · 7 Diego.
- **145** Milan V → `structure_spec` — 2×4 groups (Sandro T withdrew) + variable-leg KO + placement finals; AET/pens g5166, g5185–87, g5194; forum Carnival Cup t=9365.
- **152** Homburg II → `structure_spec` — 2×5 double-RR groups + KO incl. Playouts + placement finals; German Championship 2005; forum t=10006.
- **156** Milan X → `structure_spec` — 2×5 double-RR groups + KO; 3rd/final 3 legs (g5513–18); Access KO fragments merged via alias.
- **158** Stoke Cup → `pure_knockout` — 15p single-elim; 7 R1 ties + 1 bye (James B); phase "Round 1" not league (g5639–5652).
- **166** Milan XII → `structure_spec` — 8p double-RR league + 2-leg semis + 3-leg final; phase "Finals" plural (g5961–63).
- **171** Copenhagen Cup → `pure_knockout` — 8p from QF + full placement bracket; AET g6723 (g6715–6726).
- **173** Frankfurt → `structure_spec` — 4p double-RR league + 2-leg semis/3rd/final (g6781–88). **Jul 2026:** cleared `NON_WC_SLICE6_CUP_REVIEW_IDS`; legacy materialize on `ko2amiga_work`; RR stage `round-1` display name **`League`** (witness `g.phase` stays `Round 1`).
- **604** Groningen VII Cup → `pure_knockout` — 8p single-elim, 2-leg ties; import split from id **48**. **Jul 2026:** `materialize-pure-knockout --replace`; stage names **`Quarter Finals`** / **`Semi Finals`** / **`Final`** (witness `Round 1` / `Semi Final` unchanged).
- **174** London Marathon → `pure_rr` — 22p near-complete single RR (230/231g); James L–Vagelis D unplayed.
- **176** Milan XIV → `structure_spec` — 6p double-RR league (g7104–33) + 2-leg semis/3rd/final (g7134–41). **Jul 2026:** cleared `NON_WC_SLICE6_CUP_REVIEW_IDS`; manual materialize; **`League`** · **`Semi Finals`** (×2) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianni · 2 Luigi · 3 Marco · 4 Alessandro V · 5 Maurizio · 6 Sandro.
- **187** Hertford IV — split **deferred** (24g league + 4g cup; forum t=12376; cup g7579, 7567, 7563, 7582); stays `pending_review`.
- **189** Manchester II Cup → `pure_knockout` — 15p single-elim; 7 R1 + bye (Steve C); pens g7689, 7692; league on id 188.
- **192** Hertford V Cup → `pure_knockout` — 4p; 2-leg semis (phase "Round 1") + 2-leg 3rd/final (g7768–775); league on id 191.
- **198** Milan XVII → `structure_spec` — 2 groups (4+3) double-RR + KO incl. Playouts 5-7 (g7956–991).
- **214** Milan XVIII → `pure_rr` — 8p near-complete double RR (50/56g); Luigi F & Gabriele B left early (Maurizio L delay).
- **215** Kelkheim VII → `structure_spec` — 12p single-RR league + 2-leg KO incl. Places 5-8; AET g8931 (g8908–931).
- **248** Athens XXXVIII Cup → `pure_knockout` — 7p cup; variable-leg semis + AET final g10166; league on id 247.
- **267** Seeshaupt → `structure_spec` — 5p single-RR league + Game of Shame + final; AET g10553.
- **269** Cologne I → `structure_spec` — 25p multi-group RR + Playouts + R2 + placement KO through 24th (g10578–10822).
- **276** Langenfeld → `structure_spec` — 8p single-RR league + 2-leg KO incl. Places 5-8 (g10996–11047).
- **281** Athens L → `pure_rr` — 7p near-complete single RR (19/21g); Nikos Al missing 2 pairings.
- **284** Athens LIII → `structure_spec` — 2×7 groups + Playouts/Playoffs groups + KO; AET g11635–36.

---

## Superseded starters

- [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) — **use this**
- [`amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md) — cup subset; merged into disposition review
- [`amiga-tournament-structure-REVIEW-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md) — old non-WC queue
