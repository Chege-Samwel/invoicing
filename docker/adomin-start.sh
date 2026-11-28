#!/bin/sh
# Adomin Technologies — container entrypoint.
#
# Render forwards inbound traffic to $PORT (default 10000). An Apache that only
# listens on 80 fails Render's port scan and the service never comes up, so the
# listeners are retargeted here before handing over to the stock
# apache2-foreground (which still does its own foreground/exec work).
#
# The config test afterwards is the safety net: a broken tuning file must not be
# able to crash-loop a deployment, so it is removed and Apache starts with the
# distribution defaults instead.
#
# Before Apache starts, the database is initialised (docker/adomin-init-db.php):
# create the schema if missing and import database.sql when the schema is
# empty. That check is one query when the database is already installed, so
# restarts are not slowed down. A database failure never blocks Apache -
# the script always exits 0 and logs the exact MySQL error instead.
set -e

PORT="${PORT:-10000}"
sed -i "s|^Listen.*|Listen 0.0.0.0:${PORT}|" /etc/apache2/ports.conf
sed -i "s|<VirtualHost \*:80>|<VirtualHost *:${PORT}>|" /etc/apache2/sites-enabled/000-default.conf
echo "Adomin: Apache listening on 0.0.0.0:${PORT}"

if ! apache2ctl -t >/dev/null 2>&1; then
    echo "Adomin: apache2ctl -t failed, dropping /etc/apache2/conf-enabled/adomin-mpm.conf and starting anyway"
    apache2ctl -t || true
    rm -f /etc/apache2/conf-enabled/adomin-mpm.conf
fi

# Database initialisation (schema creation + first import). Never fatal.
if [ -f /var/www/html/docker/adomin-init-db.php ]; then
    echo "Adomin: initialising database (if needed)..."
    php /var/www/html/docker/adomin-init-db.php || echo "Adomin: database initialiser reported a problem - starting Apache anyway, see logs above."
fi

exec apache2-foreground
