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
- **22** Athens XCI → `structure_spec` (`league_placement`) — 12p **League** (66g) + two-leg placement finals (**11th** · **9th** · **7th** · **5th** · **3rd** · **Final**). **Jul 2026:** cleared structure-review block; manual materialize; 7 stages / 78g; display **League**; Tier B finish **1 Christopher D · 2 Alkis P · 3 Andy G · 4 Spyros P · 5 Nikos A · 6 Ektoras K · 7 Panayotis P · 8 George M · 9 George Ka · 10 Kostas O · 11 Vasilis K · 12 Kostas Ka**.
- **29** Rome → `structure_spec` (`league_placement`) — 6p double RR (g194–223) + two-leg Final (g224–225); NULL witness phases. **Jul 2026:** manual materialize; stages **`League`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianluca · 2 Alessandro Co · 3 Giacomo · 4 Franco · 5 Filippo · 6 Fabio.
- **48** Groningen VII → `structure_spec` — 9p; **Round 1 - Group A/B/C** + **Round 2 - Group D/E** (3×3 double RR each) + **`League 7-9`** (witness `Playouts` = 3p double RR, not KO) + KO (`Semi-Final`); cup split to **604**. **Jul 2026:** parser fix (`Semi-Final` hyphen; bare `Playouts` = league); manual materialize + group splits; **Tier E finish override** (full ladder 1–9 — auto derivation duplicates positions on multi-group format): **1 Mark P · 2 Kees V · 3 Luitzen B · 4 Riemer P · 5 Sjoerd K · 6 Evert V · 7 Niels T · 8 Gunther W · 9 Tim K**.
- **54** Kristiansand → `structure_spec` — 8p 2×4 group RR + KO playoffs (g1183–90); forum p=48040. **Jul 2026:** manual materialize; **SC-11 verified:** g1189 reg `0-0` + ET `1-0`; g1188 reg **`1-1`** + ET `0-0` + pens **7–8** (Access swap — reg 1-1 was on semi); finish 1 Klaus · 2 Aasmund · **3 Glenn** · **4 Oskar** · 5 Kjetil · 6 Jens · 7 Jon · 8 Gisle.
- **62** Gloucester III → `pure_rr` — 10p double RR (90g); Team split to id 605.
- **64** Venice → `structure_spec` (no spec_slug) — 4p two-leg league (g1451–62) + KO semis 2v3/1v4, 3rd-place, two-leg final (g1463–69); NULL witness phases; forum p=63225. **Jul 2026:** manual materialize (T11 blocked legacy auto); 5 stages — **`League`** · **`Semi Finals`** (×2) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Franco · 2 Filippo · 3 Francesco · 4 Luciano.
- **74** Athens IV Cup → `structure_spec` — 6p lucky-loser cup (QF g965–67 → Apostolos lucky loser → SF + F g968–70); forum t=2668. **Jul 2026:** manual materialize; **`Quarter Finals`** (3 fixtures) · **`Semi Finals`** · **`Final`**; `has_league=0` `has_cup=1`; Tier A finish 1 George · 2 Alkis · 3 Apostolos/Nikos · 5 Dimitris · 6 Vasilis.
- **75** Gloucester I Cup → `pure_knockout` — 24p single-elim, 8 byes R1.
- **89** Milan I → `structure_spec` — 16p; **Round 1 - Group A** · **Round 1 - Group B** · **Group B extra** (forum Extra 5g, g2394–2398) + KO. **Jul 2026:** 11 stages / 81g; L4 stage `round-1-group-b-extra` — UI Phase column from `tournament_stages.name`; witness `amiga_games.phase` stays `Round 1 - Group B`. Tier E sparse (FFZ [idd=175](https://web.archive.org/web/20030704044413/http://www.freeforumzone.com/viewmessaggi.aspx?f=3694&idd=175)); ground g2421/g2422; L5 rebuild deferred.
- **108** Grimstad II → `pure_rr` — forum Grimstad 1; G E Land–Kjetil M 3×; forum t=6550.
- **110** Kristiansand II → `structure_spec` — 8p league (28g) **seeding only**; cup playoffs on id **111** (separate catalog event); forum p=106321. **Jul 2026:** legacy `materialize --replace`; stage **`League`**; `has_league=1` `has_cup=0`; Tier C league finish (no cup merge onto 110).
- **111** Kristiansand II Cup → `pure_knockout` — **two mini-cups** seeded from league **110** bands: places **1–4** (g3195–98) + places **5–8** (g3191–94); each 4p KO (2 SF + 3rd-place + F). NULL witness phases; g3192/g3193 `extra` ET+pens. **Jul 2026:** materialize + manual stage names. **SC-11 verified** g3192/g3193 (forum p=106321). **Cup finish (111 only, Tier E on work):** 1 Klaus · 2 Glenn · 3 Kjetil · 4 Aasmund · 5 Oskar · 6 Jon · 7 Jens (pens) · 8 Espen. **110** = league seeding only (Tier C; no override).
- **121** Norwegian Champs → `structure_spec` — 15p; **Round 1 - Group A/B/C/D** (C 3p) + **QF Qualifiers** (×4 KO) + **Quarter Finals** (×4) · **Semi Finals** (×2) · **3rd Place Final** · **Final**; forum p=106321. **Jul 2026:** manual materialize + Qual. KO split; 16 stages / 54g; Tier E **1–4** podium · **=5** QF losers · **=9** Qual. losers · **=13** group-only.
- **134** Milan IV → `structure_spec` — 7p double-RR league (g4099–4140, 42g) + 8g playoffs (g4141–48: two-leg semis, 3rd, final); **g4141 = semi 2v3 leg 1** (not league — third Gianni–Alessandro meeting). **Jul 2026:** manual materialize; **`League`** · **`Semi Finals`** (×2, two-leg each) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianni · 2 Luigi · 3 Alessandro V · 4 Franco · 5 Filippo · 6 Sandro · 7 Diego.
- **145** Milan V → `structure_spec` — 2×4 groups (Sandro T withdrew) + variable-leg KO incl. Play Outs + placement finals; AET/pens g5159, g5178, g5180, g5187. **Jul 2026:** parser fix (Play Outs · Finals plural · Nth Place Finals); `materialize --replace`; 11 stages / 54 fixtures; **Group A/B** · **Quarter Finals** (×3) · **Semi Finals** (×2) · **Playout 5/6 - 7** (witness `Play Outs`) · **Final** (4 legs) · **3rd Place Final** · **5th Place Final**; `has_league=1` `has_cup=1`; Tier E **full ladder 1 Gianni · 2 Luigi · 3 Mario · 4 Maurizio · 5 Alessandro · 6 Jacopo · 7 Marco · 8 Sandro T** (withdrew). **SC-11 verified** all four extension games. **Four-leg Final:** legs g5177–5179 → **2–2**; g5180 pens **8–9** = **tournament-deciding shootout** (not leg-only) — **Gianni T champion** (Dagh verified Jul 2026).
- **152** Homburg II → `structure_spec` — 2×5 double-RR groups + KO incl. **Playouts 5-8** (QF-loser semis for 5th–8th) + 5th/7th/9th placement finals; German Championship 2005; forum [t=10006](https://ko-gathering.com/forum/viewtopic.php?t=10006). **Jul 2026:** cleared parser-fix block; manual materialize; **Group A** · **Group B** + full KO tree (15 stages / 66g); playout stage display **Playouts 5-8** (witness `Playouts`; 9th separate); NULL RR `phase_label`; Tier E **1 Michael O · 2 Stefan V · 3 Thomas K · 4 Christian D · 5 Sascha W · 6 Michael M · 7 Jorg P · 8 Wolf H · 9 Thorsten B · 10 Andreas K** (forum; Final agg 4–4, Michael champion).
- **156** Milan X → `structure_spec` — 10p; **Round 1 - Group A/B** (5+5 double RR) + **Quarter Finals** (×4, two-leg) · **Semi Finals** (×2) · **3rd Place Final** (3 legs) · **Final** (3 legs). **Jul 2026:** manual materialize; 10 stages / 58g; Tier E **1 Gianni T · 2 Luigi F · 3 Mario F · 4 Alessandro V · =5** (QF losers) **· =9** (group non-advancers: Fulvio O · Angelo S).
- **158** Stoke Cup → `pure_knockout` — 15p single-elim; 7 R1 ties + 1 bye (James B); phase "Round 1" not league (g5639–5652).
- **166** Milan XII → `structure_spec` — 8p double-RR league (56g) + 2-leg semis + 3-leg final (no 3rd). **Jul 2026:** manual materialize; **`League`** · **`Semi Finals`** (×2) · **`Final`** (3 legs); `has_league=1` `has_cup=1`; witness `Finals` on g5954–56 — **`phase_label` NULL** on KO fixtures so Tier B reads stage **`Final`**; finish **1 Gianni · 2 Luigi · 3 Marco/Mario · 5–8 league**.
- **171** Copenhagen Cup → `pure_knockout` — 8p from QF + full placement bracket; AET g6723 (g6715–6726).
- **173** Frankfurt → `structure_spec` — 4p double-RR league + 2-leg semis/3rd/final (g6781–88). **Jul 2026:** cleared `NON_WC_SLICE6_CUP_REVIEW_IDS`; legacy materialize on `ko2amiga_work`; RR stage `round-1` display name **`League`** (witness `g.phase` stays `Round 1`).
- **604** Groningen VII Cup → `pure_knockout` — 8p single-elim, 2-leg ties; import split from id **48**. **Jul 2026:** `materialize-pure-knockout --replace`; stage names **`Quarter Finals`** / **`Semi Finals`** / **`Final`** (witness `Round 1` / `Semi Final` unchanged).
- **174** London Marathon → `pure_rr` — 22p near-complete single RR (230/231g); James L–Vagelis D unplayed.
- **176** Milan XIV → `structure_spec` — 6p double-RR league (g7104–33) + 2-leg semis/3rd/final (g7134–41). **Jul 2026:** cleared `NON_WC_SLICE6_CUP_REVIEW_IDS`; manual materialize; **`League`** · **`Semi Finals`** (×2) · **`3rd Place Final`** · **`Final`**; `has_league=1` `has_cup=1`; Tier B finish 1 Gianni · 2 Luigi · 3 Marco · 4 Alessandro V · 5 Maurizio · 6 Sandro.
- **187** Hertford IV — split **deferred** (24g league + 4g cup; forum t=12376; cup g7579, 7567, 7563, 7582); stays `pending_review`.
- **189** Manchester II Cup → `pure_knockout` — 15p single-elim; 7 R1 + bye (Steve C); pens g7689, 7692; league on id 188.
- **192** Hertford V Cup → `pure_knockout` — 4p; 2-leg semis (phase "Round 1") + 2-leg 3rd/final (g7768–775); league on id 191.
- **198** Milan XVII → `structure_spec` — 7p 2 groups (4+3) double-RR + KO incl. Playouts 5-7 + placement finals (g7949–7984). **Jul 2026:** parser fix (`Playouts 5-7` = KO); manual materialize + **Group A/B split** (`round-1-group-a/b`); NULL RR `phase_label` so standings scopes split; 11 stages / 36g; Tier B finish **1 Luigi · 2 Gianni · 3 Marco · 4 Mario · 5 Alessandro · 6 Fabio · 7 Maurizio**.
- **214** Milan XVIII → `pure_rr` — 8p near-complete double RR (50/56g); Luigi F & Gabriele B left early (Maurizio L delay).
- **215** Kelkheim VII → `structure_spec` — 12p single-RR league (66g) + 2-leg KO incl. Places 5-8 + placement finals; AET g8924 (`4-4 a.e.t` = post-ET leg total). **Jul 2026:** materialize 13 stages / 90g; **SC-11 verified g8924** (`goals_et` 0-1); Tier B finish **1 Michael O · 2 Stefan V** (agg 9-8).
- **248** Athens XXXVIII Cup → `pure_knockout` — 7p cup; variable-leg semis + AET final g10166; league on id 247.
- **267** Seeshaupt → `structure_spec` — 5p single-RR league + Game of Shame + final; AET g10546. **Jul 2026:** parser fix (`Game of Shame` = KO); cleared parser-fix block; manual materialize; **`League`** · **`Game of Shame`** · **`Final`**; SC-11 verified g10546; Tier E **1 Thorsten · 2 Robert · 3 Eric · 4 Thomas · 5 Norbert**.
- **269** Cologne I → `structure_spec` — 25p multi-group RR + Playouts + R2 + placement KO through 24th (g10578–10822).
- **276** Langenfeld → `structure_spec` — 8p single-RR league (28g) + 2-leg KO incl. Places 5-8 + placement finals. **Jul 2026:** cleared slice-6 cup review; `materialize --replace`; 13 stages / 52 fixtures; **League** · **Quarter Finals** (×4) · **Places 5-8** (×2) · **7th/5th Place Final** · **Semi Finals** · **3rd Place Final** · **Final**; `has_league=1` `has_cup=1`; Tier B finish 1 Oliver · 2 Sascha · 3 Frederic · 4 Volker · 5 Uli · 6 Malte · 7 Thomas · 8 Guido.
- **281** Athens L → `pure_rr` — 7p near-complete single RR (19/21g); Nikos Al missing 2 pairings.
- **284** Athens LIII → `structure_spec` — 2×7 groups + Playouts/Playoffs groups + KO; AET g11635–36.
- **535** Birmingham XXXVII → `pure_knockout` — 4p cup; 2× **Semi Finals** (witness `Round 1`) + **Final**; g20217 reg 0-0, ET 0-0, pens 1-0 (Gary T); Access `(0-0) 1-0 p.k.`. **Jul 2026:** cleared cup-review block; `materialize-pure-knockout`; 3 stages / 3g; `has_league=0` `has_cup=1`; Tier E finish **1 Brian C · 2 Gary T · 3 Glen H · 3 John M**.
- **568** Birmingham XLV → `pure_knockout` — 4p cup; **Semi Finals** (×2) · **3rd Place Final** · **Final**; g21621 reg 5-5, ET 2-2, pens 8-7 (Steve E champion); Access `(7-7) 8-7 p.k.`. **Jul 2026:** `materialize-pure-knockout`; 4 stages / 4g; `has_league=0` `has_cup=1`; Tier B finish **1 Steve E · 2 Garry C · 3 Simon K · 4 Todd H**.
- **500** Birmingham XXVIII → `pure_knockout` — 6p cup, QF bye (Tom P); **Quarter Finals** (×2) · **Semi Finals** (×2) · **Final**; no ET/pens. **Jul 2026:** `materialize-pure-knockout`; 5 stages / 5g; `has_league=0` `has_cup=1`; Tier E finish **1 Simon K · 2 Steve E · 3 Garry C · 3 Tom P · 5 John M · 5 Thomas J** (QF losers tied at 5).
- **493** Birmingham XXVII → `pure_knockout` — 6p cup, SF bye (Rick S); witness **Round 1** → display **Quarter Finals**; **Semi Finals** (×2) · **Final**; 5g. **Jul 2026:** `materialize-pure-knockout`; 5 stages / 5g; `has_league=0` `has_cup=1`; Tier E finish **1 Jon G · 2 Steve E · 3 Rick S · 3 Tom P · 5 Dan N · 5 Garry C**.
- **519** Birmingham XXXIII → `pure_knockout` — 6p cup, SF bye (Tom P); witness **Round 1** → display **Quarter Finals**; **Semi Finals** (×2) · **Final**; 5g. **Jul 2026:** `materialize-pure-knockout`; 5 stages / 5g; `has_league=0` `has_cup=1`; Tier E finish **1 Garry C · 2 Simon K · 3 Steve E · 3 Tom P · 5 Brian C · 5 John M**.
- **414** Birmingham XIV Silver Cup → `pure_knockout` — 6p; **3× Quarter Finals** · **1 Semi Final** · **Final**; 5g. **Jul 2026:** `materialize-pure-knockout`; 5 stages / 5g; `has_league=0` `has_cup=1`; Tier E finish **1 Mandhir S · 2 Andy H · 3 Graham S · 4 Andy E · 4 Grant N · 4 Ren A** (SF loser = 3rd).
- **452** Birmingham XXI Gold Cup → `pure_knockout` — 6p; **Quarter Finals** (×2) · **Semi Finals** (×2) · **Final**; 5g. **Jul 2026:** `materialize-pure-knockout`; 5 stages / 5g; `has_league=0` `has_cup=1`; Tier E finish **1 Jon G · 2 Steve E · 3 Simon K · 3 Steve C · 5 Garry C · 5 Robert S**.
- **544** Bournemouth II → `pure_knockout` — 7p; **3× Quarter Finals** · **2× Semi Finals** · **Final**; SF bye Mark W; g20404 ET 1-0 (Garry C). **Jul 2026:** `materialize-pure-knockout`; 6 stages / 6g; `has_league=0` `has_cup=1`; Tier E finish **1 Garry C · 2 Dagh N · 3 Andy G · 3 Mark W · 5 Simon K · 5 Steve C · 5 Steve E**.
- **317** Birmingham VIII Silver Cup → `pure_knockout` — 7p; **3× Quarter Finals** · **2× Semi Finals** · **Final**; SF bye Garry C. **Jul 2026:** `materialize-pure-knockout`; 6 stages / 6g; `has_league=0` `has_cup=1`; Tier E finish **1 Garry C · 2 Mandhir S · 3 Greg N · 3 John W · 5 Mark W · 5 Darren G · 5 Nick H**.
- **316** Birmingham VIII Gold Cup → `pure_knockout` — 8p; **4× Quarter Finals** · **2× Semi Finals** · **Final**; 7g. **Jul 2026:** `materialize-pure-knockout`; 7 stages / 7g; `has_league=0` `has_cup=1`; Tier E finish **1 Jon G · 2 Wayne L · 3 Steve E · 3 Andy E · 5 Andy H · 5 James B · 5 Steve C · 5 Robert S**.
- **524** Birmingham XXXV → `pure_knockout` — 4p two-leg cup; **Semi Finals** (×2) · **3rd Place Final** · **Final**; 4 stages / 8g. **Jul 2026:** `materialize-pure-knockout`; `has_league=0` `has_cup=1`; Tier B finish **1 Steve E · 2 Jon G · 3 Garry C · 4 Simon K**.
- **503** Leicester I → `pure_knockout` — 10p; **Qualifying Round** (×2) · **Quarter Finals** (×4) · **Semi Finals** (×2) · **Final**; g18980/81/83 pens after ET 0-0. **Jul 2026:** `materialize-pure-knockout`; 9 stages / 9g; `has_league=0` `has_cup=1`; Tier E finish **1 Gordon S · 2 Simon H · 3 Gary T · 3 Matt B · 5 Mark Bp · 5 John M · 5 Arthur V · 5 Simon Bu · 9 Stuart S · 9 James R**.
- **463** Dudley XX Cup → `pure_knockout` — 4p; witness **Round 1** (6g double RR) → display **League** · **Semi Finals** (×2) · **3rd Place Final** · **Final**; 10g. **Jul 2026:** `materialize-pure-knockout`; `has_league=1` `has_cup=1`; Tier E finish **1 Steve E · 2 Garry C · 3 Simon K · 4 Kostas O** (Final + 3rd-place podium).
- **471** Seeshaupt IV → `pure_knockout` — 6p; **Round 1 - Group A/B** (2×3p double RR) · **Semi Finals** (×2) · **5th Place Final** · **3rd Place Final** · **Final**; g17737 ET 1-0; g17739 pens 3-0. **Jul 2026:** `materialize-pure-knockout`; 11 stages / 11g; `has_league=1` `has_cup=1`; Tier E finish **1 Thorsten B · 2 Markus B · 3 Norbert K · 4 Andreas M · 5 Herbert K · 6 Martin E** (5th-place final g17738: Herbert d. Martin).
- **329** Athens LXI Cup → `pure_knockout` — 7p two-leg; **Round 1** (×3) · **Semi Finals** (×2) · **3rd Place Final** · **Final**; SF bye Kostas O; g13079 reg 3-3, post-ET 7-6 (ET 4-3). **Jul 2026:** `materialize-pure-knockout`; 7 stages / 12g; `has_league=0` `has_cup=1`; Tier E finish **1 Alkis P · 2 Panayotis P · 3 Ektoras K · 4 Kostas O · =5 George Ka / Kostas K / Kostas Ka**.
- **341** Copenhagen III Cup → `pure_knockout` — 12p; witness **Round 1** → **QF Qualifiers** (×4) · **Quarter Finals** (×4) · **Semi Finals** (×2) · **3rd Place Final** · **Final**; g13426 AET 0-1; g13432 pens 12-13. **Jul 2026:** `materialize-pure-knockout`; 12 stages / 12g; `has_league=0` `has_cup=1`; Tier E finish **1 John H · 2 Dagh N · 3 Nick P · 4 Claus H · =5** (QF losers: Jacob K / Torgny A / Niki B / Dennis N) **· =9** (QF Qual. losers: Jonas S / Henrik R / Finn R / Mattias E).
- **338** Seeshaupt II → `pure_knockout` — 6p; witness **Round 1** (15g single RR) → display **League** · **5th Place Final** · **3rd Place Final** · **Final**; 4 stages / 18g. **Jul 2026:** `materialize-pure-knockout` + RR stage merge; `has_league=1` `has_cup=1`; Tier E finish **1 Thorsten B · 2 Robert St · 3 Markus B · 4 Andreas M · 5 Herbert K · 6 Norbert K**.

---

## Superseded starters

- [`amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md`](orchestration/agent-handoffs/amiga-tournament-disposition-REVIEW-STARTER-PROMPT.md) — **use this**
- [`amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-CUP-REVIEW-STARTER-PROMPT.md) — cup subset; merged into disposition review
- [`amiga-tournament-structure-REVIEW-STARTER-PROMPT.md`](archive/orchestration/agent-handoffs/amiga-tournament-structure-REVIEW-STARTER-PROMPT.md) — old non-WC queue
