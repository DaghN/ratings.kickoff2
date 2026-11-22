# Amiga organizer: reliability plan

**Status:** deferred by Dagh, 2026-11-22. Nothing here is built. Do it when the next focused work on the tournament organizer starts.

**Why it exists:** a survey of existing faults (November 2026) found ways in which saving a tournament can leave wrong data. Each was reproduced on a throwaway copy of the database, or read directly in the code where marked *(read)*. The organizer is known not to be mature yet; this is part of making it so.

**Related:** [`amiga-organizer-track-status.md`](amiga-organizer-track-status.md) (what works, gaps) · [`amiga-running-tournament-boundary-policy.md`](amiga-running-tournament-boundary-policy.md) · [`amiga-live-ops-platform.md`](amiga-live-ops-platform.md) · [`amiga-staging-backup-admin-delete-policy.md`](amiga-staging-backup-admin-delete-policy.md).

---

## 1. Until this is done

- Press **Backup now** before every **Make official**. If Make official fails or the page dies, do **not** press it again and do not rely on **Reset incomplete finish** (R1): restore that backup and try once more.
- Do not make an older tournament official in the browser when later tournaments exist (R2). Pull, do it on `ko2amiga_work`, simul and push.
- After undoing or changing a result, confirm the finishing order again even if the page does not ask for it (R3).

## 2. The faults

### R1. A Make official that fails and is tried again counts games twice

- **What happens:** the ratings are saved in one transaction, but everything after it (snapshots, head-to-head, community, World Cup and realm tables) is saved table by table, and the official flag comes last. The head-to-head totals are loaded from the present summary and the tournament's games are added to them. After a failure in between, a second try adds the same games again.
- **Seen:** a forced failure and a second try on tournament 607 left 56 head-to-head pairs two games too high, and the built-in check passed.
- **Also:** **Reset incomplete finish** clears ratings, standings and snapshots, but not the head-to-head tables or the inverse-count changelog, so it does not help *(read)*.
- **Where:** `amiga/ops/modules/finalize_tournament.php` (`loadFromSummary`, the lock released right after the first transaction, the flag at the end); `amiga/ops/fixtures.php` (`amiga_fixture_reset_incomplete_finalize`).

**Fix:**
1. One transaction from the first delete to the official flag, with the lock held throughout. First check that no called function starts its own transaction: in MySQL that silently commits the outer one (R2 has a real case).
2. Make the head-to-head step repeatable anyway: start from the state before this tournament (latest at-event rows before it), not from the present summary.
3. The built-in check must compare head-to-head games with the games themselves for every pair that met.
4. Reset incomplete finish must remove everything the tournament wrote, with the same cleaner as the tip delete.

### R2. An interrupted Make official of an older tournament leaves later ones without ratings

- **What happens:** the page first removes the results of all later tournaments, then rebuilds them one by one in separate requests. The list of what is left exists only in the browser session. If the session, the phone or the connection goes, nothing remembers the rest. A new attempt looks for later *official* tournaments, finds none, and runs a normal Make official.
- **Seen:** World Cup XXIV was left without ratings while the page reported success.
- **Also:** the preparing step is meant to be one transaction, but making the games official starts a second one inside it, which commits the first half. And sending the last step (rebuild of the present tables) again rewinds the present tables.
- **Where:** `amiga/ops/modules/insert_finalized_mid_tournament.php` (probe, prepare, finalize chain), `amiga/ops/fixtures.php` (`amiga_insert_finish_chain` in the session), `amiga_promote_running_tournament.php` (`begin_transaction`).

**Fix:**
1. Store the repair in the database: which tournaments still have to be rebuilt, and in which order. Show it on the organizer and backup pages, with a **Continue** button.
2. While a repair is open, refuse every other Make official, create and delete.
3. The probe must also count tournaments that have official games but no ratings.
4. Making the games official must be able to run inside the caller's transaction.
5. Report success only when every tournament with official games has ratings and the present tables match the latest tournament. The rebuild of the present tables must refuse to run before that, and be harmless when repeated.

### R3. The confirmed finishing order survives a changed result

- **What happens:** confirming the order writes the full ladder. Undoing a result or entering it again leaves that ladder in place, and Make official only checks that a ladder exists.
- **Seen:** a changed final stored the wrong winner.
- **Where:** `amiga/ops/fixtures.php` (`amiga_fixture_record_result`, `amiga_fixture_undo_unprocessed_result`, the check before promote).

**Fix:** store a fingerprint of the results with the confirmation. Any change clears the confirmation, and Make official refuses until the order has been confirmed for the current results. The page says why.

### R4. Smaller ones

| # | Fault | Where |
|---|---|---|
| a | The technical tools section uses the database after it is closed, so the page can break for a running tournament *(read)* | `fixtures.php`: `mysqli_close` before the Reset incomplete finish block |
| b | Backups stop making permanent reserves once the list is trimmed to its limit: the rule counts the backups that are left *(read)* | `amiga_backup_seal_lib.php`: `$existingCount` / `AMIGA_BACKUP_RESERVE_EVERY` |
| c | An open tournament dated after the inserted one breaks the numbering of an older Make official and of a delete | `amiga_chrono_integer_lib.php`: bump and decrement include tournaments that are not official |
| d | Moving a tournament to "completed" by hand leaves it without ratings and out of reach of Make official | `fixtures.php`: `amiga_fixture_browser_allowed_lifecycle_targets` |
| e | PHP and Python round some values differently | `finalize_tournament.php`, `post_game_player_state.php` |
| f | Score entry cannot hold extra time or penalties | [`amiga-organizer-track-status.md`](amiga-organizer-track-status.md) §6 |

## 3. Order of work

1. **R3** and **R4a, b**: small, and they protect every tournament.
2. **R1**: the normal Make official becomes safe to repeat.
3. **R2**: builds on R1's single transaction.
4. **R4c, d, e**, then extra time and penalties (R4f) before knockouts in the browser.

## 4. How to prove it

All on throwaway copies of `ko2amiga_work`, dropped afterwards. Never on live.

- **R1:** stop the code on purpose after each step (about a dozen places), try again, and compare the whole database with an undisturbed run. Every table must be identical.
- **R2:** stop after each phase and continue in a fresh session without cookies. Same comparison, plus a simul of the same games.
- **R3:** confirm, change a result, try Make official: refused. Confirm again: the stored winner is the new one.
- **All:** the 29-step verify suite, and PHP and Python giving the same tables.
