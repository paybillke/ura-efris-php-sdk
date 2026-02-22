<?php

namespace UraEfrisSdk;

use Psr\Log\LoggerInterface;
use UraEfrisSdk\Utils\TimeUtils;
use UraEfrisSdk\Validator;

// =========================================================
// MAIN CLIENT CLASS
// =========================================================
class Client extends BaseClient
{
    protected Validator $validator;
    protected KeyClient $keyClient;

    /**
     * Initialize client with configuration and key manager.
     *
     * @param array $config Configuration dictionary
     * @param KeyClient $keyClient KeyClient instance
     * @param LoggerInterface|null $logger Optional PSR-3 logger
     */
    public function __construct(
        array $config,
        KeyClient $keyClient,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($config, $keyClient, $logger);
        $this->validator = new Validator();
        $this->keyClient = $keyClient;
    }

    /**
     * Validate data against schema.
     *
     * @param array $data Data to validate
     * @param string $schemaKey Schema name from SchemaRegistry
     * @return array Validated data
     * @throws ValidationException If validation fails
     */
    protected function validate(array $data, string $schemaKey): array
    {
        return $this->validator->validate($data, $schemaKey);
    }

    // =========================================================================
    // AUTHENTICATION & INITIALIZATION
    // =========================================================================

    /**
     * T102: Client Initialization - Returns server public key.
     *
     * @return array
     * @throws APIException
     */
    public function clientInit(): array
    {
        return $this->send('client_init', [], encrypt: false, decrypt: false);
    }

    /**
     * T103: Sign In - Login and retrieve taxpayer/device information.
     *
     * @return array
     * @throws APIException
     */
    public function signIn(): array
    {
        $response = $this->send('sign_in', [], encrypt: false, decrypt: true);
        $content = $response['data']['content'] ?? [];
        $taxpayer = $content['taxpayer'] ?? [];

        if ($taxpayer && isset($taxpayer['id'])) {
            $this->keyClient->setTaxpayerId((string) $taxpayer['id']);
            $this->logger->debug("Updated taxpayerID from sign_in: " . $this->keyClient->getTaxpayerId());
        }

        return $response;
    }

    /**
     * T104: Get Symmetric Key - Fetch AES key for encryption.
     *
     * @param bool $force Force refresh
     * @return array
     * @throws EncryptionException
     */
    public function getSymmetricKey(bool $force = false): array
    {
        $this->keyClient->fetchAesKey(force: $force);
        return $this->keyClient->getAesKeyContentJson() ?: [];
    }

    /**
     * T105: Forget Password - Reset user password.
     *
     * @param string $userName
     * @param string $newPassword
     * @return array
     * @throws APIException
     */
    public function forgetPassword(string $userName, string $newPassword): array
    {
        $payload = [
            'userName' => $userName,
            'changedPassword' => $newPassword
        ];
        return $this->send('forget_password', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T115: System Dictionary Update - Get tax rates, currencies, etc.
     *
     * @param array|null $data
     * @return array
     * @throws APIException
     */
    public function updateSystemDictionary(?array $data = null): array
    {
        return $this->send('system_dictionary', $data ?? [], encrypt: false, decrypt: true);
    }

    // =========================================================================
    // INVOICE OPERATIONS
    // =========================================================================

    /**
     * T109: Billing Upload - Upload invoice/receipt/debit note.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function fiscaliseInvoice(array $data): array
    {
        $validated = $this->validate($data, 'T109');
        return $this->send('billing_upload', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T129: Batch Invoice Upload - Upload multiple invoices.
     *
     * @param array $invoices
     * @return array
     * @throws APIException
     */
    public function fiscaliseBatchInvoices(array $invoices): array
    {
        $payload = [];
        foreach ($invoices as $inv) {
            $payload[] = [
                'invoiceContent' => $inv['invoiceContent'] ?? '',
                'invoiceSignature' => $inv['invoiceSignature'] ?? ''
            ];
        }
        return $this->send('batch_invoice_upload', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T108: Invoice Details - Get full invoice details.
     *
     * @param string $invoiceNo
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function verifyInvoice(string $invoiceNo): array
    {
        $validated = $this->validate(['invoiceNo' => $invoiceNo], 'T108');
        return $this->send('invoice_details', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T107: Query Normal Invoice/Receipt - For credit/debit note eligibility.
     *
     * @param array $filters
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function queryInvoices(array $filters): array
    {
        $validated = $this->validate($filters, 'T107');
        return $this->send('invoice_query_normal', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T106: Invoice/Receipt Query - All invoice types with pagination.
     *
     * @param array $filters
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function queryAllInvoices(array $filters): array
    {
        $validated = $this->validate($filters, 'T106');
        return $this->send('invoice_query_all', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T117: Invoice Checks - Batch verify multiple invoices.
     *
     * @param array $invoiceChecks
     * @return array
     * @throws APIException
     */
    public function verifyInvoicesBatch(array $invoiceChecks): array
    {
        $payload = [];
        foreach ($invoiceChecks as $check) {
            $payload[] = [
                'invoiceNo' => $check['invoiceNo'],
                'invoiceType' => $check['invoiceType']
            ];
        }
        return $this->send('invoice_checks', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T186: Invoice Remain Details - Get invoice with remaining quantities.
     *
     * @param string $invoiceNo
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function invoiceRemainDetails(string $invoiceNo): array
    {
        $validated = $this->validate(['invoiceNo' => $invoiceNo], 'T186');
        return $this->send('invoice_remain_details', $validated, encrypt: true, decrypt: true);
    }

    // =========================================================================
    // CREDIT/DEBIT NOTE OPERATIONS
    // =========================================================================

    /**
     * T110: Credit Application - Apply for credit note.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function applyCreditNote(array $data): array
    {
        $data['invoiceApplyCategoryCode'] = $data['invoiceApplyCategoryCode'] ?? '101';
        $validated = $this->validate($data, 'T110');
        return $this->send('credit_application', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T110: Debit Note Application - Apply for debit note.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function applyDebitNote(array $data): array
    {
        $data['invoiceApplyCategoryCode'] = '104';
        $validated = $this->validate($data, 'T110');
        return $this->send('credit_application', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T111: Credit/Debit Note Application List Query.
     *
     * @param array $filters
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function queryCreditNoteStatus(array $filters): array
    {
        $validated = $this->validate($filters, 'T111');
        return $this->send('credit_note_query', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T112: Credit Note Application Details.
     *
     * @param string $applicationId
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function getCreditApplicationDetail(string $applicationId): array
    {
        $validated = $this->validate(['id' => $applicationId], 'T112');
        return $this->send('credit_application_detail', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T113: Credit Note Approval - Approve or reject application.
     *
     * @param string $referenceNo
     * @param bool $approve
     * @param string $taskId
     * @param string $remark
     * @return array
     * @throws APIException
     */
    public function approveCreditNote(
        string $referenceNo,
        bool $approve,
        string $taskId,
        string $remark
    ): array {
        $payload = [
            'referenceNo' => $referenceNo,
            'approveStatus' => $approve ? '101' : '103',
            'taskId' => $taskId,
            'remark' => $remark
        ];
        return $this->send('credit_note_approval', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T114: Cancel Credit/Debit Note Application.
     *
     * @param string $oriInvoiceId
     * @param string $invoiceNo
     * @param string $reasonCode
     * @param string|null $reason
     * @param string $cancelType
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function cancelCreditNoteApplication(
        string $oriInvoiceId,
        string $invoiceNo,
        string $reasonCode,
        ?string $reason = null,
        string $cancelType = '104'
    ): array {
        $payload = [
            'oriInvoiceId' => $oriInvoiceId,
            'invoiceNo' => $invoiceNo,
            'reasonCode' => $reasonCode,
            'reason' => $reason,
            'invoiceApplyCategoryCode' => $cancelType
        ];
        $validated = $this->validate($payload, 'T114');
        return $this->send('credit_note_cancel', $validated, encrypt: true, decrypt: false);
    }

    /**
     * T122: Query Cancel Credit Note Details.
     *
     * @param string $invoiceNo
     * @return array
     * @throws APIException
     */
    public function queryInvalidCreditNote(string $invoiceNo): array
    {
        return $this->send('query_invalid_credit', ['invoiceNo' => $invoiceNo], encrypt: true, decrypt: true);
    }

    /**
     * T120: Void Credit/Debit Note Application.
     *
     * @param string $businessKey
     * @param string $referenceNo
     * @return array
     * @throws APIException
     */
    public function voidCreditDebitApplication(string $businessKey, string $referenceNo): array
    {
        $payload = [
            'businessKey' => $businessKey,
            'referenceNo' => $referenceNo
        ];
        return $this->send('void_application', $payload, encrypt: true, decrypt: false);
    }

    // =========================================================================
    // TAXPAYER & BRANCH OPERATIONS
    // =========================================================================

    /**
     * T119: Query Taxpayer Information By TIN.
     *
     * @param string|null $tin
     * @param string|null $ninBrn
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function queryTaxpayerByTin(?string $tin = null, ?string $ninBrn = null): array
    {
        $payload = [
            'tin' => $tin,
            'ninBrn' => $ninBrn
        ];
        $validated = $this->validate($payload, 'T119');
        return $this->send('query_taxpayer', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T138: Get All Branches.
     *
     * @param string|null $tin
     * @return array
     * @throws APIException
     */
    public function getRegisteredBranches(?string $tin = null): array
    {
        $payload = $tin ? ['tin' => $tin] : [];
        return $this->send('get_branches', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T137: Check Exempt/Deemed Taxpayer.
     *
     * @param string $tin
     * @param string|null $commodityCategoryCode
     * @return array
     * @throws APIException
     */
    public function checkTaxpayerType(string $tin, ?string $commodityCategoryCode = null): array
    {
        $payload = ['tin' => $tin];
        if ($commodityCategoryCode) {
            $payload['commodityCategoryCode'] = $commodityCategoryCode;
        }
        return $this->send('check_taxpayer_type', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T180: Query Principal Agent TIN Information.
     *
     * @param string $tin
     * @param string $branchId
     * @return array
     * @throws APIException
     */
    public function queryPrincipalAgent(string $tin, string $branchId): array
    {
        $payload = [
            'tin' => $tin,
            'branchId' => $branchId
        ];
        return $this->send('query_principal_agent', $payload, encrypt: true, decrypt: true);
    }

    // =========================================================================
    // COMMODITY & EXCISE OPERATIONS
    // =========================================================================

    /**
     * T124: Query Commodity Category Pagination.
     *
     * @param int $pageNo
     * @param int $pageSize
     * @return array
     * @throws APIException
     */
    public function queryCommodityCategories(int $pageNo = 1, int $pageSize = 20): array
    {
        $payload = [
            'pageNo' => $pageNo,
            'pageSize' => $pageSize
        ];
        return $this->send('query_commodity_category_page', $payload, encrypt: false, decrypt: false);
    }

    /**
     * T123: Query All Commodity Categories.
     *
     * @return array
     * @throws APIException
     */
    public function queryCommodityCategoriesAll(): array
    {
        return $this->send('query_commodity_category', [], encrypt: false, decrypt: false);
    }

    /**
     * T134: Commodity Category Incremental Update.
     *
     * @param string $localVersion
     * @return array
     * @throws APIException
     */
    public function syncCommodityCategories(string $localVersion): array
    {
        return $this->send(
            'commodity_incremental',
            ['commodityCategoryVersion' => $localVersion],
            encrypt: true,
            decrypt: true
        );
    }

    /**
     * T146: Query Commodity/Excise Duty by Issue Date.
     *
     * @param string $categoryCode
     * @param string $itemType
     * @param string $issueDate
     * @return array
     * @throws APIException
     */
    public function queryCommodityByDate(string $categoryCode, string $itemType, string $issueDate): array
    {
        $payload = [
            'categoryCode' => $categoryCode,
            'type' => $itemType,
            'issueDate' => $issueDate
        ];
        return $this->send('query_commodity_by_date', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T125: Query Excise Duty Codes.
     *
     * @return array
     * @throws APIException
     */
    public function queryExciseDutyCodes(): array
    {
        return $this->send('query_excise_duty', [], encrypt: false, decrypt: false);
    }

    /**
     * T185: Query HS Code List.
     *
     * @return array
     * @throws APIException
     */
    public function queryHsCodes(): array
    {
        return $this->send('query_hs_codes', [], encrypt: false, decrypt: false);
    }

    // =========================================================================
    // EXCHANGE RATE OPERATIONS
    // =========================================================================

    /**
     * T121: Acquire Exchange Rate for Single Currency.
     *
     * @param string $currency
     * @param string|null $issueDate
     * @return array
     * @throws APIException
     */
    public function getExchangeRate(string $currency, ?string $issueDate = null): array
    {
        $payload = ['currency' => $currency];
        if ($issueDate) {
            $payload['issueDate'] = $issueDate;
        }
        return $this->send('get_exchange_rate', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T126: Get All Exchange Rates.
     *
     * @param string|null $issueDate
     * @return array
     * @throws APIException
     */
    public function getAllExchangeRates(?string $issueDate = null): array
    {
        $payload = [];
        if ($issueDate) {
            $payload['issueDate'] = $issueDate;
        }
        return $this->send('get_exchange_rates', $payload, encrypt: true, decrypt: true);
    }

    // =========================================================================
    // GOODS & STOCK OPERATIONS
    // =========================================================================

    /**
     * T130: Goods Upload - Add or modify goods.
     *
     * @param array $goods
     * @return array
     * @throws APIException
     */
    public function uploadGoods(array $goods): array
    {
        return $this->send('goods_upload', $goods, encrypt: true, decrypt: true);
    }

    /**
     * T127: Goods/Services Inquiry with pagination.
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function inquireGoods(array $filters): array
    {
        return $this->send('goods_inquiry', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T144: Query Goods by Code.
     *
     * @param string $goodsCode
     * @param string|null $tin
     * @return array
     * @throws APIException
     */
    public function queryGoodsByCode(string $goodsCode, ?string $tin = null): array
    {
        $payload = ['goodsCode' => $goodsCode];
        if ($tin) {
            $payload['tin'] = $tin;
        }
        return $this->send('query_goods_by_code', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T128: Query Stock Quantity by Goods ID.
     *
     * @param string $goodsId
     * @param string|null $branchId
     * @return array
     * @throws APIException
     */
    public function queryStockQuantity(string $goodsId, ?string $branchId = null): array
    {
        $payload = ['id' => $goodsId];
        if ($branchId) {
            $payload['branchId'] = $branchId;
        }
        return $this->send('query_stock', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T131: Goods Stock Maintain - Stock in/out operations.
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     * @throws APIException
     */
    public function maintainStock(array $data): array
    {
        $validated = $this->validate($data, 'T131');
        return $this->send('stock_maintain', $validated, encrypt: true, decrypt: true);
    }

    /**
     * T139: Goods Stock Transfer Between Branches.
     *
     * @param array $data
     * @return array
     * @throws APIException
     */
    public function transferStock(array $data): array
    {
        return $this->send('stock_transfer', $data, encrypt: true, decrypt: true);
    }

    /**
     * T145: Goods Stock Records Query.
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function queryStockRecords(array $filters): array
    {
        return $this->send('stock_records_query', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T147: Goods Stock Records Query (Current Branch Only).
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function queryStockRecordsAlt(array $filters): array
    {
        return $this->send('stock_records_query_alt', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T148: Goods Stock Record Detail Query.
     *
     * @param string $recordId
     * @return array
     * @throws APIException
     */
    public function queryStockRecordDetail(string $recordId): array
    {
        return $this->send('stock_records_detail', ['id' => $recordId], encrypt: true, decrypt: true);
    }

    /**
     * T149: Goods Stock Adjust Records Query.
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function queryStockAdjustRecords(array $filters): array
    {
        return $this->send('stock_adjust_records', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T160: Goods Stock Adjust Detail Query.
     *
     * @param string $adjustId
     * @return array
     * @throws APIException
     */
    public function queryStockAdjustDetail(string $adjustId): array
    {
        return $this->send('stock_adjust_detail', ['id' => $adjustId], encrypt: true, decrypt: true);
    }

    /**
     * T183: Goods Stock Transfer Records Query.
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function queryStockTransferRecords(array $filters): array
    {
        return $this->send('stock_transfer_records', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T184: Goods Stock Transfer Detail Query.
     *
     * @param string $transferId
     * @return array
     * @throws APIException
     */
    public function queryStockTransferDetail(string $transferId): array
    {
        return $this->send('stock_transfer_detail', ['id' => $transferId], encrypt: true, decrypt: true);
    }

    /**
     * T177: Negative Stock Configuration Inquiry.
     *
     * @return array
     * @throws APIException
     */
    public function queryNegativeStockConfig(): array
    {
        return $this->send('negative_stock_config', [], encrypt: false, decrypt: false);
    }

    // =========================================================================
    // EDC / FUEL SPECIFIC OPERATIONS
    // =========================================================================

    /**
     * T162: Query Fuel Type.
     *
     * @return array
     * @throws APIException
     */
    public function queryFuelType(): array
    {
        return $this->send('query_fuel_type', [], encrypt: false, decrypt: true);
    }

    /**
     * T163: Upload Shift Information.
     *
     * @param array $data
     * @return array
     * @throws APIException
     */
    public function uploadShiftInfo(array $data): array
    {
        return $this->send('upload_shift_info', $data, encrypt: true, decrypt: false);
    }

    /**
     * T164: Upload EDC Disconnection Data.
     *
     * @param array $logs
     * @return array
     * @throws APIException
     */
    public function uploadEdcDisconnect(array $logs): array
    {
        return $this->send('upload_edc_disconnect', $logs, encrypt: true, decrypt: false);
    }

    /**
     * T166: Update Buyer Details on EDC Invoice.
     *
     * @param array $data
     * @return array
     * @throws APIException
     */
    public function updateBuyerDetails(array $data): array
    {
        return $this->send('update_buyer_details', $data, encrypt: true, decrypt: false);
    }

    /**
     * T167: EDC Invoice/Receipt Inquiry.
     *
     * @param array $filters
     * @return array
     * @throws APIException
     */
    public function edcInvoiceQuery(array $filters): array
    {
        return $this->send('edc_invoice_query', $filters, encrypt: true, decrypt: true);
    }

    /**
     * T168: Query Fuel Pump Version.
     *
     * @return array
     * @throws APIException
     */
    public function queryFuelPumpVersion(): array
    {
        return $this->send('query_fuel_pump_version', [], encrypt: false, decrypt: true);
    }

    /**
     * T169: Query Pump/Nozzle/Tank by Pump Number.
     *
     * @param string $pumpId
     * @return array
     * @throws APIException
     */
    public function queryPumpNozzleTank(string $pumpId): array
    {
        return $this->send('query_pump_nozzle_tank', ['id' => $pumpId], encrypt: true, decrypt: true);
    }

    /**
     * T170: Query EFD Location History.
     *
     * @param string $deviceNumber
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     * @throws APIException
     */
    public function queryEdcLocation(
        string $deviceNumber,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $payload = ['deviceNumber' => $deviceNumber];
        if ($startDate) {
            $payload['startDate'] = $startDate;
        }
        if ($endDate) {
            $payload['endDate'] = $endDate;
        }
        return $this->send('query_edc_location', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T171: Query EDC UoM Exchange Rate.
     *
     * @return array
     * @throws APIException
     */
    public function queryEdcUomRate(): array
    {
        return $this->send('query_edc_uom_rate', [], encrypt: false, decrypt: true);
    }

    /**
     * T172: Fuel Nozzle Status Upload.
     *
     * @param string $nozzleId
     * @param string $nozzleNo
     * @param string $status
     * @return array
     * @throws APIException
     */
    public function uploadNozzleStatus(string $nozzleId, string $nozzleNo, string $status): array
    {
        $payload = [
            'nozzleId' => $nozzleId,
            'nozzleNo' => $nozzleNo,
            'status' => $status
        ];
        return $this->send('upload_nozzle_status', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T173: Query EDC Device Version.
     *
     * @return array
     * @throws APIException
     */
    public function queryEdcDeviceVersion(): array
    {
        return $this->send('query_edc_device_version', [], encrypt: false, decrypt: true);
    }

    /**
     * T176: Upload Device Issuing Status.
     *
     * @param string $deviceNo
     * @param string $status
     * @return array
     * @throws APIException
     */
    public function uploadDeviceStatus(string $deviceNo, string $status): array
    {
        $payload = [
            'deviceNo' => $deviceNo,
            'deviceIssuingStatus' => $status
        ];
        return $this->send('upload_device_status', $payload, encrypt: false, decrypt: false);
    }

    // =========================================================================
    // AGENT / USSD / FREQUENT CONTACTS
    // =========================================================================

    /**
     * T175: Account Creation for USSD Taxpayer.
     *
     * @param string $tin
     * @param string $mobileNumber
     * @return array
     * @throws APIException
     */
    public function ussdAccountCreate(string $tin, string $mobileNumber): array
    {
        $payload = [
            'tin' => $tin,
            'mobileNumber' => $mobileNumber
        ];
        return $this->send('ussd_account_create', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T178: EFD Transfer to Another Branch.
     *
     * @param string $destinationBranchId
     * @param string|null $remarks
     * @return array
     * @throws APIException
     */
    public function efdTransfer(string $destinationBranchId, ?string $remarks = null): array
    {
        $payload = ['destinationBranchId' => $destinationBranchId];
        if ($remarks) {
            $payload['remarks'] = $remarks;
        }
        return $this->send('efd_transfer', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T179: Query Agent Relation Information.
     *
     * @param string $tin
     * @return array
     * @throws APIException
     */
    public function queryAgentRelation(string $tin): array
    {
        return $this->send('query_agent_relation', ['tin' => $tin], encrypt: true, decrypt: true);
    }

    /**
     * T181: Upload Frequent Contacts.
     *
     * @param array $data
     * @return array
     * @throws APIException
     */
    public function uploadFrequentContacts(array $data): array
    {
        return $this->send('upload_frequent_contacts', $data, encrypt: true, decrypt: false);
    }

    /**
     * T182: Get Frequent Contacts.
     *
     * @param string|null $buyerTin
     * @param string|null $buyerLegalName
     * @return array
     * @throws APIException
     */
    public function getFrequentContacts(?string $buyerTin = null, ?string $buyerLegalName = null): array
    {
        $payload = [];
        if ($buyerTin) {
            $payload['buyerTin'] = $buyerTin;
        }
        if ($buyerLegalName) {
            $payload['buyerLegalName'] = $buyerLegalName;
        }
        return $this->send('get_frequent_contacts', $payload, encrypt: true, decrypt: true);
    }

    // =========================================================================
    // EXPORT / CUSTOMS OPERATIONS
    // =========================================================================

    /**
     * T187: Query Export FDN Status.
     *
     * @param string $invoiceNo
     * @return array
     * @throws APIException
     */
    public function queryFdnStatus(string $invoiceNo): array
    {
        return $this->send('query_fdn_status', ['invoiceNo' => $invoiceNo], encrypt: true, decrypt: true);
    }

    // =========================================================================
    // REPORTING & LOGGING
    // =========================================================================

    /**
     * T116: Z-Report Daily Upload.
     *
     * @param array $reportData
     * @return array
     * @throws APIException
     */
    public function uploadZReport(array $reportData): array
    {
        return $this->send('z_report_upload', $reportData, encrypt: true, decrypt: true);
    }

    /**
     * T132: Upload Exception Logs.
     *
     * @param array $logs
     * @return array
     * @throws APIException
     */
    public function uploadExceptionLogs(array $logs): array
    {
        $payload = [];
        foreach ($logs as $log) {
            $payload[] = [
                'interruptionTypeCode' => $log['interruptionTypeCode'],
                'description' => $log['description'],
                'errorDetail' => $log['errorDetail'] ?? null,
                'interruptionTime' => $log['interruptionTime']
            ];
        }
        return $this->send('exception_log_upload', $payload, encrypt: true, decrypt: false);
    }

    /**
     * T133: TCS Upgrade System File Download.
     *
     * @param string $tcsVersion
     * @param string $osType
     * @return array
     * @throws APIException
     */
    public function tcsUpgradeDownload(string $tcsVersion, string $osType): array
    {
        $payload = [
            'tcsVersion' => $tcsVersion,
            'osType' => $osType
        ];
        return $this->send('tcs_upgrade_download', $payload, encrypt: true, decrypt: true);
    }

    /**
     * T135: Get TCS Latest Version.
     *
     * @return array
     * @throws APIException
     */
    public function getTcsLatestVersion(): array
    {
        return $this->send('get_tcs_latest_version', [], encrypt: false, decrypt: true);
    }

    /**
     * T136: Certificate Public Key Upload.
     *
     * @param string $fileName
     * @param string $verifyString
     * @param string $fileContent
     * @return array
     * @throws APIException
     */
    public function certificateUpload(string $fileName, string $verifyString, string $fileContent): array
    {
        $payload = [
            'fileName' => $fileName,
            'verifyString' => $verifyString,
            'fileContent' => $fileContent
        ];
        return $this->send('certificate_upload', $payload, encrypt: false, decrypt: false);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get server time (T101).
     *
     * @return string
     * @throws APIException
     */
    public function getServerTime(): array
    {
        return $this->send('get_server_time', [], encrypt: false, decrypt: false);
    }

    /**
     * Check if client time is synchronized with server.
     *
     * @param int $toleranceMinutes
     * @param int $maxRetries
     * @return bool
     */
    public function isTimeSynced(int $toleranceMinutes = 10, int $maxRetries = 3): bool
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $serverTimeStr = $this->getServerTime();
                if (!$serverTimeStr) {
                    $this->logger->warning("Attempt {$attempt}: Could not retrieve server time");
                    sleep(1);
                    continue;
                }

                $clientTimeStr = TimeUtils::getUgandaTimestamp();
                if (TimeUtils::validateTimeSync($clientTimeStr, $serverTimeStr, $toleranceMinutes)) {
                    if ($attempt > 1) {
                        $this->logger->info("Time sync successful after {$attempt} attempt(s)");
                    }
                    return true;
                }

                $this->logger->warning("Attempt {$attempt}: Time sync failed");
                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            } catch (\Exception $e) {
                $this->logger->warning("Attempt {$attempt}: Time sync check error: " . $e->getMessage());
                if ($attempt < $maxRetries) {
                    sleep(2);
                }
            }
        }

        $this->logger->error("Time sync failed after {$maxRetries} attempts");
        return false;
    }

    /**
     * Refresh AES key if expired or not set.
     *
     * @return bool
     * @throws EncryptionException
     */
    public function refreshAesKeyIfNeeded(): bool
    {
        $fetchedAt = $this->keyClient->getAesKeyFetchedAt();
        if ($fetchedAt) {
            $elapsed = time() - $fetchedAt;
            if ($elapsed > (23 * 60 * 60)) {
                $this->keyClient->fetchAesKey(force: true);
                return true;
            }
        } elseif (!$this->keyClient->getAesKey()) {
            $this->keyClient->fetchAesKey();
            return true;
        }
        return false;
    }
}