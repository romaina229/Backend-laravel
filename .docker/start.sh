#!/bin/bash
set -e

echo "Starting AquaGestion Backend..."

# Attendre que la base de données soit prête (optionnel)
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database..."
    timeout=30
    while ! nc -z $DB_HOST ${DB_PORT:-3306}; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "Database connection timeout!"
            break
        fi
        sleep 1
    done
    if [ $timeout -gt 0 ]; then
        echo "Database is ready!"
    fi
fi

# Exécuter les migrations
echo "Running migrations..."
php artisan migrate --force

# Nettoyer et optimiser le cache
echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Créer le lien symbolique pour le storage
echo "Creating storage link..."
php artisan storage:link || true

# Démarrer Apache
echo "Starting Apache..."
apache2-foreground
