# One-time fix: Avast SSLKEYLOGFILE breaks Apache; Stop All leaves httpd-real running.
# Usage: powershell -ExecutionPolicy Bypass -File scripts\setup_laragon_apache_fix.ps1

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$LaragonRoot = 'C:\laragon'
$ApacheVer = 'httpd-2.4.66-260223-Win64-VS18'
$Bin = Join-Path $LaragonRoot "bin\apache\$ApacheVer\bin"
$SrcShim = Join-Path $RepoRoot 'laragon\httpd-shim.c'
$SrcWatchdog = Join-Path $RepoRoot 'scripts\laragon-apache-watchdog.ps1'
$SrcLauncher = Join-Path $RepoRoot 'laragon\start-laragon.cmd'
$DstWatchdog = Join-Path $LaragonRoot 'usr\apache-watchdog.ps1'
$ShortcutPath = 'C:\ProgramData\Microsoft\Windows\Start Menu\Programs\Laragon\Laragon.lnk'

function Stop-Apache {
    cmd /c "taskkill /F /IM httpd-real.exe 2>nul"
    cmd /c "taskkill /F /IM httpd.exe 2>nul"
    Start-Sleep -Seconds 2
}

if (-not (Test-Path $LaragonRoot)) { Write-Error "Laragon not found at $LaragonRoot" }
if (-not (Test-Path $Bin)) { Write-Error "Apache bin not found at $Bin" }
if (-not (Get-Command gcc -ErrorAction SilentlyContinue)) {
    Write-Error 'gcc (MinGW) not on PATH'
}

Write-Host 'Stopping any running Apache...'
Stop-Apache

$realExe = Join-Path $Bin 'httpd-real.exe'
if (-not (Test-Path $realExe)) {
    if (Test-Path (Join-Path $Bin 'httpd.exe.backup')) {
        Copy-Item (Join-Path $Bin 'httpd.exe.backup') $realExe -Force
    } elseif ((Get-Item (Join-Path $Bin 'httpd.exe')).VersionInfo.FileDescription -match 'Apache') {
        Copy-Item (Join-Path $Bin 'httpd.exe') $realExe -Force
        Copy-Item $realExe (Join-Path $Bin 'httpd.exe.backup') -Force
    } else {
        Write-Error 'httpd-real.exe missing — restore Apache from Laragon'
    }
}

Write-Host 'Compiling httpd shim...'
& gcc -O2 -o (Join-Path $Bin 'httpd.exe') $SrcShim
if ($LASTEXITCODE -ne 0) { Write-Error 'gcc failed' }

Copy-Item $SrcWatchdog $DstWatchdog -Force
Write-Host "Installed $DstWatchdog"

$procfile = Join-Path $LaragonRoot 'usr\Procfile'
$marker = 'apache-watchdog.ps1'
if (-not (Select-String -Path $procfile -Pattern $marker -Quiet)) {
    Add-Content -Path $procfile -Value "`nApache watchdog: autorun powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File C:/laragon/usr/apache-watchdog.ps1`n"
    Write-Host 'Added Apache watchdog to usr\Procfile (restart Laragon once)'
}

Copy-Item $SrcLauncher (Join-Path $LaragonRoot 'start-laragon.cmd') -Force

Remove-Item 'HKCU:\Software\Microsoft\Windows NT\CurrentVersion\Image File Execution Options\httpd.exe' -Recurse -Force -ErrorAction SilentlyContinue

if (Test-Path $ShortcutPath) {
    try {
        $sh = New-Object -ComObject WScript.Shell
        $lnk = $sh.CreateShortcut($ShortcutPath)
        $lnk.TargetPath = Join-Path $LaragonRoot 'start-laragon.cmd'
        $lnk.WorkingDirectory = $LaragonRoot
        $lnk.Save()
        Write-Host 'Updated Start Menu shortcut -> start-laragon.cmd'
    } catch {
        Write-Warning "Could not update shortcut: $_"
    }
}

cmd /c "set SSLKEYLOGFILE=test&& `"$(Join-Path $Bin 'httpd.exe')`" -t"
if ($LASTEXITCODE -ne 0) { Write-Error 'httpd -t failed' }

Write-Host ''
Write-Host 'Done.' -ForegroundColor Green
Write-Host 'Restart Laragon once (quit tray icon, open again). Then Start All / Stop All should match MySQL and the site.'
Write-Host 'Optional: in Avast, exclude C:\laragon\bin\apache\ from HTTPS scanning (reduces need for the shim).'
