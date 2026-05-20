@echo off
REM Clear Avast-injected SSLKEYLOGFILE so Apache (httpd) can start via Laragon Start All.
REM Install: copy to C:\laragon\start-laragon.cmd and use as Laragon shortcut target.
set SSLKEYLOGFILE=
start "" "%~dp0laragon.exe" %*
