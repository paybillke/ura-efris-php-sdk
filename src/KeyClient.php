<?php

namespace UraEfrisSdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UraEfrisSdk\Exceptions\APIException;
use UraEfrisSdk\Exceptions\AuthenticationException;
use UraEfrisSdk\Exceptions\EncryptionException;
use UraEfrisSdk\Utils\CryptoUtils;

/**
 * KeyClient - Manages cryptographic keys for EFRIS API authentication
 */
class KeyClient
{
    private const T104_ENDPOINT_TEST = "https://efristest.ura.go.ug/efrisws/ws/taapp/getInformation";
    private const T104_ENDPOINT_PROD = "https://efrisws.ura.go.ug/ws/taapp/getInformation";

    private string $pfxPath;
    private string $password;
    private string $tin;
    private string $deviceNo;
    private string $brn;
    private bool $sandbox;
    private int $timeout;
    private string $taxpayerId;

    /**
     * Cached private key
     * @var \OpenSSLAsymmetricKey|resource|null
     */
    private $_privateKey = null;
    
    private ?string $_aesKey = null;
    private ?int $_aesKeyFetchedAt = null;
    private int $_aesKeyTtlSeconds;
    private ?array $_aesKeyContentJson = null;
    private LoggerInterface $logger;

    public function __construct(
        string $pfxPath,
        string $password,
        string $tin,
        string $deviceNo,
        string $brn = "",
        bool $sandbox = true,
        int $timeout = 30,
        string $taxpayerId = "1",
        ?LoggerInterface $logger = null
    ) {
        $this->pfxPath = $pfxPath;
        $this->password = $password;
        $this->tin = $tin;
        $this->deviceNo = $deviceNo;
        $this->brn = $brn;
        $this->sandbox = $sandbox;
        $this->timeout = $timeout;
        $this->taxpayerId = $taxpayerId;
        $this->_aesKeyTtlSeconds = 23 * 60 * 60;
        $this->logger = $logger ?? new NullLogger();
    }

    private function getEndpoint(): string
    {
        return $this->sandbox ? self::T104_ENDPOINT_TEST : self::T104_ENDPOINT_PROD;
    }

    /**
     * Load and cache the RSA private key from PFX file.
     *
     * @return \OpenSSLAsymmetricKey|resource
     * @throws AuthenticationException
     */
    public function loadPrivateKey()
    {
        if ($this->_privateKey === null) {
            if (!file_exists($this->pfxPath)) {
                throw new AuthenticationException("PFX file not found: {$this->pfxPath}");
            }

            $pfxData = file_get_contents($this->pfxPath);
            if ($pfxData === false) {
                throw new AuthenticationException("Failed to read PFX file: {$this->pfxPath}");
            }

            $certs = [];
            if (!openssl_pkcs12_read($pfxData, $certs, $this->password)) {
                $this->logger->error("Failed to load PFX: " . openssl_error_string());
                throw new AuthenticationException("Failed to load PFX: " . openssl_error_string());
            }

            if (!isset($certs['pkey']) || $certs['pkey'] === null) {
                throw new AuthenticationException("Private key extraction failed");
            }

            $privateKey = openssl_pkey_get_private($certs['pkey'], $this->password);
            if ($privateKey === false) {
                throw new AuthenticationException("Failed to load private key: " . openssl_error_string());
            }

            $details = openssl_pkey_get_details($privateKey);
            if ($details && isset($details['key'])) {
                $fingerprint = hash('sha256', $details['key']);
                $this->logger->info("Loaded private key with fingerprint: {$fingerprint}");
            }

            $this->_privateKey = $privateKey;
        }

        return $this->_privateKey;
    }

    /**
     * Fetch AES symmetric key from T104 endpoint.
     *
     * @param bool $force Force refresh even if cached key is valid
     * @return string|null AES symmetric key (hex) or null if fetch fails
     * @throws APIException If T104 request fails
     * @throws EncryptionException If key decryption fails
     */
    public function fetchAesKey(bool $force = false): ?string
    {
        if (!$force && $this->_aesKey !== null && $this->_aesKeyFetchedAt !== null) {
            $elapsed = time() - $this->_aesKeyFetchedAt;
            if ($elapsed < $this->_aesKeyTtlSeconds) {
                $this->logger->debug("Using cached AES key");
                return $this->_aesKey;
            }
        }

        $this->logger->info("Fetching AES symmetric key from T104 endpoint");

        $privateKey = $this->loadPrivateKey();

        $payload = CryptoUtils::buildUnencryptedRequest(
            content: [],
            interfaceCode: "T104",
            tin: $this->tin,
            deviceNo: $this->deviceNo,
            brn: $this->brn,
            privateKey: $privateKey,
            taxpayerId: $this->taxpayerId
        );

        $url = $this->getEndpoint();
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->logger->error("T104 connection error: {$curlError}");
            throw new APIException("T104 connection error: {$curlError}");
        }

        if ($statusCode !== 200) {
            $this->logger->error("T104 HTTP {$statusCode}: {$responseBody}");
            throw new APIException(
                message: "T104 HTTP {$statusCode}: {$responseBody}",
                statusCode: $statusCode
            );
        }

        $respJson = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Invalid JSON in T104 response: " . json_last_error_msg());
            throw new APIException("Invalid JSON in T104 response: " . json_last_error_msg());
        }

        $returnState = $respJson['returnStateInfo'] ?? [];

        if (($returnState['returnMessage'] ?? '') !== 'SUCCESS') {
            $errorMsg = $returnState['returnMessage'] ?? 'Unknown error';
            $errorCode = $returnState['returnCode'] ?? '99';
            $this->logger->error("T104 failed: {$errorMsg} (code: {$errorCode})");
            throw new APIException(
                message: "T104 failed: {$errorMsg}",
                returnCode: $errorCode
            );
        }

        try {
            $contentB64 = $respJson['data']['content'] ?? '';
            if (!$contentB64) {
                throw new EncryptionException("Missing content in T104 response");
            }

            $contentJson = json_decode(base64_decode($contentB64, true), true);
            if ($contentJson === null) {
                throw new EncryptionException("Failed to decode T104 content JSON");
            }

            $encryptedAesB64 = $contentJson['passowrdDes'] ?? $contentJson['passwordDes'] ?? null;

            if (!$encryptedAesB64) {
                throw new EncryptionException("Missing AES key field in T104 response");
            }

            $encryptedAes = base64_decode($encryptedAesB64, true);
            if ($encryptedAes === false) {
                throw new EncryptionException("Failed to base64 decode encrypted AES key");
            }

            /** @var string $aesKeyRaw */
            $decrypted = openssl_private_decrypt(
                $encryptedAes,
                $aesKeyRaw,
                $privateKey,
                OPENSSL_PKCS1_PADDING
            );

            if (!$decrypted) {
                throw new EncryptionException("RSA decryption of AES key failed: " . openssl_error_string());
            }

            $aesKeyCandidate = base64_decode($aesKeyRaw, true);
            if ($aesKeyCandidate === false || strlen($aesKeyCandidate) === 0) {
                $aesKeyCandidate = $aesKeyRaw;
            }

            if (strlen($aesKeyCandidate) === 8) {
                $seed = $aesKeyCandidate;
                $aesKey = substr($seed . $seed, 0, 16);
            } elseif (in_array(strlen($aesKeyCandidate), [16, 24, 32])) {
                $aesKey = $aesKeyCandidate;
            } else {
                $aesKey = substr($aesKeyCandidate, 0, 16);
            }

            if (!in_array(strlen($aesKey), [16, 24, 32])) {
                throw new EncryptionException(
                    "Cannot use AES key of length " . strlen($aesKey) . " bytes"
                );
            }

            $this->_aesKey = bin2hex($aesKey);
            $this->_aesKeyFetchedAt = time();
            $this->_aesKeyContentJson = $contentJson;

            $this->logger->info("AES key fetched and cached successfully");
            return $this->_aesKey;

        } catch (EncryptionException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error("Failed to extract AES key: " . $e->getMessage(), ['exception' => $e]);
            throw new EncryptionException("Failed to extract AES key: " . $e->getMessage());
        }
    }

    public function getAesKey(): ?string { return $this->_aesKey; }

    public function getAesKeyBytes(): ?string
    {
        return $this->_aesKey !== null ? hex2bin($this->_aesKey) : null;
    }

    public function forgetAesKey(): void
    {
        $this->_aesKey = null;
        $this->_aesKeyFetchedAt = null;
        $this->_aesKeyContentJson = null;
    }

    public function getAesKeyValidUntil(): ?string
    {
        return $this->_aesKeyFetchedAt !== null
            ? date('Y-m-d H:i:s', $this->_aesKeyFetchedAt + $this->_aesKeyTtlSeconds)
            : null;
    }

    public function isAesKeyValid(): bool
    {
        return $this->_aesKey !== null
            && $this->_aesKeyFetchedAt !== null
            && (time() - $this->_aesKeyFetchedAt) < $this->_aesKeyTtlSeconds;
    }

    public function getAesKeyContentJson(): ?array { return $this->_aesKeyContentJson; }
    public function getAesKeyFetchedAt(): ?int { return $this->_aesKeyFetchedAt; }
    public function setTaxpayerId(string $taxpayerId): void { $this->taxpayerId = $taxpayerId; }
    public function getTaxpayerId(): string { return $this->taxpayerId; }

    /**
     * Get the cached private key.
     *
     * @return \OpenSSLAsymmetricKey|resource
     */
    public function getPrivateKey()
    {
        return $this->_privateKey ?? $this->loadPrivateKey();
    }

    /**
     * Sign data using the loaded private key.
     *
     * @param string $data Data to sign
     * @return string Base64 encoded RSA-SHA1 signature
     * @throws EncryptionException
     */
    public function signData(string $data): string
    {
        return CryptoUtils::signRsaSha1($data, $this->loadPrivateKey());
    }
}