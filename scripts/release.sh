#!/bin/sh
set -eu

export CACHE_STORE=redis
php artisan migrate --isolated --force
