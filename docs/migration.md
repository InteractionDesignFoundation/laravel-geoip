# Migration from torann/geoip

1. Swap the packages:
```bash
composer remove torann/geoip
composer require interaction-design-foundation/laravel-geoip
```

2. Update namespaces:
```diff
-use Torann\GeoIP;
+use InteractionDesignFoundation\GeoIP;
-use Torann\Location;
+use InteractionDesignFoundation\Location;
```
