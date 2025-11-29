@echo off
echo Starting LavaLust PHP Server on https://bfbackend-l9q7.onrender.com
echo.
echo Press Ctrl+C to stop the server
echo.
cd /d %~dp0
php -S localhost:3002 server.php

