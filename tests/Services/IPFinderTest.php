<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\LocationResponse;
use InteractionDesignFoundation\GeoIP\Services\IPFinder;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

final class IPFinderTest extends TestCase
{
    /** @test */
    public function it_can_locate_a_given_ip(): void
    {
        Http::fake([
            'https://api.ipapi.com/api*' => json_decode($this->validResponse(), true, 512, JSON_THROW_ON_ERROR)
        ]);
        $service = $this->getService();

        $response = $service->locate('161.185.160.93');

        $this->assertSame('161.185.160.93', $response['ip']);
        $this->assertSame('United States', $response['country_name']);
    }

    /** @test */
    public function it_can_return_a_location_object_response(): void
    {
        config()->set('geoip.should_use_dto_response', true);
        Http::fake([
            'https://api.ipapi.com/api*' => json_decode($this->validResponse(), true, 512, JSON_THROW_ON_ERROR)
        ]);
        $service = $this->getService();

        $response = $service->locate('161.185.160.93');

        $this->assertInstanceOf(LocationResponse::class, $response);
        $this->assertSame('161.185.160.93', $response->ip);
        $this->assertSame('United States', $response->country);
    }

    /** @test */
    public function it_return_exception_with_errors_for_invalid_requests(): void
    {
        Http::fake([
            'https://api.ipapi.com/api*' => Http::response(
                json_decode($this->invalidResponse(), true, 512, JSON_THROW_ON_ERROR),
                401
            )
        ]);

        $service = $this->getService();

        try {
            $service->locate('161.185.160.93');
        } catch (RequestFailedException $requestException) {
            $this->assertSame([
                'success' => false,
                'error' => [
                    'code' => 101,
                    'type' => 'invalid_access_key',
                    'info' => 'You have not supplied a valid API Access Key. [Technical Support: support@apilayer.com]'
                ]
            ], $requestException->errors());
        }
    }

    private function getService(): IPFinder
    {
        return new IPFinder(['token' => 'ip-finder-token']);
    }

    private function invalidResponse(): string
    {
        return <<<JSON
{
   "success":false,
   "error":{
      "code":101,
      "type":"invalid_access_key",
      "info":"You have not supplied a valid API Access Key. [Technical Support: support@apilayer.com]"
   }
}
JSON;

    }

    private function validResponse(): string
    {
        return <<<JSON
  {
    "ip": "161.185.160.93",
    "hostname": "161.185.160.93",
    "type": "ipv4",
    "continent_code": "NA",
    "continent_name": "North America",
    "country_code": "US",
    "country_name": "United States",
    "region_code": "NY",
    "region_name": "New York",
    "city": "Brooklyn",
    "zip": "11238",
    "latitude": 40.676,
    "longitude": -73.9629,
    "location": {
        "geoname_id": 5110302,
        "capital": "Washington D.C.",
        "languages": [
            {
                "code": "en",
                "name": "English",
                "native": "English"
            }
        ],
        "country_flag": "http://assets.ipapi.com/flags/us.svg",
        "country_flag_emoji": "🇺🇸",
        "country_flag_emoji_unicode": "U+1F1FA U+1F1F8",
        "calling_code": "1",
        "is_eu": false
    },
    "time_zone": {
        "id": "America/New_York",
        "current_time": "2018-09-24T05:07:10-04:00",
        "gmt_offset": -14400,
        "code": "EDT",
        "is_daylight_saving": true
    },
    "currency": {
        "code": "USD",
        "name": "US Dollar",
        "plural": "US dollars",
        "symbol": "$",
        "symbol_native": "$"
    },
    "connection": {
        "asn": 22252,
        "isp": "The City of New York"
    },
    "security": {
        "is_proxy": false,
        "proxy_type": null,
        "is_crawler": false,
        "crawler_name": null,
        "crawler_type": null,
        "is_tor": false,
        "threat_level": "low",
        "threat_types": null
    }
}
JSON;

    }
}