# Migration
To migrate from `torann/geoip` you simply need to :

1. change your composer.json:
```diff
-"torann/geoip": "^3.0",
+"interaction-design-foundation/laravel-geoip": "^3.1",
```

2. update namespaces in your application:
```diff
-use Torann\GeoIP\GeoIP;
-use Torann\GeoIP\Location;
+use InteractionDesignFoundation\GeoIP\GeoIP;
+use InteractionDesignFoundation\GeoIP\Location;
```
