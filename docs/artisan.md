# Artisan

Some services require downloading and use local database to detect Location by IP address.
There is a console command to download/update database:
```sh
php artisan geoip:update
```

Some cache drivers offer the ability to clear cached locations:
```sh
php artisan geoip:clear
```
