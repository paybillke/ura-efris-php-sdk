<?php

namespace UraEfrisSdk\Utils;

use UraEfrisSdk\Exceptions\APIException;
use UraEfrisSdk\Exceptions\EncryptionException;

/**
 * Cryptographic utilities for EFRIS API
 * Handles AES encryption, RSA signing, and request/response building
 */
class CryptoUtils
{
    private const AES_BLOCK_SIZE = 16;

    /* =========================================
     * PKCS7 Padding Helpers
     * ========================================= */

    private static function pkcs7Pad(string $data): string
    {
        $padLen = self::AES_BLOCK_SIZE - (strlen($data) % self::AES_BLOCK_SIZE);
        return $data . str_repeat(chr($padLen), $padLen);
    }

    private static function pkcs7Unpad(string $data): string
    {
        $length = strlen($data);

        if ($length === 0) {
            throw new EncryptionException('Cannot unpad empty string');
        }

        $padLen = ord($data[$length - 1]);

        if ($padLen < 1 || $padLen > self::AES_BLOCK_SIZE) {
            throw new EncryptionException("Invalid PKCS7 padding length: {$padLen}");
        }

        $padding = substr($data, -$padLen);

        if ($padding !== str_repeat(chr($padLen), $padLen)) {
            throw new EncryptionException('PKCS7 padding verification failed');
        }

        return substr($data, 0, $length - $padLen);
    }

    /* =========================================
     * AES Key Normalization (CRITICAL FIX)
     * ========================================= */

    /**
     * Normalize AES key to raw binary bytes.
     * Handles hex-encoded keys (common in config files).
     */
    private static function normalizeAesKey(string $key): string
    {
        // If key looks like hex (only 0-9a-fA-F, even length), decode it
        if (ctype_xdigit($key) && strlen($key) % 2 === 0) {
            $decoded = hex2bin($key);
            if ($decoded !== false && in_array(strlen($decoded), [16, 24, 32])) {
                return $decoded;
            }
        }
        // Otherwise return as-is (assumed already raw bytes)
        return $key;
    }

    /* =========================================
     * AES ECB Encrypt
     * ========================================= */

    public static function encryptAesEcb(string $plaintext, string $key): string
    {
        $key = self::normalizeAesKey($key);
        $keyLength = strlen($key);

        if (!in_array($keyLength, [16, 24, 32])) {
            throw new EncryptionException(
                "AES key must be 16/24/32 bytes, got {$keyLength}"
            );
        }

        $padded = self::pkcs7Pad($plaintext);

        $encrypted = openssl_encrypt(
            $padded,
            "AES-" . ($keyLength * 8) . "-ECB",
            $key,
            OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
        );

        if ($encrypted === false) {
            throw new EncryptionException(
                'AES encryption failed: ' . openssl_error_string()
            );
        }

        return base64_encode($encrypted);
    }

    /* =========================================
     * AES ECB Decrypt (FIXED: Key normalization + correct order)
     * ========================================= */

    public static function decryptAesEcb(
        string $ciphertextB64,
        ?string $key,
        string $encryptCode = '2',
        string $zipCode = '0'
    ): string {
        if (!$ciphertextB64) {
            return '';
        }

        /** 1️⃣ Base64 decode */
        $dataBytes = base64_decode($ciphertextB64, true);
        if ($dataBytes === false) {
            throw new EncryptionException('Base64 decode failed');
        }

        /** 2️⃣ GZIP decompress FIRST (if zipCode === '1') */
        if ($zipCode === '1') {
            if (substr($dataBytes, 0, 2) !== "\x1f\x8b") {
                throw new EncryptionException(
                    'zipCode=1 but data is not gzipped'
                );
            }

            $unzipped = gzdecode($dataBytes);

            if ($unzipped === false) {
                throw new EncryptionException('GZIP decompression failed');
            }

            $dataBytes = $unzipped;
        }

        /** 3️⃣ AES decrypt SECOND (if encryptCode === '2') */
        if ($encryptCode === '2') {
            if (!$key) {
                throw new EncryptionException('AES key required for decrypt');
            }

            // 🔑 CRITICAL: Normalize key to raw bytes
            $key = self::normalizeAesKey($key);

            if (strlen($dataBytes) % self::AES_BLOCK_SIZE !== 0) {
                throw new EncryptionException(
                    'Ciphertext length ' . strlen($dataBytes) .
                    ' not multiple of ' . self::AES_BLOCK_SIZE
                );
            }

            $keyLength = strlen($key);

            if (!in_array($keyLength, [16, 24, 32])) {
                throw new EncryptionException(
                    "Invalid AES key length: {$keyLength} (expected 16/24/32 raw bytes)"
                );
            }

            $decrypted = openssl_decrypt(
                $dataBytes,
                "AES-" . ($keyLength * 8) . "-ECB",
                $key,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
            );

            if ($decrypted === false) {
                throw new EncryptionException(
                    'AES decryption failed: ' . openssl_error_string()
                );
            }

            /** 4️⃣ Remove PKCS7 padding */
            $dataBytes = self::pkcs7Unpad($decrypted);
        }

        return $dataBytes;
    }

    /**
     * Load private key from PFX/PKCS12 file.
     */
    public static function loadPrivateKeyFromPfx(string $pfxData, string $password)
    {
        $tempPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        file_put_contents($tempPfx, $pfxData);

        $privateKey = null;
        $certs = [];
        if (!openssl_pkcs12_read(file_get_contents($tempPfx), $certs, $password)) {
            unlink($tempPfx);
            throw new EncryptionException('Failed to extract private key from .pfx: ' . openssl_error_string());
        }

        unlink($tempPfx);

        $keyResource = openssl_pkey_get_private($certs['pkey'], $password);
        if ($keyResource === false) {
            throw new EncryptionException('Failed to load private key: ' . openssl_error_string());
        }

        return $keyResource;
    }

    /**
     * Sign data using RSA-SHA1 algorithm (required by EFRIS API).
     */
    public static function signRsaSha1(string $data, $privateKey): string
    {
        $signature = '';
        $result = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA1);

        if (!$result) {
            throw new EncryptionException('RSA-SHA1 signing failed: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Build the globalInfo section of EFRIS API requests.
     */
    public static function buildGlobalInfo(
        string $interfaceCode,
        string $tin,
        string $deviceNo,
        string $brn = '',
        string $user = 'admin',
        string $longitude = '32.5825',
        string $latitude = '0.3476',
        string $taxpayerId = ''
    ): array {
        return [
            'appId' => 'AP04',
            'version' => '1.1.20191201',
            'dataExchangeId' => strtoupper(bin2hex(random_bytes(16))),
            'interfaceCode' => $interfaceCode,
            'requestCode' => 'TP',
            'requestTime' => TimeUtils::getUgandaTimestamp(),
            'responseCode' => 'TA',
            'userName' => $user,
            'deviceMAC' => 'FFFFFFFFFFFF',
            'deviceNo' => $deviceNo,
            'tin' => $tin,
            'brn' => $brn,
            'taxpayerID' => $taxpayerId ?: '1',
            'longitude' => $longitude,
            'latitude' => $latitude,
            'agentType' => '0',
            'extendField' => [
                'responseDateFormat' => 'dd/MM/yyyy',
                'responseTimeFormat' => 'dd/MM/yyyy HH:mm:ss',
                'referenceNo' => '',
                'operatorName' => $user,
                'offlineInvoiceException' => [
                    'errorCode' => '',
                    'errorMsg' => ''
                ]
            ]
        ];
    }

    /**
     * Build an encrypted EFRIS API request envelope.
     */
    public static function buildEncryptedRequest(
        array $content,
        string $aesKey,
        string $interfaceCode,
        string $tin,
        string $deviceNo,
        string $brn,
        $privateKey,
        string $taxpayerId = ''
    ): array {
        $jsonContent = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encryptedBytes = self::encryptAesEcb($jsonContent, $aesKey);
        $signature = self::signRsaSha1($encryptedBytes, $privateKey);

        return [
            'data' => [
                'content' => $encryptedBytes,
                'signature' => $signature,
                'dataDescription' => [
                    'codeType' => '1',
                    'encryptCode' => '2',
                    'zipCode' => '0'
                ]
            ],
            'globalInfo' => self::buildGlobalInfo(
                $interfaceCode, $tin, $deviceNo, $brn, 'admin', '32.5825', '0.3476', $taxpayerId
            ),
            'returnStateInfo' => [
                'returnCode' => '',
                'returnMessage' => ''
            ]
        ];
    }

    /**
     * Build an unencrypted EFRIS API request envelope.
     */
    public static function buildUnencryptedRequest(
        array $content,
        string $interfaceCode,
        string $tin,
        string $deviceNo,
        string $brn = '',
        $privateKey = null,
        string $taxpayerId = ''
    ): array {
        $contentB64 = '';
        $signature = '';

        if ($content) {
            $jsonContent = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contentB64 = base64_encode($jsonContent);
            if ($privateKey) {
                $signature = self::signRsaSha1($contentB64, $privateKey);
            }
        }

        return [
            'data' => [
                'content' => $contentB64,
                'signature' => $signature,
                'dataDescription' => [
                    'codeType' => '0',
                    'encryptCode' => '1',
                    'zipCode' => '0'
                ]
            ],
            'globalInfo' => self::buildGlobalInfo(
                $interfaceCode, $tin, $deviceNo, $brn, 'admin', '32.5825', '0.3476', $taxpayerId
            ),
            'returnStateInfo' => [
                'returnCode' => '',
                'returnMessage' => ''
            ]
        ];
    }

    /**
     * Process EFRIS API response: Base64 decode + optional AES decrypt.
     */
    public static function unwrapResponse(array $responseJson, ?string $aesKey = null): array
    {
        $returnState = $responseJson['returnStateInfo'] ?? [];
        $returnCode = $returnState['returnCode'] ?? '';
        $returnMsg = $returnState['returnMessage'] ?? '';

        // if ($returnCode === '99' || ($returnMsg && $returnMsg !== 'SUCCESS')) {
        //     throw new APIException(
        //         message: $returnMsg ?: 'Unknown API error',
        //         statusCode: 400,
        //         returnCode: $returnCode ?: '99'
        //     );
        // }

        $dataSection = $responseJson['data'] ?? [];
        $contentB64 = $dataSection['content'] ?? '';

        if (!$contentB64) {
            return $responseJson;
        }

        $dataDesc = $dataSection['dataDescription'] ?? [];
        $codeType = $dataDesc['codeType'] ?? '0';
        $encryptCode = $dataDesc['encryptCode'] ?? '0';
        $zipCode = $dataDesc['zipCode'] ?? '0';

        try {
            if ($codeType === '1') {
                if (!$aesKey) {
                    throw new EncryptionException('Encrypted response but no AES key provided');
                }
                $contentStr = self::decryptAesEcb($contentB64, $aesKey, $encryptCode, $zipCode);
            } else {
                $decodedBytes = base64_decode($contentB64, true);
                if ($decodedBytes === false) {
                    throw new EncryptionException('Base64 decode failed');
                }
                if ($zipCode === '1') {
                    $decodedBytes = gzdecode($decodedBytes);
                    if ($decodedBytes === false) {
                        throw new EncryptionException('GZIP decompression failed');
                    }
                }
                $contentStr = $decodedBytes;
            }

            $decodedContent = json_decode($contentStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $responseJson['data']['content'] = $decodedContent;
            } else {
                $responseJson['data']['content'] = $contentStr;
            }
        } catch (\Exception $e) {
            throw new EncryptionException('Response processing failed: ' . $e->getMessage());
        }
        // var_dump($responseJson);
        return $responseJson;
    }
}