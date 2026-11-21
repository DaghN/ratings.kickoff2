@AGENTS.md

---

# Claude Code — additions to AGENTS.md

**Keep the `@AGENTS.md` import as the first line.** Claude Code only reads `AGENTS.md` on its own when no `CLAUDE.md` exists; this import is what loads it now. `AGENTS.md` stays the shared entry point for Cursor and Claude, so put repo rules there. Use this file only for things that differ in Claude.

## Habits loaded in every chat (shared with Cursor)

@.cursor/rules/kool-workspace.mdc

## How Claude differs from the Cursor setup

- **Cold start:** `PROJECT_MEMORY.md` is about 180 KB, more than one Read can hold. Read the top: Current focus, Next, and the newest Recent log rows (roughly the first 230 lines). Don't page through the whole log.
- **File encoding:** `.cursor/rules/utf8-windows.mdc` works around a Cursor-only bug, so ignore it here. Claude's Write and Edit tools save UTF-8 without a BOM, and Edit keeps the repo's CRLF line endings (verified 2026-11-22). **Never** create source files with PowerShell `Out-File`, `Set-Content` or `>`. Windows PowerShell 5.1 adds a BOM or writes UTF-16, which breaks PHP output.
- **Shells:**
  - The Bash tool is Git Bash (POSIX), so heredocs work. Use `/c/...` paths.
  - The PowerShell tool is Windows PowerShell 5.1. The git rules in kool-workspace §6 apply there.
  - Use Bash for `php`, `mysql`, `python` and `scripts\*.ps1` calls; those forms match the allowlist in `.claude/settings.json`.
- **Laragon CLI paths in Git Bash form:**
  - PHP: `/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe`
  - MySQL: `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe`
  - If MySQL is down (`mysqladmin.exe -uroot ping` fails), ask Dagh to click **Start All** in Laragon.
- **Subagents:** the "composer-2.5" rule in AGENTS.md is Cursor-only. Ignore it.
- **Path-scoped rules:** `.claude/rules/` mirrors the glob-scoped rules in `.cursor/rules/`. Today that is only the K2 table PHP rule.
- **Git:** commit and push only when Dagh asks. Force-push and `reset --hard` are denied in `.claude/settings.json`.
- **Staging and prod are unchanged.** Dagh does the WinSCP sync and the browser import, and Steve owns prod.
- **Memory:** Claude's own memory folder is only for Claude-side notes. Project memory stays in `PROJECT_MEMORY.md` and `docs/`, updated through `docs/UPDATE_DOCS.md`.
