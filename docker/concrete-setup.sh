#!/bin/sh
# Run by the concrete-setup service before the web server starts.
# 1. Installs Concrete, but only if it is not installed yet (no database configuration in the volume).
# 2. Installs the Ollama Chat package if it is missing.
# 3. Creates the example page /ki-assistent with the chat block if it is missing.
# Every step can run again safely; existing data is never changed.
set -eu

cd /srv/app/public

# Concrete's own libraries trigger deprecation notices under PHP 8.4; they are hidden to keep the log readable.
c5() {
    php -d 'error_reporting=E_ALL & ~E_DEPRECATED' concrete/bin/concrete "$@"
}

if [ -f application/config/database.php ]; then
    echo "Concrete ist bereits installiert."
else
    echo "Installiere Concrete ..."
    # The canonical URL must end with a slash.
    canonical_url="${C5_CANONICAL_URL%/}/"
    # Times are stored in UTC, matching the time zone of the MariaDB container.
    c5 c5:install \
        --db-server=db \
        --db-username="$DB_USER" \
        --db-password="$DB_PASSWORD" \
        --db-database="$DB_DATABASE" \
        --site="$C5_SITE_NAME" \
        --canonical-url="$canonical_url" \
        --starting-point="$C5_STARTING_POINT" \
        --admin-email="$C5_ADMIN_EMAIL" \
        --admin-password="$C5_ADMIN_PASSWORD" \
        --timezone=UTC
fi

if c5 c5:exec /setup/package-installed.php | grep -q '^installed$'; then
    echo "Paket ollama_chat ist bereits installiert."
else
    echo "Installiere Paket ollama_chat ..."
    c5 c5:package:install ollama_chat
fi

c5 c5:exec /setup/create-example-page.php
echo "Einrichtung abgeschlossen."
