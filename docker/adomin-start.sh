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

exec apache2-foreground
