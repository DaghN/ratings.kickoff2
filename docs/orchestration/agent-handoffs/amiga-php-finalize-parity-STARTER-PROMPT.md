# Starter prompt — Amiga PHP finalize parity investigation

**Use a new chat.** Paste the **COPY INTO NEW CHAT** block below.

**Mission:** Investigate whether PHP live Make official / `finalize_tournament` matches Python `finalize-tournament` — without unsafe one-tournament rewind. Prefer evidence (inventory + forked N→N+1 fingerprint) over fear.

**Out of scope for this chat unless Dagh expands:** Track L secretary UX, staging kitchen drills, silent Finish rewind, resurrecting `verify-php-finalize-parity` / reopen-refinalize.

**Related:** [`docs/amiga-derived-write-policy.md`](../../amiga-derived-write-policy.md) · [`docs/amiga-running-tournament-boundary-policy.md`](../../amiga-running-tournament-boundary-policy.md) RTB12 · **protocol + Jul 2026 sign-off:** [`docs/amiga-php-finalize-parity-protocol.md`](../../amiga-php-finalize-parity-protocol.md) · [`docs/archive/retired-amiga-refinalize-2026-06.md`](../../archive/retired-amiga-refinalize-2026-06.md) · work checkpoints [`data/amiga/checkpoints/README.md`](../../../data/amiga/checkpoints/README.md) · staging handoff [`docs/amiga-staging-handoff.md`](../../amiga-staging-handoff.md) · practice track L0 is **separate** ([`docs/amiga-live-ops-practice-track.md`](../../amiga-live-ops-practice-track.md))

**Status note (Jul 2026-17):** Investigation complete for probe #608 — see protocol doc. Use this starter only to **re-run** or extend the fingerprint, not to rediscover the method.

---

## COPY INTO NEW CHAT

```
You are Dagh's agent for **Amiga PHP ↔ Python finalize parity investigation**.

**Mission:** Find whether the PHP live Finish/Make-official finalize path writes the same derived truth as Python `finalize-tournament`, given that:
- Recent staging bugs were catastrophic schema/shape mismatches (dead columns; `as_of_tournament_id` leaked into `amiga_player_slice_totals`).
- Assumed "PHP mirrors Python" was never continuously proven.
- **One-tournament rewind/clear of derived outputs is NOT trusted** and must not be the compare protocol.

**You are NOT fixing every mismatch in this chat** unless Dagh says go after the inventory / first fingerprint report. Default deliverable = evidence + ranked gap list + recommended next slice.

### Locked method (do not invent rewind)

**Trusted base N** = DB state where every tournament *before* the probe event is already finalized with correct derived truth, and the probe event **T** is **not** yet absorbed into any cumulative derived tables.

**Forbidden:** DELETE/UPDATE “just tournament T’s derived” on a fully finalized history (career `current`, matchup summary, slice totals, realm, community, etc. already include T — unwind is not accurate).

**Allowed protocols (prefer A):**

**A — Append after full simul (preferred for live-ops / RTB):**
1. On `ko2amiga_work`, achieve trusted full simul (or pull known-good work). That is base **N** (all historical events official).
2. Dump / clone that DB once (mysqldump or equivalent restoreable snapshot of N).
3. On fork Py: create identical kitchen/running tournament **T** (same fixtures + results) → Python `finalize-tournament` (promote + derive).
4. Restore N → on fork Php: same **T** ground/running package → PHP finalize path that staging uses (`amiga_finalize_tournament` / ops `finalize-tournament`, including promote if RTB).
5. Fingerprint-diff derived tables for T + affected players (see below).

**B — Holdout historical T (optional second probe):**
1. Partial replay/simul with `--limit` (games count) so chronology stops **before** T; T still has L3 games but `rating_finalized=0` and no T-derived rows. That is base N.
2. Dump N once → fork Python finalize T vs restore + PHP finalize T → same fingerprint.
3. Note: `--limit` is **games coverage**, not tournament id — compute the game cutoff carefully.

**Python one-tournament addition already exists:** `python -m scripts.amiga finalize-tournament --tournament-id=T` loads prior career from **prior event snapshots** (`load_player_states_before_tournament`), not from clearing T. Full in-memory batch replay is the holy loop; **single finalize-from-DB** is the fair compare to PHP live.

**`prove` / full `simul` verify oracles prove Python, not PHP.** Do not treat green prove as PHP parity.

### Work DB hygiene — no experiment pollution (locked)

| DB | Role in this investigation |
|----|----------------------------|
| Staged `ko2amiga_db` | Community prod — **never** run fork experiments here; **never** push export from a polluted work DB |
| Local `ko2amiga_work` | Living repair shop — mutable only with a **restore path** sealed first |
| Dump / throwaway DBs | Where Phase 1 forks actually run |

**Before Phase 1 (mandatory if any finalize/kitchen touch):**
1. Seal a restore point of current good work:
   `powershell -ExecutionPolicy Bypass -File scripts\seal_amiga_work_checkpoint.ps1 -Label php-parity-base`
   → `data/amiga/checkpoints/work-YYYY-MM-DD-php-parity-base/` (see `data/amiga/checkpoints/README.md`).
2. Treat that checkpoint (or an equivalent mysqldump of base **N**) as the definition of “real work before experiment.”
3. Run Python vs PHP forks only on **restored copies of N** (same dump twice), or on throwaway DBs (`ko2amiga_parity_py` / `ko2amiga_parity_php` if created). Do **not** finalize T with Python then PHP on the same living `ko2amiga_work` without restore between forks.

**After Phase 1:**
- Restore the checkpoint into `ko2amiga_work`, **or** `pull_ko2amiga_from_staging.ps1 -Force` if staged is the authority you want back.
- **`simul` alone is not enough** to undo pollution: it rebuilds derived but does **not** remove kitchen L3/L4 (probe tournament ground) you appended.
- Do **not** run `export_ko2amiga_work.ps1` → staging import until work is restored to a non-experimental state.

Phase 0 inventory is read-only on code — no checkpoint required until Phase 1.

### Phase 0 — Write-surface inventory (do first; cheap)

Inventory every INSERT/UPDATE/DELETE table+columns in:
- `site/public_html/amiga/ops/modules/finalize_tournament.php` + includes it pulls (esp. snapshot persist, slice persist, current upsert, matchup, community, realm, WC HoF)
vs
- `scripts/amiga/finalize_tournament.py` + helpers

Flag:
- PHP writes columns DDL dropped / Python never writes
- PHP skips tables/columns Python writes
- Wider row copied into narrower table (slice_totals class)
- Ungated paths Python gates (e.g. WC-only)
- Order-of-ops / `rating_finalized` timing differences

Deliver a table: Table | Python | PHP | Verdict (match / PHP-extra / PHP-missing / shape-risk)

### Phase 1 — Fingerprint (after inventory, if Dagh says continue or inventory finds only soft risks)

Minimum fingerprint set for tournament T:
- `amiga_game_ratings` (all games for T)
- `amiga_tournament_standings` (+ catalog stats if written)
- `amiga_player_event_snapshots` for participants at T
- `amiga_player_current` for participants (and document if non-participants should be untouched)
- Matchup at-event / summary for pairs that played at T
- Slice at-event / totals **only if T is World Cup** (else assert PHP did not write WC slices)
- Realm / community / WC HoF: row counts + checksums for rows touched by T (define precisely in notes)

Compare: row counts, then content hashes / sorted CSV dumps. Rank mismatches: loud schema vs silent numeric.

Existing narrower oracles (use, do not reinvent): `verify-php-standings-parity`, `verify-php-community-parity` — note coverage gaps vs whole finalize.

### Read first (in order)

1. This starter prompt (method lock)
2. docs/amiga-derived-write-policy.md — Python vs PHP finalize roles; retired refinalize
3. docs/archive/retired-amiga-refinalize-2026-06.md — why not reopen/refinalize harness
4. docs/amiga-running-tournament-boundary-policy.md — RTB6 promote then derive; RTB12 parity claim
5. scripts/amiga/finalize_tournament.py — single-event writer; already-finalized guard
6. scripts/amiga/player_stats_load.py — `load_player_states_before_tournament`
7. site/public_html/amiga/ops/modules/finalize_tournament.php — live path
8. site/public_html/amiga/ops/includes/amiga_slice_persist_lib.php — recent totals-shape fix (Jul 2026)
9. docs/amiga-live-ops-practice-track.md §4 — do **not** conflate with L0 secretary queue
10. data/amiga/checkpoints/README.md + scripts/seal_amiga_work_checkpoint.ps1 — Phase 1 restore hygiene
11. docs/amiga-staging-handoff.md — pull / export; never push polluted work

### Constraints

- **Checkpoint before Phase 1**; forks only on restored dumps / throwaway DBs — see Work DB hygiene above.
- Staged `ko2amiga_db` = never experiment / never push from polluted work.
- `simul` ≠ undo kitchen ground; restore checkpoint or pull staged after probes.
- No resurrect reopen / `verify-php-finalize-parity` / silent Finish rewind.
- UTF-8 on Windows: StrReplace on existing; PowerShell UTF8Encoding for new files (no agent Write for source).
- Laragon PHP/MySQL paths per AGENTS.md.
- No git commit unless Dagh asks.
- Track L serial feedback stays separate — this is an investigation track.

### Deliverable format

1. **Executive verdict** — how under-verified is PHP? (1 paragraph)
2. **Inventory table** — write-surface gaps
3. **Protocol used** — A and/or B; checkpoint label; dump/fork steps actually run; how work was restored
4. **Fingerprint results** — match / mismatches ranked (schema vs numeric)
5. **Recommended next slices** — e.g. fix X; add permanent `verify-php-finalize-fork` tool; expand column constants shared with Python
6. **What not to do** — any unsafe rewind or “mutate living work in place” ideas rejected with reason

**First message:**
1. Confirm mission + locked method (no one-tournament derived rewind; checkpoint before Phase 1).
2. Run Phase 0 inventory before proposing code fixes.
3. Ask Dagh before Phase 1 dump/fork if inventory already shows critical gaps that should be fixed first.
```

---

## Notes for Dagh

| Item | Guidance |
|------|----------|
| When to paste | New chat when ready to investigate PHP finalize trust — not mid–Track L UX fix |
| Stop gate | Inventory first; fork fingerprint is optional second session |
| Work pollution | Seal checkpoint **before** Phase 1; restore (or pull staged) after; never export polluted work to staging |
| Better than rewind? | Yes: **identical dump of N**, then add/finalize **T** with Python vs PHP |
| Python one-tournament? | **Yes** — `finalize-tournament`; loads prior snapshots from DB |
| `simul` after probe? | Rebuilds derived only — **does not** remove appended kitchen ground |