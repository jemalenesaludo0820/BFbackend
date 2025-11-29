<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

const MANILA_TIMEZONE_ID = 'Asia/Manila';

/**
 * Returns Manila timezone object
 */
if (!function_exists('manila_timezone')) {
    function manila_timezone(): DateTimeZone
    {
        static $tz = null;
        if ($tz === null) {
            $tz = new DateTimeZone(MANILA_TIMEZONE_ID);
        }
        return $tz;
    }
}

/**
 * Return current Manila datetime as Y-m-d H:i:s
 */
if (!function_exists('current_manila_datetime')) {
    function current_manila_datetime(): string
    {
        try {
            $date = new DateTime('now', manila_timezone());
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log("current_manila_datetime error: " . $e->getMessage());
            // Fallback to UTC if Manila timezone fails
            return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        }
    }
}
