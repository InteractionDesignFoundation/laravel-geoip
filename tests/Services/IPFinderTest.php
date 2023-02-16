<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
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
   "ip":"161.185.160.93",
   "type":"ipv4",
   "continent_code":"NA",
   "continent_name":"North America",
   "country_code":"US",
   "country_name":"United States",
   "region_code":"NY",
   "region_name":"New York",
   "city":"Coney Island",
   "zip":"11201",
   "latitude":40.69459915161133,
   "longitude":-73.99063873291016,
   "location":{
      "geoname_id":5113481,
      "capital":"Washington D.C.",
      "languages":[
         {
            "code":"en",
            "name":"English",
            "native":"English"
         }
      ],
      "country_flag":"https://assets.ipstack.com/flags/us.svg",
      "country_flag_emoji":"🇺🇸",
      "country_flag_emoji_unicode":"U+1F1FA U+1F1F8",
      "calling_code":"1",
      "is_eu":false
   }
}
JSON;

    }
}