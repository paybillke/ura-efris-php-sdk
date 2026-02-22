<?php

namespace UraEfrisSdk\Exceptions;

/**
 * API communication error
 */
class APIException extends EFRISException
{
    protected ?int $statusCode;
    protected ?string $returnCode;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $returnCode = null,
        string $errorType = 'API_ERROR'
    ) {
        parent::__construct($message, $errorType);
        $this->statusCode = $statusCode;
        $this->returnCode = $returnCode;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getReturnCode(): ?string
    {
        return $this->returnCode;
    }
}
