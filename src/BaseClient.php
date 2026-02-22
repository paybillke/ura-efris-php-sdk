<?php

namespace UraEfrisSdk;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use UraEfrisSdk\Exceptions\APIException;
use UraEfrisSdk\Exceptions\EncryptionException;
use UraEfrisSdk\KeyClient;
use UraEfrisSdk\Utils\CryptoUtils;

/**
 * Base client for EFRIS API communication.
 * Handles HTTP request/response lifecycle, encryption/decryption,
 * interface code mapping, and endpoint URL resolution.
 */
class BaseClient
{
    /**
     * Complete interface code mapping (All endpoints from EFRIS v23.0)
     */
    protected const INTERFACES = [
        // === SYSTEM / AUTHENTICATION ===
        'get_server_time' => 'T101',
        'client_init' => 'T102',
        'sign_in' => 'T103',
        'get_symmetric_key' => 'T104',
        'forget_password' => 'T105',
        // === INVOICE MANAGEMENT ===
        'invoice_query_all' => 'T106',
        'invoice_query_normal' => 'T107',
        'invoice_details' => 'T108',
        'billing_upload' => 'T109',
        'batch_invoice_upload' => 'T129',
        // === CREDIT / DEBIT NOTES ===
        'credit_application' => 'T110',
        'credit_note_query' => 'T111',
        'credit_note_details' => 'T112',
        'credit_note_approval' => 'T113',
        'credit_note_cancel' => 'T114',
        'query_credit_application' => 'T118',
        'void_application' => 'T120',
        'query_invalid_credit' => 'T122',
        'invoice_checks' => 'T117',
        // === TAXPAYER / BRANCH ===
        'query_taxpayer' => 'T119',
        'get_branches' => 'T138',
        'check_taxpayer_type' => 'T137',
        'query_principal_agent' => 'T180',
        // === COMMODITY / EXCISE / DICTIONARY ===
        'system_dictionary' => 'T115',
        'query_commodity_category' => 'T123',
        'query_commodity_category_page' => 'T124',
        'query_excise_duty' => 'T125',
        'commodity_incremental' => 'T134',
        'query_commodity_by_date' => 'T146',
        'query_hs_codes' => 'T185',
        // === EXCHANGE RATES ===
        'get_exchange_rates' => 'T126',
        'get_exchange_rate' => 'T121',
        // === GOODS / SERVICES ===
        'goods_upload' => 'T130',
        'goods_inquiry' => 'T127',
        'query_stock' => 'T128',
        'query_goods_by_code' => 'T144',
        // === STOCK MANAGEMENT ===
        'stock_maintain' => 'T131',
        'stock_transfer' => 'T139',
        'stock_records_query' => 'T145',
        'stock_records_query_alt' => 'T147',
        'stock_records_detail' => 'T148',
        'stock_adjust_records' => 'T149',
        'stock_adjust_detail' => 'T160',
        'stock_transfer_records' => 'T183',
        'stock_transfer_detail' => 'T184',
        'negative_stock_config' => 'T177',
        // === EDC / FUEL SPECIFIC ===
        'query_fuel_type' => 'T162',
        'upload_shift_info' => 'T163',
        'upload_edc_disconnect' => 'T164',
        'update_buyer_details' => 'T166',
        'edc_invoice_query' => 'T167',
        'query_fuel_pump_version' => 'T168',
        'query_pump_nozzle_tank' => 'T169',
        'query_edc_location' => 'T170',
        'query_edc_uom_rate' => 'T171',
        'upload_nozzle_status' => 'T172',
        'query_edc_device_version' => 'T173',
        // === AGENT / USSD ===
        'ussd_account_create' => 'T175',
        'upload_device_status' => 'T176',
        'efd_transfer' => 'T178',
        'query_agent_relation' => 'T179',
        'upload_frequent_contacts' => 'T181',
        'get_frequent_contacts' => 'T182',
        // === EXPORT / CUSTOMS ===
        'invoice_remain_details' => 'T186',
        'query_fdn_status' => 'T187',
        // === SYSTEM UTILITIES ===
        'z_report_upload' => 'T116',
        'exception_log_upload' => 'T132',
        'tcs_upgrade_download' => 'T133',
        'get_tcs_latest_version' => 'T135',
        'certificate_upload' => 'T136',
    ];

    protected array $config;
    protected KeyClient $keyClient;
    protected int $timeout;
    protected LoggerInterface $logger;

    /**
     * Initialize base client with configuration and key manager.
     *
     * @param array $config Configuration dictionary
     * @param KeyClient $keyClient KeyClient instance for cryptographic operations
     * @param LoggerInterface|null $logger Optional PSR-3 logger
     */
    public function __construct(
        array $config,
        KeyClient $keyClient,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->keyClient = $keyClient;
        $this->timeout = $config['http']['timeout'] ?? 60;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Get the API endpoint URL based on environment.
     *
     * @return string
     */
    protected function getEndpointUrl(): string
    {
        $env = $this->config['env'] ?? 'sbx';
        if ($env === 'sbx') {
            return 'https://efristest.ura.go.ug/efrisws/ws/taapp/getInformation';
        }
        return 'https://efrisws.ura.go.ug/ws/taapp/getInformation';
    }

    /**
     * Send HTTP request to EFRIS API.
     *
     * @param string $interfaceKey Interface name from INTERFACES dict
     * @param array $payload Request payload dictionary
     * @param bool $encrypt Whether to encrypt the request
     * @param bool $decrypt Whether to decrypt the response
     * @return array API response dictionary
     * @throws APIException If interface not configured or HTTP error
     * @throws EncryptionException If encryption/decryption fails
     */
    protected function send(
        string $interfaceKey,
        array $payload,
        bool $encrypt = true,
        bool $decrypt = false
    ): array {
        // Validate interface code
        if (!isset(self::INTERFACES[$interfaceKey])) {
            throw new APIException(
                message: "Interface [{$interfaceKey}] not configured",
                statusCode: 400
            );
        }

        $interfaceCode = self::INTERFACES[$interfaceKey];
        $aesKey = null;

        // Fetch AES key for encryption/decryption
        if ($encrypt || $decrypt) {
            $aesKey = $this->keyClient->fetchAesKey();
            if (!$aesKey) {
                throw new EncryptionException('AES symmetric key not available');
            }
        }

        // Load private key for signing (if encrypting)
        $privateKey = $this->keyClient->loadPrivateKey();

        // Build request envelope
        if ($encrypt && $aesKey && $privateKey) {
            $requestEnvelope = CryptoUtils::buildEncryptedRequest(
                content: $payload,
                aesKey: $aesKey,
                interfaceCode: $interfaceCode,
                tin: $this->config['tin'],
                deviceNo: $this->config['device_no'],
                brn: $this->config['brn'] ?? '',
                privateKey: $privateKey,
                taxpayerId: $this->keyClient->getTaxpayerId()
            );
        } else {
            $requestEnvelope = CryptoUtils::buildUnencryptedRequest(
                content: $payload,
                interfaceCode: $interfaceCode,
                tin: $this->config['tin'],
                deviceNo: $this->config['device_no'],
                brn: $this->config['brn'] ?? '',
                privateKey: $privateKey,
                taxpayerId: $this->keyClient->getTaxpayerId()
            );
        }

        // Debug logging (enable in development)
        $this->logger->debug("Sending request to interface {$interfaceCode}");
        $this->logger->debug("Encrypt: " . ($encrypt ? 'true' : 'false') . ", Decrypt: " . ($decrypt ? 'true' : 'false'));

        // Send HTTP request
        $url = $this->getEndpointUrl();
        $response = $this->httpPost($url, $requestEnvelope);

        // Handle HTTP errors
        if ($response['status_code'] !== 200) {
            throw new APIException(
                message: "HTTP {$response['status_code']}: {$response['body']}",
                statusCode: $response['status_code']
            );
        }

        // Parse JSON response
        $respJson = json_decode($response['body'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new APIException(
                message: "Invalid JSON response: " . json_last_error_msg(),
                statusCode: 500
            );
        }

        // Debug: Log response before unwrapping
        $this->logger->debug(
            "Response returnCode: " . ($respJson['returnStateInfo']['returnCode'] ?? 'N/A')
        );
        // var_dump($requestEnvelope);
        // Unwrap and decrypt response
        return CryptoUtils::unwrapResponse($respJson, $decrypt ? $aesKey : null);
    }

    /**
     * Perform HTTP POST request.
     *
     * @param string $url
     * @param array $data
     * @return array ['status_code' => int, 'body' => string]
     */
    protected function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new APIException(
                message: "cURL error: {$error}",
                statusCode: 0
            );
        }

        return [
            'status_code' => $statusCode,
            'body' => $body ?: ''
        ];
    }

    /**
     * Send GET-style request (uses POST with params).
     *
     * @param string $interfaceKey
     * @param array|null $params
     * @param bool $encrypt
     * @param bool $decrypt
     * @return array
     */
    public function get(
        string $interfaceKey,
        ?array $params = null,
        bool $encrypt = true,
        bool $decrypt = false
    ): array {
        return $this->send($interfaceKey, $params ?? [], $encrypt, $decrypt);
    }

    /**
     * Send POST-style request.
     *
     * @param string $interfaceKey
     * @param array|null $data
     * @param bool $encrypt
     * @param bool $decrypt
     * @return array
     */
    public function post(
        string $interfaceKey,
        ?array $data = null,
        bool $encrypt = true,
        bool $decrypt = false
    ): array {
        return $this->send($interfaceKey, $data ?? [], $encrypt, $decrypt);
    }
}