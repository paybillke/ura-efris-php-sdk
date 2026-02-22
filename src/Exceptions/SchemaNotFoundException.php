<?php

namespace UraEfrisSdk\Exceptions;

/**
 * Schema not found in registry
 */
class SchemaNotFoundException extends EFRISException
{
    public function __construct(string $schemaKey)
    {
        parent::__construct(
            "Schema '{$schemaKey}' not found in registry",
            'SCHEMA_NOT_FOUND'
        );
    }
}
