# Stops orphaned httpd-real when Laragon Stop All turned off MySQL but left Apache running.
# Installed to C:\laragon\usr\ and started via usr\Procfile autorun.

$ErrorActionPreference = 'SilentlyContinue'

function Test-PortListening([int]$Port) {
    $null -ne (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
}

function Test-MysqlUp {
    $null -ne (Get-Process mysqld -ErrorAction SilentlyContinue)
}

while ($true) {
    if ((Test-PortListening 80) -and -not (Test-MysqlUp)) {
        taskkill /F /IM httpd-real.exe 2>$null | Out-Null
        taskkill /F /IM httpd.exe 2>$null | Out-Null
        Start-Sleep -Seconds 2
    }
    Start-Sleep -Seconds 2
}
