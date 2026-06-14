# Handover prompt — fresh H2H versus poster (paste into a new Opus 4.8 chat)

Copy everything inside the fenced block below into a brand-new Opus 4.8 chat (high thinking). It is written to stand alone.

---

````markdown
# Build a player-vs-player "versus poster" for the Head-to-head tab — fresh start

## The situation (read this carefully)

You are designing and **implementing** a head-to-head "versus poster" for this Kick Off 2 ratings site.

There is a wrinkle worth your awareness: a previous attempt at this poster **already exists and is live on the production Opponents page**, built by an earlier agent. I was not happy that the work got funnelled through one rigid template, so I want a **genuinely fresh take from you** — your own composition and ideas, not a refinement of what's there.

Because of that, **you must NOT look at the existing poster.** It would anchor you exactly the way I'm trying to avoid. I have set up a clean **lab page** with the poster stripped out so you can start from a blank stage. Work only there.

> **Hard do-not-open list** (these contain or describe the prior design — opening them defeats the purpose):
> - `site/public_html/player/opponents.php` (the production page) and the `__poster`/`__fighter`/`__scoreboard`/`__rivalry` functions in `site/public_html/includes/player_opponents_h2h.php`
> - The `.k2-player-opponents-h2h__poster*` CSS block in `site/public_html/stylesheets/theme.css`
> - `docs/player-opponents-h2h-poster.md` (especially its "Implementation (as built)" section), and the poster rows in `docs/player-opponents-hub.md` / `docs/coordination/feature-log.md`
> - The top "H2H versus poster shipped" line in `PROJECT_MEMORY.md`'s Recent log
>
> You may of course read everything else, including the rest of those files where unrelated. If you accidentally glimpse the old design, please tell me rather than quietly adopting it.

## What I actually want (my original words to the previous agent)

I'm giving you my own framing verbatim, because I want you to infer intent from it rather than be handed a checklist:

> *In the style of our website, make a cool player vs. player poster. That's it.*
>
> *The earlier chat tried to narrow down what might be good to put there, but it was more of a brainstorm than a specification. So read it with an open mind, make your own considerations, and then come up with something cool. I don't want to straitjacket you into bad decisions.*

So: the brief is broad on purpose. Use taste.

## What to read first (in this order)

1. `PROJECT_MEMORY.md`, `AGENTS.md`, `docs/PROJECT_MAP.md` — repo orientation (skip the one MEMORY line noted above).
2. **`docs/lab/h2h-poster-fresh-brief.md`** — the curated brief: the few hard constraints (no country, no first/last, W/D/L is the hero, both players' rank+rating, size-to-fit) plus the data read path. Treat it as guard-rails + inspiration, not a rigid spec.
3. `docs/design-direction.md` — the visual identity ("neon noir statistics"; tint tokens `--k2-*`; stat palette `.blue`/`.red`; typography; surface rhythm). Honor this.
4. `site/public_html/includes/player_hero.php` — the single-player hero idiom the poster should rhyme with (avatar ring, stat layout). The page already renders this hero for the subject at the top.
5. **Inspiration (optional, with an open mind):** the brainstorm chat where I first discussed this idea is agent transcript `3fc90473-a957-41ba-b8dd-324368a64dce` — look at the last several exchanges about the poster. It is brainstorm, **not** spec, and it does **not** describe the shipped implementation. Don't feel bound by any element list there.

## Where to work (everything is scaffolded for you)

- **Page (your sandbox):** `site/public_html/player/opponents_h2h_lab.php` — a standalone copy with full chrome (header + subject hero + pickers) and a **stripped stage** showing only a plain "A vs B" headline.
- **Panel renderer:** `site/public_html/includes/player_opponents_h2h_lab.php` → `player_opponents_h2h_lab_render_panel()`. Inside the stage there is a clearly marked block:
  - `// ===== FRESH POSTER GOES HERE =====`
  - It already loads, for the selected pair:
    - `$subjectCard` / `$opponentCard` → `['player_id','name','display','rank','rating']`
    - `$record` (nullable) → `['games','wins','draws','losses','goals_for','goals_against']` (subject's perspective; `null` when no rated games)
  - Replace the placeholder headline with your poster. Feel free to add a render function (e.g. `k2_h2h_lab_render_poster(...)`) in this same lab include.
- **CSS:** `site/public_html/stylesheets/h2h-lab.css` (already linked by the lab page). Put **all** your poster CSS here, in your own class namespace (suggested `k2-h2h-poster*`). The global theme tokens (`--k2-*`) are available. **Do not edit `theme.css`.**
- Helpers available: `k2_h()` (escape), `k2_route('player-profile', ['id'=>…])` and `k2_route('lb-rating')` (links), `k2_fmt_int()`, `k2_db_is_null()`, `k2_h2h_games_meta_label()`.

This keeps the live page and its poster completely untouched while you build.

## Verify locally

Local site is `http://ratingskickoff.test/` (DB `ko2unity_db`). Open your lab page and screenshot it. Good real pairs to test:
- Balanced rivalry: `…/player/opponents_h2h_lab.php?id=263&opponent=237` (Lee vs Steve, ~877–297–868)
- Lopsided: `…/player/opponents_h2h_lab.php?id=316&opponent=263` (one side dominates)
- Omit `&opponent=` to auto-pick the top opponent.
PHP CLI for lint: `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l <file>`.

## When done

- Record your work in **`docs/lab/h2h-poster-fresh-brief.md`** (a short "Lab build" note + session log). Do **not** edit the production poster docs listed in the do-not-open block.
- Tell me how to view it and what design choices you made and why.

Make something cool.
````

---

## Notes for Dagh (not part of the paste)

- **What's scaffolded:** stripped lab page + panel (`opponents_h2h_lab.php`, `includes/player_opponents_h2h_lab.php`), a dedicated `stylesheets/h2h-lab.css`, this prompt, and the curated brief (`docs/lab/h2h-poster-fresh-brief.md`). The production page and its poster are untouched.
- **View the blank slate now:** `http://ratingskickoff.test/player/opponents_h2h_lab.php?id=263&opponent=237`.
- **Honest caveat:** this isn't total amnesia — the fresh agent still shares the design system, tokens, and brief, and will read the brainstorm chat. The point achieved is *zero exposure to the shipped poster's markup, CSS, and prose description*. The main residual risk is curiosity (it could grep its way to the old poster); the do-not-open list + physical separation make that unlikely, and I asked it to self-report if it slips.
- **Comparing later:** production poster lives at `/player/opponents.php?id=263&view=h2h&opponent=237`; the fresh one at the lab URL. Pick the winner, then we fold it onto the real page and retire the lab files.
