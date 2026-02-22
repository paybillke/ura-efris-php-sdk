<?php

namespace UraEfrisSdk\Utils;

use DateTime;
use DateTimeZone;

/**
 * Time and timestamp utilities for EFRIS API
 */
class TimeUtils
{
    /**
     * Get current timestamp in Uganda timezone (Africa/Kampala).
     * Format: yyyy-MM-dd HH:mm:ss (used for requests)
     *
     * @return string
     */
    public static function getUgandaTimestamp(): string
    {
        $ugTz = new DateTimeZone('Africa/Kampala');
        $now = new DateTime('now', $ugTz);
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Get current timestamp in Uganda timezone with DD/MM/YYYY format.
     * Format: dd/MM/yyyy HH:mm:ss (used for responses)
     *
     * @return string
     */
    public static function getUgandaTimestampDdmmyyyy(): string
    {
        $ugTz = new DateTimeZone('Africa/Kampala');
        $now = new DateTime('now', $ugTz);
        return $now->format('d/m/Y H:i:s');
    }

    /**
     * Get current date in Uganda timezone. Format: YYYYMMDD
     *
     * @return string
     */
    public static function getUgandaDateYyyymmdd(): string
    {
        $ugTz = new DateTimeZone('Africa/Kampala');
        $now = new DateTime('now', $ugTz);
        return $now->format('Ymd');
    }

    /**
     * Validate that client and server times are synchronized.
     * Handles both yyyy-MM-dd and dd/MM/yyyy formats.
     *
     * @param string $clientTime
     * @param string $serverTime
     * @param int $toleranceMinutes
     * @return bool
     */
    public static function validateTimeSync(
        string $clientTime,
        string $serverTime,
        int $toleranceMinutes = 10
    ): bool {
        $formats = ['Y-m-d H:i:s', 'd/m/Y H:i:s'];

        try {
            // Try parsing client time
            $clientDt = null;
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $clientTime);
                if ($dt && $dt->format($fmt) === $clientTime) {
                    $clientDt = $dt;
                    break;
                }
            }
            if ($clientDt === null) {
                return false;
            }

            // Try parsing server time
            $serverDt = null;
            foreach ($formats as $fmt) {
                $dt = DateTime::createFromFormat($fmt, $serverTime);
                if ($dt && $dt->format($fmt) === $serverTime) {
                    $serverDt = $dt;
                    break;
                }
            }
            if ($serverDt === null) {
                return false;
            }

            $diffSeconds = abs($serverDt->getTimestamp() - $clientDt->getTimestamp());
            return $diffSeconds <= ($toleranceMinutes * 60);
        } catch (\Exception $e) {
            return false;
        }
    }
}
