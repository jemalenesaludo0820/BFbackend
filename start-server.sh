#!/bin/bash
echo "Starting LavaLust PHP Server on https://bfbackend-l9q7.onrender.com"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""
cd "$(dirname "$0")"
php -S localhost:3002 server.php

