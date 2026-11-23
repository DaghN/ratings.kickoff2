---
paths:
  - "site/public_html/amiga/ops/includes/**/*.php"
  - "site/public_html/amiga/ops/modules/finalize_tournament.php"
  - "site/public_html/amiga/ops/modules/process_completed_game.php"
  - "site/public_html/includes/amiga_scoring_contract.php"
  - "site/public_html/includes/amiga_participation_placement.php"
  - "site/public_html/includes/amiga_match_extensions.php"
  - "site/public_html/includes/amiga_perfect_event.php"
  - "site/public_html/includes/amiga_player_slice_lib.php"
  - "site/public_html/includes/amiga_tournament_lib.php"
  - "site/public_html/includes/*performance_rating.php"
  - "site/public_html/ops/includes/post_game_constants.php"
  - "site/public_html/ops/includes/post_game_elo.php"
  - "site/public_html/ops/includes/post_game_outcome.php"
  - "site/public_html/ops/includes/post_game_player_db.php"
  - "site/public_html/ops/includes/post_game_player_state.php"
  - "scripts/amiga/*.py"
  - "scripts/amiga/modern/replay.py"
  - "scripts/amiga/modern/clear_derived.py"
  - "scripts/k2_rating_core/*.py"
  - "scripts/amiga/modern/recount.py"
  - "scripts/amiga/sql/derived/*.sql"
---

# Amiga calculation code

This mirrors the Cursor glob rule `.cursor/rules/amiga-calculation-check.mdc`, which is the source of truth.

If you change **how something is calculated** here: make the same change on the other side (PHP Finish and Python simul), run `python -m scripts.amiga simul`, then `python -m scripts.amiga php-finish-check` (about 20 minutes, local only). It must say `RESULT: SAME` before the PHP is uploaded; tell Dagh the result. Not needed for display, docs, videos or corrected results. Details: `docs/amiga-php-finalize-parity-protocol.md` §0.

A new or changed stored number also gets its recount in `scripts/amiga/modern/recount.py`, in the same change, written from the written rule and never importing calculation code; `simul` ends with that recount. If it says `DIFFERENT`, do not adjust the recount to fit: find out which side follows the rule. Details: `docs/amiga-recount-policy.md`.
