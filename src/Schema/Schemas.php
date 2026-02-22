<?php

namespace UraEfrisSdk\Schema;

// =========================================================
// CONFIGURATION
// =========================================================

class Config
{
    public const ARBITRARY_TYPES_ALLOWED = true;
    public const POPULATE_BY_NAME = true;
    public const EXTRA_IGNORE = true;
}

// =========================================================
// OUTER ENVELOPE (Protocol Format)
// =========================================================

class DataDescription
{
    public string $codeType;    // pattern: ^[01]$
    public string $encryptCode; // pattern: ^[12]$
    public string $zipCode;     // pattern: ^[01]$
    
    public function __construct(string $codeType, string $encryptCode, string $zipCode)
    {
        $this->codeType = $codeType;
        $this->encryptCode = $encryptCode;
        $this->zipCode = $zipCode;
    }
}

class Data
{
    public ?string $content = null;           // max_length: 40000, Base64 Encoded JSON
    public ?string $signature = null;         // max_length: 500
    public DataDescription $dataDescription;
    
    public function __construct(DataDescription $dataDescription, ?string $content = null, ?string $signature = null)
    {
        $this->dataDescription = $dataDescription;
        $this->content = $content;
        $this->signature = $signature;
    }
}

class ExtendField
{
    public ?string $responseDateFormat = "dd/MM/yyyy";
    public ?string $responseTimeFormat = "dd/MM/yyyy HH:mm:ss";
    public ?string $referenceNo = null;
    public ?string $operatorName = null;
    public ?string $itemDescription = null;
    public ?string $currency = null;
    public ?string $grossAmount = null;
    public ?string $taxAmount = null;
    public ?array $offlineInvoiceException = null;
}

class GlobalInfo
{
    public string $appId;              // max_length: 5
    public string $version;            // max_length: 15
    public string $dataExchangeId;     // UUID32
    public string $interfaceCode;      // max_length: 5
    public string $requestCode;        // max_length: 5
    public string $requestTime;        // DT_REQUEST
    public string $responseCode;       // max_length: 5
    public string $userName;           // max_length: 20
    public string $deviceMAC;          // max_length: 25
    public string $deviceNo;           // DEVICE_NO
    public string $tin;                // TIN
    public ?string $brn = null;        // NIN_BRN (optional)
    public string $taxpayerID;         // max_length: 20
    public ?string $longitude = null;
    public ?string $latitude = null;
    public ?string $agentType = "0";
    public ?ExtendField $extendField = null;
}

class ReturnStateInfo
{
    public string $returnCode;                    // max_length: 4
    public ?string $returnMessage = null;         // max_length: 500
}

// =========================================================
// T101: GET SERVER TIME
// =========================================================

class T101Response
{
    public string $currentTime; // DT_RESPONSE
}

// =========================================================
// T102: CLIENT INITIALIZATION
// =========================================================

class T102Request
{
    public ?string $otp = null; // max_length: 6
}

class T102Response
{
    public string $clientPriKey; // max_length: 4000
    public string $serverPubKey; // max_length: 4000
    public string $keyTable;     // max_length: 4000
}

// =========================================================
// T103: SIGN IN / LOGIN
// =========================================================

class T103Device
{
    public string $deviceModel;      // CODE_50
    public string $deviceNo;         // DEVICE_NO
    public string $deviceStatus;     // CODE_3
    public string $deviceType;       // CODE_3
    public string $validPeriod;      // DATE_RESPONSE
    public string $offlineAmount;
    public string $offlineDays;
    public string $offlineValue;
}

class T103Taxpayer
{
    public string $id;                           // CODE_18
    public string $tin;                          // TIN
    public string $ninBrn;                       // NIN_BRN
    public string $legalName;                    // CODE_256
    public string $businessName;                 // CODE_256
    public string $taxpayerStatusId;             // CODE_3
    public string $taxpayerRegistrationStatusId; // CODE_3
    public string $taxpayerType;                 // CODE_3
    public string $businessType;                 // CODE_3
    public string $departmentId;                 // CODE_6
    public string $contactName;                  // CODE_100
    public string $contactEmail;                 // CODE_50
    public string $contactMobile;                // CODE_30
    public string $contactNumber;                // CODE_30
    public string $placeOfBusiness;              // CODE_500
}

class T103TaxpayerBranch
{
    public string $branchCode;    // CODE_10
    public string $branchName;    // CODE_500
    public string $branchType;    // CODE_3
    public string $contactName;   // CODE_100
    public string $contactEmail;  // CODE_50
    public string $contactMobile; // CODE_30
    public string $contactNumber; // CODE_30
    public string $placeOfBusiness; // CODE_1000
}

class T103TaxType
{
    public string $taxTypeName;           // CODE_200
    public string $taxTypeCode;           // CODE_3
    public string $registrationDate;      // DATE_RESPONSE
    public ?string $cancellationDate = null; // DATE_RESPONSE (optional)
}

class T103Response
{
    public T103Device $device;
    public T103Taxpayer $taxpayer;
    public ?T103TaxpayerBranch $taxpayerBranch = null;
    /** @var T103TaxType[] */
    public array $taxType;
    public string $dictionaryVersion;
    public string $issueTaxTypeRestrictions; // pattern: ^[01]$
    public string $taxpayerBranchVersion;    // CODE_20
    public string $commodityCategoryVersion; // CODE_10
    public string $exciseDutyVersion;        // CODE_10
    public ?string $sellersLogo = null;      // Base64
    public string $whetherEnableServerStock; // pattern: ^[01]$
    public string $goodsStockLimit;          // STOCK_LIMIT
    public string $exportCommodityTaxRate;
    public string $exportInvoiceExciseDuty;  // pattern: ^[01]$
    public string $maxGrossAmount;
    public string $isAllowBackDate;          // pattern: ^[01]$
    public string $isReferenceNumberMandatory; // pattern: ^[01]$
    public string $isAllowIssueRebate;       // pattern: ^[01]$
    public string $isDutyFreeTaxpayer;       // pattern: ^[01]$
    public string $isAllowIssueCreditWithoutFDN; // pattern: ^[01]$
    public string $periodDate;
    public string $isTaxCategoryCodeMandatory; // pattern: ^[01]$
    public string $isAllowIssueInvoice;      // pattern: ^[01]$
    public string $isAllowOutOfScopeVAT;     // pattern: ^[01]$
    public string $creditMemoPeriodDate;
    public string $commGoodsLatestModifyVersion; // CODE_14
    public string $financialYearDate;        // CODE_4
    public string $buyerModifiedTimes;
    public string $buyerModificationPeriod;
    public string $agentFlag;                // pattern: ^[01]$
    public string $webServiceURL;
    public string $environment;              // pattern: ^[01]$
    public string $frequentContactsLimit;
    public string $autoCalculateSectionE;   // pattern: ^[01]$
    public string $autoCalculateSectionF;   // pattern: ^[01]$
    public string $hsCodeVersion;
    public string $issueDebitNote;           // pattern: ^[01]$
    public string $qrCodeURL;
}

// =========================================================
// T104: GET SYMMETRIC KEY
// =========================================================

class T104Response
{
    public string $passowrdDes; // Typo in API spec
    public string $sign;
}

// =========================================================
// T105: FORGET PASSWORD
// =========================================================

class T105Request
{
    public string $userName;         // CODE_200
    public string $changedPassword;  // CODE_200
}

// =========================================================
// T106: INVOICE/RECEIPT QUERY
// =========================================================

class T106Request
{
    public ?string $oriInvoiceNo = null;
    public ?string $invoiceNo = null;
    public ?string $deviceNo = null;
    public ?string $buyerTin = null;
    public ?string $buyerNinBrn = null;
    public ?string $buyerLegalName = null;
    public ?string $combineKeywords = null;
    public ?string $invoiceType = null;
    public ?string $invoiceKind = null;
    public ?string $isInvalid = null;
    public ?string $isRefund = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public int $pageNo = 1;
    public int $pageSize = 20;
    public ?string $referenceNo = null;
    public ?string $branchName = null;
    public ?string $queryType = "1";
    public ?string $dataSource = null;
    public ?string $sellerTinOrNin = null;
    public ?string $sellerLegalOrBusinessName = null;
}

class T106Record
{
    public string $id;
    public string $invoiceNo;
    public string $oriInvoiceId;
    public string $oriInvoiceNo;
    public string $issuedDate;
    public ?string $buyerTin = null;
    public ?string $buyerLegalName = null;
    public ?string $buyerNinBrn = null;
    public string $currency;
    public string $grossAmount;
    public string $taxAmount;
    public string $dataSource;
    public ?string $isInvalid = null;
    public ?string $isRefund = null;
    public string $invoiceType;
    public string $invoiceKind;
    public ?string $invoiceIndustryCode = null;
    public string $branchName;
    public string $deviceNo;
    public string $uploadingTime;
    public ?string $referenceNo = null;
    public string $operator;
    public string $userName;
}

class T106Page
{
    public int $pageNo;
    public int $pageSize;
    public int $totalSize;
    public int $pageCount;
}

class T106Response
{
    public T106Page $page;
    /** @var T106Record[] */
    public array $records;
}

// =========================================================
// T107: QUERY NORMAL INVOICE/RECEIPT
// =========================================================

class T107Request
{
    public ?string $invoiceNo = null;
    public ?string $deviceNo = null;
    public ?string $buyerTin = null;
    public ?string $buyerLegalName = null;
    public ?string $invoiceType = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public int $pageNo = 1;
    public int $pageSize = 20;
    public ?string $branchName = null;
}

class T107Record
{
    public string $id;
    public string $invoiceNo;
    public string $oriInvoiceId;
    public string $oriInvoiceNo;
    public string $issuedDate;
    public string $buyerTin;
    public string $buyerBusinessName;
    public string $buyerLegalName;
    public string $tin;
    public string $businessName;
    public string $legalName;
    public string $currency;
    public string $grossAmount;
    public string $dataSource;
}

class T107Response
{
    public T106Page $page;
    /** @var T107Record[] */
    public array $records;
}

// =========================================================
// T108: INVOICE DETAILS
// =========================================================

class T108Request
{
    public string $invoiceNo; // CODE_20
}

class T108SellerDetails
{
    public string $tin;
    public string $ninBrn;
    public ?string $passportNumber = null;
    public string $legalName;
    public string $businessName;
    public ?string $address = null;
    public ?string $mobilePhone = null;
    public ?string $linePhone = null;
    public ?string $emailAddress = null;
    public ?string $placeOfBusiness = null;
    public ?string $referenceNo = null;
    public string $branchId;
    public string $branchName;
    public string $branchCode;
}

class T108BasicInformation
{
    public string $invoiceId;
    public string $invoiceNo;
    public ?string $oriInvoiceNo = null;
    public ?string $antifakeCode = null;
    public string $deviceNo;
    public string $issuedDate;
    public ?string $oriIssuedDate = null;
    public ?string $oriGrossAmount = null;
    public string $operator;
    public string $currency;
    public ?string $oriInvoiceId = null;
    public string $invoiceType;
    public string $invoiceKind;
    public string $dataSource;
    public ?string $isInvalid = null;
    public ?string $isRefund = null;
    public ?string $invoiceIndustryCode = null;
    public ?string $currencyRate = null;
}

class T108BuyerDetails
{
    public ?string $buyerTin = null;
    public ?string $buyerNinBrn = null;
    public ?string $buyerPassportNum = null;
    public ?string $buyerLegalName = null;
    public ?string $buyerBusinessName = null;
    public ?string $buyerAddress = null;
    public ?string $buyerEmail = null;
    public ?string $buyerMobilePhone = null;
    public ?string $buyerLinePhone = null;
    public ?string $buyerPlaceOfBusi = null;
    public string $buyerType;
    public ?string $buyerCitizenship = null;
    public ?string $buyerSector = null;
    public ?string $buyerReferenceNo = null;
    public ?string $deliveryTermsCode = null;
}

class T108BuyerExtend
{
    public ?string $propertyType = null;
    public ?string $district = null;
    public ?string $municipalityCounty = null;
    public ?string $divisionSubcounty = null;
    public ?string $town = null;
    public ?string $cellVillage = null;
    public ?string $effectiveRegistrationDate = null;
    public ?string $meterStatus = null;
}

class T108GoodsItem
{
    public string $invoiceItemId;
    public string $item;
    public string $itemCode;
    public $qty = null; // AMOUNT_20_8 (Decimal or string)
    public string $unitOfMeasure;
    public $unitPrice = null;
    public $total; // AMOUNT_SIGNED_16_2
    public $taxRate; // RATE_12_8
    public $tax; // AMOUNT_SIGNED_16_2
    public $discountTotal = null;
    public $discountTaxRate = null;
    public int $orderNumber;
    public string $discountFlag;
    public string $deemedFlag;
    public string $exciseFlag;
    public ?string $categoryId = null;
    public ?string $categoryName = null;
    public string $goodsCategoryId;
    public string $goodsCategoryName;
    public ?string $exciseRate = null;
    public ?string $exciseRule = null;
    public $exciseTax = null;
    public $pack = null;
    public $stick = null;
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $exciseRateName = null;
    public ?string $vatApplicableFlag = "1";
    public ?string $deemedExemptCode = null;
    public ?string $vatProjectId = null;
    public ?string $vatProjectName = null;
    public ?string $totalWeight = null;
    public ?string $hsCode = null;
    public ?string $hsName = null;
    public $pieceQty = null;
    public ?string $pieceMeasureUnit = null;
    public ?string $highSeaBondFlag = null;
    public ?string $highSeaBondCode = null;
    public ?string $highSeaBondNo = null;
}

class T108TaxDetail
{
    public string $taxCategoryCode;
    public $netAmount; // AMOUNT_16_4
    public $taxRate; // RATE_12_8
    public $taxAmount; // AMOUNT_16_4
    public $grossAmount; // AMOUNT_16_4
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $taxRateName = null;
}

class T108Summary
{
    public string $netAmount;
    public string $taxAmount;
    public string $grossAmount;
    public int $itemCount;
    public string $modeCode;
    public ?string $remarks = null;
    public ?string $qrCode = null;
}

class T108PayWay
{
    public string $paymentMode;
    public $paymentAmount; // AMOUNT_16_2
    public string $orderNumber;
}

class T108Extend
{
    public ?string $reason = null;
    public ?string $reasonCode = null;
}

class T108Custom
{
    public ?string $sadNumber = null;
    public ?string $office = null;
    public ?string $cif = null;
    public ?string $wareHouseNumber = null;
    public ?string $wareHouseName = null;
    public ?string $destinationCountry = null;
    public ?string $originCountry = null;
    public ?string $importExportFlag = null;
    public ?string $confirmStatus = null;
    public ?string $valuationMethod = null;
    public ?string $prn = null;
    public ?string $exportRegime = null;
}

class T108ImportServicesSeller
{
    public ?string $importBusinessName = null;
    public ?string $importEmailAddress = null;
    public ?string $importContactNumber = null;
    public ?string $importAddress = null;
    public ?string $importInvoiceDate = null;
    public ?string $importAttachmentName = null;
    public ?string $importAttachmentContent = null;
}

class T108AirlineGoodsDetails
{
    public string $item;
    public ?string $itemCode = null;
    public $qty;
    public string $unitOfMeasure;
    public $unitPrice;
    public $total;
    public $taxRate = null;
    public $tax = null;
    public $discountTotal = null;
    public $discountTaxRate = null;
    public int $orderNumber;
    public string $discountFlag;
    public string $deemedFlag;
    public string $exciseFlag;
    public ?string $categoryId = null;
    public ?string $categoryName = null;
    public ?string $goodsCategoryId = null;
    public ?string $goodsCategoryName = null;
    public ?string $exciseRate = null;
    public ?string $exciseRule = null;
    public $exciseTax = null;
    public $pack = null;
    public $stick = null;
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $exciseRateName = null;
}

class T108EdcDetails
{
    public ?string $tankNo = null;
    public ?string $pumpNo = null;
    public ?string $nozzleNo = null;
    public ?string $controllerNo = null;
    public ?string $acquisitionEquipmentNo = null;
    public ?string $levelGaugeNo = null;
    public ?string $mvrn = null;
    public ?string $updateTimes = null;
}

class T108AgentEntity
{
    public ?string $tin = null;
    public ?string $legalName = null;
    public ?string $businessName = null;
    public ?string $address = null;
}

class T108CreditNoteExtend
{
    public ?string $preGrossAmount = null;
    public ?string $preTaxAmount = null;
    public ?string $preNetAmount = null;
}

class T108Response
{
    public T108SellerDetails $sellerDetails;
    public T108BasicInformation $basicInformation;
    public T108BuyerDetails $buyerDetails;
    public ?T108BuyerExtend $buyerExtend = null;
    /** @var T108GoodsItem[] */
    public array $goodsDetails;
    /** @var T108TaxDetail[] */
    public array $taxDetails;
    public T108Summary $summary;
    public ?array $payWay = null;
    public ?T108Extend $extend = null;
    public ?T108Custom $custom = null;
    public ?T108ImportServicesSeller $importServicesSeller = null;
    public ?array $airlineGoodsDetails = null;
    public ?T108EdcDetails $edcDetails = null;
    public ?T108AgentEntity $agentEntity = null;
    public ?T108CreditNoteExtend $creditNoteExtend = null;
    public ?array $existInvoiceList = null;
}

// =========================================================
// T109: INVOICE UPLOAD (BILLING)
// =========================================================

class T109SellerDetails
{
    public string $tin;
    public ?string $ninBrn = null;
    public string $legalName;
    public ?string $businessName = null;
    public ?string $address = null;
    public ?string $mobilePhone = null;
    public ?string $linePhone = null;
    public string $emailAddress;
    public ?string $placeOfBusiness = null;
    public ?string $referenceNo = null;
    public ?string $branchId = null;
    public ?string $isCheckReferenceNo = "0";
}

class T109BasicInformation
{
    public ?string $invoiceNo = null;
    public ?string $antifakeCode = null;
    public string $deviceNo;
    public string $issuedDate; // DT_REQUEST
    public string $operator;
    public string $currency;
    public ?string $oriInvoiceId = null;
    public string $invoiceType;
    public string $invoiceKind;
    public string $dataSource;
    public ?string $invoiceIndustryCode = null;
    public ?string $isBatch = "0";
}

class T109BuyerDetails
{
    public ?string $buyerTin = null;
    public ?string $buyerNinBrn = null;
    public ?string $buyerPassportNum = null;
    public ?string $buyerLegalName = null;
    public ?string $buyerBusinessName = null;
    public ?string $buyerAddress = null;
    public ?string $buyerEmail = null;
    public ?string $buyerMobilePhone = null;
    public ?string $buyerLinePhone = null;
    public ?string $buyerPlaceOfBusi = null;
    public string $buyerType;
    public ?string $buyerCitizenship = null;
    public ?string $buyerSector = null;
    public ?string $buyerReferenceNo = null;
    public ?string $nonResidentFlag = "0";
    public ?string $deliveryTermsCode = null;
}

class T109GoodsItem
{
    public string $item;
    public string $itemCode;
    public $qty = null;
    public string $unitOfMeasure;
    public $unitPrice = null;
    public $total;
    public $taxRate;
    public $tax;
    public $discountTotal = null;
    public $discountTaxRate = null;
    public int $orderNumber;
    public string $discountFlag;
    public string $deemedFlag;
    public string $exciseFlag;
    public ?string $categoryId = null;
    public ?string $categoryName = null;
    public string $goodsCategoryId;
    public string $goodsCategoryName;
    public ?string $exciseRate = null;
    public ?string $exciseRule = null;
    public $exciseTax = null;
    public $pack = null;
    public $stick = null;
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $exciseRateName = null;
    public ?string $vatApplicableFlag = "1";
    public ?string $deemedExemptCode = null;
    public ?string $vatProjectId = null;
    public ?string $vatProjectName = null;
    public ?string $hsCode = null;
    public ?string $hsName = null;
    public ?string $totalWeight = null;
    public $pieceQty = null;
    public ?string $pieceMeasureUnit = null;
    public ?string $highSeaBondFlag = null;
    public ?string $highSeaBondCode = null;
    public ?string $highSeaBondNo = null;
}

class T109TaxDetail
{
    public string $taxCategoryCode;
    public $netAmount;
    public $taxRate;
    public $taxAmount;
    public $grossAmount;
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $taxRateName = null;
}

class T109Summary
{
    public string $netAmount;
    public string $taxAmount;
    public string $grossAmount;
    public int $itemCount;
    public string $modeCode;
    public ?string $remarks = null;
    public ?string $qrCode = null;
}

class T109PayWay
{
    public string $paymentMode;
    public $paymentAmount;
    public string $orderNumber;
}

class T109Extend
{
    public ?string $reason = null;
    public ?string $reasonCode = null;
}

class T109BillingUpload
{
    public T109SellerDetails $sellerDetails;
    public T109BasicInformation $basicInformation;
    public ?T109BuyerDetails $buyerDetails = null;
    public ?T108BuyerExtend $buyerExtend = null;
    /** @var T109GoodsItem[] */
    public array $goodsDetails;
    /** @var T109TaxDetail[] */
    public array $taxDetails;
    public T109Summary $summary;
    public ?array $payWay = null;
    public ?T109Extend $extend = null;
    public ?T108ImportServicesSeller $importServicesSeller = null;
    public ?array $airlineGoodsDetails = null;
    public ?T108EdcDetails $edcDetails = null;
}

class T109Response
{
    public T108SellerDetails $sellerDetails;
    public T108BasicInformation $basicInformation;
    public T108BuyerDetails $buyerDetails;
    /** @var T108GoodsItem[] */
    public array $goodsDetails;
    /** @var T108TaxDetail[] */
    public array $taxDetails;
    public T108Summary $summary;
    public ?array $payWay = null;
    public ?T108Extend $extend = null;
    public ?T108ImportServicesSeller $importServicesSeller = null;
    public ?array $airlineGoodsDetails = null;
    public ?T108EdcDetails $edcDetails = null;
    public ?array $existInvoiceList = null;
    public ?T108AgentEntity $agentEntity = null;
}

// =========================================================
// T110: CREDIT NOTE APPLICATION
// =========================================================

class T110GoodsItem
{
    public string $item;
    public string $itemCode;
    public $qty; // AMOUNT_SIGNED_20_8 (negative)
    public string $unitOfMeasure;
    public $unitPrice;
    public $total; // AMOUNT_SIGNED_16_2 (negative)
    public $taxRate;
    public $tax; // AMOUNT_SIGNED_16_2 (negative)
    public int $orderNumber;
    public string $deemedFlag;
    public string $exciseFlag;
    public ?string $categoryId = null;
    public ?string $categoryName = null;
    public string $goodsCategoryId;
    public string $goodsCategoryName;
    public ?string $exciseRate = null;
    public ?string $exciseRule = null;
    public $exciseTax = null;
    public $pack = null;
    public $stick = null;
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $exciseRateName = null;
    public ?string $vatApplicableFlag = "1";
}

class T110TaxDetail
{
    public string $taxCategoryCode;
    public $netAmount; // AMOUNT_SIGNED_16_4 (negative)
    public $taxRate;
    public $taxAmount; // AMOUNT_SIGNED_16_4 (negative)
    public $grossAmount; // AMOUNT_SIGNED_16_4 (negative)
    public ?string $exciseUnit = null;
    public ?string $exciseCurrency = null;
    public ?string $taxRateName = null;
}

class T110Summary
{
    public $netAmount; // AMOUNT_SIGNED_16_2
    public $taxAmount; // AMOUNT_SIGNED_16_2
    public $grossAmount; // AMOUNT_SIGNED_16_2
    public int $itemCount;
    public string $modeCode;
    public ?string $qrCode = null;
}

class T110Attachment
{
    public string $fileName;
    public string $fileType;
    public string $fileContent; // Base64
}

class T110CreditApplication
{
    public string $oriInvoiceId;
    public string $oriInvoiceNo;
    public string $reasonCode;
    public ?string $reason = null;
    public string $applicationTime;
    public string $invoiceApplyCategoryCode;
    public string $currency;
    public ?string $contactName = null;
    public ?string $contactMobileNum = null;
    public ?string $contactEmail = null;
    public string $source;
    public ?string $remarks = null;
    public ?string $sellersReferenceNo = null;
    /** @var T110GoodsItem[] */
    public array $goodsDetails;
    /** @var T110TaxDetail[] */
    public array $taxDetails;
    public T110Summary $summary;
    public ?array $payWay = null;
    public ?T109BuyerDetails $buyerDetails = null;
    public ?T108ImportServicesSeller $importServicesSeller = null;
    public ?T109BasicInformation $basicInformation = null;
    public ?array $attachmentList = null;
}

class T110Response
{
    public string $referenceNo;
}

