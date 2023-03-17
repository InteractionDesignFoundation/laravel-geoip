<?php declare(strict_types=1);


namespace InteractionDesignFoundation\GeoIP\Exceptions;

final class RequestFailedException extends \RuntimeException
{
    /** @var array<string, mixed> $errors */
    private array $errors = [];

    /** @param array<string, mixed> $errors */
    public static function requestFailed(array $errors = []): self
    {
        $exception = new self('Request failed.');
        $exception->errors = $errors;

        throw $exception;
    }

    /** @return array<string, mixed> */
    public function errors(): array
    {
        return $this->errors;
    }
}