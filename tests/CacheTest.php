<?php

namespace InteractionDesignFoundation\GeoIP\Tests;

use Mockery;
use Illuminate\Cache\CacheManager;

class CacheTest extends TestCase
{
    /**
     * @test
     */
    public function shouldReturnValidLocation(): void
    {
        $data = [
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'lat' => 41.31,
            'lon' => -72.92,
        ];

        $cacheMock = Mockery::mock(CacheManager::class)
            ->shouldAllowMockingProtectedMethods();

        $cacheMock->shouldReceive('get')
            ->with($data['ip'])
            ->andReturn($data);

        $geo_ip = $this->makeGeoIP([], $cacheMock);

        $location = $geo_ip->getCache()->get($data['ip']);

        $this->assertInstanceOf(\InteractionDesignFoundation\GeoIP\Location::class, $location);
        $this->assertEquals($location->ip, $data['ip']);
        $this->assertEquals($location->default, false);
    }

    /**
     * @test
     */
    public function shouldReturnInvalidLocation(): void
    {
        $cacheMock = Mockery::mock(CacheManager::class)
            ->shouldAllowMockingProtectedMethods();

        $geo_ip = $this->makeGeoIP([], $cacheMock);

        $cacheMock->shouldReceive('get')
            ->with('81.2.69.142')
            ->andReturn(null);

        $cacheMock->shouldReceive('tags')
            ->with($geo_ip->config('cache_tags'))
            ->andReturnSelf();

        $this->assertEquals($geo_ip->getCache()->get('81.2.69.142'), null);
    }

    /**
     * @test
     */
    public function shouldSetLocation(): void
    {
        $location = new \InteractionDesignFoundation\GeoIP\Location([
            'ip' => '81.2.69.142',
            'iso_code' => 'US',
            'lat' => 41.31,
            'lon' => -72.92,
        ]);

        $cacheMock = Mockery::mock(CacheManager::class)
            ->shouldAllowMockingProtectedMethods();

        $geo_ip = $this->makeGeoIP([], $cacheMock);

        $cacheMock->shouldReceive('put')
            ->withArgs(['81.2.69.142', $location->toArray(), $geo_ip->config('cache_expires')])
            ->andReturn(null);

        $cacheMock->shouldReceive('tags')
            ->with($geo_ip->config('cache_tags'))
            ->andReturnSelf();

        $this->assertEquals($geo_ip->getCache()->set('81.2.69.142', $location), null);
    }

    /**
     * @test
     */
    public function shouldFlushLocations(): void
    {
        $cacheMock = Mockery::mock(CacheManager::class)
            ->shouldAllowMockingProtectedMethods();

        $geo_ip = $this->makeGeoIP([], $cacheMock);

        $cacheMock->shouldReceive('flush')
            ->andReturn(true);

        $cacheMock->shouldReceive('tags')
            ->with($geo_ip->config('cache_tags'))
            ->andReturnSelf();

        $this->assertEquals($geo_ip->getCache()->flush(), true);
    }
}