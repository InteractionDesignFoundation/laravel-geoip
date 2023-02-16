<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Contracts;

interface Client
{
    public function setConfig(array $config): self;

    public function get(string $url, array $query = [], array $headers = []): array;
}