<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests;

use InteractionDesignFoundation\GeoIP\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Location::class)]
class LocationTest extends TestCase
{
    #[Test]
    public function with_attribute_returns_new_instance_and_original_is_unchanged(): void
    {
        $original = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
        ]);

        $modified = $original->withAttribute('iso_code', 'DE');

        $this->assertNotSame($original, $modified);
        $this->assertSame('US', $original->iso_code);
        $this->assertSame('DE', $modified->iso_code);
        $this->assertSame('81.2.69.142', $modified->ip);
    }

    #[Test]
    public function setting_property_via_magic_set_throws_bad_method_call_exception(): void
    {
        $location = new Location(['ip' => '127.0.0.1']);

        $this->expectException(\BadMethodCallException::class);

        $location->ip = '10.0.0.1';
    }

    #[Test]
    public function offset_set_throws_bad_method_call_exception(): void
    {
        $location = new Location(['ip' => '127.0.0.1']);

        $this->expectException(\BadMethodCallException::class);

        $location['ip'] = '10.0.0.1';
    }

    #[Test]
    public function offset_unset_throws_bad_method_call_exception(): void
    {
        $location = new Location(['ip' => '127.0.0.1']);

        $this->expectException(\BadMethodCallException::class);

        unset($location['ip']);
    }

    #[Test]
    public function constructor_sets_default_and_cached_to_false(): void
    {
        $location = new Location(['ip' => '127.0.0.1']);

        $this->assertFalse($location->default);
        $this->assertFalse($location->cached);
    }

    #[Test]
    public function constructor_preserves_provided_default_and_cached_values(): void
    {
        $location = new Location([
            'ip' => '127.0.0.1',
            'default' => true,
            'cached' => true,
        ]);

        $this->assertTrue($location->default);
        $this->assertTrue($location->cached);
    }

    #[Test]
    public function reading_attributes_via_magic_get(): void
    {
        $location = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'country' => 'United States',
            'city' => 'New Haven',
            'state' => 'CT',
            'lat' => 41.31,
            'lon' => -72.92,
        ]);

        $this->assertSame('81.2.69.142', $location->ip);
        $this->assertSame('US', $location->iso_code);
        $this->assertSame('United States', $location->country);
        $this->assertSame('New Haven', $location->city);
        $this->assertSame('CT', $location->state);
        $this->assertSame(41.31, $location->lat);
        $this->assertSame(-72.92, $location->lon);
    }

    #[Test]
    public function reading_attributes_via_array_access(): void
    {
        $location = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
        ]);

        $this->assertSame('81.2.69.142', $location['ip']);
        $this->assertSame('US', $location['iso_code']);
    }

    #[Test]
    public function isset_returns_true_for_existing_attributes(): void
    {
        $location = new Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
        ]);

        $this->assertTrue(isset($location->ip));
        $this->assertTrue(isset($location->iso_code));
        $this->assertTrue(isset($location->default));
        $this->assertFalse(isset($location->nonexistent));
    }

    #[Test]
    public function to_array_returns_all_attributes(): void
    {
        $attributes = [
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'country' => 'United States',
        ];

        $location = new Location($attributes);
        $result = $location->toArray();

        $this->assertSame('81.2.69.142', $result['ip']);
        $this->assertSame('US', $result['iso_code']);
        $this->assertSame('United States', $result['country']);
        $this->assertFalse($result['default']);
        $this->assertFalse($result['cached']);
    }

    #[Test]
    public function same_returns_true_for_matching_ip(): void
    {
        $location = new Location(['ip' => '81.2.69.142']);

        $this->assertTrue($location->same('81.2.69.142'));
        $this->assertFalse($location->same('10.0.0.1'));
    }
}
