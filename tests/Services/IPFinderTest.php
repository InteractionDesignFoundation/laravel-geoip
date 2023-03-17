<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Config;
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
            'http://api.ipapi.com/api*' => json_decode($this->validResponse(), true, 512, JSON_THROW_ON_ERROR)
        ]);
        $service = $this->getService();

        $response = $service->locate('187.6.154.78');

        $this->assertSame('187.6.154.78', $response->ip);
        $this->assertSame('Brazil', $response->country);
    }

    /** @test */
    public function it_return_exception_with_errors_for_invalid_requests(): void
    {
        $responseBody = json_decode($this->invalidResponse(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($responseBody));

        Http::fake([
            'http://api.ipapi.com/api*' => Http::response(
                $responseBody,
                401
            )
        ]);

        $service = $this->getService();

        try {
            $service->locate('187.6.154.78');
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
        Config::set('geoip.services.ipfinder.key', 'ip-finder-api-key');
        return new IPFinder();
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
"ip": "187.6.154.78",
"type": "ipv4",
"continent_code": "SA",
"continent_name": "South America",
"country_code": "BR",
"country_name": "Brazil",
"region_code": "PR",
"region_name": "Paraná",
"city": "Curitiba",
"zip": "81730-000",
"latitude": -25.517749786376953,
"longitude": -49.22414016723633,
"location": {
"geoname_id": 3464975,
"capital": "Brasília",
"languages": [
{
"code": "pt",
"name": "Portuguese",
"native": "Português"
}
],
"country_flag": "https://assets.ipstack.com/flags/br.svg",
"country_flag_emoji": "🇧🇷",
"country_flag_emoji_unicode": "U+1F1E7 U+1F1F7",
"calling_code": "55",
"is_eu": false
}
}
JSON;
    }
}