<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Services\IPGeoLocation;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

final class IpGeoLocationTest extends TestCase
{
    /** @test */
    public function it_can_locate_a_given_ip(): void
    {
        Http::fake([
            'https://api.ipgeolocation.io*' => json_decode($this->validResponse(), true, 512, JSON_THROW_ON_ERROR)
        ]);
        $service = $this->getService();

        $response = $service->locate('1.1.1.1');

        $this->assertSame('NA', $response['continent_code']);
        $this->assertSame('United States', $response['country_name']);
    }

    private function getService(): IPGeoLocation
    {
        return new IPGeoLocation();
    }

    private function validResponse(): string
    {
        return <<<JSON
{
   "ip":"1.1.1.1",
   "continent_code":"NA",
   "continent_name":"North America",
   "country_code2":"US",
   "country_code3":"USA",
   "country_name":"United States",
   "country_capital":"Washington, D.C.",
   "state_prov":"California",
   "district":"",
   "city":"Los Angeles",
   "zipcode":"90012",
   "latitude":"34.05361",
   "longitude":"-118.24550",
   "is_eu":false,
   "calling_code":"+1",
   "country_tld":".us",
   "languages":"en-US,es-US,haw,fr",
   "country_flag":"https://ipgeolocation.io/static/flags/us_64.png",
   "geoname_id":"5332870",
   "isp":"APNIC Research and Development",
   "connection_type":"",
   "organization":"Cloudflare, Inc.",
   "currency":{
      "code":"USD",
      "name":"US Dollar",
      "symbol":"$"
   },
   "time_zone":{
      "name":"America/Los_Angeles",
      "offset":-8,
      "current_time":"2023-02-16 15:10:19.161-0800",
      "current_time_unix":1676589019.161,
      "is_dst":false,
      "dst_savings":1
   }
}
JSON;
    }

    private function invalidResponse(): string
    {
        return <<<JSON
{
  "message": "Provided API key is not valid. Contact technical support for assistance at support@ipgeolocation.io"
}
JSON;

    }
}