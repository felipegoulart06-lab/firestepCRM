@echo off
cd /d "%~dp0"
echo FirestepCRM
echo Abra: http://localhost:8080
php -c php.ini -S localhost:8080 -t public public/router.php
