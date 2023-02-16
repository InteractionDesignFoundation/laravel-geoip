<?php declare(strict_types=1);

namespace InteractionDesignFoundation\GeoIP\Exceptions;

final class RequestFailedException extends \RuntimeException
{
    private array $errors = [];

    public static function requestFailed(array $errors = []): self
    {
        $exception = new self('Request failed.');
        $exception->errors = $errors;

        throw $exception;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}