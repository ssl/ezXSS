#!/bin/bash
groupadd --gid "$PUID" ezxss || true
useradd --system --uid "$PUID" --gid "$PGID" ezxss || true
chown ezxss: /var/www/html -R || true
chown ezxss /var/log/apache2/error.log || true
chown ezxss /var/log/apache2/other_vhosts_access.log || true
echo "Launching application with UID $PUID and GID $PGID"
runuser -u ezxss apache2-foreground
