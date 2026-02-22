<?php

namespace UraEfrisSdk;

use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules\AllOf;
use ReflectionClass;
use ReflectionProperty;
use ReflectionNamedType;
use Exception;
use UraEfrisSdk\Exceptions\ValidationException;
use UraEfrisSdk\Schema\ApiEnvelope;
use UraEfrisSdk\Schema\CustomTypes;
use UraEfrisSdk\Schema\SchemaRegistry;

/**
 * Validator - Validates request/response data against PHP schema classes.
 * 
 * Provides detailed error messages for validation failures.
 */
class Validator
{
    /**
     * Validate request data against schema.
     *
     * @param array $data Data to validate
     * @param string $schemaKey Schema name from SchemaRegistry (e.g., 'T109')
     * @return array Validated data (filtered)
     * @throws ValidationException If validation fails
     */
    public function validate(array $data, string $schemaKey): array
    {
        $schemaMap = SchemaRegistry::get();
        
        // No schema defined - return data as-is
        if (!isset($schemaMap[$schemaKey]) || !isset($schemaMap[$schemaKey]['request'])) {
            return $data;
        }

        $className = $schemaMap[$schemaKey]['request'];
        
        // Handle string class names (from registry) vs actual classes
        if (is_string($className) && !class_exists($className)) {
            // If class doesn't exist yet (placeholder in registry), skip validation
            return $data;
        }

        // Handle array/list schemas (RootModel equivalent)
        if ($className === 'array') {
            if (!is_array($data)) {
                throw new ValidationException(
                    message: "Payload validation failed",
                    errors: ['_root' => 'Expected array']
                );
            }
            return $data;
        }

        try {
            $validated = $this->validateDataAgainstClass($data, $className);
            return $validated;
        } catch (NestedValidationException $e) {
            throw $this->formatValidationException($e);
        } catch (Exception $e) {
            throw new ValidationException(
                message: "Unexpected validation error: " . $e->getMessage(),
                errors: ['_general' => $e->getMessage()]
            );
        }
    }

    /**
     * Validate response data against schema (non-blocking).
     *
     * @param array $response Response data to validate
     * @param string $schemaKey Schema name
     * @return array Response data (unchanged if validation fails)
     */
    public function validateResponse(array $response, string $schemaKey): array
    {
        $schemaMap = SchemaRegistry::get();

        if (!isset($schemaMap[$schemaKey]) || !isset($schemaMap[$schemaKey]['response'])) {
            return $response;
        }

        $className = $schemaMap[$schemaKey]['response'];

        if (is_string($className) && !class_exists($className)) {
            return $response;
        }

        // Handle array/list schemas
        if ($className === 'array') {
            return is_array($response) ? $response : $response;
        }

        try {
            // For response, we validate but don't throw
            $this->validateDataAgainstClass($response, $className);
            return $response;
        } catch (NestedValidationException $e) {
            $errorMsg = $this->formatValidationException($e);
            error_log("⚠️  Response validation warning for {$schemaKey}: " . json_encode($errorMsg->getErrors()));
        } catch (Exception $e) {
            error_log("⚠️  Response validation error for {$schemaKey}: " . $e->getMessage());
        }

        return $response;
    }

    /**
     * Validate full EFRIS envelope.
     *
     * @param array $envelope Full API envelope
     * @param string $interfaceCode T101, T109, etc.
     * @return array Validated envelope
     * @throws ValidationException
     */
    public function validateEnvelope(array $envelope, string $interfaceCode): array
    {
        try {
            // Validate Outer Envelope Structure
            $this->validateDataAgainstClass($envelope, ApiEnvelope::class);
            
            // Validate Inner Data if present
            if (isset($envelope['data']['content']) && is_string($envelope['data']['content'])) {
                // Content is Base64 JSON, decode and validate against interface schema
                $decoded = json_decode(base64_decode($envelope['data']['content']), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->validate($decoded, $interfaceCode);
                }
            }

            return $envelope;
        } catch (NestedValidationException $e) {
            throw $this->formatValidationException($e);
        } catch (Exception $e) {
            throw new ValidationException(
                message: "Envelope validation failed: " . $e->getMessage(),
                errors: ['_general' => $e->getMessage()]
            );
        }
    }

    /**
     * Get schema field definitions for documentation.
     *
     * @param string $schemaKey Schema name
     * @return array|null Field definitions
     */
    public function getSchemaFields(string $schemaKey): ?array
    {
        $schemaMap = SchemaRegistry::get();
        
        if (!isset($schemaMap[$schemaKey])) {
            return null;
        }

        // Prefer request schema, fallback to response
        $className = $schemaMap[$schemaKey]['request'] ?? $schemaMap[$schemaKey]['response'] ?? null;

        if (!$className || !class_exists($className)) {
            return null;
        }

        if ($className === 'array') {
            return [
                '__root__' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'List of items'
                ]
            ];
        }

        $fields = [];
        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $type = $property->getType();
            // FIX: Check for ReflectionNamedType before getName()
            $typeName = ($type instanceof ReflectionNamedType) ? $type->getName() : 'mixed';
            $allowsNull = $type ? $type->allowsNull() : true;
            
            $isRequired = $type instanceof ReflectionNamedType 
                && !$type->allowsNull() 
                && !$property->isDefault();

            $fields[$property->getName()] = [
                'type' => $typeName,
                'required' => $isRequired,
                'default' => $property->isDefault() ? $property->getDefaultValue() : null,
                'description' => '' // PHP properties don't have docblock descriptions accessible via standard Reflection
            ];
        }

        return $fields;
    }

    /**
     * Get all available schema keys.
     *
     * @return array
     */
    public function getAllSchemaKeys(): array
    {
        return array_keys(SchemaRegistry::get());
    }

    // =========================================================
    // INTERNAL HELPERS
    // =========================================================

    /**
     * Validate array data against a PHP Class structure using Reflection and Respect\Validation.
     *
     * @param array $data
     * @param string $className
     * @return array
     * @throws NestedValidationException
     */
    private function validateDataAgainstClass(array $data, string $className): array
    {
        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $validator = v::keySet();

        foreach ($properties as $property) {
            $name = $property->getName();
            $type = $property->getType();
            $allowsNull = $type ? $type->allowsNull() : true;
            
            // Determine Validation Rule
            $rule = $this->getPropertyValidator($name, $type, $allowsNull);
            
            if ($allowsNull) {
                $rule = v::optional($rule);
            }

            $validator = $validator->key($name, $rule, !$allowsNull);
        }

        // Assert validation
        $validator->assert($data);

        // Filter data to only include defined properties (exclude extra)
        $validData = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            if (array_key_exists($name, $data)) {
                $validData[$name] = $data[$name];
            }
        }

        return $validData;
    }

    /**
     * Determine validation rule for a property based on name and type.
     * Uses CustomTypes for known field names.
     *
     * @param string $name
     * @param \ReflectionType|null $type
     * @param bool $allowsNull
     * @return AllOf
     */
    private function getPropertyValidator(string $name, ?\ReflectionType $type, bool $allowsNull): AllOf
    {
        // Map common field names to CustomTypes validators
        $customRule = null;
        switch ($name) {
            case 'tin':
                $customRule = CustomTypes::tin();
                break;
            case 'ninBrn':
            case 'buyerNinBrn':
                $customRule = CustomTypes::ninBrn();
                break;
            case 'deviceNo':
                $customRule = CustomTypes::deviceNo();
                break;
            case 'invoiceNo':
            case 'oriInvoiceNo':
                $customRule = CustomTypes::code20();
                break;
            case 'currency':
                $customRule = CustomTypes::currency();
                break;
            case 'invoiceType':
                $customRule = CustomTypes::invoiceType();
                break;
            case 'invoiceKind':
                $customRule = CustomTypes::invoiceKind();
                break;
            case 'issuedDate':
            case 'applicationTime':
                $customRule = CustomTypes::dtRequest(); 
                break;
            case 'grossAmount':
            case 'taxAmount':
            case 'netAmount':
            case 'total':
            case 'unitPrice':
                $customRule = CustomTypes::amount16_2();
                break;
            case 'taxRate':
                $customRule = CustomTypes::rate12_8();
                break;
            case 'buyerType':
                $customRule = CustomTypes::buyerType();
                break;
            case 'modeCode':
                $customRule = CustomTypes::modeCode();
                break;
            case 'dataSource':
                $customRule = CustomTypes::dataSource();
                break;
            case 'uuid32':
            case 'dataExchangeId':
                $customRule = CustomTypes::uuid32();
                break;
            case 'branchId':
            case 'invoiceId':
            case 'goodsCategoryId':
                $customRule = CustomTypes::code18();
                break;
            case 'item':
            case 'goodsName':
            case 'legalName':
            case 'businessName':
                $customRule = CustomTypes::code200();
                break;
            case 'address':
            case 'placeOfBusiness':
            case 'branchName':
                $customRule = CustomTypes::code500();
                break;
            case 'remarks':
            case 'reason':
            case 'description':
                $customRule = CustomTypes::code1024();
                break;
        }

        if ($customRule) {
            return $customRule;
        }

        // FIX: Check for ReflectionNamedType before getName()
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            switch ($typeName) {
                case 'string':
                    return v::stringType();
                case 'int':
                    return v::intType();
                case 'float':
                    return v::floatType();
                case 'array':
                    return v::arrayType();
                case 'bool':
                    return v::boolType();
                default:
                    // Assume object/class type - allow any object
                    return v::objectType();
            }
        }

        // Union types or unknown: allow anything (defer to runtime)
        return v::alwaysValid();
    }

    /**
     * Format Validation Exception.
     *
     * @param NestedValidationException $error
     * @return ValidationException
     */
    private function formatValidationException(NestedValidationException $error): ValidationException
    {
        $errors = [];
        
        // Respect\Validation returns messages as array with field paths as keys
        foreach ($error->getMessages() as $field => $message) {
            $errors[$field] = is_array($message) ? implode('; ', $message) : $message;
        }
        
        // If messages are flat, try to get full messages
        if (empty($errors)) {
            $errors['_general'] = $error->getFullMessage();
        }

        return new ValidationException(
            message: "Payload validation failed",
            errors: $errors,
            errorType: "VALIDATION_ERROR"
        );
    }
}