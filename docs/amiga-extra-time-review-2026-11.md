# Amiga extra time: World Cup review, November 2026

**Purpose:** what we found when we checked extra-time and penalty results against other sources, what we changed and left alone, why, how strong the evidence was, and what we believe about the sources.

**Decided by Dagh, 2026-11-22 and 2026-11-23.** The row-by-row register of every Access correction is [`amiga-l3-legacy-fixes-inventory.md`](amiga-l3-legacy-fixes-inventory.md); these rows are its §5.2. Placings have their own rules: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md).

---

## 1. Summary

- Access is very reliable for normal scores and much weaker for extra time: 15 of the 80 World Cup matches known to have gone past normal time had a wrong, misplaced or missing note.
- **Ten matches corrected on 2026-11-22** (nine extra-time notes and one normal-time score) and **five more on 2026-11-23**: XXI 5th and 11th place and XI 19th place and places 25-32, all extra time, and a World Cup IV group score that was turned round. In all fifteen the final placings were already right and stay as they were.
- **Two old matches left as they are in Access**, closed by Dagh: VIII 15th place and VI places 41-44 (§6).
- The same day, three placings and one bronze medal were corrected for the opposite reason: there the results were right and the placings wrong (§7).
- **Second check, 2026-11-23** (§12): the first ten corrections and the placings hold, nine of the ten with a second witness. It led to the five new ones.

## 2. Our convention

| Field | Meaning |
|---|---|
| `goals_a`, `goals_b` | Normal time only. Elo, goals and all statistics use these |
| `goals_et_a`, `goals_et_b` | Goals scored in extra time only |
| `pens_a`, `pens_b` | Penalty shootout |
| `extra` | The text shown on the site: the score of that leg **after** extra time, in the row's player order |

Write the text as `3-4 e.t.`, or as `(8-6) 12-11 p.k.` when penalties follow. The bracket is the score after extra time, also when extra time was goalless. Check: normal time + extra-time goals = the score in the text.

Alkis's pages (alkis.org) use `a.e.t.` and put the score under the second leg in that leg's player order. Two things to watch when copying:

- The leg order or the player order there can differ from our row. Turn the score round if it does.
- In a two-leg tie the score belongs to the second leg, not to the tie: 2-3 then `2-4 e.t.` means one extra-time goal, whatever happened in the first leg.

`test_world_cup_extra_time_notes_follow_the_convention` in `scripts/amiga/test_import_corrections.py` enforces this for the corrected rows. Games whose extra time a person has checked are listed in `scripts/amiga/match_extensions_verified_register.json`.

## 3. What we believe about the sources

The truth is the game on the Amiga. Everything else is a copy of it.

| Source | What it is | Trust | Why |
|---|---|---|---|
| Video | The Amiga screen itself | Decides | Only a few of these matches were filmed |
| Access `Scores`, normal time | Imported in bulk from result sheets: Access still holds an import error log for an Excel sheet named "Scores" | Very high | In World Cups I to XXIII (about 8,270 games), 816 of 840 player totals match our games exactly (804 before the corrections). The rest are slips in the totals or counted in another way (§8) |
| Access `Scores.Extra` | Extra-time text | Moderate | Looks typed by hand: 108 notes in 12 different spellings. 15 of 80 World Cup matches wrong, misplaced or missing |
| Results images on alkis.org | Pictures of the original result sheets | High, not perfect | Agree with 63 of the 69 Access notes we could compare, and in the other six Access contradicts itself. Known slips: XIV 5th place (§8); XXI 5th place has no extra-time mark although Access has the final level (§6) |
| Placings: Access `[Tables]` and the alkis.org ranking lists | Made after the event, and they agree with each other | Good, not proof | Wrong in three placement finals (§7) |
| Player comments on alkis.org | Written by Alkis after the event | Good supporting evidence for what happened; loose with numbers | They describe matches independently of the score tables |
| The tournament software's results, <https://kickoff2.com/KO2TS/index.php?player_filter=&tn=worldcup2023> (by Steve C; not there for 2011) | What was typed in at the tournament | Very high | Its game list shows the score **after** extra time, plus the goals of a penalty shootout (§13); its totals count normal time. Our 304 XXI games match its list, and 312 of 314 XXII games |
| Official KOA rankings (Access `Rankings`, monthly, frozen) | Calculated at the time from the game list | Good where a game counts for a lot | Found by rebuilding the formula roughly (§12) |

**How a score after extra time gets into a leg:** Access's XXI game list is the software's list, with the extra-time goals taken out by hand in the matches somebody remembered. In the 11th place final nobody did.

Two limits of this evidence:

- The player totals and the games probably come from the same result sheets. The totals prove that the copying was faithful, not that the sheets were right.
- alkis.org and Access are not independent of each other either. When they agree, that is weaker than two separate witnesses. When they disagree, at least one was copied wrongly.

## 4. How we checked

1. **Every note:** 74 World Cup notes in Access. Three are the unexplained `WG` marks, and two have no image on alkis.org (XVI 9th place final, XVII 23rd place final). The other 69 were compared with the images one by one.
2. **Level ties without a note:** seven World Cup ties cannot have ended level.
3. **Inside Access:** does the winner play the next round? Is the winner placed above the loser? Is the score possible at all?
4. **Player totals** per World Cup against the games.
5. **Videos** of two of the matches.

`scripts/oneoff/amiga_extra_time_audit.py` repeats steps 2 to 4 (read-only).

**Not checked:** the 34 notes outside World Cups (alkis.org covers World Cups only), and normal-time scores one by one.

**Rule we used:** Access stands unless it contradicts itself or a video. Only then does alkis.org supply the score.

## 5. The corrections

Evidence grades: **A** seen on the Amiga screen, or a player's word backed by the tournament software · **B** Access's version is impossible or contradicts Access itself · **C** Access is silent about a tie that cannot have ended level, and its own placings show the winner.

| Match | Access | Now | Grade | Evidence |
|---|---|---|---|---|
| **XXI 17th place**, Frederic G v Robert S | 3-3, `4-3 e.t.` | `3-4 e.t.` | A | [Video](https://www.youtube.com/watch?v=scSdq-IQXIE&t=980s), end screen: Fred 3, Robert 4, 2nd leg 3-3, extra time 0-1, winner Robert. Alkis's comment also has Robert S coming back from 0-3. In Access the two players are the other way round from alkis.org, but the score was not turned |
| **XVII quarter-final**, Christopher D v Thor S | no note | `(8-6) 12-11 p.k.` | B | The note sat on Robert S v Frank F (next row). Here the tie is level 10-10, 8-6 keeps it level, and Christopher D played the semi-final |
| **XVII places 9-16**, Robert S v Frank F | `(8-6) 12-11 p.k.` | no note | B | Same correction. This tie was decided 5-4 in normal time, so there was no extra time |
| **XVII places 9-12**, John H v Marco D | `3-3 e.t.` (John H through) | `2-4 e.t.` | B | In Access, Marco D plays the 9th place final and John H the 11th. Exact score from alkis.org |
| **XV 29th place**, Luke D v Olly D | `(2-1) 4-5 p.k.` | `(1-2) 4-5 p.k.` | B | Olly D had 2 goals in normal time and cannot have 1 after extra time. alkis.org has 1-2. Access's totals have the second leg as 2-1, but that tie would end 3-1 and need no penalties |
| **VII places 25-28**, Gunther W v Lorenzo C | 3-3, `3-5 e.t.` | 0-2 | B | Access's own totals for both players fit 0-2 and nothing else. Same on alkis.org. **Changes Elo** |
| **X places 25-28**, Flavio Z v Kostas Ka | no note | `(3-5) 4-5 p.k.` | C | Level 6-6; Kostas Ka played on for 25th. Exact score from alkis.org |
| **X 27th place**, Flavio Z v Thorsten B | no note | `6-5 e.t.` | C | Level 7-7; Flavio Z placed 27th. Exact score from alkis.org |
| **X 25th place**, Kostas Ka v Klaus Le | no note | `4-6 e.t.` | C | Level 8-8; Kostas Ka placed 25th. Exact score from alkis.org |
| **X 17th place**, Rodolfo M v Lorenzo L | no note | `4-6 e.t.` | C | Level 4-4; Lorenzo L placed 17th. [Video](https://www.youtube.com/watch?v=vLaFZAHJXx8&t=860s) goes to extra time with Rodolfo M on 4 and Lorenzo L ahead; the blurred figure reads as 6 |
| **XXI 29th place**, Cornelius H v Michele L | no note | `5-2 e.t.` | C | Level 7-7; Cornelius H placed 29th. Exact score from alkis.org, where this game is the second leg |

| **XXI 5th place**, Christopher D v Mario F (2026-11-23) | 6-5, no note | `6-8 e.t.` | A | Level 10-10; Mario F placed 5th. Steve C and the tournament software (§6) |
| **XXI 11th place**, Frank F v Steve C (2026-11-23) | 2-9, no note | 2-5, `2-9 e.t.` | A | Steve C, who played it: 2-5 twice, then four goals in extra time. The software's totals and Access's count 2-5. Elo unchanged |
| **XI 19th place**, Wayne L v Mandhir S (2026-11-23) | 5-2, no note | 2-2, `5-2 e.t.` | B | Totals count 2-2 and a win for Wayne L (§12). **Changes Elo** |
| **XI places 25-32**, Mark W v Peter K (2026-11-23) | 1-2, no note | 1-1, `1-2 e.t.` | B | Totals count 1-1 and a win for Peter K (§12). **Changes Elo** |
| **IV Round 1 Group B**, Nazim C v Gianni T (2026-11-23) | 1-7 | 7-1 | B | §12. **Changes Elo** |

The table has sixteen rows because the XVII note moves from one match to another.

**What rests on alkis.org alone:** the exact extra-time scores of XVII places 9-12, XXI 29th and three of the World Cup X matches. The winners do not depend on it, and extra-time goals do not count for Elo.

**Effect of the first ten:** only the World Cup VII game changes Elo, because it goes from a draw to a 2-0 win for Lorenzo C. It is an early game (2007), so small differences run on through later games: 285 current ratings change, 28 by 0.05 or more and 7 by 0.5 or more (largest: Stef K -1.4, Jan T -1.3, Niels T -1.1), and 22 places in the ranking move by one or two. No placing, medal or finish override changes; of the 27,757 games only the eleven rows above differ. Full rebuild and all 29 checks passed on 2026-11-22.

**Effect of the five of 2026-11-23:** three change Elo (IV and the two XI games). 13 current ratings change by 0.5 or more: Wayne L -10.0, Nazim C +8.0, Mandhir S +3.5, Simon B +2.0, Fulvio O -1.5, the rest by 1.2 or less; Gianni T +0.1. 54 ranking places move, no placing changes, and only these five games differ. Full rebuild and all 29 checks passed on 2026-11-23.

## 6. The matches left after the first ten

### XXI 5th place, Christopher D v Mario F: confirmed and corrected

| Source | Leg 1 | Leg 2 | Elsewhere |
|---|---|---|---|
| Access | Christopher D 6-5 Mario F | Mario F 5-4 Christopher D | Level 10-10, no note. Totals: one win each |
| alkis.org image | Christopher D 4-5 Mario F | Mario F **8-6** Christopher D | Ranking list: one win each, and three more goals for Mario F than in Access |

Dagh's reading: every recorded number is right, and Mario F won extra time 3-0. Confirmed on 2026-11-23: Steve C has 5-4, then 6-5 after normal time and 6-8 after extra time, and the tournament software shows Christopher D 6-8 Mario F while counting 6-5 in its totals. (Our first reading, 4-8, and the later idea of a plain 8-6 were both wrong.) Correction: game 26309, `6-8 e.t.`, extra-time goals 0-3.

### Two old matches: closed

Dagh, 2026-11-22: too old to spend more time on. They stay as in Access and nobody needs to be asked. Reopen only if a source turns up by itself.

| Match | What the sources say | What we believe |
|---|---|---|
| **VIII 15th place**, Alessandro V v Knut L (game 14230) | Access: `4-5 e.t.`, so Knut L wins 9-8 on aggregate. alkis.org: 4-7, so Alessandro V wins 10-9. Access's placing and the alkis.org ranking list both have Alessandro V 15th | Most likely 4-7. The 17th place final 14 rows earlier in Access has the same `4-5 e.t.` (there the video's title agrees), so it may have been copied down by mistake. But no video or later match proves it. The Stages page names Knut L; the placing is Alessandro V |
| **VI places 41-44**, Haakon E v Peter S (game 8577) | Access: 3-4 in normal time. alkis.org: 3-3, then 3-4 in extra time | Probably alkis.org: an extra-time line is easy to lose and hard to invent. Peter S wins either way, but 3-3 would change Elo |

## 7. Placings corrected the same day

Here the results were right and the placing was wrong. Dagh's rule: the result decides.

| Event | Now | Evidence |
|---|---|---|
| **XXIII Milan 2025, 25th** | Klaus Le 25th, Mick C 26th | Access and alkis.org have the same scores (3-2, 3-1). Alkis's comment says Mick C lost to Klaus Le, and they met only in this final. Both ranking lists have them the other way round |
| **XXIII Milan 2025, 27th** | Paolo A 27th, Matthias K 28th | Same scores in both (6-2, 3-2). Alkis's comment gives Paolo A seven wins, which needs both legs |
| **XIX Bremen 2019, 11th** | Frank F 11th, Alessandro V 12th | Same scores in both (5-6, 4-2: Frank F 9-8). **Only the result supports this one**: both ranking lists have Alessandro V 11th |
| **XII Milan 2012, bronze** | Gianni T, not Andy G | Our own fault, not Access's: the medal table ignored extra time. alkis.org: 5-5, then 7-6 |

**Lesson:** "the result decides" needs a check that the result itself is right. VIII 15th and XXI 17th first looked like wrong placings, and swapping them was proposed. Comparing with alkis.org showed that the extra-time notes were wrong and the placings right.

## 8. Looked at and not changed

- **XIV 5th place**, John H v Gianluca T: the alkis.org image shows two legs that are not level (3-4, 2-3) followed by extra time. Access (3-2 and 3-4, then 4-4) is level and gives John H 5th, as both sources say, and the ranking list now on alkis.org has Access's goals. Legs of 4-3 and 2-3 would fit too, with the same winner and the same totals. The official ranking of December 2014 fits Access's legs. Access stands.
- **IV KOA Cup:** some winners are placed below the player they beat. Access leaves the KOA Cup out of the World Cup IV placings, so this is not an error.
- **XX "31st Place Final", Hannes B 2-2 Manfred S:** this is the Game of Shame with the wrong phase label in Access. The score is right.
- **The 24 player totals that still differ:** walkovers counted as games (XXI, Ian K and Fabien S); goals of two second legs that went to extra time left out (X, three players); five Bronze Cup games left out (XXIII, four players); XV 29th place (§5); a penalty win counted as a win (XI, Wayne L and Thorsten B); one leg turned round (XIV, John H and Gianluca T), since put right on alkis.org; the rest are slips (I, III, V, VIII, XIV).
- **Tomasso R = Lorenzo C:** alkis.org lists Tomasso R in World Cups VII and X where Access has Lorenzo C. The placings (26th and 21st) and the VII totals match, so we treat them as one person, as Access does.

## 9. Where the corrections live

- The first ten: on live since 2026-11-22 (Amiga push verified, all tables identical to `ko2amiga_work`). The five of 2026-11-23: on live the same day (push verified MATCH).
- `SCORE_CORRECTIONS` in `scripts/amiga/import_corrections.py`: the values and the reasons, used by any Access import.
- `scripts/oneoff/amiga_fix_world_cup_extra_time_2026_11.py`: applies them to `ko2amiga_work` and refuses any row it does not recognise. Run it after restoring an older checkpoint, then simul.
- Placings: `scripts/oneoff/amiga_fix_placement_overrides_2026_11.py`. Scoring chain: `scripts/oneoff/amiga_add_extra_time_step_2026_11.py` (§10).

## 10. The winner of a level tie

Until this review, a tie that was level on goals and decided in extra time could go to the wrong player on the Stages page and in the stored stage tables: the old catalog chain (goals, then penalties) had no extra-time step, so it fell back to whoever won the leg that carries the note. The right notes alone would not have helped; in three of the corrected finals the page still named the loser.

Fixed the same day: `extra_time` now comes before `penalty_shootout` in `LEGACY_KNOCKOUT_BRIDGE_STEPS` (Python and PHP) and in the 94 stages that store the old chain (`scripts/oneoff/amiga_add_extra_time_step_2026_11.py`), and the Stages page uses the extra-time and penalty columns. Of 1,337 ties, 28 change winner on the page. 27 of them now agree with the final placing, among them the XVIII semi-final (Fabio F), the XII bronze match (Gianni T) and the Kelkheim VII final (Michael O). The 28th is VIII 15th place, where the page now follows Access's unconfirmed note and names Knut L (§6). No rating changes. One placing does: in Birmingham XL (2012, four players) Darren H lost a semi-final 0-1 in extra time and was placed 5th; now 3rd, shared with the other losing semi-finalist, with a bronze. Full rebuild and all 29 checks passed again.

## 11. Known and not fixed

- **Score entry cannot record extra time.** The organizer form has no field for it. The PHP parser behind it would also store the score after extra time as extra-time goals, and "Make official" does not copy the extra-time columns to the official game. Deferred by Dagh on 2026-11-22; tracked in [`amiga-organizer-track-status.md`](amiga-organizer-track-status.md) §6.

## 12. Second check, 2026-11-23

XXI 5th place taught us three things: a leg in a source can hold the score after extra time or a later correction; totals on alkis.org are partly corrected by hand, so they prove less than we thought; and a difference in totals that would make a tie exactly level is a warning sign. We went through everything again with that in mind.

**What we checked**

1. **Pictures, both ways:** all 74 extra-time or penalty lines in the knockout pictures of World Cups III to XXIII. 72 are the same in our data. The other two are the old matches closed in §6.
2. **Alkis's player comments:** about 40 mention extra time or penalties. Each names a match we have, with the same winner. (One contradicts the rest: a XVI comment has the XV quarter-final Andy G v Fabio F decided on penalties; the XV comment, the picture and Access all have extra time.)
3. **Goals per World Cup** on alkis.org (its table counts extra time in some years and not in others) against ours: equal, or explained by a known slip, except XXI (the three goals of §6).
4. **Player totals**, now also for II to IV: does a difference make a knockout tie level?

**The ten corrections hold.** Second witnesses found for nine:

| Match | Second witness |
|---|---|
| XXI 17th | Comment on Frederic G: lost on overtime |
| XVII quarter-final (and so Robert S v Frank F) | Comment on Thor S: knocked out by Christopher D on penalties |
| XVII places 9-12 | Comment on Marco D: knocked out Lee W, John H and Frank F |
| XV 29th | Comment on Olly D: beat his brother on penalty kicks |
| VII places 25-28 | alkis.org counts 2,150 goals. Ours are 2,150 with 0-2 and would be 2,156 with Access's 3-3 |
| X places 25-28 and X 25th | Comment on Kostas Ka: won one playoff match on penalties and one on extra time |
| X 17th | Comment on Rodolfo M: lost the trophy on extra time |
| X 27th | The totals count the leg Flavio Z lost 4-5 as a win for Flavio Z, which only extra time explains |
| XXI 29th | None beyond the picture. The tie is level and cannot have ended so |

**The placings hold too** (§7). XII bronze: the comment on Andy G says the bronze was lost in extra time. XIX 11th is still the weakest, but extra time cannot explain it: if the second leg's 4-2 includes extra time, it was Frank F who won it, and both players' totals match our games exactly.

**World Cup IV, Round 1 Group B, Nazim C v Gianni T** (game 4651, Access 4652): **decided by Dagh as 7-1 on 2026-11-23.** Access's game list has 1-7; everything else from 2004 fits 7-1.

| | Gianni T won 7-1 (as now) | Nazim C won 7-1 |
|---|---|---|
| Records | Access's game list only | alkis.org: the group's results picture, the group table beside it (27 and 24 points) and the ranking list. Access: its own copy of the group table, and its totals. All of these may come from one sheet, so they count as one witness |
| Official KOA ranking, December 2004 (Access `Rankings`) | Would give Gianni T about +125 and Nazim C about 0 | Would give about +35 and +85. Listed: **+45 and +67**. Formula rebuilt from the other 41 players; of 362 games turned round one by one, the four that improve the fit most are all Nazim C's: the two against Gianni T and the two against Nikos A |
| The football | Gianni T had won 5-1 at home the same day, and won 10-0 in 2007. Between players of similar rating, 89 who won one leg by 3 to 5 goals also won the other by 5 to 7; 28 lost it by as much | |

So in 2004 the result counted as 7-1 to Nazim C. What we cannot tell is why the game list differs: a slip when it was put together later, or a correction somebody made there afterwards. Corrections in the game list alone do exist: XIV 5th place and the XIV 12-2 are right there and wrong in the sheet, and the December 2014 ranking already follows the game list for the first. That is also why the rule in §4 is not enough here. Dagh's view of the football: Nazim C scored a lot, and Gianni T was not yet dominant and played an open 4-2-4. Gianni T has been asked; if he is sure that he won, change it back. It changes Elo, not the group order or the placings.

**Matches with the same warning sign as XXI 5th place** (player totals lower by exactly what would make the match level)

| Match | Access and picture | Totals fit | Result |
|---|---|---|---|
| XXI 11th place, second leg | Frank F 2-9 Steve C | 2-5, so level 7-7 | Extra time, confirmed by Steve C |
| XI 19th place | Wayne L 5-2 Mandhir S | 2-2 | Extra time, decided by Dagh |
| XI places 25-32 | Mark W 1-2 Peter K | 1-1 | Extra time, decided by Dagh |

All three are corrected (§5). Nobody has confirmed the two World Cup XI games: they rest on the totals alone (Access and alkis.org have the same numbers, so that is one witness). What the totals show is exact: Wayne L scored 114, not 117; Mandhir S conceded 98, not 101; Peter K scored 46, not 47; Mark W conceded 88, not 89. Both players of each pair are off by the same number, Mark W and Peter K met only once, and Access's group tables match the group games, so the difference is in these two games. Two separate slips that both leave a match exactly level are hard to believe. World Cup XI counts goals in normal time and wins after extra time and penalties (Wayne L v Thorsten B: 3-3 and a win), which is how these two look; and in XXI the same sign was right both times, also without a mark in the picture. Steve C's page does not go back to 2011, and the KOA ranking of December 2011 cannot tell.

**What no check can see:** a match whose score after extra time went into every source as a normal score.

## 13. World Cup XXII: two shootouts recorded as extra time (2026-11-23)

Dagh asked why Lorenzo L, not Thor S, went on to the 5th place final after their 3-3 in Positions 5-8. Access's note `4-5 a.e.t.` made it extra time (one goal for Thor S, two for Lorenzo L). The tournament software (tn=worldcup2024) has **7-8**, and for the 5th place final, where Access has 2-2 and `3-2 a.e.t.`, it has **5-4**. All its other 312 games of XXII match ours.

The software adds shootout goals to the score: our XXIII semi-final Dagh N v Fabio F (4-4 after extra time, 5-3 on penalties) is 9-7 there, and the final (6-7 after extra time, 6-7 on penalties) is 12-14. Read that way both XXII games fit exactly: 3-3 and a 4-5 shootout, 2-2 and a 3-2 shootout. With extra time as Access has it, the software would show 4-5 and 3-2. (A 1-1 extra time and a 3-4 shootout would also give 7-8, but Access's numbers match the shootout exactly.)

Corrected to `(3-3) 4-5 p.k.` and `(2-2) 3-2 p.k.` (§5 of the inventory, `SCORE_CORRECTIONS`). Elo, the winners and the placings do not change; the extra-time goal counts do.
