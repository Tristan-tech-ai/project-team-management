#!/bin/sh
set -e
for d in app/public app/private framework/cache/data framework/sessions framework/views logs; do
	mkdir -p "/tmp/storage/$d"
done
exec frankenphp run --config /etc/caddy/Caddyfile
