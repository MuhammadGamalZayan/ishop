#!/usr/bin/env bash
# iShop - installs the packages agreed in the design doc.
# Run once from the project root:  bash scripts/setup-packages.sh
# Everything it prints is also saved to storage/logs/setup-packages.log
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p storage/logs
exec > >(tee storage/logs/setup-packages.log) 2>&1

echo "== PHP"
php -v | head -1
missing=""
for ext in intl gd exif fileinfo pdo_mysql mbstring zip bcmath; do
  php -m | grep -qi "^${ext}$" || missing="$missing $ext"
done
if [ -n "$missing" ]; then
  echo "MISSING PHP EXTENSIONS:$missing"
  echo "Enable them in Laragon: Menu > PHP > Extensions, then run this script again."
  exit 1
fi

export COMPOSER_MAX_PARALLEL_HTTP=1

echo "== composer require"
composer require --no-interaction \
  spatie/laravel-permission \
  spatie/laravel-translatable \
  spatie/laravel-medialibrary \
  spatie/laravel-activitylog \
  spatie/laravel-sluggable \
  mcamara/laravel-localization \
  filament/filament

echo "== publish package config + migrations"
php artisan vendor:publish --no-interaction --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --no-interaction --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --no-interaction --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
php artisan vendor:publish --no-interaction --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --no-interaction --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-config"
php artisan vendor:publish --no-interaction --provider="Mcamara\LaravelLocalization\LaravelLocalizationServiceProvider"

echo "== filament"
php artisan filament:install --panels --no-interaction

echo "== result"
composer show --direct | grep -E "spatie|mcamara|filament|laravel/framework|fortify|inertia"
ls database/migrations
php artisan migrate:status --no-interaction || true
echo "SETUP DONE"
