@ECHO OFF
REM Running this file is equivalent to running `php drush`
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0drush
php "%BIN_TARGET%" %*
