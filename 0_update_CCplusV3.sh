#!usr/bin/bash
#git pull --ff-only
php artisan config:cache
php artisan route:cache
php artisan view:cache
VITE_APP_URL=http://localhost/ npm run build
/bin/cp -r public/. /var/www/ccplus/
chown -R ilona:www-data app/
chown -R ilona:www-data database/
chown -R ilona:www-data resources/
chown -R ilona:www-data routes/