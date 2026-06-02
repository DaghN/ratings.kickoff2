# Migrate work — apply schema/migrations to ko2unity_work only (never baseline or dev).
# Preferred: scripts\prepare_local_work_db.ps1 or python -m scripts.work_prepare migrate-work
# Usage: powershell -ExecutionPolicy Bypass -File scripts\apply_schema_to_work.ps1

$ErrorActionPreference = 'Stop'
$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
& powershell -ExecutionPolicy Bypass -File (Join-Path $RepoRoot 'schema\apply_local.ps1') -Database 'ko2unity_work'
