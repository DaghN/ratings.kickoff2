# Push local ko2amiga_work to staged ko2amiga_db in one command (no typed password).
# Steps: export -> staged-unchanged gate (pull before push) -> WinSCP upload of _import parts
#        -> server preview check -> apply import part by part -> verify staged == work.
#
# Usage (repo root):
#   powershell -ExecutionPolicy Bypass -File scripts\push_ko2amiga_to_staging.ps1            # asks: type PUSH
#   powershell -ExecutionPolicy Bypass -File scripts\push_ko2amiga_to_staging.ps1 -Force     # agents (non-interactive)
#   powershell -ExecutionPolicy Bypass -File scripts\push_ko2amiga_to_staging.ps1 -DryRun    # export + gate only
#   ... -SkipExport        use existing site\public_html\amiga\_import parts
#   ... -SkipStagedCheck   push even if staged changed since the last pull (loses staged-only data)
#   ... -SkipVerify        skip the post-import side-pull + compare
#
# Needs: Laragon MySQL running, WinSCP with the saved session for ratings.kickoff2.com,
#        admin password in site\public_html\amiga\_ops\amiga_ops_password.local.php (gitignored).
# Policy: docs\amiga-staging-authority-policy.md (SS-3 full replace, SS-4 pull before push).

param(
    [string]$StagingBaseUrl = 'https://ratings.kickoff2.com',
    [string]$WinScpSession = 'dagh@ratings.kickoff2.com@ratings.kickoff2.com',
    [switch]$SkipExport,
    [switch]$SkipStagedCheck,
    [switch]$SkipVerify,
    [switch]$DryRun,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'lib\Pull-Ko2AmigaStaging.ps1')
. (Join-Path $PSScriptRoot 'lib\Push-Ko2AmigaStaging.ps1')
Invoke-Ko2AmigaStagingPush @PSBoundParameters
