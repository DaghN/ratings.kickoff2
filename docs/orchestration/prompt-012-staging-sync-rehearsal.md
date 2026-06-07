# Worker prompt 012 — staging sync rehearsal (Dagh-assisted)

You are Composer 2.5 working in the Cursor workspace:

`C:\Users\daghn\Desktop\Online and Amiga 500 ELO`

This job completes the staging refresh cycle started in checkpoint 2026-06-07. Local export and public visibility boundary are in place; **WinSCP sync and browser import require Dagh**.

## Strategic context

Before showing the Amiga tournament system to interested people, staging must reflect the current DB contract (entrants, lifecycle, fixture guardrails). See [`amiga-tournament-architecture-checkpoint.md`](amiga-tournament-architecture-checkpoint.md).

## Goal

Verify end-to-end staging refresh: export (if needed) → document WinSCP sync checklist → browser preview/apply → spot-check public and ops pages.

Classification: `migration` / `internal ops` / `docs/strategy`

## Prerequisites (Dagh)

1. WinSCP sync `site/public_html/` → staging `public_html/` including all `amiga/_import/ko2amiga_*.sql` + `ko2amiga_manifest.json`.
2. Confirm Dagh has run sync **before** you attempt staging URL checks, or document that preview was skipped.

## Required scope

1. Run local export if manifest/SQL parts are stale or missing.
2. Record manifest part count and generation timestamp.
3. If Dagh confirms sync done: hit staging preview URL and record `parts: N` and importer build tag.
4. If Dagh confirms apply done: spot-check URLs listed in `docs/amiga-staging-handoff.md`.
5. Confirm public tournament index does **not** list internal `running` smokes (visibility filter).
6. Create handoff with exact results.

## Commands

```powershell
powershell -ExecutionPolicy Bypass -File scripts\export_ko2amiga_db.ps1
python -m scripts.amiga fixtures verify
python -m scripts.amiga fixtures verify-entrants
python -m scripts.amiga fixtures verify-lifecycle
```

Staging URLs (from `docs/amiga-staging-handoff.md`):

- Preview: `https://ratings.kickoff2.com/amiga/run_import_ko2amiga.php?once=ko2amiga-import-one-shot&pwd=coffee`
- Apply: add `&apply=1&part=1`

## Handoff

`docs/orchestration/agent-handoffs/2026-06-07-012-staging-sync-rehearsal.md`

Include whether WinSCP/import was run by Dagh or deferred, and what was verified locally vs on staging.

## Non-goals

- No schema changes.
- No public UI feature work.
- Do not force-add gitignored SQL dumps unless repo precedent requires it.

## Expected final response

Summarize export state, staging preview/apply results (or blocker), spot-checks, and commit hash.
