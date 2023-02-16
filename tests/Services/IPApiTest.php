<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Services;

use Illuminate\Support\Facades\Http;
use InteractionDesignFoundation\GeoIP\Services\IPApi;
use InteractionDesignFoundation\GeoIP\Tests\TestCase;

final class IPApiTest extends TestCase
{
    /** @test */
    public function it_can_locate_a_given_ip(): void
    {
        Http::fake([
            'http://ip-api.com*' => json_decode($this->validResponse(), true, 512, JSON_THROW_ON_ERROR)
        ]);

        $service = $this->getService();

        $response = $service->locate('187.6.154.78');

        $this->assertSame('BR', $response['iso_code']);
        $this->assertSame('Brazil', $response['country']);
    }

    private function getService(): IPApi
    {
        return new IPApi([
            'continent_path' => __DIR__.'/../country_continent.csv'
        ]);
    }

    private function validResponse(): string
    {
        return <<<JSON
{
    "query": "187.6.154.78",
    "status": "success",
    "continent": "South America",
    "continentCode": "SA",
    "country": "Brazil",
    "countryCode": "BR",
    "region": "SP",
    "regionName": "Sao Paulo",
    "city": "Itaim Bibi",
    "district": "",
    "zip": "",
    "lat": -23.6102,
    "lon": -46.6974,
    "timezone": "America/Sao_Paulo",
    "offset": -10800,
    "currency": "BRL",
    "isp": "V tal",
    "org": "Brasil Telecom Comunicacao Multimidia S.A",
    "as": "AS8167 V tal",
    "asname": "V tal",
    "mobile": false,
    "proxy": false,
    "hosting": false
}
JSON;
    }
}