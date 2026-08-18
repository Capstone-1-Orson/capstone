#!/bin/bash
set -e

# Railway assigns PORT dynamically at container start, not at build time.
# Default to 8080 if it's not set (e.g. running locally).
: "${PORT:=8080}"

# Replace the placeholder __PORT__ with the real runtime port value.
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

exec apache2-foreground