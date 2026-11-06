#!/bin/sh
# Lance le site du quiz sur http://localhost:8000 (Ctrl+C pour arrêter).
cd "$(dirname "$0")" || exit 1
PORT="${PORT:-8000}"
echo "Quiz BTS SIO SLAM : http://localhost:$PORT"
exec php -S "localhost:$PORT" -t public public/index.php
