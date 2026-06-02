# Shared Laragon MySQL / mysqldump paths (Windows). Dot-source from other scripts.

function Find-LaragonMysqlExe {
    $candidates = @(
        'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe',
        'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysql.exe',
        'C:\laragon\bin\mariadb\mariadb-11.4.2-winx64\bin\mysql.exe',
        'C:\laragon\bin\mariadb\mariadb-10.11.8-winx64\bin\mysql.exe'
    )
    foreach ($path in $candidates) {
        if (Test-Path $path) { return $path }
    }
    return $null
}

function Find-LaragonMysqldumpExe {
    $mysql = Find-LaragonMysqlExe
    if (-not $mysql) { return $null }
    $dump = Join-Path (Split-Path $mysql) 'mysqldump.exe'
    if (Test-Path $dump) { return $dump }
    return $null
}

function Get-RepoRoot {
    (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
}
