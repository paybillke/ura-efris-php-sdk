<?php

namespace UraEfrisSdk\Exceptions;

/**
 * Raised when authentication fails (e.g., invalid PFX, wrong password).
 * Uses HTTP 401 status code by default.
 */
class AuthenticationException extends EFRISException
{
    public function __construct(
        string $message = 'Authentication failed',
        int $statusCode = 401
    ) {
        parent::__construct($message, 'AUTHENTICATION_ERROR');
    }
}