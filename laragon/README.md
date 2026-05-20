# Laragon local fixes (Dagh’s PC)

## Problem

Avast sets `SSLKEYLOGFILE` → Laragon **Start All** starts MySQL but Apache dies (`AH10226` in `error_log`).

## Fix

From repo root, once:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\setup_laragon_apache_fix.ps1
```

Files here:

| File | Role |
|------|------|
| `httpd-shim.c` | Source for replacement `httpd.exe` (launches `httpd-real.exe` without `SSLKEYLOGFILE`) |
| `start-laragon.cmd` | Clears env before starting `laragon.exe` (copied to `C:\laragon\`) |

After Laragon upgrades Apache, re-run the setup script.
