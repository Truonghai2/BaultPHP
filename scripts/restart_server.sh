#!/bin/bash
# Restart Swoole server to reload code

echo "Stopping Swoole server..."
docker compose exec app php cli server:stop

sleep 2

echo "Starting Swoole server..."
docker compose exec app php cli server:start

echo "Server restarted!"
