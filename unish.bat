@ECHO OFF
REM Run Unish, the test suite for Drush.
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0vendor/phpunit/phpunit/phpunit
php "%BIN_TARGET%" --configuration tests  %*