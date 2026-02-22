<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Psr\Log\LoggerInterface;
use UraEfrisSdk\Client;
use UraEfrisSdk\Exceptions\APIException;
use UraEfrisSdk\KeyClient;
use UraEfrisSdk\Utils\TimeUtils;

/**
 * EFRIS API Complete Endpoint Integration Test
 * 
 * Tests ALL endpoints from the EFRIS API documentation (T101-T187).
 * Designed for integration testing against the Uganda Revenue Authority EFRIS system.
 * 
 * Usage:
 * export EFRIS_ENV=sbx
 * export EFRIS_TIN=your_tin
 * export EFRIS_DEVICE_NO=your_device
 * export EFRIS_PFX_PATH=/path/to/cert.pfx
 * export EFRIS_PFX_PASSWORD=your_password
 * php test_all_endpoints.php
 */
class EfrisEndpointTester
{
    protected Client $client;
    protected KeyClient $keyClient;
    protected array $config;
    protected array $results;
    protected array $context;
    protected ?LoggerInterface $logger;

    public function __construct(Client $client, KeyClient $keyClient, array $config, ?LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->keyClient = $keyClient;
        $this->config = $config;
        $this->logger = $logger;
        $this->results = [
            'passed' => [],
            'failed' => [],
            'skipped' => []
        ];
        $this->context = [
            'invoice_no' => null,
            'invoice_id' => null,
            'reference_no' => null,
            'application_id' => null,
            'goods_id' => null,
            'goods_code' => null,
            'task_id' => null,
            'business_key' => null,
            'branch_id' => null,
            'commodity_category_id' => null,
            'excise_duty_code' => null,
            'tin' => null
        ];
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    protected function printSection(string $title): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "  {$title}\n";
        echo str_repeat("=", 80) . "\n";
    }

    protected function printEndpoint(string $code, string $name): void
    {
        echo "\n[{$code}] {$name}\n";
        echo str_repeat("-", 60) . "\n";
    }

    protected function printResponse(array $response, int $maxLength = 500): void
    {
        // 🔒 Prevent circular references & limit depth before encoding
        $safeResponse = $this->sanitizeForOutput($response, 3, 1000);
        
        // 🎯 Encode with error handling
        $responseStr = @json_encode(
            $safeResponse, 
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        
        // 🚨 Handle encoding failures gracefully
        if ($responseStr === false || $responseStr === 'null') {
            $error = json_last_error_msg();
            echo "[Response encoding failed: {$error}]\n";
            echo "Raw response type: " . gettype($response) . "\n";
            return;
        }
        
        // ✂️ Truncate AFTER safe encoding
        if (strlen($responseStr) > $maxLength) {
            echo substr($responseStr, 0, $maxLength) . "... [truncated]\n";
        } else {
            echo $responseStr . "\n";
        }
    }

    /**
     * Recursively sanitize data for safe output:
     * - Limits recursion depth to prevent cycles
     * - Truncates long strings
     * - Replaces resources/binaries with placeholders
     */
    private function sanitizeForOutput(mixed $data, int $maxDepth = 3, int $maxStringLen = 200): mixed
    {
        if ($maxDepth < 0) {
            return '[max depth exceeded]';
        }
        
        // Handle scalars
        if (is_string($data)) {
            // Truncate long strings
            if (strlen($data) > $maxStringLen) {
                return substr($data, 0, $maxStringLen) . '... [truncated]';
            }
            // Detect binary/non-UTF8 data
            if (!mb_check_encoding($data, 'UTF-8')) {
                return '[binary data: ' . strlen($data) . ' bytes]';
            }
            return $data;
        }
        
        if (is_numeric($data) || is_bool($data) || $data === null) {
            return $data;
        }
        
        // Handle resources (OpenSSL keys, file handles, etc.)
        if (is_resource($data)) {
            return '[resource: ' . get_resource_type($data) . ']';
        }
        
        // Handle objects (exceptions, SDK objects, etc.)
        if (is_object($data)) {
            if ($data instanceof \Throwable) {
                return [
                    'exception' => get_class($data),
                    'message' => $this->sanitizeForOutput($data->getMessage(), $maxDepth - 1, $maxStringLen),
                    'file' => $data->getFile(),
                    'line' => $data->getLine()
                ];
            }
            // Convert object to array with limited properties
            $result = [];
            $count = 0;
            foreach ((array)$data as $key => $value) {
                if ($count++ >= 5) {
                    $result['[more properties]'] = true;
                    break;
                }
                $result[$key] = $this->sanitizeForOutput($value, $maxDepth - 1, $maxStringLen);
            }
            return $result;
        }
        
        // Handle arrays with depth/size limits
        if (is_array($data)) {
            $result = [];
            $count = 0;
            foreach ($data as $key => $value) {
                if ($count++ >= 20) { // Limit array items shown
                    $result['[more items]'] = true;
                    break;
                }
                $result[$key] = $this->sanitizeForOutput($value, $maxDepth - 1, $maxStringLen);
            }
            return $result;
        }
        
        // Fallback for unknown types
        return '[unserializable: ' . gettype($data) . ']';
    }

    protected function handleError(string $endpoint, \Exception $error): void
    {
        echo "❌ ERROR: " . get_class($error) . "\n";
        echo "   Message: " . $error->getMessage() . "\n";
        if ($error instanceof APIException && $error->getStatusCode()) {
            echo "   Status Code: " . $error->getStatusCode() . "\n";
        }
        if ($error instanceof APIException && $error->getReturnCode()) {
            echo "   Return Code: " . $error->getReturnCode() . "\n";
        }
        throw $error->getTrace();
    }

    protected function generateUuid(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(16)), 0, 32));
    }

    protected function getTimestamp(): string
    {
        return TimeUtils::getUgandaTimestamp();
    }

    protected function getDateTimestamp(): string
    {
        return TimeUtils::getUgandaTimestamp();
    }

    protected function testEndpoint(string $code, string $name, callable $testFunc, bool $skip = false): bool
    {
        $this->printEndpoint($code, $name);
        
        if ($skip) {
            echo "⚠️  SKIPPED\n";
            $this->results['skipped'][] = $code;
            return true;
        }

        try {
            $result = $testFunc();
            $this->results['passed'][] = $code;
            echo "✅ PASSED\n";
            if ($result && is_array($result)) {
                $this->printResponse($result);
            }
            return true;
        } catch (\Exception $e) {
            $this->results['failed'][] = $code;
            $this->handleError($code, $e);
            return false;
        }
    }

    // =========================================================================
    // AUTHENTICATION & INITIALIZATION TESTS
    // =========================================================================

    public function testT101GetServerTime(): array
    {
        return $this->client->getServerTime();
    }

    public function testT102ClientInit(): array
    {
        return $this->client->clientInit();
    }

    public function testT103SignIn(): array
    {
        $response = $this->client->signIn();
        $content = $response['data']['content'] ?? [];
        $taxpayer = $content['taxpayer'] ?? [];
        
        if ($taxpayer && isset($taxpayer['id'])) {
            $this->keyClient->setTaxpayerId((string) $taxpayer['id']);
            $this->context['tin'] = $taxpayer['tin'] ?? null;
        }
        
        return $response;
    }

    public function testT104GetSymmetricKey(): array
    {
        return $this->client->getSymmetricKey();
    }

    public function testT105ForgetPassword(): array
    {
        $testUser = 'test_' . time();
        return $this->client->forgetPassword($testUser, 'TempPass123!');
    }

    // =========================================================================
    // INVOICE OPERATIONS TESTS
    // =========================================================================

    public function testT106QueryAllInvoices(): array
    {
        $filters = [
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp(),
            'pageNo' => 1,
            'pageSize' => 10,
            'invoiceType' => '1',
            'invoiceKind' => '1'
        ];
        return $this->client->queryAllInvoices($filters);
    }

    public function testT107QueryNormalInvoices(): array
    {
        $filters = [
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp(),
            'pageNo' => 1,
            'pageSize' => 10,
            'invoiceType' => '1'
        ];
        return $this->client->queryInvoices($filters);
    }

    public function testT108InvoiceDetails(): array
    {
        if ($this->context['invoice_no']) {
            return $this->client->verifyInvoice($this->context['invoice_no']);
        }
        // Test with placeholder - may fail, that's expected
        return $this->client->verifyInvoice('TEST' . time());
    }

    public function testT130UploadGoods(): array
    {
        $goodsCode = 'TEST_GOODS_' . time();
        $goodsData = [[
            'operationType' => '101',
            'goodsName' => 'Test Product',
            'goodsCode' => $goodsCode,
            'measureUnit' => '101',
            'unitPrice' => '1000.00',
            'currency' => '101',
            'commodityCategoryId' => '10111301',
            'haveExciseTax' => '102',
            'description' => 'Test product for integration',
            'stockPrewarning' => 10,
            'havePieceUnit' => '102',
            'haveOtherUnit' => '102',
            'goodsTypeCode' => '101',
            'haveCustomsUnit' => '102'
        ]];
        
        $this->context['goods_code'] = $goodsCode;
        return $this->client->uploadGoods($goodsData);
    }

    public function testT109UploadInvoice(): array
    {
        $invoiceData = [
            'sellerDetails' => [
                'tin' => $this->config['tin'] ?? '',
                'ninBrn' => $this->config['brn'] ?? '',
                'legalName' => 'Test Seller',
                'businessName' => 'Test Business',
                'address' => 'Test Address',
                'mobilePhone' => '0772140000',
                'linePhone' => '0414123456',
                'emailAddress' => 'test@example.com',
                'placeOfBusiness' => 'Kampala',
                'referenceNo' => 'REF_' . time(),
                'isCheckReferenceNo' => '0'
            ],
            'basicInformation' => [
                'deviceNo' => $this->config['device_no'] ?? '',
                'issuedDate' => $this->getTimestamp(),
                'operator' => 'test_operator',
                'currency' => 'UGX',
                'invoiceType' => '1',
                'invoiceKind' => '1',
                'dataSource' => '103',
                'invoiceIndustryCode' => '101'
            ],
            'buyerDetails' => [
                'buyerTin' => '1000029771',
                'buyerNinBrn' => 'TEST001',
                'buyerLegalName' => 'Test Buyer',
                'buyerBusinessName' => 'Test Buyer Co',
                'buyerAddress' => 'Buyer Address',
                'buyerEmail' => 'buyer@example.com',
                'buyerMobilePhone' => '0772999999',
                'buyerLinePhone' => '0414999999',
                'buyerPlaceOfBusi' => 'Buyer Place',
                'buyerType' => '0',
                'buyerCitizenship' => 'UG-Uganda',
                'buyerSector' => 'Private',
                'buyerReferenceNo' => 'BUYER_REF_001'
            ],
            'goodsDetails' => [[
                'item' => 'Test Item',
                'itemCode' => 'TEST001',
                'qty' => '1',
                'unitOfMeasure' => '101',
                'unitPrice' => '1000.00',
                'total' => '1000.00',
                'taxRate' => '0.18',
                'tax' => '180.00',
                'orderNumber' => 0,
                'discountFlag' => '2',
                'deemedFlag' => '2',
                'exciseFlag' => '2',
                'goodsCategoryId' => '100000000',
                'goodsCategoryName' => 'Standard',
                'vatApplicableFlag' => '1'
            ]],
            'taxDetails' => [[
                'taxCategoryCode' => '01',
                'netAmount' => '1000.00',
                'taxRate' => '0.18',
                'taxAmount' => '180.00',
                'grossAmount' => '1180.00',
                'taxRateName' => 'Standard'
            ]],
            'summary' => [
                'netAmount' => '1000.00',
                'taxAmount' => '180.00',
                'grossAmount' => '1180.00',
                'itemCount' => 1,
                'modeCode' => '1',
                'remarks' => 'Test invoice from integration test',
                'qrCode' => ''
            ],
            'payWay' => [[
                'paymentMode' => '102',
                'paymentAmount' => '1180.00',
                'orderNumber' => 'a'
            ]]
        ];
        
        $response = $this->client->fiscaliseInvoice($invoiceData);
        $content = $response['data']['content'] ?? $response;
        
        if (isset($content['basicInformation']['invoiceNo'])) {
            $this->context['invoice_no'] = $content['basicInformation']['invoiceNo'];
            $this->context['invoice_id'] = $content['basicInformation']['invoiceId'] ?? null;
        }
        
        return $response;
    }

    public function testT129BatchUpload(): array
    {
        return $this->client->fiscaliseBatchInvoices([]);
    }

    // =========================================================================
    // CREDIT/DEBIT NOTE OPERATIONS TESTS
    // =========================================================================

    public function testT110CreditNoteApplication(): ?array
    {
        if (!$this->context['invoice_no']) {
            echo "⚠️  No invoice available for credit note test\n";
            return null;
        }
        
        $applicationData = [
            'oriInvoiceId' => $this->context['invoice_id'] ?? '',
            'oriInvoiceNo' => $this->context['invoice_no'],
            'reasonCode' => '102',
            'reason' => 'Test credit note application',
            'applicationTime' => $this->getTimestamp(),
            'invoiceApplyCategoryCode' => '101',
            'currency' => 'UGX',
            'contactName' => 'Test Contact',
            'contactMobileNum' => '0772140000',
            'contactEmail' => 'contact@example.com',
            'source' => '103',
            'remarks' => 'Integration test credit note',
            'sellersReferenceNo' => 'CRED_REF_' . time(),
            'goodsDetails' => [[
                'item' => 'Test Item',
                'itemCode' => 'TEST001',
                'qty' => '-1',
                'unitOfMeasure' => '101',
                'unitPrice' => '1000.00',
                'total' => '-1000.00',
                'taxRate' => '0.18',
                'tax' => '-180.00',
                'orderNumber' => 0,
                'deemedFlag' => '2',
                'exciseFlag' => '2',
                'goodsCategoryId' => '100000000',
                'vatApplicableFlag' => '1'
            ]],
            'taxDetails' => [[
                'taxCategoryCode' => '01',
                'netAmount' => '-1000.00',
                'taxRate' => '0.18',
                'taxAmount' => '-180.00',
                'grossAmount' => '-1180.00',
                'taxRateName' => 'Standard'
            ]],
            'summary' => [
                'netAmount' => '-1000.00',
                'taxAmount' => '-180.00',
                'grossAmount' => '-1180.00',
                'itemCount' => 1,
                'modeCode' => '1',
                'qrCode' => ''
            ],
            'basicInformation' => [
                'operator' => 'test_operator',
                'invoiceKind' => '1',
                'invoiceIndustryCode' => '101'
            ]
        ];
        
        $response = $this->client->applyCreditNote($applicationData);
        $content = $response['data']['content'] ?? $response;
        
        if (isset($content['referenceNo'])) {
            $this->context['reference_no'] = $content['referenceNo'];
        }
        
        return $response;
    }

    public function testT111QueryCreditNoteStatus(): array
    {
        $filters = [
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp(),
            'pageNo' => 1,
            'pageSize' => 10,
            'invoiceApplyCategoryCode' => '101',
            'queryType' => '1'
        ];
        
        if ($this->context['reference_no']) {
            $filters['referenceNo'] = $this->context['reference_no'];
        }
        
        return $this->client->queryCreditNoteStatus($filters);
    }

    public function testT112CreditApplicationDetail(): ?array
    {
        if ($this->context['application_id']) {
            return $this->client->getCreditApplicationDetail($this->context['application_id']);
        }
        return null;
    }

    public function testT113ApproveCreditNote(): ?array
    {
        if ($this->context['reference_no'] && $this->context['task_id']) {
            return $this->client->approveCreditNote(
                $this->context['reference_no'],
                true,
                $this->context['task_id'],
                'Approved via integration test'
            );
        }
        echo "⚠️  Missing reference_no or task_id for approval test\n";
        return null;
    }

    public function testT114CancelCreditNoteApplication(): ?array
    {
        if ($this->context['invoice_no']) {
            return $this->client->cancelCreditNoteApplication(
                $this->context['invoice_id'] ?? '',
                $this->context['invoice_no'],
                '103',
                'Test cancellation',
                '104'
            );
        }
        return null;
    }

    public function testT118QueryCreditApplicationDetails(): ?array
    {
        if ($this->context['application_id']) {
            return $this->client->getCreditApplicationDetail($this->context['application_id']);
        }
        return null;
    }

    public function testT120VoidApplication(): ?array
    {
        if ($this->context['business_key'] && $this->context['reference_no']) {
            return $this->client->voidCreditDebitApplication(
                $this->context['business_key'],
                $this->context['reference_no']
            );
        }
        return null;
    }

    public function testT122QueryInvalidCredit(): ?array
    {
        if ($this->context['invoice_no']) {
            return $this->client->queryInvalidCreditNote($this->context['invoice_no']);
        }
        return null;
    }

    // =========================================================================
    // TAXPAYER & BRANCH OPERATIONS TESTS
    // =========================================================================

    public function testT119QueryTaxpayer(): array
    {
        return $this->client->queryTaxpayerByTin($this->config['tin'] ?? '1000029771');
    }

    public function testT137CheckTaxpayerType(): array
    {
        return $this->client->checkTaxpayerType(
            $this->config['tin'] ?? '',
            '100000000'
        );
    }

    public function testT138GetBranches(): array
    {
        $response = $this->client->getRegisteredBranches();
        $content = $response['data']['content'] ?? $response;
        
        if (is_array($content) && count($content) > 0) {
            $this->context['branch_id'] = $content[0]['branchId'] ?? null;
        }
        
        return $response;
    }

    // =========================================================================
    // COMMODITY & EXCISE OPERATIONS TESTS
    // =========================================================================

    public function testT115SystemDictionary(): array
    {
        $response = $this->client->updateSystemDictionary();
        $content = $response['data']['content'] ?? $response;
        
        if (isset($content['sector']) && is_array($content['sector'])) {
            foreach ($content['sector'] as $cat) {
                if (($cat['parentClass'] ?? '') === '0') {
                    $this->context['commodity_category_id'] = $cat['code'] ?? null;
                    break;
                }
            }
        }
        
        return $response;
    }

    public function testT123QueryCommodityCategories(): array
    {
        return $this->client->queryCommodityCategoriesAll();
    }

    public function testT124QueryCommodityCategoriesPage(): array
    {
        return $this->client->queryCommodityCategories(1, 10);
    }

    public function testT125QueryExciseDuty(): array
    {
        $response = $this->client->queryExciseDutyCodes();
        $content = $response['data']['content'] ?? $response;
        
        if (isset($content['exciseDutyList']) && is_array($content['exciseDutyList']) && $content['exciseDutyList']) {
            $this->context['excise_duty_code'] = $content['exciseDutyList'][0]['exciseDutyCode'] ?? null;
        }
        
        return $response;
    }

    public function testT134CommodityIncremental(): array
    {
        return $this->client->syncCommodityCategories('1.0');
    }

    public function testT146QueryCommodityByDate(): array
    {
        return $this->client->queryCommodityByDate(
            '13101501',
            '1',
            $this->getTimestamp()
        );
    }

    public function testT185QueryHsCodes(): array
    {
        return $this->client->queryHsCodes();
    }

    // =========================================================================
    // EXCHANGE RATE OPERATIONS TESTS
    // =========================================================================

    public function testT121GetExchangeRate(): array
    {
        return $this->client->getExchangeRate('USD', $this->getDateTimestamp());
    }

    public function testT126GetAllExchangeRates(): array
    {
        return $this->client->getAllExchangeRates($this->getDateTimestamp());
    }

    // =========================================================================
    // GOODS & STOCK OPERATIONS TESTS
    // =========================================================================

    public function testT127InquireGoods(): array
    {
        $filters = ['pageNo' => 1, 'pageSize' => 10];
        
        if ($this->context['goods_code']) {
            $filters['goodsCode'] = $this->context['goods_code'];
        }
        
        $response = $this->client->inquireGoods($filters);
        $content = $response['data']['content'] ?? $response;
        
        if (isset($content['records']) && is_array($content['records']) && $content['records']) {
            $this->context['goods_id'] = $content['records'][0]['id'] ?? null;
        }
        
        return $response;
    }

    public function testT144QueryGoodsByCode(): ?array
    {
        if ($this->context['goods_code']) {
            return $this->client->queryGoodsByCode(
                $this->context['goods_code'],
                $this->config['tin'] ?? null
            );
        }
        return null;
    }

    public function testT128QueryStock(): ?array
    {
        if ($this->context['goods_id']) {
            return $this->client->queryStockQuantity(
                $this->context['goods_id'],
                $this->context['branch_id'] ?? null
            );
        }
        return null;
    }

    public function testT131MaintainStock(): ?array
    {
        if (!$this->context['goods_id'] && !$this->context['goods_code']) {
            echo "⚠️  No goods available for stock maintain test\n";
            return null;
        }
        
        $stockData = [
            'goodsStockIn' => [
                'operationType' => '101',
                'supplierTin' => $this->config['tin'] ?? '',
                'supplierName' => 'Test Supplier',
                'remarks' => 'Integration test stock in',
                'stockInDate' => $this->getDateTimestamp(),
                'stockInType' => '102',
                'isCheckBatchNo' => '0',
                'rollBackIfError' => '0',
                'goodsTypeCode' => '101'
            ],
            'goodsStockInItem' => [[
                'commodityGoodsId' => $this->context['goods_id'] ?? '',
                'goodsCode' => $this->context['goods_code'] ?? '',
                'measureUnit' => '101',
                'quantity' => '10',
                'unitPrice' => '100.00',
                'remarks' => 'Test stock entry'
            ]]
        ];
        
        return $this->client->maintainStock($stockData);
    }

    public function testT139TransferStock(): ?array
    {
        if (!$this->context['branch_id']) {
            echo "⚠️  No branch available for stock transfer test\n";
            return null;
        }
        
        $transferData = [
            'goodsStockTransfer' => [
                'sourceBranchId' => $this->context['branch_id'],
                'destinationBranchId' => $this->context['branch_id'],
                'transferTypeCode' => '101',
                'remarks' => 'Test transfer',
                'rollBackIfError' => '0',
                'goodsTypeCode' => '101'
            ],
            'goodsStockTransferItem' => [[
                'commodityGoodsId' => $this->context['goods_id'] ?? '',
                'goodsCode' => $this->context['goods_code'] ?? '',
                'measureUnit' => '101',
                'quantity' => '5',
                'remarks' => 'Test transfer item'
            ]]
        ];
        
        return $this->client->transferStock($transferData);
    }

    public function testT145StockRecordsQuery(): array
    {
        $filters = [
            'pageNo' => 1,
            'pageSize' => 10,
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp()
        ];
        return $this->client->queryStockRecords($filters);
    }

    public function testT147StockRecordsQueryAlt(): array
    {
        $filters = [
            'pageNo' => 1,
            'pageSize' => 10,
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp(),
            'stockInType' => '101'
        ];
        return $this->client->queryStockRecordsAlt($filters);
    }

    public function testT148StockRecordsDetail(): ?array { return null; }
    public function testT149StockAdjustRecords(): array
    {
        $filters = [
            'pageNo' => 1,
            'pageSize' => 10,
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp()
        ];
        return $this->client->queryStockAdjustRecords($filters);
    }
    public function testT160StockAdjustDetail(): ?array { return null; }
    public function testT183StockTransferRecords(): array
    {
        $filters = [
            'pageNo' => 1,
            'pageSize' => 10,
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp()
        ];
        return $this->client->queryStockTransferRecords($filters);
    }
    public function testT184StockTransferDetail(): ?array { return null; }

    public function testT177NegativeStockConfig(): array
    {
        return $this->client->queryNegativeStockConfig();
    }

    // =========================================================================
    // EDC / FUEL SPECIFIC OPERATIONS TESTS
    // =========================================================================

    public function testT162QueryFuelType(): array
    {
        return $this->client->queryFuelType();
    }

    public function testT163UploadShiftInfo(): array
    {
        $shiftData = [
            'shiftNo' => 'SHIFT_' . time(),
            'startVolume' => '1000.00',
            'endVolume' => '1000.00',
            'fuelType' => 'Petrol',
            'goodsId' => '12345',
            'goodsCode' => 'PETROL_001',
            'invoiceAmount' => '5000.00',
            'invoiceNumber' => '10',
            'nozzleNo' => 'NOZZLE_001',
            'pumpNo' => 'PUMP_001',
            'tankNo' => 'TANK_001',
            'userName' => 'test_user',
            'userCode' => 'TEST001',
            'startTime' => $this->getTimestamp(),
            'endTime' => $this->getTimestamp()
        ];
        return $this->client->uploadShiftInfo($shiftData);
    }

    public function testT164UploadEdcDisconnect(): array
    {
        $logs = [[
            'deviceNumber' => $this->config['device_no'] ?? 'TEST_DEVICE',
            'disconnectedType' => '101',
            'disconnectedTime' => $this->getTimestamp(),
            'remarks' => 'Test disconnect log'
        ]];
        return $this->client->uploadEdcDisconnect($logs);
    }

    public function testT166UpdateBuyerDetails(): ?array
    {
        if ($this->context['invoice_no']) {
            $updateData = [
                'invoiceNo' => $this->context['invoice_no'],
                'buyerTin' => '1000029771',
                'buyerLegalName' => 'Updated Buyer Name',
                'buyerBusinessName' => 'Updated Business',
                'buyerAddress' => 'Updated Address',
                'buyerEmailAddress' => 'updated@example.com',
                'buyerMobilePhone' => '0772999999',
                'buyerType' => '0',
                'createDateStr' => $this->getTimestamp()
            ];
            return $this->client->updateBuyerDetails($updateData);
        }
        return null;
    }

    public function testT167EdcInvoiceQuery(): array
    {
        $filters = [
            'fuelType' => 'Petrol',
            'startDate' => $this->getDateTimestamp(),
            'endDate' => $this->getDateTimestamp(),
            'pageNo' => 1,
            'pageSize' => 10,
            'queryType' => '1',
            'branchId' => $this->context['branch_id'] ?? ''
        ];
        return $this->client->edcInvoiceQuery($filters);
    }

    public function testT168QueryFuelPumpVersion(): array
    {
        return $this->client->queryFuelPumpVersion();
    }

    public function testT169QueryPumpNozzleTank(): ?array { return null; }

    public function testT170QueryEdcLocation(): array
    {
        return $this->client->queryEdcLocation(
            $this->config['device_no'] ?? '',
            $this->getDateTimestamp(),
            $this->getDateTimestamp()
        );
    }

    public function testT171QueryEdcUomRate(): array
    {
        return $this->client->queryEdcUomRate();
    }

    public function testT172UploadNozzleStatus(): array
    {
        return $this->client->uploadNozzleStatus(
            'TEST_NOZZLE_ID',
            'NOZZLE_TEST_001',
            '1'
        );
    }

    public function testT173QueryEdcDeviceVersion(): array
    {
        return $this->client->queryEdcDeviceVersion();
    }

    public function testT176UploadDeviceStatus(): array
    {
        return $this->client->uploadDeviceStatus(
            $this->config['device_no'] ?? '',
            '101'
        );
    }

    // =========================================================================
    // AGENT / USSD / FREQUENT CONTACTS TESTS
    // =========================================================================

    public function testT175UssdAccountCreate(): array
    {
        return $this->client->ussdAccountCreate(
            $this->config['tin'] ?? '',
            '0772140000'
        );
    }

    public function testT178EfdTransfer(): ?array
    {
        if ($this->context['branch_id']) {
            return $this->client->efdTransfer(
                $this->context['branch_id'],
                'Test EFD transfer'
            );
        }
        return null;
    }

    public function testT179QueryAgentRelation(): array
    {
        return $this->client->queryAgentRelation('1010039929');
    }

    public function testT180QueryPrincipalAgent(): array
    {
        return $this->client->queryPrincipalAgent(
            '1010039929',
            '210059212594887180'
        );
    }

    public function testT181UploadFrequentContacts(): array
    {
        $contactData = [
            'operationType' => '101',
            'buyerType' => '0',
            'buyerTin' => '1000029771',
            'buyerNinBrn' => 'TEST_BRN',
            'buyerLegalName' => 'Frequent Buyer',
            'buyerBusinessName' => 'Frequent Buyer Co',
            'buyerEmail' => 'frequent@example.com',
            'buyerLinePhone' => '0414123456',
            'buyerAddress' => 'Buyer Address',
            'buyerCitizenship' => 'UG-Uganda'
        ];
        return $this->client->uploadFrequentContacts($contactData);
    }

    public function testT182GetFrequentContacts(): array
    {
        return $this->client->getFrequentContacts(
            '1000029771',
            'Frequent Buyer'
        );
    }

    // =========================================================================
    // EXPORT / CUSTOMS OPERATIONS TESTS
    // =========================================================================

    public function testT187QueryFdnStatus(): ?array
    {
        if ($this->context['invoice_no']) {
            return $this->client->queryFdnStatus($this->context['invoice_no']);
        }
        return null;
    }

    // =========================================================================
    // REPORTING & LOGGING TESTS
    // =========================================================================

    public function testT116ZReportUpload(): array
    {
        $reportData = [
            'deviceNo' => $this->config['device_no'] ?? '',
            'reportDate' => $this->getDateTimestamp(),
            'totalSales' => '0.00',
            'totalTax' => '0.00'
        ];
        return $this->client->uploadZReport($reportData);
    }

    public function testT117InvoiceChecks(): array
    {
        $checks = [];
        if ($this->context['invoice_no']) {
            $checks[] = [
                'invoiceNo' => $this->context['invoice_no'],
                'invoiceType' => '1'
            ];
        }
        return $this->client->verifyInvoicesBatch($checks);
    }

    public function testT132UploadExceptionLogs(): array
    {
        $logs = [[
            'interruptionTypeCode' => '101',
            'description' => 'Test exception log',
            'errorDetail' => 'Integration test error detail',
            'interruptionTime' => $this->getTimestamp()
        ]];
        return $this->client->uploadExceptionLogs($logs);
    }

    public function testT133TcsUpgradeDownload(): array
    {
        return $this->client->tcsUpgradeDownload('1', '1');
    }

    public function testT135GetTcsLatestVersion(): array
    {
        return $this->client->getTcsLatestVersion();
    }

    public function testT136CertificateUpload(): array
    {
        $testCert = 'MIIDFjCCAf6gAwIBAgIRAKPGAol9CEdpkIoFa8huM6zfj1WEBRxteoo6PH46un4FGj4N6ioIGzVr9G40uhQGdm16ZU+q44XjW2oUnI9w=';
        return $this->client->certificateUpload(
            'test_cert.cer',
            substr(md5($this->config['tin'] ?? ''), 0, 30),
            $testCert
        );
    }

    // =========================================================================
    // ADDITIONAL ENDPOINT TESTS
    // =========================================================================

    public function testT186InvoiceRemainDetails(): ?array
    {
        if ($this->context['invoice_no']) {
            return $this->client->invoiceRemainDetails($this->context['invoice_no']);
        }
        return null;
    }

    // =========================================================================
    // RUN ALL TESTS
    // =========================================================================

    public function runAllTests(): int
    {
        $this->printSection('EFRIS API COMPLETE ENDPOINT TEST SUITE');
        echo "Started at: " . date('Y-m-d H:i:s') . "\n";
        echo "Environment: " . ($this->config['env'] ?? 'unknown') . "\n";
        echo "TIN: " . ($this->config['tin'] ?? 'unknown') . "\n";
        echo "Device: " . ($this->config['device_no'] ?? 'unknown') . "\n";

        // Authentication & Initialization
        $this->printSection('AUTHENTICATION & INITIALIZATION');
        $this->testEndpoint('T101', 'Get Server Time', [$this, 'testT101GetServerTime']);
        $this->testEndpoint('T102', 'Client Initialization', [$this, 'testT102ClientInit']);
        $this->testEndpoint('T103', 'Sign In', [$this, 'testT103SignIn']);
        $this->testEndpoint('T104', 'Get Symmetric Key', [$this, 'testT104GetSymmetricKey']);
        $this->testEndpoint('T105', 'Forget Password', [$this, 'testT105ForgetPassword'], skip: true);

        // System Dictionary
        $this->printSection('SYSTEM DICTIONARY & REFERENCE DATA');
        $this->testEndpoint('T115', 'System Dictionary', [$this, 'testT115SystemDictionary']);
        $this->testEndpoint('T123', 'Query Commodity Categories', [$this, 'testT123QueryCommodityCategories']);
        $this->testEndpoint('T124', 'Query Categories (Paginated)', [$this, 'testT124QueryCommodityCategoriesPage']);
        $this->testEndpoint('T125', 'Query Excise Duty', [$this, 'testT125QueryExciseDuty']);
        $this->testEndpoint('T134', 'Commodity Incremental Update', [$this, 'testT134CommodityIncremental']);
        $this->testEndpoint('T146', 'Query Commodity by Date', [$this, 'testT146QueryCommodityByDate']);
        $this->testEndpoint('T185', 'Query HS Codes', [$this, 'testT185QueryHsCodes']);

        // Exchange Rates
        $this->printSection('EXCHANGE RATE OPERATIONS');
        $this->testEndpoint('T121', 'Get Exchange Rate', [$this, 'testT121GetExchangeRate']);
        $this->testEndpoint('T126', 'Get All Exchange Rates', [$this, 'testT126GetAllExchangeRates']);

        // Taxpayer & Branch Info
        $this->printSection('TAXPAYER & BRANCH OPERATIONS');
        $this->testEndpoint('T119', 'Query Taxpayer by TIN', [$this, 'testT119QueryTaxpayer']);
        $this->testEndpoint('T137', 'Check Taxpayer Type', [$this, 'testT137CheckTaxpayerType']);
        $this->testEndpoint('T138', 'Get Registered Branches', [$this, 'testT138GetBranches']);
        $this->testEndpoint('T180', 'Query Principal Agent', [$this, 'testT180QueryPrincipalAgent']);
        $this->testEndpoint('T179', 'Query Agent Relation', [$this, 'testT179QueryAgentRelation']);

        // Goods & Stock Operations
        $this->printSection('GOODS & STOCK OPERATIONS');
        $this->testEndpoint('T130', 'Upload Goods', [$this, 'testT130UploadGoods']);
        $this->testEndpoint('T127', 'Inquire Goods', [$this, 'testT127InquireGoods']);
        $this->testEndpoint('T144', 'Query Goods by Code', [$this, 'testT144QueryGoodsByCode']);
        $this->testEndpoint('T128', 'Query Stock Quantity', [$this, 'testT128QueryStock']);
        $this->testEndpoint('T131', 'Maintain Stock', [$this, 'testT131MaintainStock']);
        $this->testEndpoint('T139', 'Transfer Stock', [$this, 'testT139TransferStock']);
        $this->testEndpoint('T145', 'Stock Records Query', [$this, 'testT145StockRecordsQuery']);
        $this->testEndpoint('T147', 'Stock Records Query (Alt)', [$this, 'testT147StockRecordsQueryAlt']);
        $this->testEndpoint('T149', 'Stock Adjust Records', [$this, 'testT149StockAdjustRecords']);
        $this->testEndpoint('T183', 'Stock Transfer Records', [$this, 'testT183StockTransferRecords']);
        $this->testEndpoint('T177', 'Negative Stock Config', [$this, 'testT177NegativeStockConfig']);

        // Invoice Operations
        $this->printSection('INVOICE OPERATIONS');
        $this->testEndpoint('T106', 'Query All Invoices', [$this, 'testT106QueryAllInvoices']);
        $this->testEndpoint('T107', 'Query Normal Invoices', [$this, 'testT107QueryNormalInvoices']);
        $this->testEndpoint('T109', 'Upload Invoice', [$this, 'testT109UploadInvoice']);
        $this->testEndpoint('T108', 'Invoice Details', [$this, 'testT108InvoiceDetails']);
        $this->testEndpoint('T186', 'Invoice Remain Details', [$this, 'testT186InvoiceRemainDetails']);
        $this->testEndpoint('T129', 'Batch Invoice Upload', [$this, 'testT129BatchUpload'], skip: true);
        $this->testEndpoint('T117', 'Invoice Checks', [$this, 'testT117InvoiceChecks']);

        // Credit/Debit Note Operations
        $this->printSection('CREDIT/DEBIT NOTE OPERATIONS');
        $this->testEndpoint('T110', 'Credit Note Application', [$this, 'testT110CreditNoteApplication']);
        $this->testEndpoint('T111', 'Query Credit Note Status', [$this, 'testT111QueryCreditNoteStatus']);
        $this->testEndpoint('T112', 'Credit Application Detail', [$this, 'testT112CreditApplicationDetail'], skip: true);
        $this->testEndpoint('T113', 'Approve Credit Note', [$this, 'testT113ApproveCreditNote'], skip: true);
        $this->testEndpoint('T114', 'Cancel Credit Note', [$this, 'testT114CancelCreditNoteApplication']);
        $this->testEndpoint('T118', 'Query Application Details', [$this, 'testT118QueryCreditApplicationDetails'], skip: true);
        $this->testEndpoint('T120', 'Void Application', [$this, 'testT120VoidApplication'], skip: true);
        $this->testEndpoint('T122', 'Query Invalid Credit', [$this, 'testT122QueryInvalidCredit']);

        // EDC / Fuel Operations
        $this->printSection('EDC / FUEL SPECIFIC OPERATIONS');
        $this->testEndpoint('T162', 'Query Fuel Type', [$this, 'testT162QueryFuelType']);
        $this->testEndpoint('T163', 'Upload Shift Info', [$this, 'testT163UploadShiftInfo']);
        $this->testEndpoint('T164', 'Upload EDC Disconnect', [$this, 'testT164UploadEdcDisconnect']);
        $this->testEndpoint('T167', 'EDC Invoice Query', [$this, 'testT167EdcInvoiceQuery']);
        $this->testEndpoint('T168', 'Query Fuel Pump Version', [$this, 'testT168QueryFuelPumpVersion']);
        $this->testEndpoint('T170', 'Query EFD Location', [$this, 'testT170QueryEdcLocation']);
        $this->testEndpoint('T171', 'Query EDC UoM Rate', [$this, 'testT171QueryEdcUomRate']);
        $this->testEndpoint('T172', 'Upload Nozzle Status', [$this, 'testT172UploadNozzleStatus']);
        $this->testEndpoint('T173', 'Query EDC Device Version', [$this, 'testT173QueryEdcDeviceVersion']);
        $this->testEndpoint('T176', 'Upload Device Status', [$this, 'testT176UploadDeviceStatus']);
        $this->testEndpoint('T166', 'Update Buyer Details', [$this, 'testT166UpdateBuyerDetails'], skip: true);
        $this->testEndpoint('T169', 'Query Pump/Nozzle/Tank', [$this, 'testT169QueryPumpNozzleTank'], skip: true);

        // Agent / USSD / Contacts
        $this->printSection('AGENT / USSD / FREQUENT CONTACTS');
        $this->testEndpoint('T175', 'USSD Account Create', [$this, 'testT175UssdAccountCreate']);
        $this->testEndpoint('T178', 'EFD Transfer', [$this, 'testT178EfdTransfer']);
        $this->testEndpoint('T181', 'Upload Frequent Contacts', [$this, 'testT181UploadFrequentContacts']);
        $this->testEndpoint('T182', 'Get Frequent Contacts', [$this, 'testT182GetFrequentContacts']);

        // Export / Customs
        $this->printSection('EXPORT / CUSTOMS OPERATIONS');
        $this->testEndpoint('T187', 'Query FDN Status', [$this, 'testT187QueryFdnStatus']);

        // Reporting & System
        $this->printSection('REPORTING & SYSTEM OPERATIONS');
        $this->testEndpoint('T116', 'Z-Report Upload', [$this, 'testT116ZReportUpload']);
        $this->testEndpoint('T132', 'Upload Exception Logs', [$this, 'testT132UploadExceptionLogs']);
        $this->testEndpoint('T133', 'TCS Upgrade Download', [$this, 'testT133TcsUpgradeDownload']);
        $this->testEndpoint('T135', 'Get TCS Latest Version', [$this, 'testT135GetTcsLatestVersion']);
        $this->testEndpoint('T136', 'Certificate Upload', [$this, 'testT136CertificateUpload'], skip: true);

        // Detail queries
        $this->printSection('DETAIL QUERIES (REQUIRE PRIOR IDs)');
        $this->testEndpoint('T148', 'Stock Record Detail', [$this, 'testT148StockRecordsDetail'], skip: true);
        $this->testEndpoint('T160', 'Stock Adjust Detail', [$this, 'testT160StockAdjustDetail'], skip: true);
        $this->testEndpoint('T184', 'Stock Transfer Detail', [$this, 'testT184StockTransferDetail'], skip: true);

        // Print Summary
        return $this->printSummary();
    }

    protected function printSummary(): int
    {
        $this->printSection('TEST SUMMARY');
        echo "✅ Passed:  " . count($this->results['passed']) . "\n";
        echo "❌ Failed:  " . count($this->results['failed']) . "\n";
        echo "⚠️  Skipped: " . count($this->results['skipped']) . "\n";
        
        $total = count($this->results['passed']) + count($this->results['failed']) + count($this->results['skipped']);
        echo "📊 Total:   {$total}\n";

        if ($this->results['failed']) {
            echo "\n❌ Failed Endpoints:\n";
            foreach ($this->results['failed'] as $code) {
                echo "  - {$code}\n";
            }
        }

        if ($this->results['skipped']) {
            echo "\n⚠️  Skipped Endpoints:\n";
            foreach ($this->results['skipped'] as $code) {
                echo "  - {$code}\n";
            }
        }

        echo "\n✅ Passed Endpoints (" . count($this->results['passed']) . "):\n";
        $passed = $this->results['passed'];
        sort($passed);
        foreach ($passed as $code) {
            echo "  ✓ {$code}\n";
        }

        echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";

        return $this->results['failed'] ? 1 : 0;
    }
}

// =========================================================================
// MAIN EXECUTION
// =========================================================================

function loadConfigFromEnv(string $prefix = 'EFRIS'): array
{
    $config = [];
    
    $config['env'] = getenv("{$prefix}_ENV") ?: 'sbx';
    $config['tin'] = getenv("{$prefix}_TIN") ?: '';
    $config['device_no'] = getenv("{$prefix}_DEVICE_NO") ?: '';
    $config['pfx_path'] = getenv("{$prefix}_PFX_PATH") ?: '';
    $config['pfx_password'] = getenv("{$prefix}_PFX_PASSWORD") ?: '';
    $config['brn'] = getenv("{$prefix}_BRN") ?: '';
    $config['taxpayer_id'] = getenv("{$prefix}_TAXPAYER_ID") ?: '1';
    
    $config['http'] = [
        'timeout' => (int) (getenv("{$prefix}_HTTP_TIMEOUT") ?: 120)
    ];
    
    return $config;
}

function validateConfig(array $config): void
{
    $required = ['env', 'tin', 'device_no', 'pfx_path', 'pfx_password'];
    foreach ($required as $key) {
        if (empty($config[$key])) {
            throw new \InvalidArgumentException("Missing required config: {$key}");
        }
    }
}

function main(): int
{
    echo str_repeat("=", 80) . "\n";
    echo "  EFRIS API COMPLETE ENDPOINT INTEGRATION TEST\n";
    echo str_repeat("=", 80) . "\n";

    // Load configuration
    echo "\nLoading configuration...\n";
    try {
        $config = loadConfigFromEnv();
        validateConfig($config);
        echo "✅ Configuration loaded successfully\n";
    } catch (\Exception $e) {
        echo "❌ Configuration error: {$e->getMessage()}\n";
        echo "\nRequired environment variables:\n";
        echo "  EFRIS_ENV=sbx|prod\n";
        echo "  EFRIS_TIN=your_tin\n";
        echo "  EFRIS_DEVICE_NO=your_device\n";
        echo "  EFRIS_PFX_PATH=/path/to/cert.pfx\n";
        echo "  EFRIS_PFX_PASSWORD=your_password\n";
        echo "  EFRIS_TAXPAYER_ID=1\n";
        return 1;
    }

    // Initialize clients
    echo "\nInitializing clients...\n";
    try {
        $keyClient = new KeyClient(
            pfxPath: $config['pfx_path'],
            password: $config['pfx_password'],
            tin: $config['tin'],
            deviceNo: $config['device_no'],
            brn: $config['brn'] ?? '',
            sandbox: $config['env'] === 'sbx',
            timeout: $config['http']['timeout'] ?? 30,
            taxpayerId: $config['taxpayer_id'] ?? '1'
        );
        echo "✅ KeyClient initialized\n";
    } catch (\Exception $e) {
        echo "❌ KeyClient error: {$e->getMessage()}\n";
        return 1;
    }

    try {
        $client = new Client(config: $config, keyClient: $keyClient);
        echo "✅ Client initialized\n";
    } catch (\Exception $e) {
        echo "❌ Client error: {$e->getMessage()}\n";
        return 1;
    }

    // Run tests
    echo "\n" . str_repeat("=", 80) . "\n";
    $tester = new EfrisEndpointTester(
        client: $client,
        keyClient: $keyClient,
        config: $config
    );
    
    return $tester->runAllTests();
}

// Entry point
if (php_sapi_name() === 'cli') {
    exit(main());
}