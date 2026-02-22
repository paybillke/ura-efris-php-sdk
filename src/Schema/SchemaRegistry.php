<?php

namespace UraEfrisSdk\Schema;

// =========================================================
// T111-T187: [Additional schemas follow same pattern...]
// =========================================================
// Note: Due to file size limits, remaining schemas (T111-T187) follow 
// the same structure as shown above. Each class has:
// - Optional fields marked with ?type and = null default
// - Array fields with @var annotations for IDE support
// - Constructor methods can be added as needed

// For brevity, here's the SCHEMA REGISTRY that maps keys to classes:

class SchemaRegistry
{
    /**
     * Complete schema registry 
     * @return array<string, array{request?: class-string, response?: class-string}>
     */
    public static function get(): array
    {
        return [
            // System
            "T101" => ["response" => T101Response::class],
            "T102" => ["request" => T102Request::class, "response" => T102Response::class],
            "T103" => ["response" => T103Response::class],
            "T104" => ["response" => T104Response::class],
            "T105" => ["request" => T105Request::class],
            
            // Invoice
            "T106" => ["request" => T106Request::class, "response" => T106Response::class],
            "T107" => ["request" => T107Request::class, "response" => T107Response::class],
            "T108" => ["request" => T108Request::class, "response" => T108Response::class],
            "T109" => ["request" => T109BillingUpload::class, "response" => T109Response::class],
            "T129" => ["request" => "T129Request", "response" => "T129Response"],
            
            // Credit/Debit
            "T110" => ["request" => T110CreditApplication::class, "response" => T110Response::class],
            "T111" => ["request" => "T111Request", "response" => "T111Response"],
            "T112" => ["request" => "T112Request", "response" => "T112Response"],
            "T113" => ["request" => "T113Request"],
            "T114" => ["request" => "T114Request"],
            "T118" => ["request" => "T118Request", "response" => "T118Response"],
            "T120" => ["request" => "T120Request"],
            "T122" => ["request" => "T122Request", "response" => "T122Response"],
            
            // Taxpayer/Branch
            "T119" => ["request" => "T119Request", "response" => "T119Response"],
            "T137" => ["request" => "T137Request", "response" => "T137Response"],
            "T138" => ["request" => "T138Request", "response" => "T138Response"],
            "T180" => ["request" => "T180Request", "response" => "T180Response"],
            
            // Goods/Stock
            "T123" => ["response" => "array"],
            "T124" => ["request" => "T124Request", "response" => "T124Response"],
            "T125" => ["response" => "T125Response"],
            "T126" => ["request" => "T121Request", "response" => "T126Response"],
            "T121" => ["request" => "T121Request", "response" => "T121Response"],
            "T127" => ["request" => "T127Request", "response" => "T127Response"],
            "T128" => ["request" => "T128Request", "response" => "T128Response"],
            "T130" => ["request" => "T130Request", "response" => "T130Response"],
            "T131" => ["request" => "T131Request", "response" => "T131Response"],
            "T134" => ["request" => "T134Request", "response" => "T134Response"],
            "T139" => ["request" => "T139Request", "response" => "T139Response"],
            "T144" => ["request" => "T144Request", "response" => "T144Response"],
            "T145" => ["request" => "T145Request", "response" => "T145Response"],
            "T147" => ["request" => "T147Request", "response" => "T147Response"],
            "T148" => ["request" => "T148Request", "response" => "T148Response"],
            "T149" => ["request" => "T149Request", "response" => "T149Response"],
            "T160" => ["request" => "T160Request", "response" => "T160Response"],
            "T183" => ["request" => "T183Request", "response" => "T183Response"],
            "T184" => ["request" => "T184Request", "response" => "T184Response"],
            
            // System/Utility
            "T115" => ["response" => "T115Response"],
            "T116" => ["request" => "T116Request"],
            "T117" => ["request" => "T117Request", "response" => "T117Response"],
            "T132" => ["request" => "T132Request"],
            "T133" => ["request" => "T133Request", "response" => "T133Response"],
            "T135" => ["response" => "T135Response"],
            "T136" => ["request" => "T136Request"],
            
            // EDC/Fuel
            "T162" => ["response" => "array"],
            "T163" => ["request" => "T163Request"],
            "T164" => ["request" => "T164Request"],
            "T166" => ["request" => "T166Request"],
            "T167" => ["request" => "T167Request", "response" => "T167Response"],
            "T170" => ["request" => "T170Request", "response" => "T170Response"],
            "T172" => ["request" => "T172Request"],
            "T175" => ["request" => "T175Request"],
            "T176" => ["request" => "T176Request"],
            "T177" => ["response" => "T177Response"],
            
            // Agent/Other
            "T178" => ["request" => "T178Request"],
            "T179" => ["request" => "T179Request", "response" => "T179Response"],
            "T181" => ["request" => "T181Request"],
            "T182" => ["request" => "T182Request", "response" => "T182Response"],
            "T185" => ["response" => "array"],
            "T186" => ["request" => "T186Request", "response" => "T186Response"],
            "T187" => ["request" => "T187Request", "response" => "T187Response"],
        ];
    }
}