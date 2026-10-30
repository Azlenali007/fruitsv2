#!/bin/bash

# 1. Ensure PHP and MariaDB are installed if fresh container booted
if ! command -v php >/dev/null 2>&1; then
    echo "Installing PHP 8.2 and MariaDB..."
    DEBIAN_FRONTEND=noninteractive apt-get update -qq && \
    DEBIAN_FRONTEND=noninteractive apt-get -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" install -y --no-install-recommends -qq \
        php-cli php-fpm php-mysql php-sqlite3 php-mbstring php-curl php-gd mariadb-server mariadb-client
fi

# 2. Ensure MariaDB service is running
if ! mysqladmin ping --silent 2>/dev/null; then
    echo "Starting MariaDB service..."
    service mariadb start 2>/dev/null || /usr/bin/mysqld_safe --user=mysql &
    sleep 2
fi

# 3. Ensure default local DB credentials exist
mariadb -e "CREATE USER IF NOT EXISTS 'na_user'@'localhost' IDENTIFIED BY 'na_pass123'; GRANT ALL PRIVILEGES ON *.* TO 'na_user'@'localhost' WITH GRANT OPTION; FLUSH PRIVILEGES;" 2>/dev/null || true

# 4. If database is not setup yet and setup.php exists, attempt setup (ignore failures so server always starts)
if [ -f "database/setup.php" ]; then
    php database/setup.php 2>/dev/null || true
fi

# 5. Start the PHP server on port 3000 (document root: public/)
echo "Starting N.A Fresh Fruits & Coconuts PHP server on port 3000..."
exec php -S 0.0.0.0:3000 -t public router.php
