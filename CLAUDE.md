@AGENTS.md

---

# Claude Code — additions to AGENTS.md

**Keep the `@AGENTS.md` import as the first line.** Claude Code only reads `AGENTS.md` on its own when no `CLAUDE.md` exists; this import is what loads it now. `AGENTS.md` stays the shared entry point for Cursor and Claude, so put repo rules there. Use this file only for things that differ in Claude.

## Habits loaded in every chat (shared with Cursor)

`.cursor/rules/kool-workspace.mdc` is the shared always-apply rule. Claude won't `@`-import a `.mdc` file (tested 2026-11-22), so a **SessionStart hook** in `.claude/settings.json` prints it into context at the start of every session. Edit that `.mdc` file, not a copy. If it isn't in your context, read it before coding.

## How Claude differs from the Cursor setup

- **Cold start:** `PROJECT_MEMORY.md` fits in one Read. Keep it that way: when the Recent log passes about 60 rows, move the older rows to `docs/archive/session-log-*.md`, following the 2026-11-22 Jul prune.
- **PowerShell scripts:** Windows PowerShell 5.1 reads UTF-8 `.ps1` files without a BOM as cp1252, and a `—` inside a double-quoted string then breaks parsing. Keep `.ps1` files UTF-8 **with BOM**. Edit keeps an existing BOM; a new `.ps1` created with Write needs one added.
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
- **WinSCP (Claude can do it; set up 2026-11-22):**
  - Saved session `dagh@ratings.kickoff2.com@ratings.kickoff2.com` (SFTP, port 5322); the password is stored in WinSCP and never shown.
  - Run `WinSCP.com /script=<file>` from the **PowerShell** tool. Git Bash mangles `/command`, and inline filemask quoting breaks.
  - In a script, `cd /public_html` first, because the session opens in `/config`.
  - Preview first (`synchronize remote|local -preview -criteria=size`), and ask Dagh before any upload to or deletion on the live site.
  - **Amiga push to live** = `scripts\push_ko2amiga_to_staging.ps1 -Force` (Bash tool, `powershell -File …`; the allowlist covers it). Ask Dagh before each live push. The script reads `$admin_password` from git-ignored `site/public_html/amiga/_ops/amiga_ops_password.local.php` itself; never paste the password into chat or a browser form.
  - Steve owns prod and the server. Never touch `ops/config/*.ini` on the server.
- **Memory:** Claude's own memory folder is only for Claude-side notes. Project memory stays in `PROJECT_MEMORY.md` and `docs/`, updated through `docs/UPDATE_DOCS.md`.
