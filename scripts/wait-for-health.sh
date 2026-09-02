#!/usr/bin/env sh
set -eu

url="${1:-http://127.0.0.1:8080/api/health}"
attempts="${HEALTH_ATTEMPTS:-60}"

i=1
while [ "$i" -le "$attempts" ]; do
  if curl --fail --silent --show-error "$url" >/dev/null; then
    echo "Salud confirmada: $url"
    exit 0
  fi
  i=$((i + 1))
  sleep 2
done

echo "La aplicación no alcanzó un estado saludable: $url" >&2
exit 1
