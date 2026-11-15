#!/usr/bin/env bash
# Runs both apps locally: Admin App on http://127.0.0.1:8101 and Team App on http://127.0.0.1:8102. Ctrl+C stops both.
cd "$(dirname "$0")"
(cd team-app && php artisan serve --host=127.0.0.1 --port=8102) &
team_pid=$!
trap 'kill $team_pid 2>/dev/null' EXIT
echo "Admin App: http://127.0.0.1:8101   Team App: http://127.0.0.1:8102   (Ctrl+C untuk berhenti)"
cd admin-app && php artisan serve --host=127.0.0.1 --port=8101
