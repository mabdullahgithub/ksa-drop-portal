#!/bin/bash

# Container entrypoint for the VPS (Coolify builds with Railpack, which uses a
# start-container.sh in the project root in place of its own). Shared hosting
# never runs this file.
#
# Same boot steps as Railpack's default, then supervisord instead of the web
# server alone, so the queue worker and the scheduler live in the same container
# as the app: one build, one env, one file cache between all three.

set -e

# Tells the schedule that an always-on worker owns the `database` queue here, so
# the every-minute drain in routes/console.php stands down. Exported before
# `optimize` because that is what bakes it into the cached config.
export QUEUE_ALWAYS_ON=true

if [ "$RAILPACK_SKIP_MIGRATIONS" != "true" ]; then
  echo "Running migrations ..."
  php artisan migrate --force
fi

php artisan storage:link
php artisan optimize:clear
php artisan optimize

echo "Starting web server, queue worker and scheduler ..."
exec supervisord -c /app/deploy/supervisord.conf
