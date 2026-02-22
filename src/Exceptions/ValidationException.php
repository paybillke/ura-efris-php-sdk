<?php

namespace UraEfrisSdk\Exceptions;

/**
 * Validation error with field-level details
 */
class ValidationException extends EFRISException
{
    protected array $errors;

    public function __construct(
        string $message,
        array $errors,
        string $errorType = 'VALIDATION_ERROR'
    ) {
        parent::__construct($message, $errorType);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFieldError(string $fieldPath): ?string
    {
        return $this->errors[$fieldPath] ?? null;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function __toString(): string
    {
        return "{$this->message}: " . json_encode($this->errors);
    }
}
