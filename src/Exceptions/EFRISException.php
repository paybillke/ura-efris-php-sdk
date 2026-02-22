<?php

namespace UraEfrisSdk\Exceptions;

use Exception;

/**
 * Base exception for EFRIS
 */
class EFRISException extends Exception
{
    protected string $errorType;

    public function __construct(string $message, string $errorType = 'UNKNOWN')
    {
        parent::__construct($message);
        $this->errorType = $errorType;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }
}