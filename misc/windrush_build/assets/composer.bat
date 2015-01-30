@echo off

SET SCRIPT_HOME=%~dp0
SET PATH=%SCRIPT_HOME%php;%PATH%

@php.exe %SCRIPT_HOME%composer.phar %*
