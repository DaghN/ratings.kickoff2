# H2H versus poster — fresh-start brief (constraints + inspiration)

**This is a deliberately curated brief for a blank-slate design pass.** It contains only the *constraints* and the *intent* — not a finished design, and not the design that already shipped on the production page. Treat the tables below as **boundaries and inspiration, not a rigid spec**. The goal is a genuinely good idea of your own, in the site's style.

**Authority:** Dagh's brief (broad) → this doc (constraints) → [`../design-direction.md`](../design-direction.md) (visual language).

> ⚠️ A prior poster attempt exists and is live on the real Opponents page. **Do not seek it out** — see the do-not-open list in the handover prompt. You are starting fresh on the lab page so you are not anchored by it.

---

## The brief (Dagh's words)

> *"In the style of our website, make a cool player vs. player poster."*

That is the whole design brief. Everything below is just the guard-rails so it fits the data and the site.

## Page job

The Head-to-head tab lets a visitor pick an opponent, then tells the **rivalry story**. The page already shows a single-player **hero** for the subject at the very top (rank · rating · games · milestones). So the poster's job is the **relational** introduction — *two* players in opposition — sitting between "who did I pick?" and the detailed stats that will come later. Think of it as the fight-card / versus summary for the pair.

## Hard constraints (do honor these)

| Element | In/out | Note |
|---------|--------|------|
| Both players' **name** | In | Link each to their profile (locked site policy) |
| Both players' **rank** | In | Ladder rank when `Display = 1`; else em dash |
| Both players' **rating** | In | Current rating when displayed |
| **W · D · L** record | In — **the hero stat** | Large, clear, colour-coded; subject's perspective |
| **Goals** (for / against in the pair) | In | Secondary to W/D/L |
| **Games** count | In | Sample size |
| A sense of **who leads** | In | One short read of dominance |
| **Country / flags** | **OUT** | Online realm — omit entirely |
| **First / last game** date or score | **OUT** | Out of scope for the poster |
| DD/CS, goal extremes, ratios | OUT (poster) | Belong to a later pair-detail band, not the poster |
| A second full copy of the subject hero | OUT | The page hero already covers the subject |

## W/D/L presentation

- Three large, clear numbers labelled **W · D · L**.
- Reuse the site **stat palette**: wins use `.blue` (`--k2-table-positive`), losses `.red` (`--k2-table-negative`), draws muted/neutral — same semantics as the player games tables. Do **not** invent one-off hex.
- Colour on the numbers/chips, not full neon panels.
- Must read for colour-blind users: **labels always visible**, never colour-only.

## Layout / surface

- **Size it to fit the information well** — let typography and spacing breathe; neither cramped nor padded with empty space. *There is no "must be compact" rule.* Judge by feel on a real pair with a typical name length.
- Lives inside the existing bordered stage panel (`.k2-player-opponents-h2h__stage`) — same surface family as the hero (`.k2-player-hero`: surface, radius, shadow). Not literal boxing kitsch / pixel art / museum frames.
- Mobile: stack or scale gracefully; the record stays legible.
- Empty states: no pair selected → the existing prompt; pair with **0 games** → both players still visible, centre reads "No rated games".

## Data (read path — already wired in the lab scaffold)

| Field | Source |
|-------|--------|
| Each player's identity, rating, rank, display | `playertable` + the rank query in `player_opponents_h2h_load_player_card()` |
| W/D/L, goals, games for the pair | `player_opponents_h2h_pair_record()` — directed row from `player_matchup_summary` (live `ratedresults` fallback if the table is missing) |

The lab panel already loads `$subjectCard`, `$opponentCard`, and `$record` for you and marks exactly where to render. **No new schema.** Ratios (if you want any) are computed at read time.

## Later (not this pass)

A **pair-detail band** below the poster — the full W/D/L · Goals · DDs fields for *this opponent only* (same columns as the three Opponents sub-tabs, one pair). Out of scope for the poster itself; just don't design something that blocks adding it underneath later.

---

## Lab build — "Two corners, one verdict" (fresh pass, Jun 2026)

A fresh, blank-slate design built only on the lab page; the production poster was **not** opened. View it locally:

- Balanced: <http://ratingskickoff.test/player/opponents_h2h_lab.php?id=263&opponent=237> (Lee vs Steve)
- Lopsided: <http://ratingskickoff.test/player/opponents_h2h_lab.php?id=316&opponent=263> (Eternalstudent vs Lee — opponent dominates)
- Empty (0 games): <http://ratingskickoff.test/player/opponents_h2h_lab.php?id=263&opponent=680> (Lee vs PDv82)

### Composition (top → bottom, reads like a fight card)

1. **Marquee** — two players with a small `vs` prop between them. Each side is a **cross anchored on the avatar ring** (name initial): the **name centred above** the ring, **Rank on the inner side** (toward the opponent / `vs`) and **Rating on the outer side** (toward the screen edge), both vertically centred on the ring. So the two players' ranks flank the `vs` while their ratings hold the outer edges. Subject's avatar/ring + name is the **blue** side, opponent's the **red** side. The marquee shares the **same 3-column grid geometry** as the W/D/L row (`--k2-h2h-grid-max` / `--k2-h2h-grid-gap`), each ring is centred in its column, and the grid aligns on the avatar band (`align-items:end`) so **the ring's centre of gravity sits directly above its outer count** (subject ring over Won, `vs` over Drew, opponent ring over Lost) regardless of how many lines a name wraps to. Rank & Rating are hung out of flow (absolute) so they can't shift the ring off centre; on ≤600 px the cross collapses to name / ring / rank / rating stacked centred.
2. **W/D/L triptych** — the hero. Three big display numbers: **Won (blue) · Drew (muted) · Lost (red)**, labels (Won/Drew/Lost) always visible beneath (colour-blind safe).
3. **Lead meter** — a single proportional bar (win | draw | loss widths) for an instant "who leads" read; same three colours.
4. **Goals** — secondary, two centred lines: the `for – against` numbers (subject-for blue-muted, opponent-against red-muted) over a small `GOALS` label.

### Design choices & why

- **Side identity = stat palette.** The subject "owns" blue (`--k2-table-positive`), the opponent "owns" red (`--k2-table-negative`) — the exact palette the player tables use. The same two tokens drive the avatar rings, **the player names**, the W/L numbers, the meter, and the goals, so the whole poster tells one coherent *from-the-subject's-view* story. No one-off hex. (Under pitch/holo tint, "positive" follows the site to green, exactly like the rest of the site's `.blue`.)
- **W/D/L is unmistakably the hero** — biggest type on the surface, dead centre, with the meter directly reinforcing it. Rings + names line up above their Won/Lost counts so each side reads as one vertical column.
- **`vs` is a scene-prop, not a feature** — small, same ink as the Drew number, no chrome; it sets the stage between the two camps without taking it.
- **Rhymes with the hero idiom** — circular avatar rings, display-font values, uppercase micro-labels, and the same bordered surface family. The page's subject hero sits above; the poster is the *relational* introduction, not a second hero.
- **Restraint** — a single faint tint wash from the top edge (no glow-on-everything), colour only on numbers/rings/names/meter per the design contract.
- **Size-to-fit** — flex column with `clamp()` rhythm; the corners + counts share a centred 3-column grid. Long names get a width cap and wrap cleanly; ≤600 px stacks the text under each ring.
- **Empty state** — both players stay; centre reads `No rated games yet`; meter/triptych/goals are suppressed.

### Where the code lives (lab only — production untouched)

- Render: `site/public_html/includes/player_opponents_h2h_lab.php` → `k2_h2h_lab_render_poster()` (+ `k2_h2h_lab_corner_html()`), wired into the stage's marked `FRESH POSTER GOES HERE` block.
- CSS: `site/public_html/stylesheets/h2h-lab.css`, namespace `k2-h2h-poster*` (theme.css not edited).

### Session log

- **Jun 2026** — Built the fresh "Two corners, one verdict" poster on the lab page from the curated brief + design-direction only (production poster deliberately not opened). Added the three render helpers + the `k2-h2h-poster*` stylesheet; verified balanced / lopsided / 0-games / ~390 px mobile in the browser. PHP `php -l` clean, no linter errors. Lab-only; live page and its poster unchanged.
- **Jun 2026 (tweak)** — Reworked the marquee from horizontal corners to vertically-centred stacks and unified its grid with the W/D/L row, so each side's circle now sits exactly above its outer count (ring → count → word column). CSS-only change.
- **Jun 2026 (tweaks)** — Names now follow the blue/red side colour (like the rings); removed the eyebrow (`Head to head · N games`) and the verdict sentence; `vs` is now a small plain prop in the Drew-number ink (no circle); goals split to a centred number line over a `GOALS` label.
- **Jun 2026 (tweak)** — Moved the name/Rank/Rating back *beside* the ring (home-left, away-right) **while keeping the ring centred above its Won/Lost count**: the marquee re-uses the scoreboard's 3-column grid, the ring centres in its column, and the text is hung out of flow on the outer side (≤600 px falls back to text-under-ring). Removed the unused `k2_h2h_lab_verdict()` helper. CSS + small PHP (markup) change.
- **Jun 2026 (tweak)** — Split the side block: the **name now sits centred above** each ring, and only **Rank/Rating** stays *beside* the ring (outer side), vertically centred on the ring. Marquee aligns on the avatar band (`align-items:end`) and the `vs` is boxed to ring height so it tracks the avatars; ≤600 px stacks name / ring / rank+rating, all centred. Markup: name lifted out of the side-block into the column above an `avatar-row` wrapper; CSS hangs Rank/Rating absolutely beside the ring. CSS + small PHP (markup) change.
- **Jun 2026 (tweak)** — Balanced the cluster around each ring and turned Rank/Rating into a **cross**: bigger hero names (`clamp(18px,3.4vw,27px)`) and more name↔ring gap (`clamp(11px,1.6vw,16px)`); **Rank moved to the inner side** (toward the `vs`), **Rating stays outer** (toward the edge), each its own absolutely-hung stat 18 px off the ring — the ring is the cross anchor (name above, rank/rating on the two horizontal arms). ≤600 px collapses both stats back under the ring, centred. Markup: the single `__ids` `<dl>` became two `__id--rank` / `__id--rating` `<dl>`s; CSS positions each per side+type. CSS + small PHP (markup) change.

---

## Lab v2 — "Two cards, one verdict" (promoted to production Jun 2026)

**Archived sandbox.** The v2 design is now the shipped H2H poster on `player/opponents.php?view=h2h`. Old lab URLs redirect or are removed.

A second, **parallel** sandbox so the v1 cross poster above is kept intact while a different idea is explored. Each player's identity (avatar · name · rank · rating) is packed into a **glowing card** that clones the single-player hero layout (avatar left, name over a rank/rating row); the two cards sit symmetrically around the `vs`, so the composition is balanced as blocks even though each card reads left→right. The big **blue Won / red Lost** counts (the part that clearly works) are kept as the loud hero below the cards.

View it locally (separate page, separate stylesheet — v1 and production untouched):

- Balanced: <http://ratingskickoff.test/player/opponents_h2h_lab2.php?id=263&opponent=237> (Lee vs Steve)
- Lopsided: <http://ratingskickoff.test/player/opponents_h2h_lab2.php?id=316&opponent=263>

### Two live toggles (buttons below the poster)

- **Cards: Aligned / Mirrored** — *Aligned* copies the hero exactly for both (both avatars on the left); *Mirrored* flips the opponent card so its avatar faces the `vs` (the two avatars bookend the centre). Default: **Mirrored**.
- **Frame: Bare / Tint / Panel** — three treatments of the outer container: *Bare* = no chrome, cards float on the page with only their own glow; *Tint* = no border, just a faint top-edge wash; *Panel* = a full bordered site surface around the cards. Default: **Tint**.

The toggles set `data-mirror` / `data-frame` on the `.k2-h2h2-poster` root (server-renders the default active button; clicks wired by a delegated listener in the lab2 page, so it survives stage re-renders).

### Notes / choices

- **Card glow** uses the milestone-card idiom (layered `color-mix` box-shadows) tinted to each side (blue subject / red opponent), kept soft so it doesn't out-shout the W/D/L counts.
- The production `.k2-player-opponents-h2h__stage` panel chrome (border + `overflow:hidden`) is **neutralised in `h2h-lab2.css` only** (not theme.css) so the Frame toggle owns the outer treatment and the glow can spill.
- This version **drops** the v1 "ring centred above its count" alignment — the card now owns horizontal placement (an accepted trade for the card symmetry).

### Where the v2 code lives (lab only — v1 + production untouched)

- Page: `site/public_html/player/opponents_h2h_lab2.php` (links `h2h-lab2.css`; toggle JS at the bottom).
- Render: `site/public_html/includes/player_opponents_h2h_lab2.php` → `k2_h2h_lab2_render_poster()` (+ `k2_h2h_lab2_card_html()`, `k2_h2h_lab2_controls_html()`, `player_opponents_h2h_lab2_render_panel()`).
- CSS: `site/public_html/stylesheets/h2h-lab2.css`, namespace `k2-h2h2-*` (self-contained; does not depend on `h2h-lab.css`).

### Session log

- **Jun 2026** — Built lab v2 as a separate page/include/stylesheet: glowing hero-style identity cards around the `vs`, with **Aligned/Mirrored** card and **Bare/Tint/Panel** frame toggles below the poster. Kept the blue/red W/D/L counts as the hero. Neutralised the stage panel chrome within `h2h-lab2.css` so the frame toggle is the real outer treatment. Verified all toggle combos (mirror on/off × 3 frames) in the browser; PHP `php -l` clean. v1 lab and the production poster unchanged.
- **Jun 2026 (tweak)** — Pushed the card glow up to the **milestone spotlight-card** strength (border `58%`; box-shadow layers `0 0 16/40/72px` at `34/22/10%` + inset rim `16%`) and made the names **bold (700)** with a tinted `text-shadow` glow (`0 0 26px`, side colour at 40%) — matching `milestone.php?key=survivor`. CSS-only.
- **Jun 2026 (fix)** — The names are *links*, so global anchor styles were stripping the new weight + glow; the `__name-link` now `font:/font-weight:/text-shadow: inherit`s from `__name` so bold + glow actually show. Also swapped the avatar ring's solid `0 0 0 4px` spread for a **soft blur halo** (`0 0 12px`/`26px`, side colour 48%/24%) like the hero avatar. CSS-only.
- **Jun 2026 (tweak)** — Names eased back to weight **600** (too bold at 700). Tightened vertical rhythm: poster flex `gap` dropped to `clamp(12px,2vw,18px)` (pulls the lead bar closer to the counts and the goals closer to the bar) with `margin-top: clamp(10px,1.8vw,16px)` on the record so the cards keep their breathing room above. CSS-only.
- **Jun 2026 (tweak)** — Toned down the glow: card border `58→46%`, card shadow layers `34/22/10 → 22/13/6%` (and outer blur `72→60px`, inset `16→12%`); avatar halo `48/24 → 34/15%` with blurs `12/26 → 10/22px`. Still clearly blue/red, just calmer. CSS-only.
- **Jun 2026 (tweak)** — Card Rank/Rating values dropped from bright `--k2-text-primary` to the muted `--k2-text-secondary` (the Drew-count grey) so they read as quieter supporting info under the names. CSS-only.
- **Jun 2026 (shipped)** — **Promoted to production H2H tab** (`player/opponents.php?view=h2h`). Fixed layout: mirrored cards, bare stage, no toggles. Code in `player_opponents_h2h.php` + `player-opponents-h2h-poster.css`. Lab v1 deleted; lab2 URL redirects.
