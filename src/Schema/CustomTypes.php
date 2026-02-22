<?php

namespace UraEfrisSdk\Schema;

use Respect\Validation\Validator as v;
use Respect\Validation\Rules\AllOf;

// =========================================================
// CUSTOM TYPE VALIDATORS
// =========================================================

class CustomTypes
{
    // --- Strings & Codes ---
    
    public static function tin(): AllOf
    {
        return v::stringType()->length(10, 20)->regex('/^[A-Z0-9]{10,20}$/');
    }
    
    public static function ninBrn(): AllOf
    {
        return v::stringType()->length(1, 100);
    }
    
    public static function deviceNo(): AllOf
    {
        return v::stringType()->length(1, 20);
    }
    
    public static function uuid32(): AllOf
    {
        return v::stringType()->length(32, 32);
    }
    
    public static function code(int $min, int $max): AllOf
    {
        return v::stringType()->length($min, $max);
    }
    
    public static function code1(): AllOf { return self::code(1, 1); }
    public static function code2(): AllOf { return self::code(1, 2); }
    public static function code3(): AllOf { return self::code(3, 3); }
    public static function code4(): AllOf { return self::code(1, 4); }
    public static function code5(): AllOf { return self::code(1, 5); }
    public static function code6(): AllOf { return self::code(1, 6); }
    public static function code10(): AllOf { return self::code(1, 10); }
    public static function code14(): AllOf { return self::code(1, 14); }
    public static function code16(): AllOf { return self::code(1, 16); }
    public static function code18(): AllOf { return self::code(1, 18); }
    public static function code20(): AllOf { return self::code(1, 20); }
    public static function code21(): AllOf { return self::code(1, 21); }
    public static function code30(): AllOf { return self::code(1, 30); }
    public static function code32(): AllOf { return self::code(1, 32); }
    public static function code35(): AllOf { return self::code(1, 35); }
    public static function code50(): AllOf { return self::code(1, 50); }
    public static function code60(): AllOf { return self::code(1, 60); }
    public static function code80(): AllOf { return self::code(1, 80); }
    public static function code100(): AllOf { return self::code(1, 100); }
    public static function code128(): AllOf { return self::code(1, 128); }
    public static function code150(): AllOf { return self::code(1, 150); }
    public static function code200(): AllOf { return self::code(1, 200); }
    public static function code256(): AllOf { return self::code(1, 256); }
    public static function code400(): AllOf { return self::code(1, 400); }
    public static function code500(): AllOf { return self::code(1, 500); }
    public static function code600(): AllOf { return self::code(1, 600); }
    public static function code1000(): AllOf { return self::code(1, 1000); }
    public static function code1024(): AllOf { return self::code(1, 1024); }
    public static function code4000(): AllOf { return self::code(1, 4000); }
    
    // --- Dates & Times ---
    
    /** Request format: yyyy-MM-dd HH:mm:ss */
    public static function dtRequest(): AllOf
    {
        return v::stringType()->regex('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
    }
    
    /** Response format: dd/MM/yyyy HH:mm:ss */
    public static function dtResponse(): AllOf
    {
        return v::stringType()->regex('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/');
    }
    
    /** Date Request: yyyy-MM-dd */
    public static function dateRequest(): AllOf
    {
        return v::stringType()->regex('/^\d{4}-\d{2}-\d{2}$/');
    }
    
    /** Date Response: dd/MM/yyyy */
    public static function dateResponse(): AllOf
    {
        return v::stringType()->regex('/^\d{2}\/\d{2}\/\d{4}$/');
    }
    
    // --- Numbers & Amounts ---
    
    /** Amount: max 16 integer digits, 2 decimal places */
    public static function amount16_2(): AllOf
    {
        return v::numeric()->callback(function ($value) {
            $str = (string)$value;
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0] ?? '0', '-+');
            $decPart = $parts[1] ?? '';
            return strlen($intPart) <= 16 && strlen($decPart) <= 2;
        }, 'must have ≤16 integer digits and ≤2 decimal places');
    }
    
    /** Amount: max 16 integer digits, 4 decimal places */
    public static function amount16_4(): AllOf
    {
        return v::numeric()->callback(function ($value) {
            $str = (string)$value;
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0] ?? '0', '-+');
            $decPart = $parts[1] ?? '';
            return strlen($intPart) <= 16 && strlen($decPart) <= 4;
        }, 'must have ≤16 integer digits and ≤4 decimal places');
    }
    
    /** Amount: max 20 integer digits, 8 decimal places */
    public static function amount20_8(): AllOf
    {
        return v::numeric()->callback(function ($value) {
            $str = (string)$value;
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0] ?? '0', '-+');
            $decPart = $parts[1] ?? '';
            return strlen($intPart) <= 20 && strlen($decPart) <= 8;
        }, 'must have ≤20 integer digits and ≤8 decimal places');
    }
    
    /** Signed Amount: max 16 integer digits, 2 decimal places (allows negative) */
    public static function amountSigned16_2(): AllOf
    {
        return self::amount16_2();
    }
    
    /** Signed Amount: max 16 integer digits, 4 decimal places */
    public static function amountSigned16_4(): AllOf
    {
        return self::amount16_4();
    }
    
    /** Signed Amount: max 20 integer digits, 8 decimal places */
    public static function amountSigned20_8(): AllOf
    {
        return self::amount20_8();
    }
    
    /** Rate: max 12 integer digits, 8 decimal places */
    public static function rate12_8(): AllOf
    {
        return v::numeric()->callback(function ($value) {
            $str = (string)$value;
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0] ?? '0', '-+');
            $decPart = $parts[1] ?? '';
            return strlen($intPart) <= 12 && strlen($decPart) <= 8;
        }, 'must have ≤12 integer digits and ≤8 decimal places');
    }
    
    /** Rate: max 5 integer digits, 2 decimal places */
    public static function rate5_2(): AllOf
    {
        return v::numeric()->callback(function ($value) {
            $str = (string)$value;
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0] ?? '0', '-+');
            $decPart = $parts[1] ?? '';
            return strlen($intPart) <= 5 && strlen($decPart) <= 2;
        }, 'must have ≤5 integer digits and ≤2 decimal places');
    }
    
    // --- Enums & Flags ---
    
    public static function yn(): AllOf { return v::stringType()->inArray(['Y', 'N']); }
    
    public static function invoiceType(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2', '4', '5']); // 1:Invoice, 2:Credit, 4:Debit, 5:Credit Memo
    }
    
    public static function invoiceKind(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2']); // 1:Invoice, 2:Receipt
    }
    
    public static function dataSource(): AllOf 
    { 
        return v::stringType()->regex('/^10[1-8]$/'); // 101-108
    }
    
    public static function industryCode(): AllOf 
    { 
        return v::stringType()->regex('/^10[1-9]|11[0-2]$/'); // 101-112
    }
    
    public static function discountFlag(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1', '2']); // 0:Discount Amount, 1:Discount Item, 2:Normal
    }
    
    public static function deemedFlag(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2']); // 1:Deemed, 2:Not Deemed
    }
    
    public static function exciseFlag(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2']); // 1:Excise, 2:Not Excise
    }
    
    public static function exciseRule(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2']); // 1:Rate, 2:Quantity
    }
    
    public static function buyerType(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1', '2', '3']); // 0:B2B, 1:B2C, 2:Foreigner, 3:B2G
    }
    
    public static function approveStatus(): AllOf 
    { 
        return v::stringType()->regex('/^10[1-4]$/'); // 101-104
    }
    
    public static function reasonCode(): AllOf 
    { 
        return v::stringType()->regex('/^10[1-5]$/');
    }
    
    public static function stockLimit(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102']); // 101:Restricted, 102:Unlimited
    }
    
    public static function currency(): AllOf 
    { 
        return v::stringType()->length(3, 3)->regex('/^[A-Z]{3}$/');
    }
    
    public static function taxCategoryCode(): AllOf 
    { 
        return v::stringType()->regex('/^[0-9]{2}$/'); // 01-11
    }
    
    public static function modeCode(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1']); // 0:Offline, 1:Online
    }
    
    public static function operationType(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102']); // 101:Stock In, 102:Stock Out
    }
    
    public static function stockInType(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102', '103', '104']); // 101-104
    }
    
    public static function transferType(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102', '103']); // 101-103
    }
    
    public static function queryType(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1']); // 0:Agent, 1:Normal
    }
    
    public static function haveExcise(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102']); // 101:Yes, 102:No
    }
    
    public static function havePiece(): AllOf { return self::haveExcise(); }
    public static function haveCustoms(): AllOf { return self::haveExcise(); }
    public static function haveOther(): AllOf { return self::haveExcise(); }
    public static function serviceMark(): AllOf { return self::haveExcise(); }
    public static function isLeafNode(): AllOf { return self::haveExcise(); }
    
    public static function enableStatus(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1']);
    }
    
    public static function exclusionType(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1', '2', '3']);
    }
    
    public static function vatApplicable(): AllOf 
    { 
        return v::stringType()->inArray(['0', '1']);
    }
    
    public static function deemedExemptCode(): AllOf 
    { 
        return v::stringType()->inArray(['101', '102']); // 101:Deemed, 102:Exempt
    }
    
    public static function highSeaBondFlag(): AllOf 
    { 
        return v::stringType()->inArray(['1', '2']); // 1:Yes, 2:No
    }
    
    public static function deliveryTerms(): AllOf 
    { 
        return v::stringType()->length(1, 3); // CIF, FOB, etc.
    }
}
