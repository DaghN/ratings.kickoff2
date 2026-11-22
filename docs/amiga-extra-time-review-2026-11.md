# Amiga extra time: World Cup review, November 2026

**Purpose:** what we found when we checked extra-time and penalty results against other sources, what we changed and left alone, why, how strong the evidence was, and what we believe about the sources.

**Decided by Dagh, 2026-11-22.** The row-by-row register of every Access correction is [`amiga-l3-legacy-fixes-inventory.md`](amiga-l3-legacy-fixes-inventory.md); these rows are its §5.2. Placings have their own rules: [`amiga-tournament-honours-rules.md`](amiga-tournament-honours-rules.md).

---

## 1. Summary

- Access is very reliable for normal scores and much weaker for extra time: 12 of the 77 World Cup matches that went past normal time had a wrong, misplaced or missing note.
- **Ten matches corrected** (nine extra-time notes and one normal-time score). In all ten the final placings were already right and stay as they were.
- **One match still to correct** with the next Amiga data push: XXI 5th place (§6).
- **Two old matches left as they are in Access**, closed by Dagh: VIII 15th place and VI places 41-44 (§6).
- The same day, three placings and one bronze medal were corrected for the opposite reason: there the results were right and the placings wrong (§7).

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
| Access `Scores`, normal time | Imported in bulk from result sheets: Access still holds an import error log for an Excel sheet named "Scores" | Very high | In the 20 World Cups with full tables (about 7,450 games), 713 of 741 player totals match our games exactly (708 before the corrections). Of the mismatches we followed up, one was a wrong Access score (World Cup VII); the rest are slips in the totals (§8) |
| Access `Scores.Extra` | Extra-time text | Moderate | Looks typed by hand: 108 notes in 12 different spellings. 12 of 77 World Cup matches wrong, misplaced or missing |
| Results images on alkis.org | Pictures of the original result sheets | High, not perfect | Agree with 63 of the 69 Access notes we could compare, and in the other six Access contradicts itself. Known slips: XIV 5th place (§8); XXI 5th place shows the score after extra time in the place of the second leg (§6) |
| Placings: Access `[Tables]` and the alkis.org ranking lists | Made after the event, and they agree with each other | Good, not proof | Wrong in three placement finals (§7) |
| Player comments on alkis.org | Written by Alkis after the event | Good supporting evidence | They describe matches independently of the score tables |

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

## 5. The ten corrections

Evidence grades: **A** seen on the Amiga screen · **B** Access's version is impossible or contradicts Access itself · **C** Access is silent about a tie that cannot have ended level, and its own placings show the winner.

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

The table has eleven rows because the XVII note moves from one match to another.

**What rests on alkis.org alone:** the exact extra-time scores of XVII places 9-12, XXI 29th and three of the World Cup X matches. The winners do not depend on it, and extra-time goals do not count for Elo.

**Effect:** only the World Cup VII game changes Elo, because it goes from a draw to a 2-0 win for Lorenzo C. It is an early game (2007), so small differences run on through later games: 285 current ratings change, 28 by 0.05 or more and 7 by 0.5 or more (largest: Stef K -1.4, Jan T -1.3, Niels T -1.1), and 22 places in the ranking move by one or two. No placing, medal or finish override changes; of the 27,757 games only the eleven rows above differ. Full rebuild and all 29 checks passed on 2026-11-22.

## 6. The three matches left after the ten

### XXI 5th place, Christopher D v Mario F: decided, not yet applied

Nobody doubts the placing: Mario F is 5th and Christopher D 6th in both sources and on our site. Only the scores looked unclear, and the first version of this review read them too strictly.

| Source | Leg 1 | Leg 2 | Elsewhere |
|---|---|---|---|
| Access | Christopher D 6-5 Mario F | Mario F 5-4 Christopher D | Level 10-10, no note. Totals: one win each |
| alkis.org image | Christopher D 4-5 Mario F | Mario F **8-6** Christopher D | Ranking list: one win each, and exactly three more goals for Mario F than in Access |

**Reading:** these are the same two games. Mario F won one leg 5-4, Christopher D the other 6-5, so it was level 10-10 and needed extra time. Mario F won that 3-0, and the image shows 8-6, the score after extra time, where the second leg should be 6-5. Access lists the legs the other way round here, as it does for the 25th, 27th and 29th place finals of the same World Cup.

**Why we believe it:** Access contradicts itself (level, no decider, Mario F 5th), so by our rule (§4) alkis.org supplies the score. The 5-4 and Christopher D's 6 goals are the same in both sources, and both give each player one win, which two wins for Mario F would not. **What speaks against it:** the image has no `a.e.t.` line here, and Alkis counted these three goals in his totals, which he did not do for the 17th and 29th place finals. So: very likely, not proved. Normal-time scores, Elo and placings do not change either way.

**To apply** (next Amiga data push): game 26309 (Access 26929), Christopher D 6-5 Mario F: `6-8 e.t.`, extra-time goals 0-3, in the three places named in §9, then simul. Until then the Stages page names Christopher D as the winner of this final.

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

- **XIV 5th place**, John H v Gianluca T: the alkis.org image shows two legs that are not level (3-4, 2-3) followed by extra time. Access (3-2 and 3-4, then 4-4) is level and gives John H 5th, as both sources say. Access is right.
- **IV KOA Cup:** some winners are placed below the player they beat. Access leaves the KOA Cup out of the World Cup IV placings, so this is not an error.
- **XX "31st Place Final", Hannes B 2-2 Manfred S:** this is the Game of Shame with the wrong phase label in Access. The score is right.
- **The 28 player totals that still differ:** walkovers counted as games (XXI, Ian K and Fabien S); goals of three second legs left out (X, three players); goals or games miscounted (I, V, VIII, XI, XIV, XXI, XXIII); and XV 29th place (§5). Wherever we compared a results image it agrees with our games. Not followed up one by one: I, V and part of XI.
- **Tomasso R = Lorenzo C:** alkis.org lists Tomasso R in World Cups VII and X where Access has Lorenzo C. The placings (26th and 21st) and the VII totals match, so we treat them as one person, as Access does.

## 9. Where the corrections live

- On live since 2026-11-22 (Amiga push verified, all tables identical to `ko2amiga_work`).
- `SCORE_CORRECTIONS` in `scripts/amiga/import_corrections.py`: the values and the reasons, used by any Access import.
- `scripts/oneoff/amiga_fix_world_cup_extra_time_2026_11.py`: applies them to `ko2amiga_work` and refuses any row it does not recognise. Run it after restoring an older checkpoint, then simul.
- Placings: `scripts/oneoff/amiga_fix_placement_overrides_2026_11.py`. Scoring chain: `scripts/oneoff/amiga_add_extra_time_step_2026_11.py` (§10).

## 10. The winner of a level tie

Until this review, a tie that was level on goals and decided in extra time could go to the wrong player on the Stages page and in the stored stage tables: the old catalog chain (goals, then penalties) had no extra-time step, so it fell back to whoever won the leg that carries the note. The right notes alone would not have helped; in three of the corrected finals the page still named the loser.

Fixed the same day: `extra_time` now comes before `penalty_shootout` in `LEGACY_KNOCKOUT_BRIDGE_STEPS` (Python and PHP) and in the 94 stages that store the old chain (`scripts/oneoff/amiga_add_extra_time_step_2026_11.py`), and the Stages page uses the extra-time and penalty columns. Of 1,337 ties, 28 change winner on the page. 27 of them now agree with the final placing, among them the XVIII semi-final (Fabio F), the XII bronze match (Gianni T) and the Kelkheim VII final (Michael O). The 28th is VIII 15th place, where the page now follows Access's unconfirmed note and names Knut L (§6). No rating changes. One placing does: in Birmingham XL (2012, four players) Darren H lost a semi-final 0-1 in extra time and was placed 5th; now 3rd, shared with the other losing semi-finalist, with a bronze. Full rebuild and all 29 checks passed again.

## 11. Known and not fixed

- **Score entry cannot record extra time.** The organizer form has no field for it. The PHP parser behind it would also store the score after extra time as extra-time goals, and "Make official" does not copy the extra-time columns to the official game. Deferred by Dagh on 2026-11-22; tracked in [`amiga-organizer-track-status.md`](amiga-organizer-track-status.md) §6.
