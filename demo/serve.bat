@echo off
rem Seeds the demo database and serves it, so that looking at the admin is one
rem command rather than four environment variables typed correctly.
rem
rem The variables below are the same ones the test suite reads, documented in
rem CLAUDE.md. Change them here if your local server differs; nothing else in
rem the demo needs editing.

set RA_TEST_MYSQL_DSN=mysql:host=127.0.0.1;dbname=rockadmin_test
set RA_TEST_MYSQL_USER=root
set RA_TEST_MYSQL_PASSWORD=root

set PORT=%1
if "%PORT%"=="" set PORT=8099

echo Seeding %RA_TEST_MYSQL_DSN%
php "%~dp0seed.php"
if errorlevel 1 (
    echo.
    echo Seeding failed. The database named above has to exist already --
    echo the demo creates its tables, not the database itself.
    exit /b 1
)

echo.
echo   http://127.0.0.1:%PORT%/
echo   super@example.com / rockadmin    sees everything
echo   editor@example.com / rockadmin   can create, edit and delete
echo   viewer@example.com / rockadmin   read-only: no buttons at all
echo.
echo Running the test suite wipes this data -- it shares rockadmin_test with
echo the fixtures. Run this script again if the demo looks empty.
echo.

php -S 127.0.0.1:%PORT% -t "%~dp0." "%~dp0index.php"
