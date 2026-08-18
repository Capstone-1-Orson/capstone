#!/bin/bash
set -e

# --- Railway MPM workaround ---
# Railway's environment has a known issue where more than one Apache MPM
# (mpm_event / mpm_prefork) ends up loaded at container start, even when
# the image builds correctly. Force-clean it here, at runtime, right
# before Apache starts.
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
rm -f /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_worker.load \
      /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null 2>&1 || true

# --- Runtime port binding ---
# Railway assigns PORT dynamically at container start, not at build time.
: "${PORT:=8080}"
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

exec apache2-foreground