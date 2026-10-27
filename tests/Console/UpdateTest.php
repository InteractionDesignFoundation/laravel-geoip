<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Tests\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use InteractionDesignFoundation\GeoIP\Console\Update;
use InteractionDesignFoundation\GeoIP\GeoIPServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Update::class)]
final class UpdateTest extends \Orchestra\Testbench\TestCase
{
    /** @return list<class-string> */
    #[\Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [GeoIPServiceProvider::class];
    }

    #[Test]
    public function fails_with_a_message_when_no_service_is_configured(): void
    {
        config(['geoip.service' => 'not_configured']);

        $this->assertSame(Command::FAILURE, Artisan::call('geoip:update'));
        $this->assertStringContainsString('No GeoIP service is configured.', Artisan::output());
    }

    #[Test]
    public function fails_with_a_message_when_a_config_key_is_missing(): void
    {
        config(['geoip.service' => 'maxmind_database', 'geoip.services.maxmind_database.database_path' => null]);

        $this->assertSame(Command::FAILURE, Artisan::call('geoip:update'));
        $this->assertStringContainsString('database_path', Artisan::output());
    }
}
