<?php

namespace UraEfrisSdk\Exceptions;

/**
 * Encryption/decryption error
 */
class EncryptionException extends EFRISException
{
    public function __construct(string $message, string $errorType = 'ENCRYPTION_ERROR')
    {
        parent::__construct($message, $errorType);
    }
}
