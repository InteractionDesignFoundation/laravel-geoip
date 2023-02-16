<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Exceptions\RequestFailedException;
use InteractionDesignFoundation\GeoIP\Services\IPData;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

final class IPDataTest extends TestCase
{
    /** @test */
    public function it_can_locate_a_given_ip(): void
    {
        Http::fake([
            'https://api.ipdata.co*' => json_decode($this->mockedApiResponse(), true, 512, JSON_THROW_ON_ERROR),
        ]);
        $service = $this->getService();

        $response = $service->locate('187.6.154.78');

        $this->assertSame('BR', $response['iso_code']);
        $this->assertSame('America/Sao_Paulo', $response['timezone']);
    }

    /** @test */
    public function it_throws_an_exception_when_response_is_invalid(): void
    {
        Http::fake([
            'https://api.ipdata.co*' => Http::response(
                json_decode($this->invalidResponse(), true, 512, JSON_THROW_ON_ERROR),
                401
            )
        ]);
        $service = $this->getService();

        $this->expectException(RequestFailedException::class);
        $this->expectExceptionMessage('Request failed.');

        $service->locate('187.6.154.78');
    }

    /** @test */
    public function exception_contains_request_errors(): void
    {
        Http::fake([
            'https://api.ipdata.co*' => Http::response(
                json_decode($this->invalidResponse(), true, 512, JSON_THROW_ON_ERROR),
                401
            )
        ]);
        $service = $this->getService();

        try {
            $service->locate('187.6.154.78');
        } catch (RequestFailedException $exception) {
            $this->assertSame(['message' => 'You have not provided a valid API Key.'], $exception->errors());
        }
    }

    private function getService(): IPData
    {
        return new IPData(['key' => 'service-api-key']);
    }

    private function mockedApiResponse(): string
    {
        return <<<JSON
{
   "ip":"187.6.154.78",
   "is_eu":false,
   "city":"Curitiba",
   "region":"Parana",
   "region_code":"PR",
   "region_type":"state",
   "country_name":"Brazil",
   "country_code":"BR",
   "continent_name":"South America",
   "continent_code":"SA",
   "latitude": -25.502599716187,
   "longitude": -49.29079818725586,
   "postal":"80000",
   "calling_code":"55",
   "flag":"https://ipdata.co/flags/br.png",
   "emoji_flag":"🇧🇷",
   "emoji_unicode":"U+1F1E7 U+1F1F7",
   "asn":{
      "asn":"AS8167",
      "name":"V TAL",
      "domain":null,
      "route":"187.6.128.0/18",
      "type":"business"
   },
   "languages":[
      {
         "name":"Portuguese",
         "native":"Português",
         "code":"pt"
      }
   ],
   "currency":{
      "name":"Brazilian Real",
      "code":"BRL",
      "symbol":"R$",
      "native":"R$",
      "plural":"Brazilian reals"
   },
   "time_zone":{
      "name":"America/Sao_Paulo",
      "abbr":"-03",
      "offset":"-0300",
      "is_dst":false,
      "current_time":"2023-02-16T18:12:32-03:00"
   },
   "threat":{
      "is_tor":false,
      "is_icloud_relay":false,
      "is_proxy":false,
      "is_datacenter":false,
      "is_anonymous":false,
      "is_known_attacker":false,
      "is_known_abuser":false,
      "is_threat":false,
      "is_bogon":false,
      "blocklists":[
         
      ]
   },
   "count":"6"
}
JSON;

    }

    private function invalidResponse(): string
    {
        return <<<JSON
{
  "message": "You have not provided a valid API Key."
}
JSON;

    }
}