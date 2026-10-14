@echo off
cd /d "%~dp0"
set "INTELLECTA_PHP="
for /f "delims=" %%P in ('where php 2^>nul') do if not defined INTELLECTA_PHP set "INTELLECTA_PHP=%%P"
if not defined INTELLECTA_PHP if exist "C:\xampp\php\php.exe" set "INTELLECTA_PHP=C:\xampp\php\php.exe"
if not defined INTELLECTA_PHP for /d %%D in ("C:\laragon\bin\php\*") do if exist "%%~D\php.exe" set "INTELLECTA_PHP=%%~D\php.exe"
if not defined INTELLECTA_PHP (
  echo Instale PHP 8.1 ou superior ou adicione o PHP ao PATH.
  pause
  exit /b 1
)
echo Abra http://localhost:8000
"%INTELLECTA_PHP%" -S localhost:8000 -t public router.php
