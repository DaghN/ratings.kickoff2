# Alias — safe sandbox setup (never touches ko2unity_db).
# See scripts\setup_local_prod_sandbox.ps1

$ErrorActionPreference = 'Stop'
& (Join-Path $PSScriptRoot 'setup_local_prod_sandbox.ps1') @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
