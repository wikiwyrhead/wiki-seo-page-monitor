@echo off
REM Wrapper: calls the PowerShell packaging script (package.ps1)
REM Usage: run this from the `build` folder in CMD (it delegates to PowerShell safely)
SETLOCAL

REM Resolve path to this script and call the PowerShell file with ExecutionPolicy bypass
SET SCRIPT_DIR=%~dp0
IF EXIST "%SCRIPT_DIR%package.ps1" (
	powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_DIR%package.ps1"
) ELSE (
	echo "package.ps1 not found in %SCRIPT_DIR%"
	exit /b 1
)

ENDLOCAL
