#!/bin/sh
set -eu

# Railway menyediakan PORT pada runtime; default lokal adalah 80.
if [ "${1:-}" = "apache2-foreground" ]; then
    echo 'SIGAP startup: memastikan Apache hanya menggunakan mpm_prefork.'
    a2dismod -f mpm_event mpm_worker
    a2enmod mpm_prefork

    app_port="${PORT:-80}"
    case "$app_port" in
        ''|*[!0-9]*) echo 'PORT harus berupa angka.' >&2; exit 1 ;;
    esac
    if [ "$app_port" -lt 1 ] || [ "$app_port" -gt 65535 ]; then
        echo 'PORT harus berada antara 1 dan 65535.' >&2
        exit 1
    fi

    printf 'Listen %s\n' "$app_port" > /etc/apache2/ports.conf
    cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:${app_port}>
    DocumentRoot /var/www/html/public
    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>
EOF

    if ! apache2ctl -t; then
        echo 'Konfigurasi Apache gagal. Direktif MPM aktif:' >&2
        grep -R -n -E '^[[:space:]]*LoadModule[[:space:]]+mpm_' \
            /etc/apache2/mods-enabled /etc/apache2/conf-enabled \
            /etc/apache2/sites-enabled /etc/apache2/apache2.conf >&2 || true
        exit 1
    fi

    mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache

    php artisan package:discover --no-interaction
    php artisan config:cache --no-interaction
    php artisan view:cache --no-interaction

    if [ ! -e public/storage ] && [ ! -L public/storage ]; then
        php artisan storage:link --no-interaction
    fi
fi

# Mendukung override command untuk migrasi atau worker tanpa memulai Apache.
exec "$@"
