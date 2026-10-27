<?php

declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Services;

use InteractionDesignFoundation\GeoIP\Contracts\ServiceInterface;
use InteractionDesignFoundation\GeoIP\Exceptions\MissingConfigurationException;
use InteractionDesignFoundation\GeoIP\Location;
use InteractionDesignFoundation\GeoIP\Support\HttpClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractService implements ServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Create a new service instance.
     *
     * @param array $config
     */
    public function __construct(protected array $config = [])
    {
        $this->boot();
    }

    /** The "booting" method of the service. */
    #[\Override]
    public function boot(): void {}

    /** {@inheritDoc} */
    #[\Override]
    public function hydrate(array $attributes = []): Location
    {
        return new Location($attributes);
    }

    /**
     * Get configuration value.
     * @return mixed
     */
    #[\Override]
    public function config(string $key, mixed $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * This method ensures that the given key was filled
     * by the user, so that the service can be called without
     * errors raised linked to missing configuration.
     *
     * @param string|list<string> $keys
     * @return void
     */
    public function ensureConfigurationParameterDefined(string | array $keys): void
    {
        // Be able to accept a string and an array of strings.
        $keys = is_string($keys) ? [$keys] : $keys;

        foreach ($keys as $key) {
            // If the config is not defined / is empty.
            if (empty($this->config($key))) {
                $service = (new \ReflectionClass($this))->getShortName();

                throw new MissingConfigurationException(sprintf("Missing '%s' parameter (service: %s)", $key, $service));
            }
        }
    }

    /**
     * Ensure the value is a syntactically valid IPv4/IPv6 address before it is placed into a request URL.
     *
     * @psalm-taint-escape ssrf
     * @psalm-taint-escape file
     * @psalm-pure
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('Invalid IP address passed to the GeoIP service.');
        }

        return $ip;
    }

    /**
     * HttpClient settings shared by all HTTP-based services; per-service values override the global ones.
     *
     * @return array{timeout: mixed, connect_timeout: mixed}
     */
    protected function timeoutConfig(): array
    {
        return [
            'timeout' => $this->config('timeout', HttpClient::DEFAULT_TIMEOUT),
            'connect_timeout' => $this->config('connect_timeout', HttpClient::DEFAULT_CONNECT_TIMEOUT),
        ];
    }

    /**
     * An error page must never be parsed as a lookup result.
     *
     * @throws \RuntimeException
     */
    protected function ensureSuccessfulResponse(HttpClient $client, string $provider): void
    {
        $status = $client->getHttpCode();

        if ($status < 200 || $status > 299) {
            throw new \RuntimeException(sprintf('%s request failed with HTTP status %d', $provider, $status));
        }
    }
}
