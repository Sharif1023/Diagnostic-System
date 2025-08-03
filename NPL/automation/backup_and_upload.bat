@echo off
setlocal enabledelayedexpansion

:: === CONFIGURATION ===
set DB_NAME=diagnostic_center
set DB_USER=root
set DB_PASS=         :: Leave blank if no password
set BACKUP_DIR=C:\xampp\htdocs\NPL\db_backups
set RCLONE_REMOTE=griveNPL
set DRIVE_FOLDER=XAMPP_Backups

:: === FORMATTING DATE & TIME ===
for /f "tokens=1-4 delims=/ " %%a in ("%DATE%") do (
    set YYYY=%%d
    set MM=%%b
    set DD=%%c
)
for /f "tokens=1-3 delims=:. " %%a in ("%TIME%") do (
    set HH=00%%a
    set MN=00%%b
    set SS=00%%c
)
set HH=%HH:~-2%
set MN=%MN:~-2%
set SS=%SS:~-2%

set DATESTAMP=%YYYY%-%MM%-%DD%_%HH%%MN%%SS%
set BACKUP_FILE=%BACKUP_DIR%\%DB_NAME%_%DATESTAMP%.sql
set ZIP_FILE=%BACKUP_FILE%.zip

:: === CREATE BACKUP ===
echo Creating MySQL dump...
if "%DB_PASS%"=="" (
    "C:\xampp\mysql\bin\mysqldump.exe" -u%DB_USER% %DB_NAME% > "%BACKUP_FILE%"
) else (
    "C:\xampp\mysql\bin\mysqldump.exe" -u%DB_USER% -p%DB_PASS% %DB_NAME% > "%BACKUP_FILE%"
)

if errorlevel 1 (
    echo [ERROR] Failed to dump database.
    exit /b 1
)

:: === COMPRESS BACKUP ===
echo Compressing backup...
powershell -Command "Compress-Archive -Path '%BACKUP_FILE%' -DestinationPath '%ZIP_FILE%'"
if errorlevel 1 (
    echo [ERROR] Failed to compress backup.
    exit /b 1
)

:: === DELETE ORIGINAL SQL ===
del "%BACKUP_FILE%"

:: === UPLOAD TO GOOGLE DRIVE ===
echo Uploading to Google Drive...
rclone copy "%ZIP_FILE%" %RCLONE_REMOTE%:/%DRIVE_FOLDER%

:: === DELETE LOCAL FILES OLDER THAN 7 DAYS ===
echo Cleaning up local files older than 7 days...
forfiles /p "%BACKUP_DIR%" /s /m *.zip /d -7 /c "cmd /c del @path"

:: === DELETE REMOTE FILES OLDER THAN 7 DAYS ===
echo Cleaning up remote Google Drive files older than 7 days...
rclone delete --min-age 7d "%RCLONE_REMOTE%:/%DRIVE_FOLDER%"

:: === DONE ===
echo Backup and upload complete.
pause
